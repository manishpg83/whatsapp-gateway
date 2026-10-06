<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chatbot business hours: the per-instance settings, and what kind of
     * bot reply a message is — 'answer' (an entry's answer) or 'closed'
     * (the "we're closed" message). Null = not sent by the bot.
     */
    public function up(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            // {enabled, days, open, close, timezone, message} — see App\Support\ChatbotHours.
            $table->json('chatbot_hours')->nullable()->after('chatbot_enabled');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('bot_reply', 20)->nullable()->after('chatbot_rule_id');
        });

        DB::table('messages')->whereNotNull('chatbot_rule_id')->update(['bot_reply' => 'answer']);
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('bot_reply');
        });

        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropColumn('chatbot_hours');
        });
    }
};
