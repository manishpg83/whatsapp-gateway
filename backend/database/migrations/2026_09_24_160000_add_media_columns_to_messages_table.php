<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Incoming messages are no longer text-only: images, videos, voice
     * notes, audio, documents, stickers, locations and contact cards. For
     * media, `body` holds the caption (or ""), and the file itself lives
     * on the whatsapp_media disk (outside the web root), NOT in the DB.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // text | image | video | audio | voice | document | sticker | location | contact | unsupported
            $table->string('type')->default('text')->after('direction');
            // stored | too_large | failed — null for non-media messages
            $table->string('media_status')->nullable()->after('body');
            // Relative to the whatsapp_media disk, e.g. "<instance_id>/3EB0ABC.jpg"
            $table->string('media_path')->nullable()->after('media_status');
            $table->string('media_mime_type')->nullable()->after('media_path');
            // The sender's original file name (documents only) — display only
            $table->string('media_file_name')->nullable()->after('media_mime_type');
            $table->unsignedBigInteger('media_size')->nullable()->after('media_file_name');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['type', 'media_status', 'media_path', 'media_mime_type', 'media_file_name', 'media_size']);
        });
    }
};
