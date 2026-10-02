<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bulk messaging: one campaign = one message sent to a list of
     * numbers, paced one every `interval_seconds` by the
     * App\Jobs\SendBulkMessage job. Each send is also a normal row in
     * `messages` (so it counts toward the monthly limit and gets
     * delivered/read receipts), linked from its recipient row.
     */
    public function up(): void
    {
        Schema::create('bulk_campaigns', function (Blueprint $table) {
            $table->id();
            // Public id for URLs (and the future API) — not the auto-increment id.
            $table->uuid('campaign_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_session_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('text');
            $table->text('body');
            $table->unsignedSmallInteger('interval_seconds');
            $table->string('status');                   // running | paused | completed | cancelled
            $table->string('pause_reason')->nullable(); // set when WE paused it (e.g. instance offline)
            // New value on every start/resume; a queued job carrying an old
            // value stops, so pause → resume never runs two send loops.
            $table->uuid('run_token')->nullable();
            $table->timestamp('run_started_at')->nullable(); // last start/resume
            $table->timestamp('started_at')->nullable();     // first start
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('bulk_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bulk_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 20);
            $table->string('status')->default('pending'); // pending | sent | failed | skipped
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['bulk_campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_campaign_recipients');
        Schema::dropIfExists('bulk_campaigns');
    }
};
