<?php

declare(strict_types=1);

/**
 * Session Configuration
 *
 * Configure session storage and behavior.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Session Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default session driver that will be used.
    |
    | Supported drivers: "file", "database", "redis", "cookie"
    |
    */
    'default' => env('SESSION_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Session Lifetime
    |--------------------------------------------------------------------------
    |
    | Here you may specify the number of seconds that you wish the session
    | to be allowed to remain idle before it expires.
    |
    */
    'lifetime' => env('SESSION_LIFETIME', 7200), // 2 hours

    /*
    |--------------------------------------------------------------------------
    | Session Name
    |--------------------------------------------------------------------------
    |
    | Here you may change the name of the session cookie used by the framework.
    |
    */
    'name' => env('SESSION_NAME', 'PHPSESSID'),

    /*
    |--------------------------------------------------------------------------
    | Session Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the session stores for your application.
    |
    */
    'stores' => [
        'default' => [
            'driver' => env('SESSION_DRIVER', 'file'),
            'lifetime' => env('SESSION_LIFETIME', 7200),
            'name' => env('SESSION_NAME', 'PHPSESSID'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => __DIR__ . '/../storage/sessions',
            'lifetime' => env('SESSION_LIFETIME', 7200),
            'name' => env('SESSION_NAME', 'PHPSESSID'),
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'sessions',
            'lifetime' => env('SESSION_LIFETIME', 7200),
            'name' => env('SESSION_NAME', 'PHPSESSID'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'prefix' => 'session:',
            'lifetime' => env('SESSION_LIFETIME', 7200),
            'name' => env('SESSION_NAME', 'PHPSESSID'),
        ],

        'cookie' => [
            'driver' => 'cookie',
            'lifetime' => env('SESSION_LIFETIME', 7200),
            'name' => env('SESSION_NAME', 'PHPSESSID'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Security
    |--------------------------------------------------------------------------
    |
    | Configure session security features:
    | - IP binding: Bind session to IP address (prevents session hijacking)
    | - Device fingerprinting: Bind session to device (User-Agent, etc.)
    | - Session rotation: Regenerate session ID periodically
    | - Maximum lifetime: Force session expiration after specified time
    |
    */
    'security' => [
        'enable_ip_binding' => env('SESSION_SECURITY_IP_BINDING', true),
        'enable_fingerprinting' => env('SESSION_SECURITY_FINGERPRINTING', true),
        'rotation_interval' => env('SESSION_SECURITY_ROTATION_INTERVAL', 300), // 5 minutes
        'max_lifetime' => env('SESSION_SECURITY_MAX_LIFETIME', 0), // 0 = no limit
    ],
];
