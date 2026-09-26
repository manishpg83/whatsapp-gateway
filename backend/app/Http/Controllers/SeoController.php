<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * robots.txt and sitemap.xml, built from routes so they always use the
 * current APP_URL (no hard-coded domain). Only public pages are listed;
 * the app, admin and API areas are private.
 */
class SeoController extends Controller
{
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            // Private areas — they need a login anyway, keep crawlers out.
            'Disallow: /admin',
            'Disallow: /dashboard',
            'Disallow: /instances',
            'Disallow: /messages',
            'Disallow: /billing',
            'Disallow: /account',
            'Disallow: /docs',
            'Disallow: /api-logs',
            'Disallow: /api/',
            'Disallow: /internal/',
            'Disallow: /webhooks/',
            'Disallow: /media/',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        // [url, last changed, change frequency, priority]
        $pages = [
            [route('home'), null, 'weekly', '1.0'],
            [route('register'), null, 'monthly', '0.8'],
            [route('contact'), null, 'monthly', '0.6'],
            [route('login'), null, 'monthly', '0.5'],
            [route('terms'), TermsController::LAST_UPDATED, 'yearly', '0.3'],
            [route('privacy'), PrivacyController::LAST_UPDATED, 'yearly', '0.3'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($pages as [$url, $lastModified, $frequency, $priority]) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.e($url)."</loc>\n";
            if ($lastModified) {
                $xml .= "    <lastmod>{$lastModified}</lastmod>\n";
            }
            $xml .= "    <changefreq>{$frequency}</changefreq>\n";
            $xml .= "    <priority>{$priority}</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
