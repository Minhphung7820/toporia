<?php

declare(strict_types=1);

/**
 * API Configuration
 */
return [
    /*
    |--------------------------------------------------------------------------
    | API Versioning
    |--------------------------------------------------------------------------
    */
    'versioning' => [
        'enabled' => env('API_VERSIONING_ENABLED', true),
        'default' => env('API_DEFAULT_VERSION', 'v1'),
        'supported' => ['v1'],
        'deprecated' => [],

        'resolvers' => [
            'header' => [
                'enabled' => true,
                'names' => ['X-API-Version', 'Accept-Version', 'API-Version'],
            ],
            'path' => [
                'enabled' => true,
                'prefix' => 'api',
            ],
            'accept' => [
                'enabled' => false,
                'vendor' => 'vnd.api',
            ],
            'query' => [
                'enabled' => false,
                'param' => 'api_version',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Headers
    |--------------------------------------------------------------------------
    */
    'headers' => [
        'include_version' => true,
        'include_deprecation' => true,
    ],
];
