<?php

namespace RaiseStudio\Import\License;

use RaiseStudio\License\Contracts\LoggerInterface;
use Illuminate\Support\Facades\Log;

/**
 * Bridges the license-client SDK's LoggerInterface to Laravel's log channel.
 *
 * The SDK already produces detailed diagnostics (signature failure,
 * domain/product mismatch, expiry, activation rejection, offline fallback…)
 * but by default they go to a silent NullLogger. Wiring this adapter in makes
 * those reasons actually appear in the application log — which is exactly what
 * was missing when a correctly-configured license still didn't enable Pro.
 *
 * - debug-level SDK logs are suppressed by default to avoid noise.
 * - enable with config('raise-import.license.sdk_debug') or
 *   RAISE_IMPORT_LICENSE_SDK_DEBUG=true for step-by-step tracing.
 * - license keys / JWTs are redacted before writing.
 */
class LaravelLogger implements LoggerInterface
{
    private function debugEnabled(): bool
    {
        return (bool) config('raise-import.license.sdk_debug', false);
    }

    public function debug(string $message, array $context = []): void
    {
        if (! $this->debugEnabled()) {
            return;
        }
        $this->write('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        try {
            Log::{$level}('[raise-import][sdk] ' . $message, $this->sanitize($context));
        } catch (\Throwable $e) {
            // logging must never break the application
        }
    }

    /**
     * Redact secrets (license key, JWT, private key) so they never hit logs.
     */
    private function sanitize(array $context): array
    {
        $sensitive = ['license_key', 'licensekey', 'token', 'jwt', 'key', 'private_key', 'secret', 'password', 'public_key'];
        foreach ($context as $key => $value) {
            if (is_string($key)
                && in_array(strtolower($key), $sensitive, true)
                && is_string($value)
                && $value !== ''
            ) {
                $context[$key] = substr($value, 0, 4) . '…(redacted)';
            }
        }
        return $context;
    }
}
