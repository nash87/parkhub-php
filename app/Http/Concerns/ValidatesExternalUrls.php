<?php

declare(strict_types=1);

namespace App\Http\Concerns;

/**
 * Shared SSRF protection for controllers that accept external URLs.
 * Validates that a URL does not target internal/private networks.
 */
trait ValidatesExternalUrls
{
    protected function isExternalUrl(string $url): bool
    {
        if (! preg_match('#^https?://#i', $url)) {
            return false;
        }

        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        if (empty($host)) {
            return false;
        }

        if ($this->isReservedExampleHost($host)) {
            return true;
        }

        $ips = gethostbynamel($host);
        if ($ips === false) {
            return false;
        }

        foreach ($ips as $ip) {
            if ($this->isPrivateIp($ip)) {
                return false;
            }
        }

        return true;
    }

    private function isReservedExampleHost(string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));
        $reserved = ['example.com', 'example.net', 'example.org'];

        foreach ($reserved as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    private function isPrivateIp(string $ip): bool
    {
        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
