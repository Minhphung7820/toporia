<?php

declare(strict_types=1);

namespace Toporia\Framework\Session\Drivers;

use Toporia\Framework\Session\Contracts\SessionStoreInterface;
use Toporia\Framework\Database\Contracts\ConnectionInterface;

/**
 * Class DatabaseSessionDriver
 *
 * Stores sessions in database table.
 * Better for distributed systems and scalability.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Session\Drivers
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
final class DatabaseSessionDriver implements SessionStoreInterface
{
    private bool $started = false;
    private array $data = [];
    private string $id;
    private string $name;
    private bool $exists = false;

    public function __construct(
        private ConnectionInterface $connection,
        private string $table = 'sessions',
        string $name = 'PHPSESSID',
        private int $lifetime = 7200
    ) {
        $this->name = $name;
        $this->id = $this->generateId();
    }

    /**
     * Start the session.
     *
     * @return bool True on success
     */
    public function start(): bool
    {
        if ($this->started) {
            return true;
        }

        // Load session from database
        $this->loadFromDatabase();
        $this->started = true;
        return true;
    }

    /**
     * Get a session value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Set a session value.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Put a session value (alias for set).
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function put(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    /**
     * Check if a session key exists.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Remove a session value.
     *
     * @param string $key
     * @return void
     */
    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * Get all session data.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Clear all session data.
     *
     * @return void
     */
    public function flush(): void
    {
        $this->data = [];
    }

    /**
     * Regenerate session ID.
     *
     * @param bool $deleteOldSession
     * @return bool True on success
     */
    public function regenerate(bool $deleteOldSession = false): bool
    {
        $oldId = $this->id;
        $this->id = $this->generateId();

        if ($deleteOldSession) {
            $this->connection->execute(
                "DELETE FROM {$this->table} WHERE id = ?",
                [$oldId]
            );
        }

        $this->exists = false;
        return true;
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
        $this->exists = false;
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
     * @return bool True on success
     */
    public function save(): bool
    {
        if (!$this->started) {
            return true;
        }

        $payload = serialize($this->data);
        $expiresAt = now()->getTimestamp() + $this->lifetime;

        if ($this->exists) {
            // Update existing session
            $this->connection->execute(
                "UPDATE {$this->table} SET payload = ?, last_activity = ?, expires_at = ? WHERE id = ?",
                [$payload, now()->getTimestamp(), $expiresAt, $this->id]
            );
        } else {
            // Insert new session
            try {
                $this->connection->execute(
                    "INSERT INTO {$this->table} (id, payload, last_activity, expires_at) VALUES (?, ?, ?, ?)",
                    [$this->id, $payload, now()->getTimestamp(), $expiresAt]
                );
                $this->exists = true;
            } catch (\Exception $e) {
                // Session might exist, try update
                $this->connection->execute(
                    "UPDATE {$this->table} SET payload = ?, last_activity = ?, expires_at = ? WHERE id = ?",
                    [$payload, now()->getTimestamp(), $expiresAt, $this->id]
                );
                $this->exists = true;
            }
        }

        return true;
    }

    /**
     * Load session from database.
     *
     * @return void
     */
    private function loadFromDatabase(): void
    {
        $result = $this->connection->selectOne(
            "SELECT payload, expires_at FROM {$this->table} WHERE id = ?",
            [$this->id]
        );

        if ($result === null) {
            $this->data = [];
            $this->exists = false;
            return;
        }

        // Check expiration
        if (isset($result['expires_at']) && $result['expires_at'] < now()->getTimestamp()) {
            $this->connection->execute("DELETE FROM {$this->table} WHERE id = ?", [$this->id]);
            $this->data = [];
            $this->exists = false;
            return;
        }

        $this->data = unserialize($result['payload']) ?: [];
        $this->exists = true;
    }

    /**
     * Generate a cryptographically secure session ID.
     *
     * @return string
     */
    private function generateId(): string
    {
        return bin2hex(random_bytes(32));
    }
}
