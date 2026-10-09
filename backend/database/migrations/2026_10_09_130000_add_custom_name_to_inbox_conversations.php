<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inbox: the owner's own name for a customer ("Ramesh – Pune shop"),
     * shown instead of their WhatsApp profile name (`name`). New WhatsApp
     * names keep updating `name`, but never overwrite this.
     */
    public function up(): void
    {
        Schema::table('inbox_conversations', function (Blueprint $table) {
            $table->string('custom_name', 100)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('inbox_conversations', function (Blueprint $table) {
            $table->dropColumn('custom_name');
        });
    }
};
