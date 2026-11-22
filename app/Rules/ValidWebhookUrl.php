<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidWebhookUrl implements ValidationRule
{
    /**
     * Optional custom DNS resolver for testing or custom environments.
     */
    public static ?Closure $dnsResolver = null;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value) || ! is_string($value)) {
            return;
        }

        if (! static::isValidUrl($value)) {
            $fail('The :attribute must be a valid http or https URL.');

            return;
        }

        if (! static::isTargetAllowed($value)) {
            $fail('The :attribute points to a restricted internal or private network host.');
        }
    }

    public static function isValidUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (! $parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
            return false;
        }

        return in_array(strtolower($parsed['scheme']), ['http', 'https'], true);
    }

    public static function isTargetAllowed(string $url): bool
    {
        if (config('flowform.webhooks.allow_private_targets', false)) {
            return true;
        }

        $parsed = parse_url($url);
        if (! $parsed || empty($parsed['host'])) {
            return false;
        }

        $rawHost = $parsed['host'];
        $host = trim($rawHost, '[]');

        if (strtolower($host) === 'localhost') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! static::isPrivateOrReservedIp($host);
        }

        $ips = static::resolveHost($host);

        if (empty($ips)) {
            return false;
        }

        foreach ($ips as $ip) {
            if (static::isPrivateOrReservedIp($ip)) {
                return false;
            }
        }

        return true;
    }

    public static function resolveHost(string $host): array
    {
        if (static::$dnsResolver !== null) {
            return (array) call_user_func(static::$dnsResolver, $host);
        }

        $ips = gethostbynamel($host);
        if ($ips === false || empty($ips)) {
            $records = @dns_get_record($host, DNS_AAAA);
            if (! empty($records)) {
                $ips = array_column($records, 'ipv6');
            }
        }

        return $ips ?: [];
    }

    public static function isPrivateOrReservedIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if ($long !== false && $long >= ip2long('100.64.0.0') && $long <= ip2long('100.127.255.255')) {
                return true;
            }
        }

        return false;
    }
}
