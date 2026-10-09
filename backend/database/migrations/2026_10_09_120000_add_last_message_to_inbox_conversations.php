<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inbox speed: the conversation list used to be worked out from ALL of
     * an instance's messages on every poll (every 5 seconds). Now each
     * conversation row remembers its latest message (kept up to date by
     * Message::booted()), so the list is one small indexed query.
     *
     * Also indexes the customer number on messages, for opening one chat.
     * Every existing conversation gets a row here (unread 0, like before).
     */
    public function up(): void
    {
        Schema::table('inbox_conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('last_message_id')->nullable()->after('unread_count');
            $table->index(['whatsapp_session_id', 'last_message_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index(['whatsapp_session_id', 'from_number']);
            $table->index(['whatsapp_session_id', 'to_number']);
        });

        // One-time backfill — the old (slow) grouping, run once.
        $contact = "CASE WHEN direction = 'incoming' THEN from_number ELSE to_number END";

        DB::table('messages')
            ->selectRaw("whatsapp_session_id, {$contact} as phone, MAX(id) as last_id")
            ->groupBy('whatsapp_session_id')
            ->groupByRaw($contact)
            ->orderBy('whatsapp_session_id')
            ->get()
            ->filter(fn ($row) => $row->phone !== null && $row->phone !== '' && strlen($row->phone) <= 32)
            ->chunk(500)
            ->each(fn ($rows) => DB::table('inbox_conversations')->upsert(
                $rows->map(fn ($row) => [
                    'whatsapp_session_id' => $row->whatsapp_session_id,
                    'phone' => $row->phone,
                    'last_message_id' => $row->last_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->values()->all(),
                ['whatsapp_session_id', 'phone'],
                ['last_message_id']
            ));
    }

    public function down(): void
    {
        // MySQL/MariaDB silently dropped the foreign key's own index on
        // whatsapp_session_id when the indexes above were added (they can
        // serve the foreign key). Put it back first, or the last of them
        // can't be dropped.
        if (! Schema::hasIndex('messages', 'messages_whatsapp_session_id_foreign')) {
            Schema::table('messages', fn (Blueprint $table) => $table->index('whatsapp_session_id', 'messages_whatsapp_session_id_foreign'));
        }

        foreach (['from_number', 'to_number'] as $column) {
            if (Schema::hasIndex('messages', ['whatsapp_session_id', $column])) {
                Schema::table('messages', fn (Blueprint $table) => $table->dropIndex(['whatsapp_session_id', $column]));
            }
        }

        Schema::table('inbox_conversations', function (Blueprint $table) {
            $table->dropIndex(['whatsapp_session_id', 'last_message_id']);
            $table->dropColumn('last_message_id');
        });
    }
};
