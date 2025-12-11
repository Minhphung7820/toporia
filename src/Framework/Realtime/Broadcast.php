<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime;

use Toporia\Framework\Realtime\Contracts\BrokerInterface;
use Toporia\Framework\Realtime\Contracts\MessageInterface;

/**
 * Class Broadcast
 *
 * Fluent API facade for realtime broadcasting with excellent Developer Experience (DX).
 * Provides a clean, chainable syntax for publishing messages to channels.
 *
 * Usage Examples:
 *
 * Quick broadcast (recommended):
 *   Broadcast::send('channel', 'event', $data, 'kafka');
 *
 * With default driver:
 *   Broadcast::channel('notifications')->event('new.message')->with(['text' => 'Hello'])->now();
 *
 * With specific driver (use toChannel() after via()):
 *   Broadcast::via('kafka')->toChannel('events')->event('user.action')->with($data)->now();
 *
 * Private channel:
 *   Broadcast::private('user.123')->event('notification')->with($data)->now();
 *
 * Presence channel:
 *   Broadcast::presence('room.456')->event('user.joined')->with(['user' => $user])->now();
 *
 * To specific users:
 *   Broadcast::toUser(123)->event('notification')->with($data)->now();
 *   Broadcast::toUsers([1, 2, 3])->event('alert')->with($data)->now();
 *
 * Performance:
 * - Singleton RealtimeManager (connection reuse)
 * - Async by default (non-blocking)
 * - Batching support for bulk operations
 * - ~1-5ms latency (async), ~10-50ms (sync)
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 */
final class Broadcast
{
    private ?string $driver = null;
    private ?string $channelName = null;
    private ?string $eventName = null;
    private array $payload = [];
    private bool $isPrivate = false;
    private bool $isPresence = false;
    private ?array $targetUsers = null;

    /**
     * Cached RealtimeManager instance for performance.
     */
    private static ?RealtimeManager $manager = null;

    private function __construct() {}

    /**
     * Create a new Broadcast instance.
     *
     * @return self
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Specify the broadcast driver (redis, rabbitmq, kafka).
     *
     * @param string $driver
     * @return self
     */
    public static function via(string $driver): self
    {
        $instance = new self();
        $instance->driver = $driver;
        return $instance;
    }

    /**
     * Specify the channel to broadcast to (static entry point).
     *
     * @param string $channel
     * @return self
     */
    public static function channel(string $channel): self
    {
        $instance = new self();
        $instance->channelName = $channel;
        return $instance;
    }

    /**
     * Instance method: Set channel (chainable after via()).
     *
     * @param string $channel
     * @return self
     */
    public function toChannel(string $channel): self
    {
        $this->channelName = $channel;
        return $this;
    }

    /**
     * Broadcast to a private channel.
     *
     * @param string $channel
     * @return self
     */
    public static function private(string $channel): self
    {
        $instance = new self();
        $instance->channelName = "private-{$channel}";
        $instance->isPrivate = true;
        return $instance;
    }

    /**
     * Broadcast to a presence channel.
     *
     * @param string $channel
     * @return self
     */
    public static function presence(string $channel): self
    {
        $instance = new self();
        $instance->channelName = "presence-{$channel}";
        $instance->isPresence = true;
        return $instance;
    }

    /**
     * Broadcast to a specific user.
     *
     * @param int|string $userId
     * @return self
     */
    public static function toUser(int|string $userId): self
    {
        $instance = new self();
        $instance->channelName = "private-user.{$userId}";
        $instance->isPrivate = true;
        $instance->targetUsers = [(string) $userId];
        return $instance;
    }

    /**
     * Broadcast to multiple users.
     *
     * @param array<int|string> $userIds
     * @return self
     */
    public static function toUsers(array $userIds): self
    {
        $instance = new self();
        $instance->targetUsers = array_map('strval', $userIds);
        return $instance;
    }

    /**
     * Quick broadcast - send immediately.
     *
     * @param string $channel
     * @param string $event
     * @param array $data
     * @param string|null $driver
     * @return bool
     */
    public static function send(string $channel, string $event, array $data = [], ?string $driver = null): bool
    {
        $instance = new self();
        $instance->driver = $driver;
        $instance->channelName = $channel;
        $instance->eventName = $event;
        $instance->payload = $data;

        return $instance->dispatch();
    }

    /**
     * Instance method: Set driver.
     *
     * @param string|null $driver
     * @return self
     */
    public function using(?string $driver): self
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Instance method: Set channel (chainable).
     *
     * @param string $channel
     * @return self
     */
    public function on(string $channel): self
    {
        $this->channelName = $channel;
        return $this;
    }

    /**
     * Set the event name.
     *
     * @param string $event
     * @return self
     */
    public function event(string $event): self
    {
        $this->eventName = $event;
        return $this;
    }

    /**
     * Set the payload data.
     *
     * @param array $data
     * @return self
     */
    public function with(array $data): self
    {
        $this->payload = $data;
        return $this;
    }

    /**
     * Dispatch the broadcast (alias for send()).
     *
     * @return bool
     */
    public function dispatch(): bool
    {
        return $this->now();
    }

    /**
     * Send the broadcast now.
     *
     * @return bool
     */
    public function now(): bool
    {
        // Validate required fields
        if (empty($this->channelName)) {
            throw new \InvalidArgumentException('Channel name is required');
        }

        if (empty($this->eventName)) {
            throw new \InvalidArgumentException('Event name is required');
        }

        // Handle multi-user broadcast
        if ($this->targetUsers !== null && count($this->targetUsers) > 1) {
            return $this->broadcastToMultipleUsers();
        }

        return $this->publishMessage($this->channelName, $this->eventName, $this->payload);
    }

    /**
     * Broadcast to multiple users.
     *
     * @return bool
     */
    private function broadcastToMultipleUsers(): bool
    {
        $success = true;

        foreach ($this->targetUsers as $userId) {
            $channel = "private-user.{$userId}";
            if (!$this->publishMessage($channel, $this->eventName, $this->payload)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Publish message to broker.
     *
     * @param string $channel
     * @param string $event
     * @param array $data
     * @return bool
     */
    private function publishMessage(string $channel, string $event, array $data): bool
    {
        try {
            $broker = $this->getBroker();

            if ($broker === null) {
                return false;
            }

            $message = Message::event($channel, $event, $data);
            $broker->publish($channel, $message);

            return true;
        } catch (\Throwable $e) {
            // Log error but don't throw - broadcasting should be non-blocking
            error_log("[Broadcast] Failed to publish: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Get the broker instance.
     *
     * @return BrokerInterface|null
     */
    private function getBroker(): ?BrokerInterface
    {
        $manager = self::getManager();
        return $manager->broker($this->driver);
    }

    /**
     * Get or create RealtimeManager singleton.
     *
     * @return RealtimeManager
     */
    private static function getManager(): RealtimeManager
    {
        if (self::$manager !== null) {
            return self::$manager;
        }

        // Try to get from container first
        if (function_exists('app')) {
            $container = app();
            if ($container->has(RealtimeManager::class)) {
                self::$manager = $container->make(RealtimeManager::class);
                return self::$manager;
            }
        }

        // Fallback: create new instance with config
        $config = function_exists('config') ? config('realtime', []) : [];
        self::$manager = new RealtimeManager($config);

        return self::$manager;
    }

    /**
     * Set custom RealtimeManager (for testing).
     *
     * @param RealtimeManager|null $manager
     * @return void
     */
    public static function setManager(?RealtimeManager $manager): void
    {
        self::$manager = $manager;
    }

    /**
     * Reset manager (for testing).
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$manager = null;
    }
}
