<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Connection history per instance (connected, connection lost,
     * disconnected/logged out by the user or by WhatsApp, reconnect
     * requested…), shown on the instance page so owners can answer "why did
     * my messages fail yesterday?". No message content or secrets here.
     */
    public function up(): void
    {
        Schema::create('instance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_session_id')->constrained()->cascadeOnDelete();
            // See InstanceEvent::TYPES for the list.
            $table->string('type');
            $table->string('detail')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['whatsapp_session_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instance_events');
    }
};
