<?php

declare(strict_types=1);

/**
 * Audit Logging Configuration
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Enable Audit Logging
    |--------------------------------------------------------------------------
    */
    'enabled' => env('AUDIT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Driver
    |--------------------------------------------------------------------------
    */
    'default' => env('AUDIT_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Audit Drivers
    |--------------------------------------------------------------------------
    */
    'drivers' => [
        'database' => [
            'driver' => \Toporia\Audit\Drivers\DatabaseDriver::class,
            'connection' => env('AUDIT_DB_CONNECTION', null),
            'table' => env('AUDIT_TABLE', 'audit_logs'),
            'batch_size' => 1000,
        ],
        'file' => [
            'driver' => \Toporia\Audit\Drivers\FileDriver::class,
            'path' => env('AUDIT_FILE_PATH', null),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Exclude
    |--------------------------------------------------------------------------
    */
    'exclude' => [
        'password',
        'remember_token',
        'api_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ],

    /*
    |--------------------------------------------------------------------------
    | Events to Audit
    |--------------------------------------------------------------------------
    */
    'events' => [
        'created' => true,
        'updated' => true,
        'deleted' => true,
        'restored' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | User Resolution
    |--------------------------------------------------------------------------
    */
    'user' => [
        'id_method' => 'getKey',
        'name_properties' => ['name', 'full_name', 'username', 'email'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    */
    'retention' => [
        'enabled' => env('AUDIT_RETENTION_ENABLED', false),
        'days' => env('AUDIT_RETENTION_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'enabled' => env('AUDIT_QUEUE_ENABLED', false),
        'connection' => env('AUDIT_QUEUE_CONNECTION', null),
        'queue' => env('AUDIT_QUEUE_NAME', 'audit'),
    ],
];
