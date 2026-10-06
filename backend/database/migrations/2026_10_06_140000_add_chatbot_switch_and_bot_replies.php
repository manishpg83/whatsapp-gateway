<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chatbot step 3: the per-instance ON/OFF switch, and which entry sent
     * an outgoing message (null = not a bot reply).
     */
    public function up(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->boolean('chatbot_enabled')->default(false)->after('fallback_enabled');
        });

        Schema::table('messages', function (Blueprint $table) {
            // Deleting the entry keeps the message, just without the link.
            $table->foreignId('chatbot_rule_id')->nullable()->after('api_token_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chatbot_rule_id');
        });

        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropColumn('chatbot_enabled');
        });
    }
};
