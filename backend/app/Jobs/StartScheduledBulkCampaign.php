<?php

namespace App\Jobs;

use App\Models\BulkCampaign;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued with a delay when a campaign is scheduled (BulkCampaign::schedule()),
 * so it runs at the chosen time — no cron needed, just the queue worker.
 * (If the worker is stopped at that moment, it runs as soon as it starts.)
 *
 * Does nothing if the campaign was sent early, cancelled, or rescheduled
 * meanwhile (run_token changed). Pauses with a reason instead of starting
 * when sending can't work yet.
 */
class StartScheduledBulkCampaign implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $campaignId, public string $runToken) {}

    public function handle(): void
    {
        $campaign = BulkCampaign::with('whatsappSession')->find($this->campaignId);

        if (! $campaign || $campaign->status !== 'scheduled' || $campaign->run_token !== $this->runToken) {
            return;
        }

        if ($campaign->whatsappSession->status !== 'connected') {
            $campaign->pause('It was scheduled to start, but the WhatsApp instance was not connected. Reconnect it, then resume.');

            return;
        }

        if ($campaign->otherRunningOnInstance()) {
            $campaign->pause('It was scheduled to start, but another campaign was still sending from this instance. Resume it when that one finishes.');

            return;
        }

        $campaign->start();
    }
}
