<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    */
    'table_names' => [
        'import_logs' => 'import_logs',
    ],

    /*
    |--------------------------------------------------------------------------
    | License Key
    |--------------------------------------------------------------------------
    |
    | Pro license key. Leave empty for Community mode.
    | Set via RAISE_IMPORT_LICENSE_KEY environment variable.
    |
    | IMPORTANT: Local development environments (localhost, 127.0.0.1,
    | .local / .test domains, private IPs) are EXEMPT from license checks
    | and get full Pro features without a key.
    |
    */
    'license_key' => env('RAISE_IMPORT_LICENSE_KEY', null),

    /*
    |--------------------------------------------------------------------------
    | License Verify URL
    |--------------------------------------------------------------------------
    |
    | URL of the license verification server.
    | The plugin will POST the license key and domain for validation.
    |
    */
    'license_verify_url' => env(
        'RAISE_IMPORT_LICENSE_VERIFY_URL',
        'https://license.raisestudio.dev/api/v1/verify'
    ),

    /*
    |--------------------------------------------------------------------------
    | License Server Shared Secret
    |--------------------------------------------------------------------------
    |
    | Shared HMAC secret used to verify the signature returned by the license
    | server. MUST match the value configured on raise-license-server.
    |
    | Without this secret, the plugin cannot trust any positive verification
    | response — a forged or relayed endpoint returning `valid:true` will be
    | rejected because it cannot produce a valid HMAC signature.
    |
    | Set via RAISE_IMPORT_LICENSE_SECRET environment variable.
    |
    */
    'license_secret' => env('RAISE_IMPORT_LICENSE_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | License Product Slug
    |--------------------------------------------------------------------------
    |
    | Product identifier sent to the license server during verification.
    | Must match a Product slug configured on raise-license-server.
    |
    | Set via RAISE_IMPORT_LICENSE_PRODUCT environment variable.
    |
    */
    'license_product' => env('RAISE_IMPORT_LICENSE_PRODUCT', 'raise-import'),

    /*
    |--------------------------------------------------------------------------
    | Force Community Mode (Testing)
    |--------------------------------------------------------------------------
    |
    | Set to true to force community mode even in local/dev environments.
    | Useful for testing community feature isolation during development.
    |
    | Usage: RAISE_IMPORT_FORCE_COMMUNITY=true in .env
    |
    */
    'force_community' => env('RAISE_IMPORT_FORCE_COMMUNITY', false),

    /*
    |--------------------------------------------------------------------------
    | Integrity Self-Check
    |--------------------------------------------------------------------------
    |
    | Verifies that the Pro gatekeeper files have not been tampered with
    | (e.g. patched to force Pro mode). If a file's SHA-256 differs from the
    | expected value, Pro features are refused and the installation silently
    | falls back to Community mode.
    |
    | This is a deterrent, not a hard lock — PHP source is always on the
    | client's machine. It raises the cost of bypassing the license.
    |
    | Regenerate `integrity_hashes` for every release with:
    |     php artisan raise-import:integrity:rehash
    |
    | `integrity_version` must match the installed package version. If it
    | does not (e.g. an upgrade without a rehash), the check is skipped to
    | avoid false positives.
    |
    | Disable entirely with RAISE_IMPORT_INTEGRITY_DISABLED=true (debugging).
    |
    */
    'integrity_disabled' => env('RAISE_IMPORT_INTEGRITY_DISABLED', false),

    'integrity_version' => '1.0.0',

    'integrity_hashes' => [
        // 'src/License.php' => '...',
        // 'src/Pro/Actions/ProImportAction.php' => '...',
        // 'src/RaiseImportServiceProvider.php' => '...',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Chunk Size
    |--------------------------------------------------------------------------
    |
    | Number of rows to process per database transaction.
    |
    */
    'chunk_size' => 500,

    /*
    |--------------------------------------------------------------------------
    | Default Duplicate Behavior
    |--------------------------------------------------------------------------
    |
    | One of: 'skip', 'update', 'error'
    |
    */
    'duplicate_behavior' => 'skip',

    /*
    |--------------------------------------------------------------------------
    | Allowed File Types
    |--------------------------------------------------------------------------
    */
    'allowed_extensions' => ['csv', 'xlsx', 'ods'],

    /*
    |--------------------------------------------------------------------------
    | Max File Size (in MB)
    |--------------------------------------------------------------------------
    */
    'max_file_size' => 50,

    /*
    |--------------------------------------------------------------------------
    | Preview Row Limit
    |--------------------------------------------------------------------------
    */
    'preview_limit' => 10,

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Set to true to process large imports via queue.
    | Imports with total_rows > chunk_size * 10 will be queued by default.
    |
    */
    'queue' => [
        'enabled' => false,
        'connection' => env('QUEUE_CONNECTION', 'sync'),
        'queue' => 'imports',
        'threshold' => 5000,
    ],
];
