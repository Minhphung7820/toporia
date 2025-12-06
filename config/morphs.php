<?php

declare(strict_types=1);

/**
 * Morph Map Configuration
 *
 * Define custom morph type aliases for polymorphic relationships.
 *
 * This configuration is automatically loaded and merged with the global morph map
 * when the Relation class is first accessed.
 *
 * Benefits of using morph aliases:
 * - Shorter database values (e.g., 'post' instead of 'App\Models\Post')
 * - Decoupled from class names (refactoring-safe)
 * - More readable database records
 *
 * @see \Toporia\Framework\Database\ORM\Relations\Relation::morphMap()
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Morph Map
    |--------------------------------------------------------------------------
    |
    | Define mappings from short aliases to full class names.
    |
    | Format: 'alias' => 'Full\Class\Name'
    |
    | Example:
    | 'post' => App\Models\Post::class,
    | 'video' => App\Models\Video::class,
    | 'user' => App\Models\User::class,
    |
    */
    'map' => [
        // Add your morph type mappings here
        // 'post' => App\Models\Post::class,
        // 'video' => App\Models\Video::class,
        // 'comment' => App\Models\Comment::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Discovery
    |--------------------------------------------------------------------------
    |
    | Enable auto-discovery of morph types from model classes.
    | When enabled, models with a static $morphAlias property will be
    | automatically registered in the morph map.
    |
    | Set to true to enable auto-discovery during application boot.
    |
    */
    'auto_discover' => env('MORPH_AUTO_DISCOVER', false),

    /*
    |--------------------------------------------------------------------------
    | Auto-Discovery Paths
    |--------------------------------------------------------------------------
    |
    | Directories to scan for models when auto-discovery is enabled.
    | Paths are relative to the application base path.
    |
    */
    'discovery_paths' => [
        'app/Models',
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Discovery Namespace
    |--------------------------------------------------------------------------
    |
    | Base namespace for auto-discovered models.
    |
    */
    'discovery_namespace' => 'App\\Models',
];
