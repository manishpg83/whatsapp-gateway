<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When the one-time welcome email was sent (after the user first verifies
 * their email). Changing email later makes them verify again, so this is
 * what stops a second welcome email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('welcome_sent_at')->nullable()->after('email_verified_at');
        });

        // Users who are already verified signed up before this existed —
        // count them as welcomed so an email change never welcomes them now.
        DB::table('users')->whereNotNull('email_verified_at')->update(['welcome_sent_at' => DB::raw('email_verified_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('welcome_sent_at');
        });
    }
};
