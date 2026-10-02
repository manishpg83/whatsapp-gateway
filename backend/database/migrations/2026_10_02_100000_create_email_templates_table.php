<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-edited versions of our emails (Admin → Email Templates). Only
     * templates an admin has changed get a row; the rest use the built-in
     * defaults in config/email-templates.php. "Reset to default" deletes
     * the row.
     */
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();    // e.g. welcome, usage_80 — a key of config/email-templates.php
            $table->string('subject');
            $table->text('body');               // cleaned HTML from the editor, with {placeholders}
            $table->string('button_text');
            // Nulled (not deleted) if that admin account is ever removed.
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
