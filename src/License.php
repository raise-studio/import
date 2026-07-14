<?php

namespace RaiseStudio\Import;

use Illuminate\Support\Facades\Http;

class License
{
    private static ?bool $cache = null;
    private static ?bool $integrityCache = null;

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

        // Central (cached) Pro gate: locally exempt OR holds a valid key,
        // AND the gatekeeper files pass the integrity self-check.
        return self::$cache = (self::hasValidKey() && self::isIntegrityValid());
    }

    /**
     * Distributed-gate primitive: true when the install is locally exempt OR
     * holds a currently-valid license key (uncached online check). Does NOT
     * consult the integrity self-check — callers combine it with
     * isIntegrityValid() to build an independent gate (see gatePro()).
     */
    public static function hasValidKey(): bool
    {
        if (self::isLocalEnvironment()) {
            return true;
        }

        $key = config('raise-import.license_key');

        if (empty($key)) {
            return false;
        }

        return self::validateOnline($key);
    }

    /**
     * Independent, uncached Pro gate for feature execution points.
     *
     * Unlike isPro() it never reads the static cache, forcing a fresh
     * re-evaluation. This makes a patched isPro() (or its cached result)
     * insufficient to unlock the actual Pro features — each critical file
     * re-checks on its own. Part of the 2026-07-07 "分布式 gate" strategy.
     */
    public static function gatePro(): bool
    {
        if (config('raise-import.force_community', false)) {
            return false;
        }

        return self::hasValidKey() && self::isIntegrityValid();
    }

    /**
     * Validate a license key via online verification server,
     * with 24-hour cache fallback when the server is unreachable.
     */
    private static function validateOnline(string $key): bool
    {
        $siteUrl = config('app.url') ?: 'http://localhost';
        $domain = parse_url($siteUrl, PHP_URL_HOST) ?? 'localhost';
        $product = config('raise-import.license_product', 'raise-import');

        $verifyUrl = config('raise-import.license_verify_url');

        try {
            $response = Http::timeout(5)->post($verifyUrl, [
                'license_key' => $key,
                'site_url' => $siteUrl,
                'product' => $product,
            ]);

            if (!$response->successful()) {
                return self::validateFromCache($key);
            }

            $data = $response->json();

            // 1. Server signature must verify — blocks forged/relayed endpoints
            if (!self::verifyResponseSignature($data)) {
                return false;
            }

            // 2. License must be valid for the Pro edition
            if (!($data['valid'] ?? false) || ($data['edition'] ?? null) !== 'pro') {
                return false;
            }

            // 3. Domain lock — the server binds each key to a domain
            if (!self::isDomainAllowed($data['domain'] ?? null, $domain)) {
                return false;
            }

            self::cacheValidation($key);

            return true;
        } catch (\Exception $e) {
            // Network error — fall back to 24-hour local cache
            return self::validateFromCache($key);
        }
    }

    /**
     * Shared HMAC secret used to verify the license server's response
     * signature. Must match the value configured on raise-license-server.
     */
    private static function secret(): string
    {
        return (string) config('raise-import.license_secret', '');
    }

    /**
     * Verify the HMAC-SHA256 signature returned by the license server.
     *
     * Canonical payload: valid|domain|expires_at|edition
     * Timing-safe comparison prevents signature-timing leaks.
     */
    private static function verifyResponseSignature(array $data): bool
    {
        $secret = self::secret();

        // No secret configured → cannot trust any positive response.
        if ($secret === '') {
            return false;
        }

        $canonical = sprintf(
            '%s|%s|%s|%s',
            var_export($data['valid'] ?? false, true),
            $data['domain'] ?? '',
            $data['expires_at'] ?? '',
            $data['edition'] ?? ''
        );

        $expected = hash_hmac('sha256', $canonical, $secret);

        return hash_equals($expected, (string) ($data['signature'] ?? ''));
    }

    /**
     * Enforce domain binding. The license server returns the domain the key
     * is bound to. Supports an optional `*.suffix` wildcard so a key issued
     * for `*.example.com` also validates `app.example.com` but not bare
     * `example.com`.
     */
    private static function isDomainAllowed(?string $boundDomain, string $currentHost): bool
    {
        if (empty($boundDomain)) {
            return false;
        }

        // Exact match
        if (strcasecmp($boundDomain, $currentHost) === 0) {
            return true;
        }

        // Wildcard: *.example.com allows any subdomain of example.com
        if (str_starts_with($boundDomain, '*.')) {
            $suffix = substr($boundDomain, 1); // ".example.com"

            return str_ends_with($currentHost, $suffix)
                && $currentHost !== ltrim($suffix, '.');
        }

        return false;
    }

    /**
     * Verify the integrity of the Pro gatekeeper files.
     *
     * Returns true when the check passes OR is skipped (not configured,
     * explicitly disabled, or version mismatch). Returns false ONLY when a
     * known file has been tampered with — the caller must then refuse Pro.
     *
     * This is a deterrent, not a hard lock: PHP source lives on the client's
     * machine, so a determined attacker can still bypass it. It raises the
     * cost of patching the license gate.
     */
    public static function isIntegrityValid(): bool
    {
        if (self::$integrityCache !== null) {
            return self::$integrityCache;
        }

        // Explicit opt-out for legitimate debugging or local patching.
        if (config('raise-import.integrity_disabled', false)) {
            return self::$integrityCache = true;
        }

        $expected = config('raise-import.integrity_hashes', []);
        if (!is_array($expected) || $expected === []) {
            // No hashes shipped — cannot verify, skip gracefully.
            return self::$integrityCache = true;
        }

        // Version guard: if the shipped hashes don't match the installed
        // package version, skip instead of forcing legitimate users into
        // Community mode (e.g. after an upgrade without a rehash).
        $shippedVersion = config('raise-import.integrity_version');
        if ($shippedVersion && $shippedVersion !== self::packageVersion()) {
            self::logIntegrity('version mismatch (' . $shippedVersion . ' != ' . self::packageVersion() . '), skipping');
            return self::$integrityCache = true;
        }

        $base = dirname(__DIR__); // package root: src/License.php → ..

        foreach ($expected as $relative => $hash) {
            $absolute = $base . DIRECTORY_SEPARATOR . $relative;
            if (!is_file($absolute)) {
                self::logIntegrity('file missing: ' . $relative);
                return self::$integrityCache = false;
            }
            $actual = hash_file('sha256', $absolute);
            if (!hash_equals(strtolower((string) $hash), $actual)) {
                self::logIntegrity('hash mismatch for: ' . $relative);
                return self::$integrityCache = false;
            }
        }

        return self::$integrityCache = true;
    }

    /**
     * Resolve the installed package version for the integrity version guard.
     */
    private static function packageVersion(): string
    {
        if (class_exists(\Composer\InstalledVersions::class)
            && \Composer\InstalledVersions::isInstalled('raise-studio/raise-import')) {
            return (string) \Composer\InstalledVersions::getPrettyVersion('raise-studio/raise-import');
        }

        $composer = dirname(__DIR__) . '/composer.json';
        if (is_file($composer)) {
            $data = json_decode((string) file_get_contents($composer), true);
            if (!empty($data['version'])) {
                return (string) $data['version'];
            }
        }

        return 'unknown';
    }

    /**
     * Log an integrity event without ever throwing.
     */
    private static function logIntegrity(string $message): void
    {
        try {
            logger()->warning('[raise-import] License integrity: ' . $message);
        } catch (\Throwable $e) {
            // logging must never break the application
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

        // 2. Loopback host only (this machine). Network-based exemptions
        //    such as *.test / *.local TLDs and private IP ranges
        //    (10.x, 172.16-31.x, 192.168.x) were intentionally removed to
        //    tighten the free-Pro surface — see 2026-07-07 strategy (收敛豁免).
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';

        return in_array($host, ['localhost', '127.0.0.1', '[::1]', '0.0.0.0'], true);
    }

    /**
     * Flush the cached license check result.
     */
    public static function flushCache(): void
    {
        self::$cache = null;
        self::$integrityCache = null;
    }
}
