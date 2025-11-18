<?php

declare(strict_types=1);

namespace Toporia\Framework\Session\Contracts;

/**
 * Session Store Interface
 *
 * Contract for session storage implementations.
 * Supports multiple drivers: file, database, redis, cookie.
 *
 * Clean Architecture:
 * - Dependency Inversion: Framework depends on abstraction
 * - Open/Closed: Extensible via new implementations
 *
 * SOLID Principles:
 * - I: Interface Segregation - focused interface
 * - D: Dependency Inversion - depends on abstraction
 */
interface SessionStoreInterface
{
    /**
     * Start the session.
     *
     * @return bool True on success
     */
    public function start(): bool;

    /**
     * Get a session value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set a session value.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, mixed $value): void;

    /**
     * Check if a session key exists.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Remove a session value.
     *
     * @param string $key
     * @return void
     */
    public function remove(string $key): void;

    /**
     * Get all session data.
     *
     * @return array
     */
    public function all(): array;

    /**
     * Clear all session data.
     *
     * @return void
     */
    public function flush(): void;

    /**
     * Regenerate session ID.
     *
     * @param bool $deleteOldSession Delete old session data
     * @return bool True on success
     */
    public function regenerate(bool $deleteOldSession = false): bool;

    /**
     * Get session ID.
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Set session ID.
     *
     * @param string $id
     * @return void
     */
    public function setId(string $id): void;

    /**
     * Get session name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Save session data.
     *
     * @return bool True on success
     */
    public function save(): bool;
}
