<?php

declare(strict_types=1);

/**
 * Bus Configuration
 *
 * Command/Query/Job Bus settings.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Command => Handler Mappings
    |--------------------------------------------------------------------------
    |
    | Explicit command to handler mappings.
    | If not specified, convention is used: CommandName => CommandNameHandler
    |
    | Example:
    | 'mappings' => [
    |     \App\Application\User\CreateUser\CreateUserCommand::class => \App\Application\User\CreateUser\CreateUserHandler::class,
    | ],
    */
    'mappings' => [
        // Add your explicit mappings here...
    ],

    /*
    |--------------------------------------------------------------------------
    | Bus Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware to run before dispatching commands.
    | Useful for logging, validation, transaction wrapping, etc.
    |
    | Example:
    | 'middleware' => [
    |     \App\Bus\Middleware\LogCommand::class,
    |     \App\Bus\Middleware\WrapInTransaction::class,
    | ],
    */
    'middleware' => [
        // Add your middleware here...
    ],

    /*
    |--------------------------------------------------------------------------
    | Batch Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for batch job operations.
    */
    'batch' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'job_batches',
    ],
];
