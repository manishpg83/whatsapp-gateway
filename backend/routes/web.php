<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CashfreeWebhookController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstanceController;
use App\Http\Controllers\InternalSessionsController;
use App\Http\Controllers\TermsController;
use App\Http\Controllers\WorkerWebhookController;
use Illuminate\Support\Facades\Route;

// The home page is the dashboard (guests are then sent on to the login page).
Route::redirect('/', '/dashboard');

// Public — no auth required either way, so it can be linked from the
// register page before an account exists, and still read afterward.
Route::get('/terms', TermsController::class)->name('terms');

// Only for visitors who are NOT logged in.
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);

    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
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

    Route::post('/instances/{instance}/send-test-message', [InstanceController::class, 'sendTestMessage'])
        ->middleware('throttle:messages')
        ->name('instances.send-test-message');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/subscribe/{plan}', [BillingController::class, 'subscribe'])->name('billing.subscribe');
    Route::post('/billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');

    Route::get('/docs', ApiDocsController::class)->name('docs.index');

    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');
    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');
});

// Called by the Node worker only — authenticated by shared secret, not a
// browser session. See App\Http\Middleware\VerifyInternalSecret.
Route::post('/internal/worker/events', WorkerWebhookController::class)
    ->middleware('internal.secret')
    ->name('internal.worker.events');

// Called by the worker once at boot, to reconnect whatever should be live.
Route::get('/internal/worker/sessions', InternalSessionsController::class)
    ->middleware('internal.secret')
    ->name('internal.worker.sessions');

// Called by Cashfree only — authenticated by its own webhook signature,
// not a browser session or our internal secret. See CashfreeWebhookController.
Route::post('/webhooks/cashfree', CashfreeWebhookController::class)->name('webhooks.cashfree');

// Where Cashfree's checkout sends the customer's browser back to. Deliberately
// outside the 'auth' group and CSRF (see bootstrap/app.php) — Cashfree POSTs
// here itself, not via a form with our session/CSRF token, and it's just a
// bounce-back to /billing either way, so no auth is actually needed here.
Route::match(['get', 'post'], '/billing/return', [BillingController::class, 'return'])->name('billing.return');
