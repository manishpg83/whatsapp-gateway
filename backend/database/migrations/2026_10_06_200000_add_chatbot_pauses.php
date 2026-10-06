<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chatbot "human takeover": when the owner replies to a customer from
     * their phone, the bot stays quiet in that chat for a while.
     */
    public function up(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            // How long to pause; 0 = never pause.
            $table->unsignedSmallInteger('chatbot_pause_minutes')->default(30)->after('chatbot_hours');
        });

        // One row per chat, moved forward each time the owner replies.
        Schema::create('chatbot_pauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_session_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 32);
            $table->timestamp('paused_until');
            $table->timestamps();

            $table->unique(['whatsapp_session_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_pauses');

        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropColumn('chatbot_pause_minutes');
        });
    }
};
