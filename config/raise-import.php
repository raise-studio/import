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
    | License Configuration
    |--------------------------------------------------------------------------
    |
    | Uses the raise-studio/license-client SDK for JWT-based license validation.
    | See: https://github.com/raise-studio/raise-license-client-sdk
    |
    | Environment variables:
    |   RAISE_IMPORT_LICENSE_KEY      — License activation key (set in .env to auto-activate)
    |   RAISE_IMPORT_LICENSE_EMAIL    — Email associated with the license (optional)
    |   RAISE_IMPORT_PUBLIC_KEY       — RSA public key (Base64) for JWT verification
    |   RAISE_IMPORT_API_URL          — License server API base URL
    |   RAISE_IMPORT_LICENSE_LOCALE   — 'zh' or 'en'
    |
    | NOTE: Authorization is decided entirely by the ACTUAL domain the site is
    | accessed through and whether it is bound to a valid license (JWT site_hash).
    | No config value (APP_ENV / APP_URL / force_community) can grant or deny Pro.
    |
    */
    'license' => [
        'key'                => env('RAISE_IMPORT_LICENSE_KEY', ''),
        'email'              => env('RAISE_IMPORT_LICENSE_EMAIL', ''),
        'product_code'       => env('RAISE_IMPORT_LICENSE_PRODUCT', 'raise-import'),
        'public_key_base64'  => env('RAISE_IMPORT_PUBLIC_KEY', ''),
        'api_base_url'       => env('RAISE_IMPORT_API_URL', 'https://admin.raisestudio.dev/api/v1'),
        'integrity_disabled' => env('RAISE_IMPORT_INTEGRITY_DISABLED', false),
        'integrity_version'  => '1.0.0',
        'integrity_hashes'   => [],
        'locale'             => env('RAISE_IMPORT_LICENSE_LOCALE', 'zh'),
        'free_features'      => [
            'basic_import',
            'csv_support',
            'excel_support',
            'auto_mapping',
        ],
        'all_pro_features'   => [
            'advanced_mapping',
            'queue',
            'import_log',
            'merge_split',
            'pipeline',
        ],
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
