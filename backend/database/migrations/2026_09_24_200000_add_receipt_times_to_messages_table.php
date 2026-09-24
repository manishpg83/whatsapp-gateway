<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * WhatsApp delivery receipts: an outgoing message now moves
     * sent → delivered (✓✓) → read (blue ✓✓), reported by the worker.
     * These record when each step happened.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('status');
            $table->timestamp('read_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['delivered_at', 'read_at']);
        });
    }
};
