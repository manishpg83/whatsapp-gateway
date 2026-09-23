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
        Schema::table('users', function (Blueprint $table) {
            // Deliberately NOT in User's #[Fillable(...)] list, same
            // reasoning as is_admin — only an admin action can set this,
            // never mass assignment from a form/API request.
            $table->boolean('is_suspended')->default(false)->after('is_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_suspended');
        });
    }
};
