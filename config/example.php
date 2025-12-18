<?php

declare(strict_types=1);

/**
 * Example Package Configuration
 *
 * This configuration file is auto-discovered by Toporia Framework
 * via PackageManifest and merged into the application config.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Example Feature Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable the example package features.
    |
    */
    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Example API Key
    |--------------------------------------------------------------------------
    |
    | API key for example service integration.
    |
    */
    'api_key' => env('EXAMPLE_API_KEY', 'default-key'),

    /*
    |--------------------------------------------------------------------------
    | Example Options
    |--------------------------------------------------------------------------
    |
    | Additional options for the example package.
    |
    */
    'options' => [
        'cache_ttl' => 3600,
        'retry_attempts' => 3,
        'timeout' => 30,
    ],
];
