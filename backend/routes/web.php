<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InstanceController as AdminInstanceController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\RevenueController as AdminRevenueController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\ApiLogController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CashfreeWebhookController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstanceController;
use App\Http\Controllers\InternalSessionsController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MessageMediaController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\TermsController;
use App\Http\Controllers\WorkerWebhookController;
use Illuminate\Support\Facades\Route;

// Guests see the marketing landing page; logged-in users are sent straight
// to the dashboard (see HomeController).
Route::get('/', HomeController::class)->name('home');

// Public — no auth required either way, so it can be linked from the
// register page before an account exists, and still read afterward.
Route::get('/terms', TermsController::class)->name('terms');
Route::get('/privacy', PrivacyController::class)->name('privacy');

// Public — guests and logged-in users can both reach support.
Route::get('/contact', [ContactController::class, 'create'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.send');

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

// Only for logged-in users. `not_suspended` catches a session that was
// already active when an admin suspended the account (login itself is
// blocked separately in LoginController).
// These few work before the email is verified: logging out, and the
// "check your email" page / link / resend button.
Route::middleware(['auth', 'not_suspended'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

// Everything else needs a verified email (`verified` sends unverified
// users to the "check your email" page above).
Route::middleware(['auth', 'not_suspended', 'verified'])->group(function () {
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
    Route::post('/instances/{instance}/webhook/test', [InstanceController::class, 'testWebhook'])
        ->middleware('throttle:webhook-test')
        ->name('instances.webhook.test');

    Route::post('/instances/{instance}/send-test-message', [InstanceController::class, 'sendTestMessage'])
        ->middleware('throttle:messages')
        ->name('instances.send-test-message');

    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    // A received image / voice note / document — owner only.
    Route::get('/messages/{message}/media', [MessageMediaController::class, 'show'])->name('messages.media');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/subscribe/{plan}', [BillingController::class, 'subscribe'])->name('billing.subscribe');
    Route::post('/billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');

    Route::get('/docs', ApiDocsController::class)->name('docs.index');

    // A standalone module (not nested under one instance) — one flat log
    // across every instance the user owns, optionally filtered to one.
    Route::get('/api-logs', [ApiLogController::class, 'index'])->name('api-logs.index');

    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile.update');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');
    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');
});

// Cross-tenant visibility + management for admin accounts only — see
// App\Http\Middleware\EnsureUserIsAdmin. There is no in-app way to become
// an admin; it's granted by directly setting is_admin on a user row.
Route::middleware(['auth', 'not_suspended', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::put('/users/{user}/plan', [AdminUserController::class, 'updatePlan'])->name('users.plan.update');
    Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
    Route::post('/users/{user}/unsuspend', [AdminUserController::class, 'unsuspend'])->name('users.unsuspend');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

    Route::get('/instances', [AdminInstanceController::class, 'index'])->name('instances.index');

    Route::get('/revenue', [AdminRevenueController::class, 'index'])->name('revenue.index');

    // Read-only: the audit log is append-only, so no edit/delete routes.
    Route::get('/audit-log', [AdminAuditLogController::class, 'index'])->name('audit-log.index');

    Route::get('/plans', [AdminPlanController::class, 'index'])->name('plans.index');
    Route::get('/plans/create', [AdminPlanController::class, 'create'])->name('plans.create');
    Route::post('/plans', [AdminPlanController::class, 'store'])->name('plans.store');
    Route::get('/plans/{plan}/edit', [AdminPlanController::class, 'edit'])->name('plans.edit');
    Route::put('/plans/{plan}', [AdminPlanController::class, 'update'])->name('plans.update');
    Route::delete('/plans/{plan}', [AdminPlanController::class, 'destroy'])->name('plans.destroy');
});

// The 24-hour media download link sent in webhooks, for the customer's
// own server — no login, protected by the URL signature instead.
Route::get('/media/{message}', [MessageMediaController::class, 'signed'])
    ->middleware('signed')
    ->name('media.signed');

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
