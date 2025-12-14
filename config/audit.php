<?php

declare(strict_types=1);

/**
 * Audit Logging Configuration
 *
 * Configure how model changes are tracked and stored.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Enable Audit Logging
    |--------------------------------------------------------------------------
    |
    | Set to false to completely disable audit logging.
    |
    */
    'enabled' => env('AUDIT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Driver
    |--------------------------------------------------------------------------
    |
    | The default storage driver for audit logs.
    | Supported: "database", "file"
    |
    */
    'default' => env('AUDIT_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Audit Drivers
    |--------------------------------------------------------------------------
    |
    | Configure each storage driver's specific settings.
    |
    */
    'drivers' => [
        /*
        |----------------------------------------------------------------------
        | Database Driver
        |----------------------------------------------------------------------
        |
        | Stores audit logs in a database table.
        | High performance with batch inserts.
        |
        */
        'database' => [
            'driver' => \Toporia\Framework\Audit\Drivers\DatabaseDriver::class,
            'connection' => env('AUDIT_DB_CONNECTION', null), // null = default
            'table' => env('AUDIT_TABLE', 'audit_logs'),
            'batch_size' => 1000, // Max records per insert
        ],

        /*
        |----------------------------------------------------------------------
        | File Driver
        |----------------------------------------------------------------------
        |
        | Stores audit logs as JSON Lines files.
        | Daily rotation. Useful for development/debugging.
        |
        */
        'file' => [
            'driver' => \Toporia\Framework\Audit\Drivers\FileDriver::class,
            'path' => env('AUDIT_FILE_PATH', null), // null = storage/logs/audit
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Exclude
    |--------------------------------------------------------------------------
    |
    | Attributes that should never be audited (applied to all models).
    | Individual models can override this via $auditExclude property.
    |
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
    |
    | Model events that trigger audit logging.
    |
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
    |
    | Configure how the current user is resolved for audit entries.
    |
    */
    'user' => [
        /*
        | Method to get user ID
        | Options: 'getKey', 'getId', 'id' (property)
        */
        'id_method' => 'getKey',

        /*
        | Properties to try for user name (in order)
        */
        'name_properties' => ['name', 'full_name', 'username', 'email'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Configure how long audit logs are retained.
    | Set to null to keep forever.
    |
    */
    'retention' => [
        'enabled' => env('AUDIT_RETENTION_ENABLED', false),
        'days' => env('AUDIT_RETENTION_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Queue audit operations for better performance.
    | Useful for high-traffic applications.
    |
    */
    'queue' => [
        'enabled' => env('AUDIT_QUEUE_ENABLED', false),
        'connection' => env('AUDIT_QUEUE_CONNECTION', null),
        'queue' => env('AUDIT_QUEUE_NAME', 'audit'),
    ],
];
