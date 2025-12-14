<?php

declare(strict_types=1);

/**
 * Multi-Tenancy Configuration
 *
 * Configure how tenants are identified, resolved, and managed.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Enable Multi-Tenancy
    |--------------------------------------------------------------------------
    */
    'enabled' => env('TENANCY_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Tenant Model
    |--------------------------------------------------------------------------
    */
    'model' => env('TENANCY_MODEL', 'App\\Domain\\Entities\\Tenant'),

    /*
    |--------------------------------------------------------------------------
    | Tenant ID Column
    |--------------------------------------------------------------------------
    */
    'column' => env('TENANCY_COLUMN', 'tenant_id'),

    /*
    |--------------------------------------------------------------------------
    | Tenant Resolvers
    |--------------------------------------------------------------------------
    */
    'resolvers' => [
        'subdomain' => [
            'enabled' => env('TENANCY_SUBDOMAIN_ENABLED', false),
            'base_domain' => env('TENANCY_BASE_DOMAIN', 'example.com'),
            'excluded' => ['www', 'api', 'admin', 'mail', 'ftp', 'static', 'cdn'],
        ],
        'header' => [
            'enabled' => env('TENANCY_HEADER_ENABLED', true),
            'name' => env('TENANCY_HEADER_NAME', 'X-Tenant-ID'),
        ],
        'path' => [
            'enabled' => env('TENANCY_PATH_ENABLED', false),
            'segment' => 0,
            'prefix' => '',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        'required' => env('TENANCY_REQUIRED', true),
        'check_active' => env('TENANCY_CHECK_ACTIVE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    */
    'database' => [
        'strategy' => env('TENANCY_DB_STRATEGY', 'single'),
        'database_name_template' => 'tenant_{tenant_id}',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('TENANCY_CACHE_ENABLED', true),
        'prefix' => 'tenant:',
        'ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Central Domains
    |--------------------------------------------------------------------------
    */
    'central_domains' => [],
];
