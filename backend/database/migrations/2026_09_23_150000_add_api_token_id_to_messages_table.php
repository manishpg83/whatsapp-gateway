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
        Schema::table('messages', function (Blueprint $table) {
            // Only set for outgoing messages sent through the real public
            // API (AuthenticateApiToken) — null for incoming messages and
            // for the dashboard's own "send a test message" button, which
            // has no token. This is what lets the API Logs page show which
            // token was used and reconstruct the original curl request.
            // nullOnDelete rather than cascade — a token is only ever
            // revoked (soft), never hard-deleted, but this is the correct
            // behaviour if that ever changes: keep the message history.
            $table->foreignId('api_token_id')->nullable()->after('whatsapp_session_id')
                ->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('api_token_id');
        });
    }
};
