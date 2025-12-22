<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SaveArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Jobs\GenerateArticleAudio;
use App\Models\Article;
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

        $article = Article::updateOrCreate(
            [
                'device_token_id' => $deviceToken->id,
                'url' => $request->validated('url'),
            ],
            [
                'title' => $request->validated('title'),
                'body' => $request->validated('body'),
            ]
        );

        if ($article->body) {
            GenerateArticleAudio::dispatch($article);
        }

        return response()->json([
            'ok' => true,
            'title' => $article->title ?: $article->url,
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
        if (! Storage::disk('public')->exists($audio->audio_path)) {
            return response()->json([
                'ok' => false,
                'status' => 'failed',
                'message' => 'Audio file not found.',
            ], 404);
        }

        return Storage::disk('public')->response($audio->audio_path);
    }
}
