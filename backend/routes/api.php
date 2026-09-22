<?php

use App\Http\Controllers\Api\MessageController;
use Illuminate\Support\Facades\Route;

// Public messaging API (CLAUDE.md §6). Authenticated by a Bearer token,
// never a browser session — see App\Http\Middleware\AuthenticateApiToken.
Route::post('/v1/messages/send', [MessageController::class, 'send'])
    ->middleware(['api.token', 'throttle:messages']);
