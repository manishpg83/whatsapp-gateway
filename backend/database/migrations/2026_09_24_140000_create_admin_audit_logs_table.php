<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only record of admin actions (plan changes, suspensions,
     * deletions, plan edits). Names/emails are copied in at the moment of
     * the action, so the entry still reads correctly after the admin or the
     * target is deleted. Never stores passwords, tokens or message content.
     */
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            // Nulled (not deleted) if the admin account is ever removed.
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('admin_name');
            $table->string('action');           // e.g. user.suspended, plan.updated
            $table->string('target_type');      // user | plan
            $table->unsignedBigInteger('target_id')->nullable(); // not a FK: the target may be deleted
            $table->string('target_label');     // e.g. "Jane Doe (jane@example.com)"
            $table->json('details')->nullable(); // e.g. {"from": "free", "to": "growth"}
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
