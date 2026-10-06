<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Which currency prices are shown in: South African Rand on a .za domain
 * (see config/currency.php), Indian Rupees everywhere else. Payments
 * through Cashfree are always INR.
 */
class Currency
{
    public const INR = 'INR';

    public const ZAR = 'ZAR';

    private const SYMBOLS = [self::INR => '₹', self::ZAR => 'R'];

    public static function current(): string
    {
        $forced = strtoupper((string) config('currency.force'));

        if (isset(self::SYMBOLS[$forced])) {
            return $forced;
        }

        // Outside a web request (queue jobs) the host is "localhost": INR.
        $host = strtolower(request()->getHost());

        return Str::endsWith($host, config('currency.zar_domains', [])) ? self::ZAR : self::INR;
    }

    public static function isZar(): bool
    {
        return self::current() === self::ZAR;
    }

    public static function symbol(?string $currency = null): string
    {
        return self::SYMBOLS[$currency ?? self::current()];
    }
}
