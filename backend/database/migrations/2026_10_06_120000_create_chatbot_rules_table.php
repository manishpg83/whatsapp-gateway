<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chatbot step 1: the owner's keyword → answer entries, per instance.
     * Nothing replies automatically yet — that comes in a later step.
     */
    public function up(): void
    {
        Schema::create('chatbot_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_session_id')->constrained()->cascadeOnDelete();
            // A label for the owner only ("What are your prices?") — never sent.
            $table->string('question', 150);
            // Lower-cased list, e.g. ["price", "cost", "rate"].
            $table->json('keywords');
            $table->text('answer');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_rules');
    }
};
