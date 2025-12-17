<?php

declare(strict_types=1);

/**
 * Dominion RBAC Configuration
 *
 * Enterprise-grade Role-Based Access Control settings for Toporia Framework.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Model Classes
    |--------------------------------------------------------------------------
    |
    | Specify the model classes to use for roles and permissions.
    | You can override these with your own custom implementations.
    |
    */
    'models' => [
        'role' => \Toporia\Dominion\Models\Role::class,
        'permission' => \Toporia\Dominion\Models\Permission::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Table Names
    |--------------------------------------------------------------------------
    |
    | Customize the table names used by the Dominion RBAC package.
    |
    */
    'tables' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'role_user' => 'role_user',
        'permission_role' => 'permission_role',
        'permission_user' => 'permission_user',
    ],

    /*
    |--------------------------------------------------------------------------
    | Foreign Key Column Names
    |--------------------------------------------------------------------------
    |
    | Specify the foreign key column names for relationships.
    |
    */
    'foreign_keys' => [
        'users' => 'user_id',
        'roles' => 'role_id',
        'permissions' => 'permission_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The user model class that will be used for RBAC relationships.
    |
    */
    'user_model' => null, // Will auto-detect from auth config if null

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configure caching for optimal performance.
    | Caching significantly improves permission check speed (O(1) after first load).
    |
    */
    'cache' => [
        // Enable/disable caching
        'enabled' => true,

        // Cache driver (null = use default cache driver)
        'driver' => null,

        // Time-to-live in seconds (default: 24 hours)
        'ttl' => 86400,

        // Cache key prefix
        'prefix' => 'dominion_',

        // Cache tags for selective clearing (requires tag-supporting driver like Redis)
        'tags' => ['dominion'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Super Admin Configuration
    |--------------------------------------------------------------------------
    |
    | Configure super admin bypass behavior.
    | Super admins automatically pass all permission checks.
    |
    */
    'super_admin' => [
        // Enable super admin bypass
        'enabled' => true,

        // Role name that grants super admin privileges
        'role' => 'super-admin',

        // Permission that grants super admin privileges (alternative to role)
        'permission' => '*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Hierarchy
    |--------------------------------------------------------------------------
    |
    | Configure role hierarchy and inheritance behavior.
    | Child roles can inherit permissions from parent roles.
    |
    */
    'hierarchy' => [
        // Enable role hierarchy
        'enabled' => true,

        // Child roles inherit parent permissions
        'inherit_permissions' => true,

        // Maximum hierarchy depth (0 = unlimited)
        'max_depth' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Wildcard Permissions
    |--------------------------------------------------------------------------
    |
    | Configure wildcard permission matching.
    | Examples: "users.*" matches "users.create", "users.update", etc.
    |
    */
    'wildcards' => [
        // Enable wildcard permission matching
        'enabled' => true,

        // Separator between resource and action
        'separator' => '.',

        // Wildcard character
        'character' => '*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Gate Integration
    |--------------------------------------------------------------------------
    |
    | Automatically register RBAC with the authorization Gate.
    | This enables using can() and authorize() with RBAC permissions.
    |
    */
    'register_gate' => true,

    /*
    |--------------------------------------------------------------------------
    | Middleware Aliases
    |--------------------------------------------------------------------------
    |
    | Define middleware aliases for use in routes.
    |
    */
    'middleware' => [
        'role' => \Toporia\Dominion\Middleware\RoleMiddleware::class,
        'permission' => \Toporia\Dominion\Middleware\PermissionMiddleware::class,
        'role_or_permission' => \Toporia\Dominion\Middleware\RoleOrPermissionMiddleware::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Exception Settings
    |--------------------------------------------------------------------------
    |
    | Configure exception behavior for unauthorized access.
    |
    */
    'exceptions' => [
        // HTTP status code for unauthorized responses
        'status_code' => 403,

        // Custom exception class (null = use default UnauthorizedException)
        'class' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Settings
    |--------------------------------------------------------------------------
    |
    | Track changes to roles and permissions for audit purposes.
    |
    */
    'audit' => [
        // Enable audit logging
        'enabled' => true,

        // Events to audit
        'events' => [
            'role_created',
            'role_updated',
            'role_deleted',
            'permission_created',
            'permission_updated',
            'permission_deleted',
            'role_assigned',
            'role_revoked',
            'permission_assigned',
            'permission_revoked',
        ],
    ],
];
