<?php

declare(strict_types=1);

/**
 * Realtime Configuration
 *
 * Multi-transport and multi-broker realtime communication system.
 * Supports: WebSocket, Socket.IO, Redis Pub/Sub, RabbitMQ, Kafka, NATS
 *
 * Architecture:
 * - Transports: WebSocket/Socket.IO servers (bidirectional, standalone)
 * - Brokers: Message brokers for multi-server scaling (Redis, Kafka, RabbitMQ)
 * - SSE: Moved to Http layer (see Toporia\Framework\Http\Sse\SseController)
 *
 * Performance Tips:
 * - Use WebSocket for best latency (<5ms)
 * - Use Redis broker for multi-server scaling
 * - Use Memory transport for single-server testing
 * - Enable presence channels for online user tracking
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Transport
    |--------------------------------------------------------------------------
    |
    | Default transport for client-server communication.
    | Options: 'memory', 'websocket', 'socketio'
    |
    | - memory: In-memory (testing only)
    | - websocket: Native WebSocket via Swoole (production, fastest)
    | - socketio: Socket.IO v4 compatible (production, more features)
    |
    | Note: SSE is NOT a transport - it's an HTTP streaming controller.
    | Use Toporia\Framework\Http\Sse\SseController for SSE.
    |
    */
    'default_transport' => env('REALTIME_TRANSPORT', 'memory'),

    /*
    |--------------------------------------------------------------------------
    | Default Broker
    |--------------------------------------------------------------------------
    |
    | Default message broker for multi-server fan-out.
    | Options: null, 'redis', 'rabbitmq', 'nats', 'postgres'
    |
    | null: No broker (single server only)
    | redis: Redis Pub/Sub (simple, fast)
    | kafka: Apache Kafka (high-throughput, persistent)
    | rabbitmq: RabbitMQ AMQP (durable, routing)
    | nats: NATS messaging (ultra-fast, clustering)
    | postgres: PostgreSQL LISTEN/NOTIFY (DB-based)
    |
    */
    'default_broker' => env('REALTIME_BROKER', null),

    /*
    |--------------------------------------------------------------------------
    | Transport Drivers
    |--------------------------------------------------------------------------
    |
    | Configure available transport drivers.
    |
    */
    'transports' => [
        'memory' => [
            'driver' => 'memory',
        ],

        'websocket' => [
            'driver' => 'websocket',
            'host' => env('REALTIME_WS_HOST', '0.0.0.0'),
            'port' => env('REALTIME_WS_PORT', 6001),
            'ssl' => env('REALTIME_WS_SSL', false),
            'cert' => env('REALTIME_WS_CERT'),
            'key' => env('REALTIME_WS_KEY'),

            // Performance settings
            'max_connections' => (int) env('REALTIME_MAX_CONNECTIONS', 10000),
            'worker_num' => (int) env('REALTIME_WORKER_NUM', 0), // 0 = auto (CPU * 2)

            // Authentication settings
            'require_auth' => env('REALTIME_REQUIRE_AUTH', false), // Reject unauthenticated connections on connect?
            'require_auth_for_subscribe' => env('REALTIME_REQUIRE_AUTH_SUBSCRIBE', true), // Require auth before subscribing?
        ],

        'socketio' => [
            'driver' => 'socketio',
            'host' => env('REALTIME_SOCKETIO_HOST', '0.0.0.0'),
            'port' => env('REALTIME_SOCKETIO_PORT', 3000),
            'ssl' => env('REALTIME_SOCKETIO_SSL', false),
            'cert' => env('REALTIME_SOCKETIO_CERT'),
            'key' => env('REALTIME_SOCKETIO_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Broker Drivers
    |--------------------------------------------------------------------------
    |
    | Configure available broker drivers for multi-server scaling.
    |
    */
    'brokers' => [
        'redis' => [
            'driver' => 'redis',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD'),
            'database' => env('REDIS_DB', 0),
            'timeout' => env('REDIS_TIMEOUT', 5.0),
            'read_timeout' => env('REDIS_READ_TIMEOUT', 30.0), // Increased for long-running consumers
            'write_timeout' => env('REDIS_WRITE_TIMEOUT', 5.0),

            // Redis Streams settings
            'max_stream_length' => (int) env('REDIS_STREAM_MAX_LENGTH', 100000), // 100K messages
            'consumer_group' => env('REDIS_STREAM_CONSUMER_GROUP', 'realtime-group'),
            'consumer_batch_size' => (int) env('REDIS_STREAM_BATCH_SIZE', 1000), // Increased from 100 for throughput
            'consumer_block_ms' => (int) env('REDIS_STREAM_BLOCK_MS', 100), // Reduced from 1000ms for lower latency
            'idle_time_ms' => (int) env('REDIS_STREAM_IDLE_TIME_MS', 60000), // 1 min
            'consumer_prefetch_count' => (int) env('REDIS_STREAM_PREFETCH_COUNT', 5000), // Messages to prefetch

            // Circuit breaker settings
            'circuit_breaker_threshold' => env('REDIS_CB_THRESHOLD', 5),
            'circuit_breaker_timeout' => env('REDIS_CB_TIMEOUT', 60),
        ],

        'rabbitmq' => [
            'driver' => 'rabbitmq',
            'host' => env('RABBITMQ_HOST', '127.0.0.1'),
            'port' => env('RABBITMQ_PORT', 5672),
            'user' => env('RABBITMQ_USER', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'vhost' => env('RABBITMQ_VHOST', '/'),
            'exchange' => env('RABBITMQ_EXCHANGE', 'realtime'),
            'exchange_type' => env('RABBITMQ_EXCHANGE_TYPE', 'topic'),
            'exchange_durable' => env('RABBITMQ_EXCHANGE_DURABLE', true),
            'exchange_auto_delete' => env('RABBITMQ_EXCHANGE_AUTO_DELETE', false),
            'queue_prefix' => env('RABBITMQ_QUEUE_PREFIX', 'realtime'),
            'queue_durable' => env('RABBITMQ_QUEUE_DURABLE', false),
            'queue_exclusive' => env('RABBITMQ_QUEUE_EXCLUSIVE', true),
            'queue_auto_delete' => env('RABBITMQ_QUEUE_AUTO_DELETE', true),
            'prefetch_count' => env('RABBITMQ_PREFETCH_COUNT', 50),
            'persistent_messages' => env('RABBITMQ_PERSISTENT_MESSAGES', true),
            // Channel pool settings
            'max_channels' => env('RABBITMQ_MAX_CHANNELS', 10),
            // Circuit breaker settings
            'circuit_breaker_threshold' => env('RABBITMQ_CB_THRESHOLD', 5),
            'circuit_breaker_timeout' => env('RABBITMQ_CB_TIMEOUT', 60),
        ],

        'nats' => [
            'driver' => 'nats',
            'url' => env('NATS_URL', 'nats://localhost:4222'),
        ],

        'postgres' => [
            'driver' => 'postgres',
            // Uses existing database connection
        ],

        'kafka' => [
            'driver' => 'kafka',
            'client' => env('KAFKA_CLIENT', 'auto'), // php, rdkafka, auto (auto = prefer rdkafka)
            'brokers' => explode(',', env('KAFKA_BROKERS', 'localhost:9092')),
            'topic_prefix' => env('KAFKA_TOPIC_PREFIX', 'realtime'),
            'consumer_group' => env('KAFKA_CONSUMER_GROUP', 'realtime-servers'),

            // Circuit breaker settings
            'circuit_breaker_threshold' => env('KAFKA_CB_THRESHOLD', 5),
            'circuit_breaker_timeout' => env('KAFKA_CB_TIMEOUT', 60),

            // Topic Strategy: 'one-per-channel' (legacy) or 'grouped' (recommended)
            'topic_strategy' => env('KAFKA_TOPIC_STRATEGY', 'grouped'),

            // Topic Mapping (for grouped strategy)
            'topic_mapping' => (function () {
                $kafkaConfig = @include __DIR__ . '/kafka.php';
                return $kafkaConfig['topic_mapping'] ?? [];
            })(),
            'default_topic' => env('KAFKA_DEFAULT_TOPIC', 'realtime'),
            'default_partitions' => (int) env('KAFKA_DEFAULT_PARTITIONS', 10),

            // Manual Commit (recommended for production)
            'manual_commit' => env('KAFKA_MANUAL_COMMIT', true),

            // High-performance settings
            'async_queue' => env('KAFKA_ASYNC_QUEUE', true), // Non-blocking HTTP publish
            'producer_pool' => env('KAFKA_PRODUCER_POOL', false), // Multiple producer instances (CLI)
            'pool_size' => (int) env('KAFKA_POOL_SIZE', 4), // Number of producers in pool

            // Shared Memory Queue (APCu) - True async via inter-process communication
            // Requires: APCu extension with apc.enable_cli=1
            // Use with: php console kafka:flush-worker
            'shared_memory' => env('KAFKA_SHARED_MEMORY', false),
            'shared_queue_name' => env('KAFKA_SHARED_QUEUE_NAME', 'kafka_queue'),
            'shared_queue_max_size' => (int) env('KAFKA_SHARED_QUEUE_MAX_SIZE', 1000000), // 1M messages
            'shared_queue_ttl' => (int) env('KAFKA_SHARED_QUEUE_TTL', 300), // 5 min TTL

            // Async queue settings (in-process)
            'queue_max_size' => (int) env('KAFKA_QUEUE_MAX_SIZE', 131072), // 128K messages
            'batch_size' => (int) env('KAFKA_BATCH_SIZE', 1000), // Messages per batch
            'flush_interval_ms' => (int) env('KAFKA_FLUSH_INTERVAL_MS', 50), // Max time between flushes

            // Dead Letter Queue
            'dlq_enabled' => env('KAFKA_DLQ_ENABLED', false),
            'dlq_prefix' => env('KAFKA_DLQ_PREFIX', 'dlq'),
            'dlq_max_retries' => (int) env('KAFKA_DLQ_MAX_RETRIES', 3),

            // Producer configuration (rdkafka format)
            'producer_config' => [
                'security.protocol' => env('KAFKA_SECURITY_PROTOCOL', 'plaintext'),
                'compression.type' => env('KAFKA_COMPRESSION', 'lz4'),
                'batch.size' => env('KAFKA_PRODUCER_BATCH_SIZE', '262144'), // 256KB
                'linger.ms' => env('KAFKA_LINGER_MS', '10'),
                'acks' => env('KAFKA_ACKS', '1'),
                'max.in.flight.requests.per.connection' => env('KAFKA_MAX_IN_FLIGHT', '10'),
            ],

            // Consumer configuration (rdkafka format)
            'consumer_config' => [
                'auto.offset.reset' => 'earliest',
                'session.timeout.ms' => '30000',
                'max.poll.interval.ms' => '300000',
                'fetch.min.bytes' => env('KAFKA_FETCH_MIN_BYTES', '1024'),
                'fetch.wait.max.ms' => env('KAFKA_FETCH_MAX_WAIT_MS', '100'),
                'max.partition.fetch.bytes' => env('KAFKA_MAX_PARTITION_FETCH_BYTES', '1048576'),
                'security.protocol' => env('KAFKA_SECURITY_PROTOCOL', 'plaintext'),
                'metadata.max.age.ms' => env('KAFKA_METADATA_MAX_AGE_MS', '300000'),
                'topic.metadata.refresh.interval.ms' => env('KAFKA_TOPIC_METADATA_REFRESH_MS', '300000'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Channel Authorization (Legacy - Use routes/channels.php Instead)
    |--------------------------------------------------------------------------
    |
    | DEPRECATED: Define channel authorization in routes/channels.php instead.
    | This config is kept for backward compatibility only.
    |
    | New way (routes/channels.php):
    |   ChannelRoute::channel('user.{userId}', function($conn, $userId) {
    |       return $conn->getUserId() === (int) $userId;
    |   })->middleware(['auth']);
    |
    */
    'authorizers' => [
        // Legacy authorizers (will be used only if routes/channels.php doesn't define the channel)
    ],

    /*
    |--------------------------------------------------------------------------
    | Presence Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for presence channel features.
    |
    */
    'presence' => [
        'enabled' => true,
        'timeout' => 120, // seconds before user marked offline
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Protect against message flooding.
    |
    | NOTE: This is CHANNEL-level rate limiting.
    | For CONNECTION-level rate limiting, use RateLimitMiddleware in routes/channels.php.
    |
    */
    'rate_limit' => [
        'enabled' => env('REALTIME_RATE_LIMIT', true),
        'messages_per_minute' => env('REALTIME_RATE_LIMIT_MESSAGES', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Input Validation
    |--------------------------------------------------------------------------
    |
    | Enable input validation for channel names and events.
    |
    */
    'validate_input' => env('REALTIME_VALIDATE_INPUT', true),

    /*
    |--------------------------------------------------------------------------
    | Channel Middleware
    |--------------------------------------------------------------------------
    |
    | Register channel middleware aliases for use in routes/channels.php
    |
    | Built-in middleware (Framework v2.0):
    | - 'auth' => AuthMiddleware::class (requires authentication)
    | - 'role' => RoleMiddleware::class (requires specific role)
    | - 'ratelimit' => RateLimitMiddleware::class (multi-layer rate limiting)
    | - 'security' => SecurityMiddleware::class (DDoS protection, IP filtering)
    | - 'ip_whitelist' => IpWhitelistMiddleware::class (IP access control)
    |
    | You can register custom middleware here:
    | 'premium' => App\Realtime\Middleware\PremiumMiddleware::class
    |
    | Usage in routes/channels.php:
    | ChannelRoute::channel('channel-name', fn($conn) => true)
    |     ->middleware(['security', 'auth', 'ratelimit', 'premium']);
    |
    | NOTE: These middleware are ONLY for realtime channels, NOT for HTTP!
    |
    | Recommended order:
    | 1. security (DDoS, IP filtering)
    | 2. ratelimit (rate limiting)
    | 3. auth (authentication)
    | 4. role/premium/team (authorization)
    |
    */
    'channel_middleware' => [
        // Framework security middleware (v2.0 - Enterprise Grade)
        'security' => Toporia\Framework\Realtime\Middleware\SecurityMiddleware::class,
        'ratelimit' => Toporia\Framework\Realtime\Middleware\RateLimitMiddleware::class,
        'ip_whitelist' => Toporia\Framework\Realtime\Middleware\IpWhitelistMiddleware::class,

        // Common middleware (application-level)
        'auth' => App\Infrastructure\Realtime\Middleware\AuthMiddleware::class,

        // Uncomment and create these middleware classes as needed:
        // 'role' => App\Infrastructure\Realtime\Middleware\RoleMiddleware::class,
        // 'premium' => App\Infrastructure\Realtime\Middleware\PremiumMiddleware::class,
        // 'verified' => App\Infrastructure\Realtime\Middleware\VerifiedMiddleware::class,
        // 'team' => App\Infrastructure\Realtime\Middleware\TeamMemberMiddleware::class,
        // 'admin' => App\Infrastructure\Realtime\Middleware\AdminMiddleware::class,
        // 'subscription' => App\Infrastructure\Realtime\Middleware\ActiveSubscriptionMiddleware::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Enhanced Middleware Pipeline (v2.0)
    |--------------------------------------------------------------------------
    |
    | Use EnhancedChannelMiddlewarePipeline for better performance:
    | - Priority-based ordering
    | - Result caching (5-10x faster)
    | - Metrics collection
    | - Circuit breaker integration
    |
    | Set to true to enable enhanced pipeline.
    |
    */
    'use_enhanced_pipeline' => env('REALTIME_ENHANCED_PIPELINE', true),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | For detailed rate limiting configuration, see:
    | config/realtime-ratelimit.php
    |
    | Quick settings here for backward compatibility.
    |
    */
    'rate_limiting' => [
        'enabled' => env('REALTIME_RATELIMIT_ENABLED', true),
        'algorithm' => env('REALTIME_RATELIMIT_ALGORITHM', 'token_bucket'),
        'connection_limit' => (int) env('REALTIME_RATELIMIT_CONNECTION_LIMIT', 60),
        'ip_limit' => (int) env('REALTIME_RATELIMIT_IP_LIMIT', 100),
        'user_limit' => (int) env('REALTIME_RATELIMIT_USER_LIMIT', 1000),
    ],
];
