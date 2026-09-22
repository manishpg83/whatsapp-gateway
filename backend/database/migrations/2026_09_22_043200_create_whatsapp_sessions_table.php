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
        Schema::create('whatsapp_sessions', function (Blueprint $table) {
            $table->id();
            // Public identifier used in URLs and sent to the worker.
            // Deliberately not the auto-increment id, so instances can't
            // be enumerated by guessing numbers.
            $table->uuid('instance_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // One of: connecting, qr_pending, connected, disconnected, logged_out.
            // Kept as a plain string (not a DB enum) so MySQL/MariaDB
            // upgrades don't require an ALTER TABLE to add a new status.
            $table->string('status')->default('connecting');
            $table->text('qr_code')->nullable();
            $table->timestamp('qr_updated_at')->nullable();
            $table->string('phone_number')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->string('last_disconnect_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_sessions');
    }
};
