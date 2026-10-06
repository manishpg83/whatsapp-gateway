<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional Meta WhatsApp Cloud API fallback: when a text message can't
     * be sent through the linked device (Baileys), it is retried through
     * the owner's own Cloud API number. The Meta access token is stored
     * encrypted (see the WhatsappSession 'encrypted' cast), never plain.
     */
    public function up(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->boolean('fallback_enabled')->default(false)->after('webhook_secret');
            $table->string('cloud_phone_number_id', 64)->nullable()->after('fallback_enabled');
            $table->text('cloud_access_token')->nullable()->after('cloud_phone_number_id');
        });

        Schema::table('messages', function (Blueprint $table) {
            // null = no fallback attempted; otherwise 'sent' or 'failed'.
            $table->string('fallback_status', 20)->nullable()->after('error');
            $table->string('fallback_message_id')->nullable()->after('fallback_status');
            $table->text('fallback_error')->nullable()->after('fallback_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['fallback_status', 'fallback_message_id', 'fallback_error']);
        });

        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropColumn(['fallback_enabled', 'cloud_phone_number_id', 'cloud_access_token']);
        });
    }
};
