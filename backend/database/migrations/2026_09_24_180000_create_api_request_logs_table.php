<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * API calls that were REJECTED (401 revoked token, 422 validation /
     * not connected / plan limit / bad media, 429 rate limit, 5xx) — the
     * ones that never became a message row, so they'd otherwise leave no
     * trace on the API Logs page. Successful and "worker failed" sends are
     * already visible there via the messages table.
     *
     * Only recorded when the token identifies an instance, so rows always
     * belong to a real owner. Never stores the token or the message text.
     */
    public function up(): void
    {
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('api_token_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 10);
            $table->string('path');
            $table->unsignedSmallInteger('status_code');
            $table->string('error')->nullable();
            $table->string('to_number')->nullable();
            $table->string('type')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['whatsapp_session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
