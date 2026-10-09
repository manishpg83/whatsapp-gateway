<?php

use App\Models\Subscription;
use App\Notifications\SubscriptionRenewalReminder;
use App\Services\MediaCrypto;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Emails a reminder to every active paid subscription that renews within
 * the next 3 days — once per billing period (remembered in the cache with
 * the renewal date in the key, same idea as UsageWarner). Scheduled daily
 * below; needs `php artisan schedule:work` running, or run it by hand.
 */
Artisan::command('billing:renewal-reminders', function () {
    $sent = 0;

    Subscription::with('user')
        ->where('status', 'active')
        ->where('plan', '!=', 'free')
        ->whereBetween('current_period_end', [now(), now()->addDays(3)])
        ->each(function (Subscription $subscription) use (&$sent) {
            $plan = $subscription->planDetails();

            if ($plan['price'] <= 0) {
                return;
            }

            $key = "renewal-reminder:{$subscription->id}:".$subscription->current_period_end->toDateString();

            if (! Cache::add($key, true, $subscription->current_period_end->copy()->addMonthNoOverflow())) {
                return; // already reminded for this renewal
            }

            try {
                $subscription->user->notify(new SubscriptionRenewalReminder($plan['name'], (int) $plan['price'], $subscription->current_period_end));
                $sent++;
            } catch (Throwable $e) {
                Cache::forget($key); // let tomorrow's run try again
                Log::warning('Could not send renewal reminder email', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

    $this->info("Renewal reminders sent: {$sent}");
})->purpose('Email users whose paid plan renews within 3 days');

Schedule::command('billing:renewal-reminders')->dailyAt('10:00')->timezone('Asia/Kolkata');

/*
 * Privacy (CLAUDE.md §17): encrypts media files saved before media
 * encryption existed. Run once after deploying it; safe to run again —
 * files that are already encrypted are skipped. --decrypt undoes it.
 * Needs MEDIA_ENCRYPTION_KEY in .env (the same one the worker uses).
 */
Artisan::command('media:encrypt {--decrypt : Turn encrypted files back into normal files (undo)}', function () {
    $crypto = app(MediaCrypto::class);
    $disk = Storage::disk('whatsapp_media');
    $changed = $skipped = $failed = 0;

    foreach ($disk->allFiles() as $file) {
        // Half-finished temp files from an interrupted run.
        if (str_ends_with($file, '.encrypting') || str_ends_with($file, '.decrypting')) {
            continue;
        }

        try {
            $done = $this->option('decrypt')
                ? $crypto->decryptInPlace($disk->path($file))
                : $crypto->encryptInPlace($disk->path($file));
            $done ? $changed++ : $skipped++;
        } catch (Throwable $e) {
            $failed++;
            $this->error("{$file}: {$e->getMessage()}");
        }
    }

    $this->info(($this->option('decrypt') ? 'Decrypted' : 'Encrypted').": {$changed}, already done: {$skipped}, failed: {$failed}");
})->purpose('Encrypt (or with --decrypt, decrypt) the stored WhatsApp media files');
