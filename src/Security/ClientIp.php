<?php

namespace Oriel\Security;

class ClientIp
{
    /**
     * Environment shorthands mapping to the header the platform's edge sets.
     * Kinsta sits behind Cloudflare, so it is Cloudflare-shaped.
     */
    private const ENVIRONMENT_HEADERS = [
        'cloudflare' => 'CF-Connecting-IP',
        'kinsta' => 'CF-Connecting-IP',
        'wpengine' => 'X-Forwarded-For',
    ];

    /**
     * Resolve the client IP for the current request.
     *
     * Defaults to REMOTE_ADDR — the only value a client cannot spoof.
     * Forwarding headers are only consulted when the site owner has declared
     * a trusted proxy topology via a constant or filter, because any HTTP_*
     * header is attacker-controlled on a site not actually behind that proxy.
     *
     * @return string Resolved IP, or '' if none could be determined.
     */
    public static function resolve(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $header = self::trustedHeader();

        if ($header) {
            $ip = self::fromHeader($header) ?? $ip;
        }

        return (string) apply_filters('oriel_client_ip', $ip);
    }

    /**
     * Determine which forwarding header, if any, the site owner trusts.
     *
     * An explicit header (ORIEL_TRUSTED_IP_HEADER / oriel_trusted_ip_header)
     * takes precedence over an environment shorthand
     * (ORIEL_TRUSTED_IP_ENVIRONMENT / oriel_trusted_ip_environment).
     */
    private static function trustedHeader(): ?string
    {
        $header = apply_filters(
            'oriel_trusted_ip_header',
            defined('ORIEL_TRUSTED_IP_HEADER') ? ORIEL_TRUSTED_IP_HEADER : null
        );

        if (is_string($header) && trim($header) !== '') {
            return trim($header);
        }

        $environment = apply_filters(
            'oriel_trusted_ip_environment',
            defined('ORIEL_TRUSTED_IP_ENVIRONMENT') ? ORIEL_TRUSTED_IP_ENVIRONMENT : null
        );

        if (is_string($environment)) {
            $environment = strtolower(trim($environment));

            if (isset(self::ENVIRONMENT_HEADERS[$environment])) {
                return self::ENVIRONMENT_HEADERS[$environment];
            }
        }

        return null;
    }

    /**
     * Extract and validate a client IP from a forwarding header.
     *
     * @return string|null Valid IP, or null to fall back to REMOTE_ADDR.
     */
    private static function fromHeader(string $header): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
        $value = $_SERVER[$key] ?? '';

        if (! is_string($value) || $value === '') {
            return null;
        }

        // Headers like X-Forwarded-For carry a comma-separated chain that the
        // client can seed with fake entries; trusted proxies append to the
        // right. Walk right-to-left and take the first public IP, so
        // client-seeded entries are only reachable past every appended hop.
        $parts = array_map('trim', explode(',', $value));
        $fallback = null;

        foreach (array_reverse($parts) as $part) {
            if (filter_var($part, FILTER_VALIDATE_IP) === false) {
                continue;
            }

            $isPublic = filter_var(
                $part,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;

            if ($isPublic) {
                return $part;
            }

            // Remember the rightmost private/reserved IP so local and staging
            // environments still resolve to something stable.
            $fallback = $fallback ?? $part;
        }

        return $fallback;
    }
}
