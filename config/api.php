<?php

declare(strict_types=1);

/**
 * API Configuration
 *
 * Configure API versioning, rate limiting, and other API-related settings.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | API Versioning
    |--------------------------------------------------------------------------
    |
    | Configure how API versions are detected and managed.
    |
    */
    'versioning' => [
        /*
        | Enable API versioning
        */
        'enabled' => env('API_VERSIONING_ENABLED', true),

        /*
        | Default API version when none specified
        */
        'default' => env('API_DEFAULT_VERSION', 'v1'),

        /*
        | Supported versions (newest first)
        | Requests with unsupported versions will use default
        */
        'supported' => ['v1'],

        /*
        | Deprecated versions with sunset dates
        | Format: 'version' => 'YYYY-MM-DD'
        */
        'deprecated' => [
            // 'v1' => '2025-12-31',
        ],

        /*
        | Version resolvers
        | Configure how version is detected from requests
        */
        'resolvers' => [
            /*
            | Header resolver (highest priority: 100)
            | Checks: X-API-Version, Accept-Version, API-Version
            */
            'header' => [
                'enabled' => true,
                'names' => ['X-API-Version', 'Accept-Version', 'API-Version'],
            ],

            /*
            | Path resolver (priority: 90)
            | Extracts version from URL: /api/v1/users
            */
            'path' => [
                'enabled' => true,
                'prefix' => 'api',
            ],

            /*
            | Accept header resolver (priority: 85)
            | Parses: Accept: application/vnd.api.v1+json
            */
            'accept' => [
                'enabled' => false,
                'vendor' => 'vnd.api',
            ],

            /*
            | Query parameter resolver (lowest priority: 80)
            | Reads: ?api_version=v1
            */
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
    |
    | Headers to include in API responses.
    |
    */
    'headers' => [
        'include_version' => true,
        'include_deprecation' => true,
    ],
];
