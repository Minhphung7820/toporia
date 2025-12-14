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
    |
    | Set to false to completely disable multi-tenancy features.
    |
    */
    'enabled' => env('TENANCY_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Tenant Model
    |--------------------------------------------------------------------------
    |
    | The model class that represents a tenant. Must implement TenantInterface.
    |
    */
    'model' => env('TENANCY_MODEL', 'App\\Domain\\Entities\\Tenant'),

    /*
    |--------------------------------------------------------------------------
    | Tenant ID Column
    |--------------------------------------------------------------------------
    |
    | The column name used to store tenant ID in tenant-scoped tables.
    |
    */
    'column' => env('TENANCY_COLUMN', 'tenant_id'),

    /*
    |--------------------------------------------------------------------------
    | Tenant Resolvers
    |--------------------------------------------------------------------------
    |
    | Configure how tenants are identified from incoming requests.
    | Multiple resolvers can be enabled; they run in priority order.
    |
    */
    'resolvers' => [
        /*
        |----------------------------------------------------------------------
        | Subdomain Resolver
        |----------------------------------------------------------------------
        |
        | Identify tenant from subdomain: tenant1.example.com
        | Priority: 100 (highest)
        |
        */
        'subdomain' => [
            'enabled' => env('TENANCY_SUBDOMAIN_ENABLED', false),
            'base_domain' => env('TENANCY_BASE_DOMAIN', 'example.com'),
            'excluded' => ['www', 'api', 'admin', 'mail', 'ftp', 'static', 'cdn'],
        ],

        /*
        |----------------------------------------------------------------------
        | Header Resolver
        |----------------------------------------------------------------------
        |
        | Identify tenant from HTTP header (ideal for APIs).
        | Priority: 90
        |
        */
        'header' => [
            'enabled' => env('TENANCY_HEADER_ENABLED', true),
            'name' => env('TENANCY_HEADER_NAME', 'X-Tenant-ID'),
        ],

        /*
        |----------------------------------------------------------------------
        | Path Resolver
        |----------------------------------------------------------------------
        |
        | Identify tenant from URL path: /tenant1/dashboard
        | Priority: 80 (lowest)
        |
        */
        'path' => [
            'enabled' => env('TENANCY_PATH_ENABLED', false),
            'segment' => 0,  // 0-based index of path segment
            'prefix' => '',  // Optional prefix (e.g., 't-' for /t-tenant1/...)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the tenant identification middleware behavior.
    |
    */
    'middleware' => [
        /*
        | If true, requests without a valid tenant will be rejected.
        | If false, requests continue without tenant context.
        */
        'required' => env('TENANCY_REQUIRED', true),

        /*
        | Check if tenant is active before allowing access.
        */
        'check_active' => env('TENANCY_CHECK_ACTIVE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how tenant data is stored and accessed.
    |
    */
    'database' => [
        /*
        | Strategy: 'single' (shared with tenant_id column) or 'multi' (database per tenant)
        */
        'strategy' => env('TENANCY_DB_STRATEGY', 'single'),

        /*
        | For 'multi' strategy: template for database name
        | Placeholders: {tenant_id}, {tenant_slug}
        */
        'database_name_template' => 'tenant_{tenant_id}',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Tenant resolution caching settings.
    |
    */
    'cache' => [
        'enabled' => env('TENANCY_CACHE_ENABLED', true),
        'prefix' => 'tenant:',
        'ttl' => 3600, // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Central Domains
    |--------------------------------------------------------------------------
    |
    | Domains that should NOT trigger tenant resolution.
    | Use for admin panels, landing pages, etc.
    |
    */
    'central_domains' => [
        // 'admin.example.com',
        // 'www.example.com',
    ],
];
