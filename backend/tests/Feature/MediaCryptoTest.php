<?php

namespace Tests\Feature;

use App\Services\MediaCrypto;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class MediaCryptoTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/media-crypto-test-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*'));
        rmdir($this->dir);

        parent::tearDown();
    }

    private function encrypted(string $content): string
    {
        file_put_contents("{$this->dir}/plain", $content);
        (new MediaCrypto)->encryptFile("{$this->dir}/plain", "{$this->dir}/enc");

        return "{$this->dir}/enc";
    }

    private function decrypt(string $path, ?MediaCrypto $crypto = null): string
    {
        return implode('', iterator_to_array(($crypto ?? new MediaCrypto)->chunks($path), false));
    }

    public function test_files_of_every_size_round_trip(): void
    {
        // Empty, tiny, and around the 64 KB chunk boundary.
        foreach ([0, 1, 65535, 65536, 65537, 200000] as $size) {
            $content = $size > 0 ? random_bytes($size) : '';
            $path = $this->encrypted($content);

            $this->assertTrue((new MediaCrypto)->isEncrypted($path));
            $this->assertSame($content, $this->decrypt($path), "size {$size}");
            $this->assertSame($size, (new MediaCrypto)->plainSize($path), "size {$size}");
        }
    }

    public function test_the_stored_file_does_not_contain_the_original(): void
    {
        $path = $this->encrypted('PRIVATE PHOTO BYTES');

        $this->assertStringNotContainsString('PRIVATE PHOTO BYTES', file_get_contents($path));
    }

    public function test_a_changed_byte_is_detected(): void
    {
        $path = $this->encrypted(random_bytes(1000));
        $bytes = file_get_contents($path);
        $bytes[100] = chr(ord($bytes[100]) ^ 1);
        file_put_contents($path, $bytes);

        $this->expectException(RuntimeException::class);
        $this->decrypt($path);
    }

    public function test_a_cut_off_file_is_detected(): void
    {
        // Two full chunks + an empty last one; drop the last one.
        $path = $this->encrypted(random_bytes(65536 * 2));
        file_put_contents($path, substr(file_get_contents($path), 0, 6 + 2 * (12 + 65536 + 16)));

        $this->expectException(RuntimeException::class);
        $this->decrypt($path);
    }

    public function test_the_wrong_key_cannot_decrypt(): void
    {
        $path = $this->encrypted('hello');

        $this->expectException(RuntimeException::class);
        $this->decrypt($path, new MediaCrypto('base64:'.base64_encode(str_repeat('x', 32))));
    }

    public function test_a_missing_key_is_refused(): void
    {
        config(['worker.media_key' => null]);

        $this->expectException(RuntimeException::class);
        new MediaCrypto;
    }

    public function test_old_unencrypted_files_are_read_as_they_are_and_can_be_converted(): void
    {
        $path = "{$this->dir}/old.jpg";
        file_put_contents($path, 'old plain file');
        $crypto = new MediaCrypto;

        $this->assertFalse($crypto->isEncrypted($path));
        $this->assertSame('old plain file', $this->decrypt($path));

        $this->assertTrue($crypto->encryptInPlace($path));
        $this->assertFalse($crypto->encryptInPlace($path)); // already done
        $this->assertTrue($crypto->isEncrypted($path));
        $this->assertSame('old plain file', $this->decrypt($path));

        $this->assertTrue($crypto->decryptInPlace($path));
        $this->assertSame('old plain file', file_get_contents($path));
    }

    public function test_the_response_serves_the_decrypted_file(): void
    {
        Storage::fake('whatsapp_media');
        $disk = Storage::disk('whatsapp_media');
        $disk->makeDirectory('abc');
        file_put_contents("{$this->dir}/plain", 'the picture');
        (new MediaCrypto)->encryptFile("{$this->dir}/plain", $disk->path('abc/photo.jpg'));

        $response = TestResponse::fromBaseResponse((new MediaCrypto)->response('abc/photo.jpg', 'photo.jpg', ['Content-Type' => 'image/jpeg'], 'inline'));

        $this->assertSame('the picture', $response->streamedContent());
        $this->assertSame('11', $response->headers->get('Content-Length'));
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_a_file_written_by_the_worker_can_be_read(): void
    {
        // Encrypted by whatsapp-worker/src/whatsapp/mediaCrypto.ts with the
        // test key from phpunit.xml — proves both sides use the same format.
        $path = "{$this->dir}/from-worker";
        file_put_contents($path, base64_decode(self::WORKER_FIXTURE));

        $this->assertSame('Hello from the Node worker', $this->decrypt($path));
    }

    private const WORKER_FIXTURE = 'SU1FTkMxuHHUsrwXjOJQJYMyi4j4SmkDRlY1Zc3Fiy7hcikEcRnoffuAPXob0bFEbEA1Xe6KpuhJcxU5';
}
