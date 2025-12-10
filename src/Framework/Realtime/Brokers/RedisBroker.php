<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Brokers;

use Toporia\Framework\Realtime\Contracts\{BrokerInterface, HealthCheckableInterface, HealthCheckResult, MessageInterface};
use Toporia\Framework\Realtime\Exceptions\{BrokerException, BrokerTemporaryException};
use Toporia\Framework\Realtime\RealtimeManager;

/**
 * Class RedisBroker
 *
 * Redis Pub/Sub broker for multi-server realtime communication. Enables horizontal scaling by broadcasting messages across servers.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Realtime\Brokers
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
final class RedisBroker implements BrokerInterface, HealthCheckableInterface
{
    private \Redis $redis;
    private \Redis $subscriber;
    private array $subscriptions = [];
    private array $patternSubscriptions = [];
    private bool $connected = false;
    private bool $consuming = false;

    public function __construct(
        array $config = [],
        private readonly ?RealtimeManager $manager = null
    ) {
        // Runtime check: Ensure Redis extension is loaded
        if (!extension_loaded('redis')) {
            throw BrokerException::invalidConfiguration(
                'redis',
                "Redis extension is not installed. Install it with:\n" .
                "  Ubuntu/Debian: sudo apt-get install php-redis\n" .
                "  macOS: pecl install redis"
            );
        }

        $this->redis = new \Redis();
        $this->subscriber = new \Redis();

        // Connect to Redis
        // Note: read_timeout is always 0 (infinite) for subscriber to prevent
        // "read error on connection" during blocking SUBSCRIBE
        $this->connect(
            $config['host'] ?? '127.0.0.1',
            (int) ($config['port'] ?? 6379),
            (float) ($config['timeout'] ?? 2.0),
            0.0 // Always 0 (infinite) for subscriber - DO NOT use config['read_timeout']
        );

        // Authenticate if password provided
        if (!empty($config['password'])) {
            try {
                $this->redis->auth($config['password']);
                $this->subscriber->auth($config['password']);
            } catch (\RedisException $e) {
                throw BrokerException::connectionFailed('redis', "Authentication failed: {$e->getMessage()}", $e);
            }
        }

        // Select database
        if (isset($config['database'])) {
            $this->redis->select((int) $config['database']);
            $this->subscriber->select((int) $config['database']);
        }

        $this->connected = true;
    }

    /**
     * Connect to Redis with retry logic.
     *
     * @param string $host
     * @param int $port
     * @param float $timeout Connection timeout
     * @param float $readTimeout Read timeout (0 = infinite, needed for subscriber blocking)
     * @return void
     */
    private function connect(string $host, int $port, float $timeout, float $readTimeout = 0.0): void
    {
        try {
            // Publisher connection - normal timeout
            $this->redis->connect($host, $port, $timeout);

            // Subscriber connection
            $this->subscriber->connect($host, $port, $timeout);

            // Set read timeout for subscriber AFTER connect
            // Use very large value (24 hours = 86400 seconds) instead of -1 or 0
            // Some phpredis versions don't support -1 or 0 for infinite
            $this->subscriber->setOption(\Redis::OPT_READ_TIMEOUT, 86400.0);
        } catch (\RedisException $e) {
            throw BrokerException::connectionFailed('redis', "{$host}:{$port} - {$e->getMessage()}", $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function publish(string $channel, MessageInterface $message): void
    {
        if (!$this->connected) {
            throw BrokerException::notConnected('redis');
        }

        // Publish to Redis channel
        // Format: realtime:{channel}
        $redisChannel = "realtime:{$channel}";
        $payload = $message->toJson();

        try {
            $this->redis->publish($redisChannel, $payload);
        } catch (\RedisException $e) {
            throw BrokerException::publishFailed('redis', $channel, $e->getMessage(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function subscribe(string $channel, callable $callback): void
    {
        if (!$this->connected) {
            throw BrokerException::notConnected('redis');
        }

        $redisChannel = "realtime:{$channel}";

        // Store callback with channel mapping
        if (!isset($this->subscriptions[$redisChannel])) {
            $this->subscriptions[$redisChannel] = [];
        }
        $this->subscriptions[$redisChannel][$channel] = $callback;

        // Note: Actual subscription happens in consume() method
        // This method just registers the subscription
    }

    /**
     * Subscribe to channels using pattern matching (PSUBSCRIBE).
     *
     * Supports Redis pattern syntax:
     * - '*' matches any characters
     * - '?' matches single character
     * - '[abc]' matches a, b, or c
     *
     * Examples:
     * - 'realtime:*' - all realtime channels
     * - 'realtime:user.*' - all user channels
     * - 'realtime:presence-*' - all presence channels
     *
     * @param string $pattern Pattern to match (without 'realtime:' prefix)
     * @param callable $callback Callback receives (MessageInterface $message, string $channel)
     * @return void
     */
    public function psubscribe(string $pattern, callable $callback): void
    {
        if (!$this->connected) {
            throw BrokerException::notConnected('redis');
        }

        // Add 'realtime:' prefix to pattern
        $redisPattern = "realtime:{$pattern}";

        // Store pattern subscription
        $this->patternSubscriptions[$redisPattern] = $callback;
    }

    /**
     * Start consuming messages from subscribed channels.
     *
     * This method is called by the Redis consumer command.
     * It runs in a loop, consuming messages and invoking callbacks.
     *
     * Performance Optimizations:
     * - Batch message processing for high throughput
     * - Graceful shutdown support via signal handlers
     * - Error handling and retry logic
     * - Memory-efficient message processing
     *
     * Architecture:
     * - Redis subscribe() is blocking by design (this is expected)
     * - Signal handlers allow graceful shutdown (SIGTERM, SIGINT)
     * - Processes messages immediately as they arrive
     * - Supports multiple channels with pattern matching
     *
     * Note: Redis Pub/Sub is designed to be blocking. This is not a bug,
     * it's the intended behavior for real-time message delivery.
     *
     * @param int $timeoutMs Poll timeout in milliseconds (not used for Redis, kept for interface compatibility)
     * @param int $batchSize Maximum messages per batch (not used for Redis, kept for interface compatibility)
     * @return void
     */
    public function consume(int $timeoutMs = 1000, int $batchSize = 100): void
    {
        // Check if we have pattern subscriptions (PSUBSCRIBE)
        if (!empty($this->patternSubscriptions)) {
            $this->consumePatterns();
            return;
        }

        // Check if we have regular subscriptions
        if (empty($this->subscriptions)) {
            return; // No subscriptions
        }

        $this->consuming = true;

        // Get all Redis channels to subscribe
        $redisChannels = array_keys($this->subscriptions);

        if (empty($redisChannels)) {
            return;
        }

        // Subscribe to all channels (blocking operation)
        // This will block until a message arrives or unsubscribe is called
        // Signal handlers (SIGTERM, SIGINT) will call stopConsuming() to exit gracefully
        //
        // Performance: Event-driven push model (no polling overhead)
        // Architecture: This is the correct pattern for Redis Pub/Sub
        $this->subscriber->subscribe($redisChannels, function ($redis, $redisChannel, $payload) {
            // Check if we should stop (called by signal handler)
            if (!$this->consuming) {
                return false; // Stop consuming (exit subscribe loop)
            }

            $subscriptions = $this->subscriptions[$redisChannel] ?? null;

            if (!$subscriptions) {
                return true; // Continue but skip
            }

            try {
                // Decode message
                $message = \Toporia\Framework\Realtime\Message::fromJson($payload);

                // Extract channel name from Redis channel (remove "realtime:" prefix)
                $channel = str_replace('realtime:', '', $redisChannel);

                // Handle new format: array of callbacks per channel
                if (is_array($subscriptions)) {
                    $callback = $subscriptions[$channel] ?? null;
                    if ($callback) {
                        $callback($message);
                    } else {
                        // Fallback: try all callbacks
                        foreach ($subscriptions as $cb) {
                            if (is_callable($cb)) {
                                $cb($message);
                            }
                        }
                    }
                } elseif (is_callable($subscriptions)) {
                    // Old format: single callback (backward compatibility)
                    $subscriptions($message);
                }
            } catch (\Throwable $e) {
                error_log("Redis subscriber error on {$redisChannel}: {$e->getMessage()}");
                // Continue consuming even on error
            }

            return true; // Continue consuming
        });
    }

    /**
     * Consume messages using pattern subscriptions (PSUBSCRIBE).
     *
     * This allows subscribing to multiple channels using wildcards:
     * - 'realtime:*' - all realtime channels
     * - 'realtime:user.*' - all user channels
     *
     * @return void
     */
    private function consumePatterns(): void
    {
        $this->consuming = true;

        $patterns = array_keys($this->patternSubscriptions);

        if (empty($patterns)) {
            return;
        }

        // Use PSUBSCRIBE for pattern matching
        $this->subscriber->psubscribe($patterns, function ($redis, $pattern, $redisChannel, $payload) {
            // Check if we should stop
            if (!$this->consuming) {
                return false;
            }

            $callback = $this->patternSubscriptions[$pattern] ?? null;

            if (!$callback) {
                return true; // Continue but skip
            }

            try {
                // Decode message
                $message = \Toporia\Framework\Realtime\Message::fromJson($payload);

                // Extract channel name (remove "realtime:" prefix)
                $channel = str_replace('realtime:', '', $redisChannel);

                // Call callback with message and channel
                $callback($message, $channel);
            } catch (\Throwable $e) {
                error_log("Redis psubscriber error on {$redisChannel} (pattern: {$pattern}): {$e->getMessage()}");
            }

            return true; // Continue consuming
        });
    }

    /**
     * Stop consuming messages.
     *
     * Unsubscribes from all channels to exit the blocking subscribe() call.
     *
     * Performance:
     * - O(N) where N = number of subscribed channels
     * - Fast operation (Redis command)
     *
     * @return void
     */
    public function stopConsuming(): void
    {
        $this->consuming = false;

        // Unsubscribe from pattern subscriptions
        if (!empty($this->patternSubscriptions)) {
            $patterns = array_keys($this->patternSubscriptions);
            try {
                $this->subscriber->punsubscribe($patterns);
            } catch (\Throwable $e) {
                error_log("Error punsubscribing from Redis: {$e->getMessage()}");
            }
        }

        // Unsubscribe from all channels to exit blocking subscribe()
        // This will cause subscribe() callback to return false and exit
        if (!empty($this->subscriptions)) {
            $redisChannels = array_keys($this->subscriptions);
            try {
                $this->subscriber->unsubscribe($redisChannels);
            } catch (\Throwable $e) {
                // Ignore errors during shutdown
                error_log("Error unsubscribing from Redis: {$e->getMessage()}");
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function unsubscribe(string $channel): void
    {
        $redisChannel = "realtime:{$channel}";

        // Handle both old format (single callback) and new format (array of callbacks)
        if (isset($this->subscriptions[$redisChannel])) {
            if (is_array($this->subscriptions[$redisChannel])) {
                unset($this->subscriptions[$redisChannel][$channel]);
                // Remove Redis channel entry if no more channels
                if (empty($this->subscriptions[$redisChannel])) {
                    unset($this->subscriptions[$redisChannel]);
                    $this->subscriber->unsubscribe([$redisChannel]);
                }
            } else {
                // Old format: single callback
                unset($this->subscriptions[$redisChannel]);
                $this->subscriber->unsubscribe([$redisChannel]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscriberCount(string $channel): int
    {
        $redisChannel = "realtime:{$channel}";

        // Use PUBSUB NUMSUB command
        $result = $this->redis->pubsub('NUMSUB', $redisChannel);

        return (int) ($result[$redisChannel] ?? 0);
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect(): void
    {
        if (!$this->connected) {
            return;
        }

        try {
            $this->redis->close();
            $this->subscriber->close();
        } catch (\Throwable $e) {
            error_log("Error disconnecting from Redis: {$e->getMessage()}");
        }

        $this->connected = false;
        $this->subscriptions = [];
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'redis';
    }

    /**
     * {@inheritdoc}
     */
    public function healthCheck(): HealthCheckResult
    {
        if (!$this->connected) {
            return HealthCheckResult::unhealthy('Redis broker not connected');
        }

        $start = microtime(true);

        try {
            // Ping Redis to check connection
            $pong = $this->redis->ping();
            $latencyMs = (microtime(true) - $start) * 1000;

            if ($pong === true || $pong === '+PONG' || $pong === 'PONG') {
                // Get additional info
                $info = $this->redis->info('server');
                $version = $info['redis_version'] ?? 'unknown';

                return HealthCheckResult::healthy(
                    message: 'Redis connection healthy',
                    details: [
                        'version' => $version,
                        'connected_clients' => $info['connected_clients'] ?? 0,
                    ],
                    latencyMs: $latencyMs
                );
            }

            return HealthCheckResult::degraded(
                message: 'Redis ping returned unexpected response',
                details: ['response' => $pong],
                latencyMs: $latencyMs
            );
        } catch (\Throwable $e) {
            return HealthCheckResult::unhealthy(
                message: "Redis health check failed: {$e->getMessage()}",
                details: ['exception' => $e::class]
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getHealthCheckName(): string
    {
        return 'redis-broker';
    }

    /**
     * Destructor - ensure clean disconnect.
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
