<?php

use App\Http\Controllers\Api\MessageController;
use Illuminate\Support\Facades\Route;

// Public messaging API (CLAUDE.md §6). Authenticated by a Bearer token,
// never a browser session — see App\Http\Middleware\AuthenticateApiToken.
// api.log comes first so it also sees api.token's own 401s (rejected calls
// show under API Logs → "Rejected requests").
Route::post('/v1/messages/send', [MessageController::class, 'send'])
    ->middleware(['api.log', 'api.token', 'throttle:messages']);

// Status of a message sent above, by the message_id it returned. Its own
// rate limit so polling for status never eats into the send budget.
Route::get('/v1/messages/{messageId}', [MessageController::class, 'show'])
    ->middleware(['api.token', 'throttle:message-status']);
