<?php

declare(strict_types=1);

/**
 * Realtime Rate Limiting Configuration
 *
 * Enterprise-grade rate limiting for realtime connections.
 *
 * Features:
 * - Multi-algorithm support (Token Bucket, Sliding Window)
 * - Multi-layer protection (Global, IP, Connection, User, Channel, API Key)
 * - Distributed limiting with Redis
 * - Adaptive limiting based on system load
 * - DDoS protection
 * - IP whitelist/blacklist
 *
 * Performance:
 * - Token Bucket: <1ms per check (Redis) or <0.1ms (memory)
 * - Sliding Window: <2ms per check (Redis) or <0.2ms (memory)
 * - Multi-layer: <5ms total for all layers
 *
 * Scalability:
 * - Supports millions of concurrent connections
 * - Redis Cluster compatible
 * - Horizontal scaling ready
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Master switch for rate limiting.
    | Set to false to disable all rate limiting (not recommended for production).
    |
    */
    'enabled' => env('REALTIME_RATELIMIT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Algorithm
    |--------------------------------------------------------------------------
    |
    | Default rate limiting algorithm.
    |
    | Options:
    | - token_bucket: Allows bursts, smooth average rate (recommended)
    | - sliding_window: Most accurate, higher memory usage
    | - leaky_bucket: Smooth traffic flow, no bursts (coming soon)
    | - fixed_window: Simple and fast, edge case issues (coming soon)
    |
    */
    'default_algorithm' => env('REALTIME_RATELIMIT_ALGORITHM', 'token_bucket'),

    /*
    |--------------------------------------------------------------------------
    | Redis Configuration
    |--------------------------------------------------------------------------
    |
    | Redis is required for distributed rate limiting across multiple servers.
    | If not configured, falls back to in-memory (single server only).
    |
    */
    'redis' => [
        'enabled' => env('REALTIME_RATELIMIT_REDIS_ENABLED', true),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD'),
        'database' => env('REDIS_RATELIMIT_DB', 1), // Separate DB for rate limiting
        'timeout' => env('REDIS_TIMEOUT', 2.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Layer Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Defense-in-depth strategy with multiple layers of protection.
    | Each layer is independent and checked in priority order.
    |
    | Layers (priority order):
    | 1. Global: Entire system (prevents total overload)
    | 2. IP Address: Per IP (DDoS protection)
    | 3. Connection: Per WebSocket connection (connection abuse)
    | 4. User: Per authenticated user (account abuse)
    | 5. API Key: Per API key (API abuse)
    | 6. Channel: Per channel (channel flooding)
    |
    */
    'layers' => [
        // Global layer: Protects entire system
        'global' => [
            'enabled' => env('REALTIME_RATELIMIT_GLOBAL_ENABLED', true),
            'limit' => (int) env('REALTIME_RATELIMIT_GLOBAL_LIMIT', 100000), // 100k requests/min globally
            'window' => (int) env('REALTIME_RATELIMIT_GLOBAL_WINDOW', 60), // 1 minute
            'algorithm' => 'token_bucket',
        ],

        // IP layer: Per IP address
        'ip' => [
            'enabled' => env('REALTIME_RATELIMIT_IP_ENABLED', true),
            'limit' => (int) env('REALTIME_RATELIMIT_IP_LIMIT', 100), // 100 requests/min per IP
            'window' => (int) env('REALTIME_RATELIMIT_IP_WINDOW', 60),
            'algorithm' => 'sliding_window', // High accuracy for DDoS detection
        ],

        // Connection layer: Per WebSocket connection
        'connection' => [
            'enabled' => env('REALTIME_RATELIMIT_CONNECTION_ENABLED', true),
            'limit' => (int) env('REALTIME_RATELIMIT_CONNECTION_LIMIT', 60), // 60 messages/min per connection
            'window' => (int) env('REALTIME_RATELIMIT_CONNECTION_WINDOW', 60),
            'algorithm' => 'token_bucket',
        ],

        // User layer: Per authenticated user
        'user' => [
            'enabled' => env('REALTIME_RATELIMIT_USER_ENABLED', true),
            'limit' => (int) env('REALTIME_RATELIMIT_USER_LIMIT', 1000), // 1000 messages/hour per user
            'window' => (int) env('REALTIME_RATELIMIT_USER_WINDOW', 3600), // 1 hour
            'algorithm' => 'token_bucket',
        ],

        // API Key layer: Per API key
        'api_key' => [
            'enabled' => env('REALTIME_RATELIMIT_APIKEY_ENABLED', false), // Disabled by default
            'limit' => (int) env('REALTIME_RATELIMIT_APIKEY_LIMIT', 10000), // 10k requests/day per API key
            'window' => (int) env('REALTIME_RATELIMIT_APIKEY_WINDOW', 86400), // 1 day
            'algorithm' => 'token_bucket',
        ],

        // Channel layer: Per channel
        'channel' => [
            'enabled' => env('REALTIME_RATELIMIT_CHANNEL_ENABLED', true),
            'limit' => (int) env('REALTIME_RATELIMIT_CHANNEL_LIMIT', 10000), // 10k messages/min per channel
            'window' => (int) env('REALTIME_RATELIMIT_CHANNEL_WINDOW', 60),
            'algorithm' => 'token_bucket',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Adaptive Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Automatically adjust rate limits based on system load and health.
    |
    | When system is under stress:
    | - CPU load high: Reduce limits
    | - Memory pressure: Reduce limits
    | - Circuit breaker open: Drastically reduce limits
    |
    | When system has capacity:
    | - Restore to normal limits gradually
    |
    */
    'adaptive' => [
        'enabled' => env('REALTIME_RATELIMIT_ADAPTIVE_ENABLED', true),
        'adjustment_rate' => (float) env('REALTIME_RATELIMIT_ADAPTIVE_RATE', 0.5), // 50% max reduction
        'load_update_interval' => (int) env('REALTIME_RATELIMIT_ADAPTIVE_INTERVAL', 5), // Check every 5s
    ],

    /*
    |--------------------------------------------------------------------------
    | DDoS Protection
    |--------------------------------------------------------------------------
    |
    | Detects and blocks DDoS attacks automatically.
    |
    | Features:
    | - Connection rate limiting per IP
    | - Automatic IP blocking
    | - Progressive penalties
    | - Whitelist/blacklist support
    |
    */
    'ddos_protection' => [
        'enabled' => env('REALTIME_DDOS_PROTECTION_ENABLED', true),
        'connection_threshold' => (int) env('REALTIME_DDOS_CONNECTION_THRESHOLD', 10), // Max 10 connections/min per IP
        'connection_window' => (int) env('REALTIME_DDOS_CONNECTION_WINDOW', 60), // 1 minute
        'block_duration' => (int) env('REALTIME_DDOS_BLOCK_DURATION', 3600), // Block for 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | IP Whitelist/Blacklist
    |--------------------------------------------------------------------------
    |
    | IP-based access control.
    |
    | Whitelist mode: Only listed IPs allowed (strict)
    | Blacklist mode: All IPs allowed except listed (default)
    |
    | Supports:
    | - Individual IPs: 192.168.1.100
    | - CIDR notation: 192.168.0.0/24
    | - Wildcard: 192.168.*.*
    |
    */
    'ip_control' => [
        'whitelist_mode' => env('REALTIME_IP_WHITELIST_MODE', false),
        'whitelist' => explode(',', env('REALTIME_IP_WHITELIST', '')),
        'blacklist' => explode(',', env('REALTIME_IP_BLACKLIST', '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Metrics
    |--------------------------------------------------------------------------
    |
    | Performance monitoring and metrics collection.
    |
    | Metrics tracked:
    | - Rate limit hits/misses
    | - Middleware execution time
    | - Layer statistics
    | - DDoS events
    |
    | Export formats:
    | - Prometheus
    | - StatsD
    | - CloudWatch
    | - Custom
    |
    */
    'metrics' => [
        'enabled' => env('REALTIME_METRICS_ENABLED', true),
        'export_format' => env('REALTIME_METRICS_FORMAT', 'prometheus'), // prometheus, statsd, cloudwatch
        'export_endpoint' => env('REALTIME_METRICS_ENDPOINT', '/metrics'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fail-Safe Behavior
    |--------------------------------------------------------------------------
    |
    | How to handle rate limiter failures (e.g., Redis down).
    |
    | Options:
    | - fail_open: Allow all requests (default, prioritize availability)
    | - fail_closed: Deny all requests (prioritize security)
    |
    */
    'fail_safe' => env('REALTIME_RATELIMIT_FAILSAFE', 'fail_open'),

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Result caching for performance optimization.
    |
    | Caches middleware results to avoid repeated checks.
    | Dramatically improves performance for repeated subscriptions.
    |
    */
    'cache' => [
        'enabled' => env('REALTIME_RATELIMIT_CACHE_ENABLED', true),
        'ttl' => (int) env('REALTIME_RATELIMIT_CACHE_TTL', 60), // Cache for 60 seconds
    ],
];

