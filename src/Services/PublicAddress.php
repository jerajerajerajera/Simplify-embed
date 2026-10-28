<?php

declare(strict_types=1);

namespace Simplify\Embed\Services;

/**
 * Only addresses that players outside the home or office network can reach are published.
 */
final class PublicAddress
{
    /**
     * The first reachable address of the candidates, or null.
     *
     * @param  list<string|null>  $candidates
     */
    public static function first(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && self::reachable($candidate)) {
                return mb_substr($candidate, 0, 120);
            }
        }

        return null;
    }

    /** "host:port", with brackets around an IPv6 host. */
    public static function join(?string $host, int $port): ?string
    {
        $host = trim((string) $host);
        if ($host === '' || $port < 1 || $port > 65535) {
            return null;
        }

        return (str_contains($host, ':') ? '['.$host.']' : $host).':'.$port;
    }

    /** False for private, CGNAT (Tailscale), loopback and reserved IPs, and for .ts.net, .local and .internal names. */
    public static function reachable(string $address): bool
    {
        if (preg_match('/^\[([^\]]+)\]/', $address, $match)) {
            $host = $match[1];
        } elseif (substr_count($address, ':') > 1) {
            $host = $address;
        } else {
            $host = explode(':', $address)[0];
        }
        $host = strtolower(trim($host));
        if ($host === '' || $host === 'localhost') {
            return false;
        }
        foreach (['.ts.net', '.local', '.internal', '.lan', '.home.arpa'] as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return false;
            }
        }
        if (! filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', $host);
        }
        if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // 100.64.0.0/10 (carrier-grade NAT, used by Tailscale) is not covered by the flags above.
            $number = ip2long($host);

            return ! ($number >= ip2long('100.64.0.0') && $number <= ip2long('100.127.255.255'));
        }

        // Tailscale's IPv6 range.
        return ! str_starts_with($host, 'fd7a:115c:a1e0');
    }
}
