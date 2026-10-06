<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How many chatbot entries a plan allows, across all of the user's
     * instances. 0 = the chatbot isn't included in the plan.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('chatbot_entries')->default(0)->after('messages_per_month');
        });

        // Starting values for the original plans (admins can change them).
        foreach (['free' => 5, 'starter' => 50, 'growth' => 200, 'business' => 1000] as $slug => $entries) {
            DB::table('plans')->where('slug', $slug)->update(['chatbot_entries' => $entries]);
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('chatbot_entries');
        });
    }
};
