<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * In production, a webhook URL must point to the public internet — never
 * localhost, a private network (10.x, 192.168.x, ...) or a reserved range
 * (e.g. 169.254.169.254, the cloud metadata address). Otherwise anyone
 * with an account could make our server send requests to things only it
 * can reach, like the WhatsApp worker on 127.0.0.1:3001 (SSRF).
 *
 * Outside production the check is skipped, so a local receiver
 * (http://127.0.0.1:...) can still be used while developing.
 *
 * Checked when the URL is saved AND again right before every delivery
 * (see WebhookDispatcher::attempt()), because a hostname's DNS can change
 * after it was saved.
 */
class PublicWebhookUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ! self::isAllowed($value)) {
            $fail('The webhook URL must be a public internet address, not localhost or a private network.');
        }
    }

    public static function isAllowed(string $url): bool
    {
        if (! app()->isProduction()) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        // IPv6 literals come wrapped in brackets: http://[::1]/
        $host = trim($host, '[]');

        // gethostbynamel() only returns IPv4 addresses; a hostname that
        // doesn't resolve at all is rejected rather than allowed.
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if ($ips === []) {
            return false;
        }

        // GLOBAL_RANGE (PHP 8.2+) also rejects ranges the other two flags
        // miss, e.g. 100.64.0.0/10 (carrier-grade NAT).
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE;

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, $flags)) {
                return false;
            }
        }

        return true;
    }
}
