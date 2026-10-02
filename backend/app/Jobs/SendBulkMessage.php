<?php

namespace App\Jobs;

use App\Models\BulkCampaign;
use App\Services\MessageSender;
use App\Services\PlanLimiter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends the NEXT pending message of a bulk campaign, then queues itself
 * again `interval_seconds` later — so a campaign sends one message at a
 * time at a steady pace, never in a burst, and its progress is saved per
 * recipient (a restart just carries on from the next pending number).
 *
 * It stops (without sending) when the campaign is no longer running or
 * its run_token changed (paused and resumed meanwhile — the resume queued
 * its own job). It pauses the campaign itself when sending can't work:
 * instance not connected, monthly limit reached, or too many failures in
 * a row.
 */
class SendBulkMessage implements ShouldQueue
{
    use Queueable;

    // Sending is not safe to repeat (the message may already be out).
    public int $tries = 1;

    public function __construct(public int $campaignId, public string $runToken) {}

    public function handle(MessageSender $sender, PlanLimiter $limiter): void
    {
        $campaign = BulkCampaign::with(['whatsappSession', 'user'])->find($this->campaignId);

        if (! $campaign || $campaign->status !== 'running' || $campaign->run_token !== $this->runToken) {
            return;
        }

        if ($campaign->whatsappSession->status !== 'connected') {
            $campaign->pause('The WhatsApp instance is not connected. Reconnect it, then resume.');

            return;
        }

        if (! $limiter->canSendMessage($campaign->user)) {
            $campaign->pause("You've reached your plan's monthly message limit. Upgrade, or resume next month.");

            return;
        }

        $recipient = $campaign->recipients()->where('status', 'pending')->orderBy('id')->first();

        if (! $recipient) {
            $campaign->finish();

            return;
        }

        $message = $sender->send(
            $campaign->whatsappSession,
            $recipient->phone,
            $campaign->bodyFor($recipient->name),
            type: $campaign->type,
            media: $campaign->media(),
        );

        $recipient->update([
            'status' => $message->status === 'failed' ? 'failed' : 'sent',
            'message_id' => $message->id,
            'error' => $message->error ? Str::limit($message->error, 250) : null,
            'processed_at' => now(),
        ]);

        if ($this->failedInARow($campaign)) {
            $campaign->pause('The last '.self::failureLimit().' messages all failed. Check the errors below and the instance, then resume.');

            return;
        }

        if ($campaign->recipients()->where('status', 'pending')->exists()) {
            self::dispatch($campaign->id, $this->runToken)->delay(now()->addSeconds($campaign->interval_seconds));
        } else {
            $campaign->finish();
        }
    }

    /**
     * Something unexpected broke (not a normal failed send — those are
     * handled above): pause, so the owner can see it and resume.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Bulk campaign job crashed', ['bulk_campaign_id' => $this->campaignId, 'error' => $exception->getMessage()]);

        $campaign = BulkCampaign::find($this->campaignId);

        if ($campaign?->status === 'running' && $campaign->run_token === $this->runToken) {
            $campaign->pause('Sending stopped because of an unexpected error. Resume to try again.');
        }
    }

    /**
     * Never below 1: with 0, "the last 0 sends all failed" is always true
     * and every campaign would pause after its first message (this
     * happened when a long-running queue worker didn't have
     * config/bulk.php loaded yet).
     */
    private static function failureLimit(): int
    {
        return max(1, (int) config('bulk.pause_after_failures', 5));
    }

    private function failedInARow(BulkCampaign $campaign): bool
    {
        $limit = self::failureLimit();

        // Only sends since the last start/resume — resuming gives a fresh count.
        $last = $campaign->recipients()
            ->whereIn('status', ['sent', 'failed'])
            ->where('processed_at', '>=', $campaign->run_started_at)
            ->latest('processed_at')->latest('id')
            ->take($limit)
            ->pluck('status');

        return $last->count() === $limit && $last->every(fn (string $status) => $status === 'failed');
    }
}
