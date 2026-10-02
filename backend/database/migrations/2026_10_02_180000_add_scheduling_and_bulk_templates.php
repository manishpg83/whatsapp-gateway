<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * B4: schedule a campaign for later, and saved messages ("templates")
     * a user can reuse across campaigns.
     */
    public function up(): void
    {
        Schema::table('bulk_campaigns', function (Blueprint $table) {
            // When a "scheduled" campaign starts (UTC), and the timezone the
            // user picked it in — only used to show the time back to them.
            $table->timestamp('scheduled_at')->nullable()->after('interval_seconds');
            $table->string('timezone', 64)->nullable()->after('scheduled_at');
        });

        // Saved message text (or caption) with {name} placeholders. Text
        // only — the image/video/document is chosen per campaign.
        Schema::create('bulk_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('body');
            $table->timestamps();

            $table->index(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_templates');

        Schema::table('bulk_campaigns', function (Blueprint $table) {
            $table->dropColumn(['scheduled_at', 'timezone']);
        });
    }
};
