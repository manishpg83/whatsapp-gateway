<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chatbot: an optional file sent with an entry's answer (a price list
     * PDF, a menu photo, ...). The answer text becomes the caption.
     * Null media_type = text only.
     */
    public function up(): void
    {
        Schema::table('chatbot_rules', function (Blueprint $table) {
            // 'image', 'video' or 'document'.
            $table->string('media_type', 20)->nullable()->after('answer');
            // On the whatsapp_media disk, e.g. "<instance_id>/out-<uuid>.pdf".
            $table->string('media_path')->nullable()->after('media_type');
            $table->string('media_mime_type')->nullable()->after('media_path');
            $table->string('media_file_name')->nullable()->after('media_mime_type'); // documents only: name the customer sees
            $table->unsignedBigInteger('media_size')->nullable()->after('media_file_name');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_rules', function (Blueprint $table) {
            $table->dropColumn(['media_type', 'media_path', 'media_mime_type', 'media_file_name', 'media_size']);
        });
    }
};
