<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            // Referenced by subscriptions.plan (a plain string column, not
            // a foreign key — kept that way so this migration doesn't need
            // to touch existing subscription rows). Immutable after
            // creation: nothing in the app lets it be edited once set.
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description');
            $table->unsignedInteger('price'); // INR, 0 = free
            $table->unsignedInteger('instances');
            $table->unsignedInteger('messages_per_month');
            $table->boolean('popular')->default(false);
            // Null for the free plan. For paid plans, "{slug}_v{price_version}"
            // — see App\Models\Plan::cashfreePlanId(). Cashfree Plan objects
            // are immutable once created, so a price edit bumps
            // price_version and derives a new id rather than reusing the
            // old one — existing subscribers keep paying what they signed
            // up for; only new subscriptions pick up the new price.
            $table->string('cashfree_plan_id')->nullable();
            $table->unsignedInteger('price_version')->default(1);
            $table->timestamps();
        });

        // Seed the 4 plans that already exist in production, preserving
        // their exact cashfree_plan_id values (config/plans.php is being
        // retired in this same change) so existing Cashfree subscriptions/
        // checkout flows keep working without interruption.
        DB::table('plans')->insert([
            [
                'slug' => 'free',
                'name' => 'Free',
                'description' => 'Try the full API before you commit to anything.',
                'price' => 0,
                'instances' => 1,
                'messages_per_month' => 50,
                'popular' => false,
                'cashfree_plan_id' => null,
                'price_version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'description' => 'For a single WhatsApp number handling regular traffic.',
                'price' => 749,
                'instances' => 1,
                'messages_per_month' => 1000,
                'popular' => false,
                'cashfree_plan_id' => 'starter_monthly',
                'price_version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'growth',
                'name' => 'Growth',
                'description' => 'For teams running multiple numbers and steady campaigns.',
                'price' => 1499,
                'instances' => 3,
                'messages_per_month' => 5000,
                'popular' => true,
                'cashfree_plan_id' => 'growth_monthly',
                'price_version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'business',
                'name' => 'Business',
                'description' => 'For high-volume senders across many numbers.',
                'price' => 2999,
                'instances' => 10,
                'messages_per_month' => 50000,
                'popular' => false,
                'cashfree_plan_id' => 'business_monthly',
                'price_version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
