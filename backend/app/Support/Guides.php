<?php

namespace App\Support;

/**
 * The public tutorials under /guides. Each one is a Blade view at
 * resources/views/guides/{slug}.blade.php; this list holds what the index
 * page, the sitemap and search engines need. Add new guides here.
 */
class Guides
{
    // When the /guides list itself last changed (its sitemap date). Each
    // guide below has its own 'published' and 'updated' dates: bump a
    // guide's 'updated' whenever its content changes.
    public const UPDATED = '2026-10-07';

    /**
     * @return array<string, array{title: string, description: string, language: string, icon: string, published: string, updated: string}>
     */
    public static function all(): array
    {
        return [
            'send-whatsapp-message-php' => [
                'title' => 'Send a WhatsApp Message with PHP',
                'description' => 'Send WhatsApp messages from PHP with cURL: text, images and documents, error handling and delivery status. Copy-paste code, no SDK needed.',
                'language' => 'PHP',
                'icon' => 'bi-filetype-php',
                'published' => '2026-10-06',
                'updated' => '2026-10-06',
            ],
            'send-whatsapp-message-python' => [
                'title' => 'Send a WhatsApp Message with Python',
                'description' => 'Send WhatsApp messages from Python with requests: text, images and documents, error handling and delivery status, in a few lines.',
                'language' => 'Python',
                'icon' => 'bi-filetype-py',
                'published' => '2026-10-06',
                'updated' => '2026-10-06',
            ],
            'send-whatsapp-message-nodejs' => [
                'title' => 'Send a WhatsApp Message with Node.js',
                'description' => 'Send WhatsApp messages from Node.js with built-in fetch: text, images and documents, error handling and delivery status. No packages needed.',
                'language' => 'Node.js',
                'icon' => 'bi-filetype-js',
                'published' => '2026-10-06',
                'updated' => '2026-10-06',
            ],
            'whatsapp-auto-reply-chatbot' => [
                'title' => 'Set Up a WhatsApp Auto-Reply Chatbot',
                'description' => 'Answer customers on WhatsApp automatically with keywords: how matching works, files in answers, business hours, pausing when you reply, Excel import, and limits. No code needed.',
                'language' => 'Chatbot',
                'icon' => 'bi-robot',
                'published' => '2026-10-06',
                'updated' => '2026-10-07',
            ],
            'receive-whatsapp-messages-webhook' => [
                'title' => 'Receive WhatsApp Messages with a Webhook',
                'description' => 'Get incoming WhatsApp messages and delivery updates on your server: a verified webhook receiver in PHP, Node.js and Python, with retries explained.',
                'language' => 'Webhooks',
                'icon' => 'bi-arrow-down-left-circle',
                'published' => '2026-10-06',
                'updated' => '2026-10-06',
            ],
        ];
    }

    /**
     * @return array{slug: string, title: string, description: string, language: string, icon: string, published: string, updated: string}|null
     */
    public static function find(string $slug): ?array
    {
        $guide = self::all()[$slug] ?? null;

        return $guide ? ['slug' => $slug] + $guide : null;
    }
}
