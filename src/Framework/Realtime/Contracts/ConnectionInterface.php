<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Contracts;


/**
 * Interface ConnectionInterface
 *
 * Contract defining the interface for ConnectionInterface implementations
 * in the Real-time broadcasting layer of the Toporia Framework.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Realtime\Contracts
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
interface ConnectionInterface
{
    /**
     * Get connection unique identifier.
     *
     * Format: connection_xxxxx (uniqid)
     *
     * @return string Connection ID
     */
    public function getId(): string;

    /**
     * Get connection metadata.
     *
     * Contains: user_id, ip_address, user_agent, connected_at, etc.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array;

    /**
     * Set connection metadata.
     *
     * @param array<string, mixed> $metadata
     * @return void
     */
    public function setMetadata(array $metadata): void;

    /**
     * Get specific metadata value.
     *
     * @param string $key Metadata key
     * @param mixed $default Default value
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set specific metadata value.
     *
     * @param string $key Metadata key
     * @param mixed $value Metadata value
     * @return void
     */
    public function set(string $key, mixed $value): void;

    /**
     * Get subscribed channels.
     *
     * @return array<string> Channel names
     */
    public function getChannels(): array;

    /**
     * Subscribe to a channel.
     *
     * @param string $channel Channel name
     * @return void
     */
    public function subscribe(string $channel): void;

    /**
     * Unsubscribe from a channel.
     *
     * @param string $channel Channel name
     * @return void
     */
    public function unsubscribe(string $channel): void;

    /**
     * Check if subscribed to a channel.
     *
     * @param string $channel Channel name
     * @return bool
     */
    public function isSubscribed(string $channel): bool;

    /**
     * Check if connection is authenticated.
     *
     * @return bool
     */
    public function isAuthenticated(): bool;

    /**
     * Get authenticated user ID.
     *
     * @return string|int|null
     */
    public function getUserId(): string|int|null;

    /**
     * Get connection timestamp.
     *
     * @return int Unix timestamp
     */
    public function getConnectedAt(): int;

    /**
     * Get last activity timestamp.
     *
     * @return int Unix timestamp
     */
    public function getLastActivityAt(): int;

    /**
     * Update last activity timestamp.
     *
     * @return void
     */
    public function updateLastActivity(): void;
}
