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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_session_id')->constrained()->cascadeOnDelete();
            // 'outgoing' for everything in M7. 'incoming' arrives in M8.
            $table->string('direction')->default('outgoing');
            $table->string('to_number')->nullable();
            $table->string('from_number')->nullable();
            $table->text('body');
            // One of: pending, sent, failed.
            $table->string('status')->default('pending');
            $table->string('whatsapp_message_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
