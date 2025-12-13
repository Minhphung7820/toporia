<?php

declare(strict_types=1);

use App\Infrastructure\Consumers\SendOrderCreatedHandler;
use App\Infrastructure\Consumers\UserActionEventHandler;

/**
 * Consumer Handlers Configuration
 *
 * Register consumer handlers here. Each handler is a class that processes
 * messages from realtime brokers (Redis, RabbitMQ, Kafka).
 *
 * Handlers can be:
 * 1. Registered explicitly in this file
 * 2. Auto-discovered from configured paths
 *
 * Usage:
 *   php console broker:handler:consume --handler=SendOrderCreated --driver=rabbitmq
 *   php console broker:consumers                    # List all running consumers
 *   php console broker:consumer:status <id>        # Show consumer details
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Registered Handlers
    |--------------------------------------------------------------------------
    |
    | Map of handler names to handler classes.
    | Handler name is used in --handler=Name option.
    |
    | Format:
    |   'HandlerName' => HandlerClass::class,
    |
    | Example:
    |   'SendOrderCreated' => SendOrderCreatedHandler::class,
    |   'ProcessPayment' => ProcessPaymentHandler::class,
    |
    */
    'handlers' => [
        'SendOrderCreated' => SendOrderCreatedHandler::class,
        'UserActionEvent' => UserActionEventHandler::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Discovery Paths
    |--------------------------------------------------------------------------
    |
    | Directories to scan for consumer handler classes.
    | Classes must implement ConsumerHandlerInterface.
    |
    | Format:
    |   'path/to/handlers' => 'Namespace\\For\\Handlers',
    |
    */
    'discovery' => [
        // Enable auto-discovery from these paths:
        // base_path('app/Infrastructure/Consumers') => 'App\\Infrastructure\\Consumers',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for consumer handlers.
    | Can be overridden in individual handler classes.
    |
    */
    'defaults' => [
        // Default broker driver if handler doesn't specify one
        'driver' => env('CONSUMER_DEFAULT_DRIVER', 'redis'),

        // Maximum retry attempts for failed messages
        'max_retries' => (int) env('CONSUMER_MAX_RETRIES', 3),

        // Base retry delay in milliseconds
        'retry_delay' => (int) env('CONSUMER_RETRY_DELAY', 1000),

        // Use exponential backoff for retries
        'exponential_backoff' => (bool) env('CONSUMER_EXPONENTIAL_BACKOFF', true),

        // Memory limit in MB (0 = unlimited)
        'memory_limit' => (int) env('CONSUMER_MEMORY_LIMIT', 128),

        // Maximum messages before worker restart (0 = unlimited)
        'max_messages' => (int) env('CONSUMER_MAX_MESSAGES', 0),

        // Maximum runtime in seconds (0 = unlimited)
        'max_time' => (int) env('CONSUMER_MAX_TIME', 0),

        // Sleep time in ms between consume cycles
        'sleep' => (int) env('CONSUMER_SLEEP', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Process Management
    |--------------------------------------------------------------------------
    |
    | Settings for consumer process tracking and management.
    |
    */
    'process' => [
        // Heartbeat interval in seconds
        'heartbeat_interval' => (int) env('CONSUMER_HEARTBEAT_INTERVAL', 10),

        // Heartbeat timeout in seconds (process considered dead after this)
        'heartbeat_timeout' => (int) env('CONSUMER_HEARTBEAT_TIMEOUT', 60),

        // TTL for stopped process records in seconds
        'stopped_ttl' => (int) env('CONSUMER_STOPPED_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Logging configuration for consumer handlers.
    |
    */
    'logging' => [
        // Log channel for consumer messages
        'channel' => env('CONSUMER_LOG_CHANNEL', 'daily'),

        // Log level for message processing
        'level' => env('CONSUMER_LOG_LEVEL', 'info'),

        // Log message data (may contain sensitive info)
        'log_data' => (bool) env('CONSUMER_LOG_DATA', false),
    ],
];
