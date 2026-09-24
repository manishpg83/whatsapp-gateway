<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time data fix: email verification was switched on after these
     * accounts already existed, so treat everyone registered before now
     * as verified — otherwise they'd all be locked out. Only new sign-ups
     * have to verify.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    /**
     * Nothing to undo — there's no record of which users were unverified.
     */
    public function down(): void
    {
        //
    }
};
