<?php

declare(strict_types=1);

/**
 * Console Commands Configuration
 *
 * Define your application-specific console commands here.
 * Framework commands are auto-registered.
 *
 * This file provides explicit command mapping for optimal performance:
 * - Lazy loading (commands instantiated only when executed)
 * - No auto-discovery overhead on every request
 * - Clear command structure
 *
 * Format:
 * 'command:name' => CommandClass::class
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Application Commands
    |--------------------------------------------------------------------------
    |
    | Register your custom application commands here.
    | These are business-logic commands specific to your application.
    |
    | Example:
    |   'user:create' => App\Presentation\Console\Commands\CreateUserCommand::class,
    |   'report:generate' => App\Presentation\Console\Commands\GenerateReportCommand::class,
    |
    */

    // Excel commands
    'export:excel' => App\Presentation\Console\Commands\ExportExcelCommand::class,
    'import:excel' => App\Presentation\Console\Commands\ImportExcelCommand::class,

    // Posts commands
    'import:posts' => App\Presentation\Console\Commands\ImportPostsCommand::class,
    'export:posts' => App\Presentation\Console\Commands\ExportPostsCommand::class,

    // Email commands
    'email:daily' => App\Presentation\Console\Commands\SendDailyEmailCommand::class,

    /*
    |--------------------------------------------------------------------------
    | Auto-Discovery
    |--------------------------------------------------------------------------
    |
    | Enable auto-discovery to scan directories for commands.
    | Set to false for better performance (manual registration above).
    |
    */
    'auto_discovery' => [
        'enabled' => false, // Set to true to enable auto-discovery
        'paths' => [
            // Directories to scan for commands
            base_path('app/Presentation/Console/Commands'),
        ],
        'namespaces' => [
            // Corresponding namespaces
            'App\\Presentation\\Console\\Commands',
        ],
        'cache' => storage_path('cache/commands.php'), // Cache file path
    ],

    /*
    |--------------------------------------------------------------------------
    | Framework Commands
    |--------------------------------------------------------------------------
    |
    | Framework commands are automatically registered by ConsoleServiceProvider.
    | No need to list them here.
    |
    | Categories:
    | - Database: migrate, migrate:rollback, migrate:status, db:seed, etc.
    | - Cache: cache:clear, cache:table, config:cache, route:cache
    | - Queue: queue:work, queue:listen, queue:retry, queue:failed
    | - Schedule: schedule:run, schedule:work, schedule:list, schedule:test
    | - Event: event:list, event:cache, event:generate
    | - Realtime: realtime:serve, realtime:kafka, realtime:redis, realtime:rabbitmq
    | - Make: make:command, make:controller, make:model, make:migration, etc.
    | - Optimize: optimize, optimize:clear, view:cache, storage:link
    | - App: about, env, down, up, inspire, tinker, serve, test
    |
    */
];
