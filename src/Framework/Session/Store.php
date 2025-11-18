<?php

declare(strict_types=1);

namespace Toporia\Framework\Session;

use Toporia\Framework\Session\Contracts\SessionStoreInterface;

/**
 * Session Store
 *
 * Wrapper around PHP native session with driver support.
 * Provides unified interface for session management.
 *
 * Performance:
 * - O(1) get/set operations (array access)
 * - Lazy session start (only when needed)
 * - Efficient serialization
 *
 * Clean Architecture:
 * - Single Responsibility: Only handles session storage
 * - Dependency Inversion: Uses SessionStoreInterface
 * - Open/Closed: Extensible via drivers
 *
 * SOLID Principles:
 * - S: Only handles session operations
 * - O: Extensible via drivers
 * - L: Implements interface correctly
 * - I: Focused interface
 * - D: Depends on driver abstraction
 */
final class Store implements SessionStoreInterface
{
    private bool $started = false;
    private string $id;
    private string $name;

    public function __construct(
        private SessionStoreInterface $driver,
        string $name = 'PHPSESSID'
    ) {
        $this->name = $name;
        $this->id = $this->driver->getId();
    }

    /**
     * Start the session.
     *
     * Performance: O(1) - Direct driver call
     *
     * @return bool True on success
     */
    public function start(): bool
    {
        if ($this->started) {
            return true;
        }

        $this->started = $this->driver->start();
        if ($this->started) {
            $this->id = $this->driver->getId();
        }

        return $this->started;
    }

    /**
     * Get a session value.
     *
     * Performance: O(1) - Array access
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        return $this->driver->get($key, $default);
    }

    /**
     * Set a session value.
     *
     * Performance: O(1) - Array assignment
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $this->driver->set($key, $value);
    }

    /**
     * Check if a session key exists.
     *
     * Performance: O(1) - Array key check
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        $this->ensureStarted();
        return $this->driver->has($key);
    }

    /**
     * Remove a session value.
     *
     * Performance: O(1) - Array unset
     *
     * @param string $key
     * @return void
     */
    public function remove(string $key): void
    {
        $this->ensureStarted();
        $this->driver->remove($key);
    }

    /**
     * Get all session data.
     *
     * Performance: O(N) where N = session keys
     *
     * @return array
     */
    public function all(): array
    {
        $this->ensureStarted();
        return $this->driver->all();
    }

    /**
     * Clear all session data.
     *
     * Performance: O(1) - Direct clear
     *
     * @return void
     */
    public function flush(): void
    {
        $this->ensureStarted();
        $this->driver->flush();
    }

    /**
     * Regenerate session ID.
     *
     * Security: Prevents session fixation attacks.
     * Performance: O(1) - Direct driver call
     *
     * @param bool $deleteOldSession Delete old session data
     * @return bool True on success
     */
    public function regenerate(bool $deleteOldSession = false): bool
    {
        $this->ensureStarted();
        $result = $this->driver->regenerate($deleteOldSession);
        if ($result) {
            $this->id = $this->driver->getId();
        }
        return $result;
    }

    /**
     * Get session ID.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set session ID.
     *
     * @param string $id
     * @return void
     */
    public function setId(string $id): void
    {
        $this->id = $id;
        $this->driver->setId($id);
    }

    /**
     * Get session name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Save session data.
     *
     * Performance: O(N) where N = session data size
     *
     * @return bool True on success
     */
    public function save(): bool
    {
        if (!$this->started) {
            return true;
        }

        return $this->driver->save();
    }

    /**
     * Ensure session is started.
     *
     * Performance: O(1) - Check flag, start if needed
     *
     * @return void
     */
    private function ensureStarted(): void
    {
        if (!$this->started) {
            $this->start();
        }
    }
}
