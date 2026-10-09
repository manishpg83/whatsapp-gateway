<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Privacy (CLAUDE.md §17): the rest of the message text is now stored
     * encrypted too — bulk campaign text, saved bulk messages and chatbot
     * answers (`encrypted` casts on their models). This encrypts the rows
     * already there. These texts are max 4,096 characters, so TEXT columns
     * are still big enough once encrypted.
     *
     * Rows that are already encrypted are skipped (safe to run twice).
     */
    private const COLUMNS = [
        'bulk_campaigns' => 'body',
        'bulk_templates' => 'body',
        'chatbot_rules' => 'answer',
    ];

    public function up(): void
    {
        $this->eachValue(function (string $value) {
            try {
                Crypt::decryptString($value);

                return null; // already encrypted
            } catch (DecryptException) {
                return Crypt::encryptString($value);
            }
        });
    }

    public function down(): void
    {
        $this->eachValue(function (string $value) {
            try {
                return Crypt::decryptString($value);
            } catch (DecryptException) {
                return null; // already plain text
            }
        });
    }

    /**
     * Runs $convert on every value of every column above, in batches, and
     * saves what it returns (null = leave that row as it is).
     */
    private function eachValue(callable $convert): void
    {
        foreach (self::COLUMNS as $table => $column) {
            DB::table($table)->select(['id', $column])->orderBy('id')
                ->chunkById(500, function ($rows) use ($table, $column, $convert) {
                    foreach ($rows as $row) {
                        $new = $convert((string) $row->{$column});

                        if ($new !== null) {
                            DB::table($table)->where('id', $row->id)->update([$column => $new]);
                        }
                    }
                });
        }
    }
};
