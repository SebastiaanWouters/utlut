<?php

namespace App\Http\Controllers\Api;

use App\Enums\AudioErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SaveArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Jobs\CleanArticleContent;
use App\Jobs\ExtractArticleContent;
use App\Jobs\GenerateArticleAudio;
use App\Models\Article;
use App\Services\AudioProgressEstimator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArticleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $deviceToken = $request->input('device_token');

        $articles = Article::where('device_token_id', $deviceToken->id)
            ->when($request->search, function ($q) use ($request) {
                $q->where('title', 'like', "%{$request->search}%")
                    ->orWhere('url', 'like', "%{$request->search}%");
            })
            ->when($request->status === 'ready', fn ($q) => $q->whereHas('audio', fn ($sq) => $sq->where('status', 'ready')))
            ->latest()
            ->paginate(config('utlut.pagination.articles'));

        return ArticleResource::collection($articles);
    }

    /**
     * Display the specified resources by ID.
     */
    public function batch(Request $request): AnonymousResourceCollection
    {
        $deviceToken = $request->input('device_token');
        $ids = array_filter(explode(',', $request->query('ids', '')));

        if (empty($ids)) {
            return ArticleResource::collection(collect());
        }

        $articles = Article::where('device_token_id', $deviceToken->id)
            ->whereIn('id', $ids)
            ->get();

        // Sort by the order of IDs provided if requested
        if ($request->boolean('preserve_order')) {
            $articles = $articles->sortBy(fn ($article) => array_search($article->id, $ids))->values();
        }

        return ArticleResource::collection($articles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SaveArticleRequest $request): JsonResponse
    {
        $deviceToken = $request->input('device_token');
        $hasBody = ! empty($request->validated('body'));

        // Check if article already exists and is extracting (to avoid duplicate jobs)
        $existingStatus = Article::where('device_token_id', $deviceToken->id)
            ->where('url', $request->validated('url'))
            ->value('extraction_status');

        // Build update attributes - extraction_status is 'extracting' until cleanup/extraction completes
        $updateAttributes = [
            'extraction_status' => 'extracting',
        ];

        // Only set title if explicitly provided and not empty (will be refined by LLM)
        $title = $request->validated('title');
        if (! empty($title)) {
            $updateAttributes['title'] = $title;
        }

        $article = Article::updateOrCreate(
            [
                'device_token_id' => $deviceToken->id,
                'url' => $request->validated('url'),
            ],
            $updateAttributes
        );

        if ($hasBody && $existingStatus !== 'extracting') {
            // Body provided directly (e.g., from iOS shortcut), clean it via LLM then generate audio
            CleanArticleContent::dispatch($article, $request->validated('body'), $title);
        } elseif (! $hasBody && $existingStatus !== 'extracting') {
            // No body provided and not already extracting, extract content from URL
            ExtractArticleContent::dispatch($article);
        }

        return response()->json([
            'ok' => true,
            'id' => $article->id,
            'title' => $article->title ?: $article->url,
            'extraction_status' => $article->extraction_status,
        ]);
    }

    /**
     * Dispatch TTS generation for the given article.
     */
    public function dispatchTts(Request $request, Article $article): JsonResponse
    {
        $audio = $article->audio()->first();

        if ($audio && $audio->status === 'pending') {
            return response()->json([
                'ok' => true,
                'status' => 'pending',
            ]);
        }

        if ($audio && $audio->status === 'ready' && $audio->content_hash === hash('sha256', $article->body)) {
            return response()->json([
                'ok' => true,
                'status' => 'ready',
            ]);
        }

        GenerateArticleAudio::dispatch($article);

        return response()->json([
            'ok' => true,
            'status' => 'pending',
        ]);
    }

    /**
     * Get the audio for the given article.
     */
    public function getAudio(Request $request, Article $article): JsonResponse|StreamedResponse|RedirectResponse
    {
        $audio = $article->audio()->first();

        if (! $audio || $audio->status !== 'ready') {
            return response()->json([
                'ok' => false,
                'status' => $audio ? $audio->status : 'pending',
                'message' => 'Audio is not ready yet.',
            ], 409);
        }

        // Stream audio from external URL if stored as URL
        // Otherwise serve from local storage
        if (str_starts_with($audio->audio_path, 'http')) {
            // If it's an external URL (like our mock), we stream it
            return response()->stream(function () use ($audio) {
                try {
                    $stream = fopen($audio->audio_path, 'r');
                    if ($stream) {
                        fpassthru($stream);
                        fclose($stream);
                    }
                } catch (\Throwable $e) {
                    // Log error but don't expose to client (streaming already started)
                    \Log::error('Audio stream failed', ['path' => $audio->audio_path, 'error' => $e->getMessage()]);
                }
            }, 200, [
                'Content-Type' => 'audio/mpeg',
                'Content-Disposition' => 'attachment; filename="article-audio-'.$article->id.'.mp3"',
            ]);
        }

        // If it's a local path, we serve it from storage
        $disk = config('filesystems.default');
        if (! Storage::disk($disk)->exists($audio->audio_path)) {
            return response()->json([
                'ok' => false,
                'status' => 'failed',
                'message' => 'Audio file not found.',
            ], 404);
        }

        return Storage::disk($disk)->response($audio->audio_path);
    }

    /**
     * Get detailed progress status for audio generation.
     */
    public function getProgressStatus(Request $request, Article $article): JsonResponse
    {
        $audio = $article->audio()->first();
        $estimator = app(AudioProgressEstimator::class);

        if (! $audio) {
            return response()->json([
                'status' => 'not_started',
                'progress_percent' => 0,
                'polling_interval' => 3000,
            ]);
        }

        $etaSeconds = $estimator->calculateEtaSeconds($audio);
        $pollingInterval = $estimator->getOptimalPollingInterval($audio);

        $response = [
            'status' => $audio->status,
            'progress_percent' => $audio->progress_percent ?? 0,
            'completed_chunks' => $audio->completed_chunks ?? 0,
            'total_chunks' => $audio->total_chunks ?? 1,
            'eta_seconds' => $etaSeconds,
            'polling_interval' => $pollingInterval,
            'retry_count' => $audio->retry_count ?? 0,
        ];

        // Include error info if failed
        if ($audio->status === 'failed' && $audio->error_code) {
            $errorCode = AudioErrorCode::tryFrom($audio->error_code);
            $response['error_code'] = $audio->error_code;
            $response['error_message'] = $errorCode?->userMessage() ?? 'An error occurred';
            $response['can_retry'] = $errorCode?->isRetryable() ?? true;
        }

        // Include next retry info if scheduled
        if ($audio->next_retry_at) {
            $response['next_retry_at'] = $audio->next_retry_at->toIso8601String();
            $response['retry_countdown_seconds'] = max(0, now()->diffInSeconds($audio->next_retry_at, false));
        }

        return response()->json($response);
    }
}
