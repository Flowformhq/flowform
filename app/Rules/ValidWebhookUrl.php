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

        return static::resolveAllowedTarget($url) !== null;
    }

    /**
     * Resolve the host once and return the address to pin the connection to, so DNS cannot change between check and connect.
     *
     * @return array{host: string, port: int, ip: string}|null
     */
    public static function resolveAllowedTarget(string $url): ?array
    {
        if (! static::isValidUrl($url)) {
            return null;
        }

        $parsed = (array) parse_url($url);
        $host = trim((string) $parsed['host'], '[]');
        $port = (int) ($parsed['port'] ?? (strtolower((string) $parsed['scheme']) === 'https' ? 443 : 80));

        if (strtolower($host) === 'localhost') {
            return null;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : static::resolveHost($host);

        if ($ips === []) {
            return null;
        }

        foreach ($ips as $ip) {
            if (static::isPrivateOrReservedIp($ip)) {
                return null;
            }
        }

        return ['host' => $host, 'port' => $port, 'ip' => $ips[0]];
    }

    /**
     * @return list<string>
     */
    public static function resolveHost(string $host): array
    {
        if (static::$dnsResolver !== null) {
            return array_values((array) call_user_func(static::$dnsResolver, $host));
        }

        $ipv4 = gethostbynamel($host) ?: [];
        $ipv6 = array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6');

        return array_values(array_unique([...$ipv4, ...$ipv6]));
    }

    public static function isPrivateOrReservedIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && str_starts_with((string) inet_ntop((string) inet_pton($ip)), '64:ff9b::')) {
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
