<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chatbot numbered menu: "Reply 1 for prices, 2 for timings, 0 to talk
     * to a person". The options are the owner's own entries.
     */
    public function up(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            // {enabled, intro, keywords, human_option, human_reply} — see App\Support\ChatbotMenu.
            $table->json('chatbot_menu')->nullable()->after('chatbot_pause_minutes');
        });

        Schema::table('chatbot_rules', function (Blueprint $table) {
            // Shown as an option in the numbered menu.
            $table->boolean('in_menu')->default(false)->after('enabled');
        });

        // Who is looking at a menu right now, and what its numbers meant
        // when it was sent — so "2" still means the same entry even if the
        // owner reorders the list in the meantime.
        Schema::create('chatbot_menu_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_session_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 32);
            // The entry ids in menu order: option 1 = first id.
            $table->json('rule_ids');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['whatsapp_session_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_menu_states');

        Schema::table('chatbot_rules', function (Blueprint $table) {
            $table->dropColumn('in_menu');
        });

        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropColumn('chatbot_menu');
        });
    }
};
