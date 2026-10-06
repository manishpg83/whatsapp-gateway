<?php

namespace App\Support;

/**
 * The few things that differ between the Indian site (the default) and the
 * South African one (a .za domain — see Currency): country, language,
 * example phone numbers, and the hreflang links between the two. Everything
 * else is the same on both.
 */
class Site
{
    public static function isSouthAfrica(): bool
    {
        return Currency::isZar();
    }

    /** ISO country code, e.g. for schema.org areaServed. */
    public static function country(): string
    {
        return self::isSouthAfrica() ? 'ZA' : 'IN';
    }

    /** e.g. "en-ZA" — for schema.org inLanguage and hreflang. */
    public static function language(): string
    {
        return 'en-'.self::country();
    }

    /** e.g. "en_ZA" — for og:locale. */
    public static function locale(): string
    {
        return 'en_'.self::country();
    }

    /** An example WhatsApp number (country code + number, digits only). */
    public static function samplePhone(): string
    {
        return self::isSouthAfrica() ? '27821234567' : '919876543210';
    }

    /** A second example number, for lists. */
    public static function samplePhone2(): string
    {
        return self::isSouthAfrica() ? '27831234567' : '919812345678';
    }

    /** Two example first names that suit the country, for bulk-message samples. */
    public static function sampleNames(): array
    {
        return self::isSouthAfrica() ? ['Thabo', 'Lerato'] : ['Rahul', 'Priya'];
    }

    /**
     * hreflang => URL of this same page on each site, plus x-default (India).
     * Empty unless both domains are set and this request is on one of them
     * (so localhost / test domains never get these links).
     *
     * @return array<string, string>
     */
    public static function alternates(): array
    {
        $india = strtolower((string) config('sites.india_domain'));
        $southAfrica = strtolower((string) config('sites.south_africa_domain'));
        $host = preg_replace('/^www\./', '', strtolower(request()->getHost()));

        if ($india === '' || $southAfrica === '' || ! in_array($host, [$india, $southAfrica], true)) {
            return [];
        }

        $path = request()->getPathInfo() === '/' ? '' : request()->getPathInfo();

        return [
            'en-IN' => "https://{$india}{$path}",
            'en-ZA' => "https://{$southAfrica}{$path}",
            'x-default' => "https://{$india}{$path}",
        ];
    }
}
