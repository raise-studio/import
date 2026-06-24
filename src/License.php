<?php

namespace RaiseStudio\Import;

use Illuminate\Support\Facades\Http;

class License
{
    private static ?bool $cache = null;

    /**
     * Check if the current installation has a valid Pro license.
     *
     * Local/dev environments are auto-authorized — no license key needed.
     * Production environments use online verification with 24-hour cache fallback.
     */
    public static function isPro(): bool
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        // Force community mode for testing
        if (config('raise-import.force_community', false)) {
            return self::$cache = false;
        }

        // Local/dev environments always get Pro features for free
        if (self::isLocalEnvironment()) {
            return self::$cache = true;
        }

        $key = config('raise-import.license_key');

        if (empty($key)) {
            return self::$cache = false;
        }

        return self::$cache = self::validateOnline($key);
    }

    /**
     * Validate a license key via online verification server,
     * with 24-hour cache fallback when the server is unreachable.
     */
    private static function validateOnline(string $key): bool
    {
        $domain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';

        $verifyUrl = config('raise-import.license_verify_url');

        try {
            $response = Http::timeout(5)->post($verifyUrl, [
                'license_key' => $key,
                'domain' => $domain,
            ]);

            if ($response->successful() && ($response->json()['valid'] ?? false)) {
                self::cacheValidation($key);

                return true;
            }

            return false;
        } catch (\Exception $e) {
            // Network error — fall back to 24-hour local cache
            return self::validateFromCache($key);
        }
    }

    /**
     * Cache a successful validation for 24 hours (in-memory + Laravel cache).
     */
    private static function cacheValidation(string $key): void
    {
        $cacheKey = 'raise_import_license_' . md5($key);
        $expiresAt = now()->addHours(24);

        cache()->put($cacheKey, [
            'expires_at' => $expiresAt->timestamp,
        ], $expiresAt);
    }

    /**
     * Validate from 24-hour cache when the verification server is unreachable.
     */
    private static function validateFromCache(string $key): bool
    {
        $cacheKey = 'raise_import_license_' . md5($key);
        $cached = cache()->get($cacheKey);

        return $cached && ($cached['expires_at'] ?? 0) > now()->timestamp;
    }

    /**
     * Detect if we're running in a local development environment
     * where license checks should be bypassed.
     *
     * When simulate_production env is set, this returns false and the
     * code falls through to the REAL license key validation — the
     * exact same code path that runs in production.
     */
    private static function isLocalEnvironment(): bool
    {
        // ⚠️ RAISE_IMPORT_SIMULATE_PRODUCTION: disable local exemption
        // and fall through to the real license key check below.
        // Uses getenv() directly to bypass opcache issues with config/env().
        if (getenv('RAISE_IMPORT_SIMULATE_PRODUCTION')) {
            return false;
        }

        // 1. Laravel APP_ENV === 'local'
        if (app()->environment('local')) {
            return true;
        }

        // 2. Check domain/IP patterns
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';

        // Common local domains
        $localPatterns = [
            'localhost',
            '127.0.0.1',
            '[::1]',
            '0.0.0.0',
        ];

        if (in_array($host, $localPatterns, true)) {
            return true;
        }

        // .local / .test / .localhost TLDs
        foreach (['.local', '.test', '.localhost'] as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        // 192.168.x.x / 10.x.x.x / 172.16-31.x.x private IPs
        if (self::isPrivateIp($host)) {
            return true;
        }

        return false;
    }

    /**
     * Check if a hostname is a private/internal IP address.
     */
    private static function isPrivateIp(string $host): bool
    {
        // Only check IPv4 — local IPv6 is caught by [::1] above
        if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        // 10.0.0.0/8
        if (str_starts_with($host, '10.')) {
            return true;
        }

        // 172.16.0.0/12
        if (preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $host)) {
            return true;
        }

        // 192.168.0.0/16
        if (str_starts_with($host, '192.168.')) {
            return true;
        }

        return false;
    }

    /**
     * Flush the cached license check result.
     */
    public static function flushCache(): void
    {
        self::$cache = null;
    }
}
