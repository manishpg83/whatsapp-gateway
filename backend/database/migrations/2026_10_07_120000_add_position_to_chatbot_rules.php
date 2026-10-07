<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chatbot: the owner can reorder entries. The order decides which
     * entry answers when two match equally (higher in the list wins).
     */
    public function up(): void
    {
        Schema::table('chatbot_rules', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('whatsapp_session_id');
        });

        // Existing entries keep today's order (oldest first): 1, 2, 3... per instance.
        $sessionIds = DB::table('chatbot_rules')->distinct()->pluck('whatsapp_session_id');

        foreach ($sessionIds as $sessionId) {
            $ids = DB::table('chatbot_rules')->where('whatsapp_session_id', $sessionId)->orderBy('id')->pluck('id');

            foreach ($ids as $index => $id) {
                DB::table('chatbot_rules')->where('id', $id)->update(['position' => $index + 1]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('chatbot_rules', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
