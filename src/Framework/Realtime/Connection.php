<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime;

use Toporia\Framework\Realtime\Contracts\ConnectionInterface;

/**
 * Class Connection
 *
 * Represents a client connection with metadata and channel subscriptions.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Realtime
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
final class Connection implements ConnectionInterface
{
    private string $id;
    private array $metadata = [];
    private array $channels = [];
    private int $connectedAt;
    private int $lastActivityAt;

    /**
     * @param mixed $resource Underlying connection resource (socket, stream, etc.)
     * @param array $metadata Initial metadata
     */
    public function __construct(
        private readonly mixed $resource,
        array $metadata = []
    ) {
        $this->id = uniqid('conn_', true);
        $this->metadata = $metadata;
        $this->connectedAt = now()->getTimestamp();
        $this->lastActivityAt = now()->getTimestamp();
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getChannels(): array
    {
        return array_keys($this->channels);
    }

    /**
     * {@inheritdoc}
     */
    public function subscribe(string $channel): void
    {
        $this->channels[$channel] = true;
    }

    /**
     * {@inheritdoc}
     */
    public function unsubscribe(string $channel): void
    {
        unset($this->channels[$channel]);
    }

    /**
     * {@inheritdoc}
     */
    public function isSubscribed(string $channel): bool
    {
        return isset($this->channels[$channel]);
    }

    /**
     * {@inheritdoc}
     */
    public function isAuthenticated(): bool
    {
        return isset($this->metadata['user_id']);
    }

    /**
     * {@inheritdoc}
     */
    public function getUserId(): string|int|null
    {
        return $this->metadata['user_id'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function getConnectedAt(): int
    {
        return $this->connectedAt;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastActivityAt(): int
    {
        return $this->lastActivityAt;
    }

    /**
     * {@inheritdoc}
     */
    public function updateLastActivity(): void
    {
        $this->lastActivityAt = now()->getTimestamp();
    }

    /**
     * Get underlying connection resource.
     *
     * @return mixed Socket, stream, or other resource
     */
    public function getResource(): mixed
    {
        return $this->resource;
    }

    /**
     * Set connection ID (for testing).
     *
     * @param string $id
     * @return void
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * Check if connection has a specific role.
     *
     * @param string $role Role name
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        $roles = $this->get('roles', []);
        return is_array($roles) && in_array($role, $roles, true);
    }

    /**
     * Check if connection has any of the specified roles.
     *
     * @param array<string> $roles Role names
     * @return bool
     */
    public function hasAnyRole(array $roles): bool
    {
        $userRoles = $this->get('roles', []);

        if (!is_array($userRoles)) {
            return false;
        }

        return !empty(array_intersect($roles, $userRoles));
    }

    /**
     * Check if connection has all of the specified roles.
     *
     * @param array<string> $roles Role names
     * @return bool
     */
    public function hasAllRoles(array $roles): bool
    {
        $userRoles = $this->get('roles', []);

        if (!is_array($userRoles)) {
            return false;
        }

        return count(array_intersect($roles, $userRoles)) === count($roles);
    }

    /**
     * Get username.
     *
     * @return string|null
     */
    public function getUsername(): ?string
    {
        return $this->get('username');
    }

    /**
     * Get roles.
     *
     * @return array<string>
     */
    public function getRoles(): array
    {
        $roles = $this->get('roles', []);
        return is_array($roles) ? $roles : [];
    }
}
