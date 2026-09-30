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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // One row per payment Cashfree tells us about (via the
            // SUBSCRIPTION_PAYMENT_* webhooks) — the user's payment history.
            // Unlike `subscriptions`, rows are never overwritten.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Plan slug + name as they were when the payment happened, so
            // history stays correct after an upgrade or a plan rename.
            $table->string('plan');
            $table->string('plan_name');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('INR');
            // One of: paid, failed, cancelled.
            $table->string('status');
            $table->string('cashfree_subscription_id')->nullable();
            // Cashfree's own payment id — unique, so a webhook Cashfree
            // retries can't create the same payment twice.
            $table->string('cf_payment_id')->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
