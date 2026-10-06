<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The monthly price in South African Rand, shown on .za domains (see
     * App\Support\Currency). Null = not priced in Rand yet: those sites show
     * "Contact us" for the plan. Set by admins on the plan edit page.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('price_zar')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('price_zar');
        });
    }
};
