<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chatbot: switch one entry off without deleting it (e.g. an offer
     * that ended). The bot skips switched-off entries.
     */
    public function up(): void
    {
        Schema::table('chatbot_rules', function (Blueprint $table) {
            $table->boolean('enabled')->default(true)->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_rules', function (Blueprint $table) {
            $table->dropColumn('enabled');
        });
    }
};
