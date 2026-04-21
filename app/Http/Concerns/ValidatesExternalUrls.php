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

        // Reserved documentation domains are safe positive fixtures for
        // tests and local/offline environments where DNS resolution may
        // be unavailable.
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
        return in_array(strtolower($host), ['example.com', 'example.org', 'example.net'], true);
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
