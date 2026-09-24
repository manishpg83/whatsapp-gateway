<?php

namespace App\Services;

use App\Exceptions\MediaFetchException;
use App\Models\WhatsappSession;
use App\Rules\PublicWebhookUrl;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Throwable;

/**
 * Gets the file for an outgoing media message so the worker can send it on
 * WhatsApp — either by downloading the caller's `media_url` (fetch()) or
 * from a direct file upload (fromUpload()). Saved on the whatsapp_media
 * disk (outside the web root) as "<instance_id>/out-<uuid>.<ext>" and
 * kept, like received media.
 *
 * Safety rules, because this fetches a URL the caller chose:
 * - only http(s), and (in production) never localhost / private networks
 *   (same rule as webhooks, App\Rules\PublicWebhookUrl) — checked again
 *   on every redirect, max 3 redirects;
 * - streamed to disk in chunks with a per-type size cap, so a huge file
 *   can't fill memory or disk;
 * - the file type is detected from the actual bytes, never trusted from
 *   the URL or the server's Content-Type header.
 */
class MediaFetcher
{
    private const MB = 1024 * 1024;

    // type => [max bytes, allowed detected MIME types (null = any)]
    public const RULES = [
        'image' => [5 * self::MB, ['image/jpeg', 'image/png', 'image/webp']],
        'video' => [16 * self::MB, ['video/mp4', 'video/3gpp']],
        'audio' => [16 * self::MB, ['audio/mpeg', 'audio/ogg', 'application/ogg', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/x-hx-aac-adts', 'audio/amr']],
        'voice' => [16 * self::MB, ['audio/ogg', 'application/ogg']],
        'document' => [100 * self::MB, null],
    ];

    private const EXTENSIONS = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
        'video/mp4' => 'mp4', 'video/3gpp' => '3gp',
        'audio/mpeg' => 'mp3', 'audio/ogg' => 'ogg', 'application/ogg' => 'ogg', 'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a', 'audio/aac' => 'aac', 'audio/x-hx-aac-adts' => 'aac', 'audio/amr' => 'amr',
        'application/pdf' => 'pdf',
    ];

    /**
     * @return array{path: string, mime_type: string, file_name: string|null, size: int}
     *
     * @throws MediaFetchException with a message that's safe to show the API caller
     */
    public function fetch(WhatsappSession $session, string $url, string $type, ?string $fileName = null): array
    {
        [$maxBytes] = self::RULES[$type] ?? throw new MediaFetchException('Unsupported media type.');

        if (! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) || ! PublicWebhookUrl::isAllowed($url)) {
            throw new MediaFetchException('media_url must be a public http(s) address.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'wamedia');

        try {
            $size = $this->download($url, $tmp, $maxBytes);

            // No file_name given? Use the last part of the URL, e.g. ".../Price%20List.pdf" -> "Price List.pdf".
            $nameHint = $fileName ?: rawurldecode(basename((string) parse_url($url, PHP_URL_PATH)));

            return $this->store($session, $tmp, $size, $type, $nameHint, 'The file at media_url');
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Same as fetch(), for a file uploaded directly — the dashboard's
     * drag-and-drop box, or an API call sent as multipart/form-data. The
     * same size and file-type rules apply.
     *
     * @return array{path: string, mime_type: string, file_name: string|null, size: int}
     *
     * @throws MediaFetchException with a message that's safe to show the caller
     */
    public function fromUpload(WhatsappSession $session, UploadedFile $file, string $type, ?string $fileName = null): array
    {
        if (! isset(self::RULES[$type])) {
            throw new MediaFetchException('Unsupported media type.');
        }

        if (! $file->isValid()) {
            throw new MediaFetchException('The file could not be uploaded (it may be larger than the server allows: '.ini_get('upload_max_filesize').').');
        }

        [$maxBytes] = self::RULES[$type];

        if ($file->getSize() > $maxBytes) {
            throw new MediaFetchException('The uploaded file is too large (max '.intdiv($maxBytes, self::MB).' MB for this type).');
        }

        return $this->store($session, $file->getRealPath(), (int) $file->getSize(), $type, $fileName ?: $file->getClientOriginalName(), 'The uploaded file');
    }

    /**
     * The checks and storage both fetch() and fromUpload() share: detect the
     * real file type from its bytes, make sure it's allowed for $type, and
     * copy it onto the whatsapp_media disk.
     *
     * @return array{path: string, mime_type: string, file_name: string|null, size: int}
     */
    private function store(WhatsappSession $session, string $localPath, int $size, string $type, ?string $nameHint, string $label): array
    {
        [, $allowedMimes] = self::RULES[$type];

        if ($size === 0) {
            throw new MediaFetchException("{$label} is empty.");
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($localPath) ?: 'application/octet-stream';

        if ($allowedMimes !== null && ! in_array($mime, $allowedMimes, true)) {
            throw new MediaFetchException("{$label} is {$mime}, which can't be sent as {$type}.");
        }

        $name = 'out-'.Str::uuid().'.'.$this->extension($mime, $nameHint);
        Storage::disk('whatsapp_media')->putFileAs($session->instance_id, new File($localPath), $name);

        return [
            'path' => "{$session->instance_id}/{$name}",
            'mime_type' => $mime,
            'file_name' => $type === 'document' ? $this->documentName($nameHint, $mime) : null,
            'size' => $size,
        ];
    }

    /**
     * Streams the URL into $tmp, 1 MB at a time, stopping as soon as it
     * passes $maxBytes. Returns the number of bytes written.
     */
    private function download(string $url, string $tmp, int $maxBytes): int
    {
        try {
            $response = Http::withOptions([
                'stream' => true,
                'allow_redirects' => [
                    'max' => 3,
                    'protocols' => ['http', 'https'],
                    // Re-check every hop: a public URL must not bounce us to a private address.
                    'on_redirect' => function (RequestInterface $request, ResponseInterface $response, UriInterface $uri) {
                        if (! PublicWebhookUrl::isAllowed((string) $uri)) {
                            throw new MediaFetchException('media_url redirected to a non-public address.');
                        }
                    },
                ],
            ])->timeout(30)->get($url);
        } catch (MediaFetchException $e) {
            throw $e;
        } catch (Throwable $e) {
            // Could be DNS, timeout, a redirect loop, or our on_redirect check (wrapped by Guzzle).
            if ($e->getPrevious() instanceof MediaFetchException) {
                throw $e->getPrevious();
            }

            throw new MediaFetchException('Could not download media_url (connection failed or timed out).');
        }

        if (! $response->successful()) {
            throw new MediaFetchException("Could not download media_url (it returned HTTP {$response->status()}).");
        }

        $body = $response->toPsrResponse()->getBody();
        $out = fopen($tmp, 'wb');
        $size = 0;

        try {
            while (! $body->eof()) {
                $chunk = $body->read(self::MB);
                $size += strlen($chunk);

                if ($size > $maxBytes) {
                    throw new MediaFetchException('The file at media_url is too large (max '.intdiv($maxBytes, self::MB).' MB for this type).');
                }

                fwrite($out, $chunk);
            }
        } finally {
            fclose($out);
            $body->close();
        }

        return $size;
    }

    private function extension(string $mime, ?string $nameHint): string
    {
        if (isset(self::EXTENSIONS[$mime])) {
            return self::EXTENSIONS[$mime];
        }

        // Documents of any type: fall back to the file name's own extension.
        $fromName = strtolower(pathinfo((string) $nameHint, PATHINFO_EXTENSION));

        return preg_match('/^[a-z0-9]{1,10}$/', $fromName) ? $fromName : 'bin';
    }

    /**
     * The name the recipient sees for a document: the caller's file_name,
     * the uploaded file's own name, or the last part of the URL — else
     * "document.<ext>". Cleaned of path separators and control characters.
     */
    private function documentName(?string $nameHint, string $mime): string
    {
        $name = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\]+/', '', (string) $nameHint) ?? '');

        return $name !== '' ? Str::limit($name, 200, '') : 'document.'.$this->extension($mime, null);
    }
}
