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
        'https://license.raise-studio.com/api/license/verify'
    ),

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
