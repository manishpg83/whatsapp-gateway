<?php

namespace App\Services;

use Generator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Encrypts media files on the whatsapp_media disk (privacy, CLAUDE.md §17).
 * The Node worker uses the SAME format (whatsapp-worker/src/whatsapp/
 * mediaCrypto.ts) with the same MEDIA_ENCRYPTION_KEY, so either side can
 * read what the other wrote. Change one, change both.
 *
 * File format:
 *   "IMENC1"                                    6-byte marker
 *   then chunks of: nonce (12) | ciphertext | tag (16)
 * Each chunk is up to 64 KB of the original file, encrypted with
 * AES-256-GCM, so huge videos are never loaded into memory at once. The
 * chunk's number and a "last chunk" flag are authenticated with it, so
 * chunks can't be reordered, dropped or cut off unnoticed. The last chunk
 * is always shorter than 64 KB (it may be empty) — that's how a reader
 * knows it is the last one.
 *
 * Files without the marker are old, unencrypted ones: they are read as
 * they are, until `php artisan media:encrypt` has converted them.
 */
class MediaCrypto
{
    public const MARKER = 'IMENC1';

    private const CHUNK = 65536;

    private const NONCE = 12;

    private const TAG = 16;

    // Size of one full chunk on disk.
    private const RECORD = self::NONCE + self::CHUNK + self::TAG;

    private string $key;

    public function __construct(?string $key = null)
    {
        $key ??= (string) config('worker.media_key');
        $raw = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : false;

        if ($raw === false || strlen($raw) !== 32) {
            throw new RuntimeException('MEDIA_ENCRYPTION_KEY is missing or invalid (expected "base64:" + 32 random bytes).');
        }

        $this->key = $raw;
    }

    /**
     * Writes an encrypted copy of $source to $destination.
     */
    public function encryptFile(string $source, string $destination): void
    {
        $in = $this->open($source, 'rb');
        $out = $this->open($destination, 'wb');

        try {
            fwrite($out, self::MARKER);

            for ($index = 0; ; $index++) {
                $plain = $this->read($in, self::CHUNK);
                $last = strlen($plain) < self::CHUNK;

                $nonce = random_bytes(self::NONCE);
                $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $nonce, $tag, $this->aad($index, $last), self::TAG);

                if ($cipher === false || fwrite($out, $nonce.$cipher.$tag) === false) {
                    throw new RuntimeException('Could not encrypt the media file.');
                }

                if ($last) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            fclose($out);
            @unlink($destination);

            throw $e;
        } finally {
            fclose($in);

            if (is_resource($out)) {
                fclose($out);
            }
        }
    }

    public function isEncrypted(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $start = fread($handle, strlen(self::MARKER));
        fclose($handle);

        return $start === self::MARKER;
    }

    /**
     * The original (decrypted) file's contents, a piece at a time.
     *
     * @return Generator<int, string>
     */
    public function chunks(string $path): Generator
    {
        $in = $this->open($path, 'rb');

        try {
            if ($this->read($in, strlen(self::MARKER)) !== self::MARKER) {
                // Old unencrypted file: pass it through as it is.
                rewind($in);

                while (($data = $this->read($in, self::CHUNK)) !== '') {
                    yield $data;
                }

                return;
            }

            for ($index = 0; ; $index++) {
                $record = $this->read($in, self::RECORD);
                $last = strlen($record) < self::RECORD;

                if (strlen($record) < self::NONCE + self::TAG) {
                    throw new RuntimeException('The encrypted media file is damaged.');
                }

                $plain = openssl_decrypt(
                    substr($record, self::NONCE, -self::TAG), 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA,
                    substr($record, 0, self::NONCE), substr($record, -self::TAG), $this->aad($index, $last)
                );

                if ($plain === false) {
                    throw new RuntimeException('The media file could not be decrypted (damaged, or a different MEDIA_ENCRYPTION_KEY).');
                }

                yield $plain;

                if ($last) {
                    return;
                }
            }
        } finally {
            fclose($in);
        }
    }

    /**
     * Size of the original (decrypted) file, without decrypting it.
     */
    public function plainSize(string $path): int
    {
        clearstatcache(true, $path); // the file may have just been rewritten
        $size = (int) filesize($path);

        if (! $this->isEncrypted($path)) {
            return $size;
        }

        $records = (int) ceil(($size - strlen(self::MARKER)) / self::RECORD);

        return $size - strlen(self::MARKER) - $records * (self::NONCE + self::TAG);
    }

    /**
     * A download/view response with the decrypted file. $diskPath is
     * relative to the whatsapp_media disk; the other arguments are the
     * same as Storage::disk(...)->response().
     */
    public function response(string $diskPath, string $name, array $headers, string $disposition): StreamedResponse
    {
        $path = Storage::disk('whatsapp_media')->path($diskPath);

        $response = new StreamedResponse(function () use ($path) {
            foreach ($this->chunks($path) as $data) {
                echo $data;
                flush();
            }
        });

        $headers['Content-Length'] ??= $this->plainSize($path);
        $headers['Content-Disposition'] ??= $response->headers->makeDisposition(
            $disposition, $name, $this->asciiName($name)
        );

        $response->headers->replace($headers);

        return $response;
    }

    /**
     * Encrypts an old unencrypted file where it is. Returns false if it was
     * already encrypted.
     */
    public function encryptInPlace(string $path): bool
    {
        if ($this->isEncrypted($path)) {
            return false;
        }

        $tmp = $path.'.encrypting';
        $this->encryptFile($path, $tmp);
        $this->replace($tmp, $path);

        return true;
    }

    /**
     * Turns an encrypted file back into the original, where it is (undo).
     * Returns false if it wasn't encrypted.
     */
    public function decryptInPlace(string $path): bool
    {
        if (! $this->isEncrypted($path)) {
            return false;
        }

        $tmp = $path.'.decrypting';
        $out = $this->open($tmp, 'wb');

        try {
            foreach ($this->chunks($path) as $data) {
                fwrite($out, $data);
            }
        } catch (\Throwable $e) {
            fclose($out);
            @unlink($tmp);

            throw $e;
        }

        fclose($out);
        $this->replace($tmp, $path);

        return true;
    }

    private function aad(int $index, bool $last): string
    {
        return pack('NC', $index, $last ? 1 : 0);
    }

    /**
     * Reads exactly $length bytes, or fewer only at the end of the file.
     */
    private function read($handle, int $length): string
    {
        $data = '';

        while (strlen($data) < $length && ! feof($handle)) {
            $part = fread($handle, $length - strlen($data));

            if ($part === false || $part === '') {
                break;
            }

            $data .= $part;
        }

        return $data;
    }

    /**
     * @return resource
     */
    private function open(string $path, string $mode)
    {
        $handle = @fopen($path, $mode);

        if ($handle === false) {
            throw new RuntimeException("Could not open media file {$path}.");
        }

        return $handle;
    }

    private function replace(string $tmp, string $path): void
    {
        if (! @rename($tmp, $path)) {
            @unlink($tmp);

            throw new RuntimeException("Could not replace media file {$path}.");
        }
    }

    // Same idea as Laravel's own fallback name: ASCII only, for old browsers.
    private function asciiName(string $name): string
    {
        return str_replace('%', '', Str::ascii($name));
    }
}
