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
    | ======================================================================
    |  使用者只需在 .env 配置 1 行（其余全部有内置默认值）：
    |      RAISE_IMPORT_LICENSE_KEY=你拿到的授权码
    |  插件启动时会自动用该 Key 静默激活，无需后台操作。
    | ======================================================================
    |
    | 可选覆盖项（一般不用动）：
    |   RAISE_IMPORT_LICENSE_EMAIL  — 绑定邮箱（可选）
    |   RAISE_IMPORT_PUBLIC_KEY     — RSA 公钥；已随包内置，仅升级密钥时覆盖
    |   RAISE_IMPORT_PUBLIC_KEY_FP  — 公钥指纹锁定（安全加固，可选）；见 tools/public_key_fingerprint.php
    |   RAISE_IMPORT_API_URL        — 授权服务器地址（默认已配好）
    |   RAISE_IMPORT_LICENSE_LOCALE — 'zh' | 'en'
    |   RAISE_IMPORT_INTEGRITY_DISABLED — 调试用，生产务必 false
    |   RAISE_IMPORT_LICENSE_SDK_DEBUG — true 时输出 SDK 逐步排障日志（失败原因 warning/error 默认已输出）
    |
    | NOTE: 授权判定完全由「实际访问域名 + 该域名是否绑定有效 License」
    | 决定，任何配置值（APP_ENV/APP_URL/force_community）都无法绕过。
    |
    */
    'license' => [
        'key'                => env('RAISE_IMPORT_LICENSE_KEY', ''),
        'email'              => env('RAISE_IMPORT_LICENSE_EMAIL', ''),
        'product_code'       => env('RAISE_IMPORT_LICENSE_PRODUCT', 'import-for-filament'),
        'public_key_base64'  => env('RAISE_IMPORT_PUBLIC_KEY', 'LS0tLS1CRUdJTiBQVUJMSUMgS0VZLS0tLS0NCk1JSUJJakFOQmdrcWhraUc5dzBCQVFFRkFBT0NBUThBTUlJQkNnS0NBUUVBcFVIOTdrRjl0aThjZHhOT00vVDQNCmtoODMwREY3MnMzUlhib2NxQXZKTkhXbzJ0NkJtbk43Zk9aNDNjMTQ0WVNObTd4aFFSSHQyZlVMelNMKy8wRUsNClhRMCtYdEJqTUc3ZS9VaEdtMHRhLy9UdGtLRndwbmV1UGcwckFqa293U3AxRWljN2lxemtHSk9RZTNDa0NsT1MNCkVydFh6UGpSYzZaRUFBRWRpSi8rNUtacFg3TUU0bDNOUUNUWDNxbDg1MzVxanI4ZkJjeTJ4SGJYdm1EMnRXL24NCndpVEJOTHJBQTdobTNjWXkvK0R1MVlacWk5dk03dXpCV2MwanFOK0trbUwzMXNCckFLTTVUYlZBQzdrU2JDRXUNCjBoeXNyaVptNTBXOG5jUWdQZ2pNM05KT2cydW93WlhoSkRZRjF2Q1NOdGg3dXFqaE9zOGo4MDh1UDJha0pNSS8NCk93SURBUUFCDQotLS0tLUVORCBQVUJMSUMgS0VZLS0tLS0NCg=='),
        // 公钥指纹锁定（可选，安全加固）：填写授权服务器公钥的 SHA-256 指纹后，
        // 客户端只信任指纹匹配的公钥（抗中间人）。留空=TOFU（首次信任服务器下发公钥）。
        // 计算方法见 tools/public_key_fingerprint.php
        'public_key_fingerprint' => env('RAISE_IMPORT_PUBLIC_KEY_FP', ''),
        'api_base_url'       => env('RAISE_IMPORT_API_URL', 'https://open.raisestudio.dev/api/v1'),
        'integrity_disabled' => env('RAISE_IMPORT_INTEGRITY_DISABLED', false),
        'integrity_version'  => '1.0.0',
        'integrity_hashes'   => [],
        // Set true (RAISE_IMPORT_LICENSE_SDK_DEBUG=true) to also log the SDK's
        // per-step debug tracing. Failure reasons (warning/error) are always logged.
        'sdk_debug'          => env('RAISE_IMPORT_LICENSE_SDK_DEBUG', false),
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
