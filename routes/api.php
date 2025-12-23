<?php

use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\PlaylistController;
use App\Http\Controllers\Api\TokenController;
use Illuminate\Support\Facades\Route;

Route::post('/token', [TokenController::class, 'store'])
    ->middleware(['auth', 'throttle:10,1']);

Route::middleware(['device_token', 'throttle:60,1'])->group(function () {
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::get('/articles/batch', [ArticleController::class, 'batch']);
    Route::post('/save', [ArticleController::class, 'store']);
    Route::post('/articles/{article}/tts', [ArticleController::class, 'dispatchTts'])
        ->middleware('throttle:5,1');
    Route::get('/articles/{article}/audio', [ArticleController::class, 'getAudio']);
    Route::get('/articles/{article}/progress', [ArticleController::class, 'getProgressStatus']);

    // Playlists
    Route::post('/playlists', [PlaylistController::class, 'store']);
    Route::get('/playlists/{playlist}', [PlaylistController::class, 'show']);
    Route::put('/playlists/{playlist}', [PlaylistController::class, 'update']);
    Route::post('/playlists/{playlist}/items', [PlaylistController::class, 'addItem']);
    Route::put('/playlists/{playlist}/items/{item}', [PlaylistController::class, 'reorder']);
    Route::delete('/playlists/{playlist}/items/{item}', [PlaylistController::class, 'removeItem']);
});
