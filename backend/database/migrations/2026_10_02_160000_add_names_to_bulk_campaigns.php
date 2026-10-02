<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Personalisation: each number may have a name (from a CSV upload or a
     * pasted "number, name" line), put into the message wherever it says
     * {name}. `name_fallback` is used for numbers without a name.
     */
    public function up(): void
    {
        Schema::table('bulk_campaign_recipients', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->after('phone');
        });

        Schema::table('bulk_campaigns', function (Blueprint $table) {
            $table->string('name_fallback', 50)->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('bulk_campaign_recipients', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('bulk_campaigns', function (Blueprint $table) {
            $table->dropColumn('name_fallback');
        });
    }
};
