<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per webhook we try to deliver to an owner's webhook URL, so
     * they can see on the instance page whether their receiver works.
     * Deliberately does NOT store the payload (it contains message text)
     * or the receiver's response body — only what's needed to debug.
     */
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_session_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->string('url', 2048);
            // pending (queued / still retrying), success, failed (gave up)
            $table->string('status')->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
