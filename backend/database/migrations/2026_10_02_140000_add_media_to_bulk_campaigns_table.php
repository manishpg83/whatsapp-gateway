<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Image / video / document campaigns. The file is uploaded once
     * (App\Services\MediaFetcher, whatsapp_media disk) and every message
     * of the campaign points at that same file — not one copy per number.
     * `body` is then the caption (may be empty).
     */
    public function up(): void
    {
        Schema::table('bulk_campaigns', function (Blueprint $table) {
            $table->string('media_path')->nullable()->after('body');
            $table->string('media_mime_type')->nullable()->after('media_path');
            $table->string('media_file_name')->nullable()->after('media_mime_type'); // documents only: name the recipient sees
            $table->unsignedBigInteger('media_size')->nullable()->after('media_file_name');
        });
    }

    public function down(): void
    {
        Schema::table('bulk_campaigns', function (Blueprint $table) {
            $table->dropColumn(['media_path', 'media_mime_type', 'media_file_name', 'media_size']);
        });
    }
};
