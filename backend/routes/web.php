<?php

use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstanceController;
use App\Http\Controllers\WorkerWebhookController;
use Illuminate\Support\Facades\Route;

// The home page is the dashboard (guests are then sent on to the login page).
Route::redirect('/', '/dashboard');

// Only for visitors who are NOT logged in.
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);

    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

// Only for logged-in users.
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/instances', [InstanceController::class, 'index'])->name('instances.index');
    Route::get('/instances/create', [InstanceController::class, 'create'])->name('instances.create');
    Route::post('/instances', [InstanceController::class, 'store'])->name('instances.store');
    Route::get('/instances/{instance}', [InstanceController::class, 'show'])->name('instances.show');
    Route::get('/instances/{instance}/status', [InstanceController::class, 'status'])->name('instances.status');
    Route::post('/instances/{instance}/reconnect', [InstanceController::class, 'reconnect'])->name('instances.reconnect');
    Route::delete('/instances/{instance}', [InstanceController::class, 'destroy'])->name('instances.destroy');

    Route::post('/instances/{instance}/tokens', [ApiTokenController::class, 'store'])->name('instances.tokens.store');
    Route::delete('/instances/{instance}/tokens/{token}', [ApiTokenController::class, 'destroy'])->name('instances.tokens.destroy');

    Route::post('/instances/{instance}/webhook', [InstanceController::class, 'updateWebhook'])->name('instances.webhook.update');
});

// Called by the Node worker only — authenticated by shared secret, not a
// browser session. See App\Http\Middleware\VerifyInternalSecret.
Route::post('/internal/worker/events', WorkerWebhookController::class)
    ->middleware('internal.secret')
    ->name('internal.worker.events');
