<?php

use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\PlaylistController;
use App\Http\Controllers\Api\TokenController;
use Illuminate\Support\Facades\Route;

Route::post('/token', [TokenController::class, 'store'])->middleware('auth');

Route::middleware('device_token')->group(function () {
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::post('/save', [ArticleController::class, 'store']);
    Route::post('/articles/{article}/tts', [ArticleController::class, 'dispatchTts']);
    Route::get('/articles/{article}/audio', [ArticleController::class, 'getAudio']);

    // Playlists
    Route::post('/playlists', [PlaylistController::class, 'store']);
    Route::get('/playlists/{playlist}', [PlaylistController::class, 'show']);
    Route::put('/playlists/{playlist}', [PlaylistController::class, 'update']);
    Route::post('/playlists/{playlist}/items', [PlaylistController::class, 'addItem']);
    Route::put('/playlists/{playlist}/items/{item}', [PlaylistController::class, 'reorder']);
    Route::delete('/playlists/{playlist}/items/{item}', [PlaylistController::class, 'removeItem']);
});
