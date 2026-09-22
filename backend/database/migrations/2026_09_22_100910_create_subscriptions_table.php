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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            // One row per user, always — created automatically at
            // registration on the 'free' plan. Not a history ledger; it's
            // updated in place as the user upgrades/downgrades/cancels.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Matches a key in config/plans.php.
            $table->string('plan')->default('free');
            // Null for the free plan (nothing to track at Cashfree for it).
            $table->string('cashfree_subscription_id')->nullable()->unique();
            // One of: active, pending (checkout started, not yet
            // authorized), cancelled, past_due.
            $table->string('status')->default('active');
            $table->timestamp('current_period_end')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
