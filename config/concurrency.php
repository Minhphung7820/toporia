<?php

declare(strict_types=1);

/**
 * Concurrency Configuration
 *
 * Configure the Concurrency subsystem for parallel task execution.
 *
 * @see \Toporia\Framework\Concurrency\ConcurrencyManager
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Concurrency Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default concurrency "driver" that will be used
    | when running concurrent tasks. Available drivers:
    |
    | - "process" - Spawns PHP CLI processes (works everywhere)
    | - "fork"    - Uses pcntl_fork (CLI only, faster)
    | - "sync"    - Sequential execution (for testing)
    |
    */

    'default' => env('CONCURRENCY_DRIVER', 'process'),

    /*
    |--------------------------------------------------------------------------
    | Global Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time in seconds for concurrent task execution.
    | Set to 0 for no timeout (not recommended in production).
    |
    */

    'timeout' => (int) env('CONCURRENCY_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Maximum Concurrent Tasks
    |--------------------------------------------------------------------------
    |
    | Maximum number of tasks that can run simultaneously.
    | Recommended: number of CPU cores or slightly higher for I/O-bound tasks.
    |
    */

    'max_concurrent' => (int) env('CONCURRENCY_MAX', 4),

    /*
    |--------------------------------------------------------------------------
    | Secret Key for Closure Signing
    |--------------------------------------------------------------------------
    |
    | Optional secret key used to sign serialized closures for security.
    | If not set, closures will be serialized without signing.
    |
    */

    'secret_key' => env('CONCURRENCY_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Driver Specific Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options specific to each driver.
    |
    */

    'drivers' => [

        'process' => [
            /*
            |------------------------------------------------------------------
            | PHP Binary Path
            |------------------------------------------------------------------
            |
            | Path to the PHP binary used to spawn child processes.
            | Usually "php" is sufficient if PHP is in PATH.
            |
            */
            'binary' => env('CONCURRENCY_PHP_BINARY', 'php'),

            /*
            |------------------------------------------------------------------
            | Console Binary Path
            |------------------------------------------------------------------
            |
            | Path to the Toporia console entry point.
            | Usually "console" relative to the project root.
            |
            */
            'command' => env('CONCURRENCY_CONSOLE_BINARY', 'console'),

            /*
            |------------------------------------------------------------------
            | Working Directory
            |------------------------------------------------------------------
            |
            | Working directory for spawned processes.
            | Defaults to the application base path.
            |
            */
            'working_directory' => env('CONCURRENCY_WORKING_DIR'),
        ],

        'fork' => [
            /*
            |------------------------------------------------------------------
            | Enabled
            |------------------------------------------------------------------
            |
            | Whether fork driver is enabled.
            | Will be disabled automatically if PCNTL is not available.
            |
            */
            'enabled' => env('CONCURRENCY_FORK_ENABLED', true),
        ],

        'sync' => [
            /*
            |------------------------------------------------------------------
            | Timeout
            |------------------------------------------------------------------
            |
            | Timeout for sync driver (0 = no timeout).
            | Since sync runs sequentially, this is the total timeout.
            |
            */
            'timeout' => 0,
        ],

    ],
];
