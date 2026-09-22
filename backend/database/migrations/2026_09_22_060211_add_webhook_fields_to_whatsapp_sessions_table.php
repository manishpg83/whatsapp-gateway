<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->string('webhook_url')->nullable();
            // Generated the first time a webhook_url is set; used to HMAC-sign
            // outgoing webhook payloads so the receiver can verify they're
            // really from us. Not a secret in the same sense as an API
            // token (it can't be used to access anything here), so it's
            // shown in the UI rather than one-time-revealed.
            $table->string('webhook_secret')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropColumn(['webhook_url', 'webhook_secret']);
        });
    }
};
