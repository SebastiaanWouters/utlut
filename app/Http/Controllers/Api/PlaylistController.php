<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlaylistController extends Controller
{
    /**
     * Create a new playlist.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $deviceToken = $request->input('device_token');

        $playlist = Playlist::create([
            'device_token_id' => $deviceToken->id,
            'name' => $request->input('name'),
        ]);

        return response()->json([
            'ok' => true,
            'playlist' => $playlist,
        ], 201);
    }

    /**
     * Get a playlist with items.
     */
    public function show(Playlist $playlist): JsonResponse
    {
        $playlist->load('items.article');

        return response()->json([
            'ok' => true,
            'playlist' => $playlist,
        ]);
    }

    /**
     * Rename a playlist.
     */
    public function update(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorizePlaylist($request, $playlist);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $playlist->update([
            'name' => $request->input('name'),
        ]);

        return response()->json([
            'ok' => true,
            'playlist' => $playlist,
        ]);
    }

    /**
     * Add an item to a playlist.
     */
    public function addItem(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorizePlaylist($request, $playlist);

        $request->validate([
            'article_id' => ['required', 'exists:articles,id'],
        ]);

        $position = ($playlist->items()->max('position') ?? 0) + 1;

        $item = $playlist->items()->create([
            'article_id' => $request->input('article_id'),
            'position' => $position,
        ]);

        return response()->json([
            'ok' => true,
            'item' => $item,
        ], 201);
    }

    /**
     * Reorder an item in a playlist.
     */
    public function reorder(Request $request, Playlist $playlist, PlaylistItem $item): JsonResponse
    {
        $this->authorizePlaylist($request, $playlist);
        $this->ensureItemInPlaylist($playlist, $item);

        $request->validate([
            'position' => ['required', 'integer', 'min:1'],
        ]);

        $oldPosition = $item->position;
        $newPosition = $request->input('position');

        if ($oldPosition === $newPosition) {
            return response()->json(['ok' => true]);
        }

        $maxPosition = $playlist->items()->max('position');
        if ($newPosition > $maxPosition) {
            $newPosition = $maxPosition;
        }

        DB::transaction(function () use ($playlist, $item, $oldPosition, $newPosition) {
            if ($newPosition > $oldPosition) {
                $playlist->items()
                    ->whereBetween('position', [$oldPosition + 1, $newPosition])
                    ->decrement('position');
            } else {
                $playlist->items()
                    ->whereBetween('position', [$newPosition, $oldPosition - 1])
                    ->increment('position');
            }

            $item->update(['position' => $newPosition]);
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Remove an item from a playlist.
     */
    public function removeItem(Request $request, Playlist $playlist, PlaylistItem $item): JsonResponse
    {
        $this->authorizePlaylist($request, $playlist);
        $this->ensureItemInPlaylist($playlist, $item);

        $position = $item->position;
        $item->delete();

        // Reorder remaining items to fill the gap
        $playlist->items()
            ->where('position', '>', $position)
            ->decrement('position');

        return response()->json(['ok' => true]);
    }

    /**
     * Authorize that the request's device token owns the playlist.
     */
    protected function authorizePlaylist(Request $request, Playlist $playlist): void
    {
        $deviceToken = $request->input('device_token');

        if ($playlist->device_token_id !== $deviceToken->id) {
            abort(403, 'Unauthorized access to playlist');
        }
    }

    /**
     * Ensure the item belongs to the playlist.
     */
    protected function ensureItemInPlaylist(Playlist $playlist, PlaylistItem $item): void
    {
        if ($item->playlist_id !== $playlist->id) {
            abort(404, 'Item not found in playlist');
        }
    }
}
