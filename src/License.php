<?php

namespace RaiseStudio\Import;

use RaiseStudio\License\Contracts\CacheInterface;
use RaiseStudio\License\Contracts\HttpClientInterface;
use RaiseStudio\License\FeatureGate;
use RaiseStudio\License\LicenseClient;
use RaiseStudio\License\Adapters\Laravel\LaravelCache;
use RaiseStudio\License\Adapters\Laravel\LaravelHttp;

/**
 * Static facade for the raise-studio/license-client SDK.
 *
 * Keeps the same public API (isPro(), gatePro(), flushCache(), etc.)
 * so all existing code continues to work unchanged. The actual license
 * validation logic is delegated to LicenseClient and FeatureGate.
 *
 * When the container does not have LicenseClient registered (e.g., during
 * tests or early boot), falls back to local-environment detection.
 */
class License
{
    private static ?bool $cache = null;
    private static ?bool $integrityCache = null;

    /**
     * Check if the current installation has a valid Pro license.
     *
     * Local/dev environments are auto-authorized — no license key needed.
     * Production environments use SDK's 6-level degradation strategy.
     */
    public static function isPro(): bool
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        // Force community mode for testing
        $forceCommunity = config('raise-import.license.force_community', false)
            ?: config('raise-import.force_community', false); // backward compat
        if ($forceCommunity) {
            return self::$cache = false;
        }

        // Local environment auto-exemption
        if (self::isLocalEnvironment()) {
            return self::$cache = true;
        }

        // Integrity self-check — refuse Pro if gatekeeper files are tampered
        if (!self::isIntegrityValid()) {
            return self::$cache = false;
        }

        // Delegates to SDK: FeatureGate::canUse('*') = isPro()
        $gate = self::resolveFeatureGate();
        if ($gate !== null) {
            return self::$cache = $gate->canUse('*');
        }

        // Fallback: no SDK registered — conservative, return false
        return self::$cache = false;
    }

    /**
     * Independent, uncached Pro gate for feature execution points.
     *
     * Unlike isPro() it never reads the static cache, forcing a fresh
     * re-evaluation. This makes a patched isPro() (or its cached result)
     * insufficient to unlock the actual Pro features — each critical file
     * re-checks on its own. Part of the distributed gate strategy.
     */
    public static function gatePro(): bool
    {
        $forceCommunity = config('raise-import.license.force_community', false)
            ?: config('raise-import.force_community', false);
        if ($forceCommunity) {
            return false;
        }

        if (self::isLocalEnvironment()) {
            return true;
        }

        // Integrity self-check — refuse Pro if gatekeeper files are tampered
        if (!self::isIntegrityValid()) {
            return false;
        }

        $gate = self::resolveFeatureGate();

        return $gate !== null && $gate->canUse('*');
    }

    /**
     * Check whether a valid license key is configured and active.
     *
     * Does NOT auto-exempt local environments here — the auto-exemption
     * is handled by isPro() / gatePro() callers.
     */
    public static function hasValidKey(): bool
    {
        $client = self::resolveClient();
        if ($client === null) {
            return false;
        }

        return $client->getStoredLicenseKey() !== null || $client->isPro();
    }

    /**
     * Verify integrity of Pro gatekeeper files.
     *
     * Uses the SDK's IntegrityCheck under the hood. Returns true when
     * the check passes OR is skipped (not configured, explicitly disabled).
     * Returns false ONLY when a known file has been tampered with.
     */
    public static function isIntegrityValid(): bool
    {
        if (self::$integrityCache !== null) {
            return self::$integrityCache;
        }

        $disabled = config('raise-import.license.integrity_disabled', false)
            ?: config('raise-import.integrity_disabled', false);
        if ($disabled) {
            return self::$integrityCache = true;
        }

        $expected = config('raise-import.license.integrity_hashes', []);
        if (empty($expected)) {
            $expected = config('raise-import.integrity_hashes', []);
        }
        if (!is_array($expected) || $expected === []) {
            return self::$integrityCache = true;
        }

        $shippedVersion = config('raise-import.license.integrity_version')
            ?: config('raise-import.integrity_version', '');
        if ($shippedVersion && $shippedVersion !== self::packageVersion()) {
            self::logIntegrity('version mismatch (' . $shippedVersion . ' != ' . self::packageVersion() . '), skipping');
            return self::$integrityCache = true;
        }

        $base = dirname(__DIR__); // src/License.php → package root

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
     * Flush the cached license check result and SDK in-memory state.
     */
    public static function flushCache(): void
    {
        self::$cache = null;
        self::$integrityCache = null;

        // Also flush the SDK's internal memory cache by re-resolving the client
        if (self::containerReady()) {
            try {
                app()->forgetInstance(LicenseClient::class);
                app()->forgetInstance(FeatureGate::class);
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    /**
     * Resolve the SDK FeatureGate from the container.
     * Returns null when the container or SDK instance is unavailable.
     */
    private static function resolveFeatureGate(): ?FeatureGate
    {
        if (!self::containerReady()) {
            return null;
        }

        try {
            $gate = app(FeatureGate::class);
            // Ensure free/pro feature lists are set
            $gate->setFreeFeatures(config('raise-import.license.free_features', []));
            $gate->setAllProFeatures(config('raise-import.license.all_pro_features', []));

            return $gate;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Resolve the SDK LicenseClient from the container.
     * Returns null when the container or SDK instance is unavailable.
     */
    private static function resolveClient(): ?LicenseClient
    {
        if (!self::containerReady()) {
            return null;
        }

        try {
            return app(LicenseClient::class);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Check whether the application container is ready for service resolution.
     */
    private static function containerReady(): bool
    {
        return class_exists(\Illuminate\Support\Facades\App::class)
            && (app()->bound(LicenseClient::class) || app()->bound(FeatureGate::class));
    }

    /**
     * Detect if we're running in a local development environment
     * where license checks should be bypassed.
     */
    private static function isLocalEnvironment(): bool
    {
        // RAISE_IMPORT_SIMULATE_PRODUCTION: disable local exemption
        // and fall through to the real license key check below.
        if (getenv('RAISE_IMPORT_SIMULATE_PRODUCTION')) {
            return false;
        }

        // 1. Laravel APP_ENV === 'local'
        if (app()->environment('local')) {
            return true;
        }

        // 2. Loopback host only (this machine)
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';

        return in_array($host, ['localhost', '127.0.0.1', '[::1]', '0.0.0.0'], true);
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
}
