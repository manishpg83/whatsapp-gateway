<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inbox: one row per conversation (instance + customer number) that has
     * had a message since this table was added — its unread count (each
     * incoming message adds 1; opening the chat or replying sets it to 0)
     * and the customer's WhatsApp profile name. No row = nothing unread, so
     * existing chats start as read.
     */
    public function up(): void
    {
        Schema::create('inbox_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_session_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 32);
            $table->string('name', 100)->nullable(); // their WhatsApp "push name"
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();

            $table->unique(['whatsapp_session_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_conversations');
    }
};
