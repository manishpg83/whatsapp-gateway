<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Privacy: message text is now stored encrypted (the `encrypted` cast on
     * Message::body, using APP_KEY). This encrypts the rows already there.
     * Encrypted text is ~1.8x longer, so the column grows to MEDIUMTEXT
     * (incoming WhatsApp texts can be up to 65,536 characters).
     *
     * Rows that are already encrypted are skipped, so messages saved by the
     * new code between deploy and migrate are not encrypted twice.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->mediumText('body')->change();
        });

        $this->eachBody(function (string $body) {
            try {
                Crypt::decryptString($body);

                return null; // already encrypted
            } catch (DecryptException) {
                return Crypt::encryptString($body);
            }
        });
    }

    public function down(): void
    {
        $this->eachBody(function (string $body) {
            try {
                return Crypt::decryptString($body);
            } catch (DecryptException) {
                return null; // already plain text
            }
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->text('body')->change();
        });
    }

    /**
     * Runs $convert on every message body in batches and saves what it
     * returns (null = leave that row as it is).
     */
    private function eachBody(callable $convert): void
    {
        DB::table('messages')->select(['id', 'body'])->orderBy('id')
            ->chunkById(500, function ($messages) use ($convert) {
                foreach ($messages as $message) {
                    $new = $convert((string) $message->body);

                    if ($new !== null) {
                        DB::table('messages')->where('id', $message->id)->update(['body' => $new]);
                    }
                }
            });
    }
};
