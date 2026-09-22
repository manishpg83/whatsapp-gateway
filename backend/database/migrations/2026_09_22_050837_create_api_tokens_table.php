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
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_session_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // SHA-256 hash of the token (CLAUDE.md §6: hashed, not bcrypt —
            // tokens are already long/random, a fast hash lets us look
            // them up directly by hash). The plaintext is shown once and
            // never stored.
            $table->string('token_hash')->unique();
            // First 8 chars of the plaintext, so a token can be identified
            // in the UI without ever re-displaying the full value.
            $table->string('token_prefix');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
