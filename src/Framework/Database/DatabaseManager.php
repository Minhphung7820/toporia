<?php

declare(strict_types=1);

namespace Toporia\Framework\Database;

use Toporia\Framework\Database\Contracts\ConnectionInterface;
use Toporia\Framework\Database\Schema\SchemaBuilder;


/**
 * Class DatabaseManager
 *
 * Core class for the Database query building and ORM layer providing
 * essential functionality for the Toporia Framework.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Database
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 *
 * @internal    This class is a core component and should not be extended
 *              directly unless you know what you're doing.
 */
class DatabaseManager
{
    /**
     * @var array<string, ConnectionInterface> Active connections.
     */
    private array $connections = [];

    /**
     * @var array<string, array> Connection configurations.
     */
    private array $config;

    /**
     * @var string Default connection name.
     */
    private string $defaultConnection = 'default';

    /**
     * @param array $config Connection configurations.
     *        Example: ['default' => ['driver' => 'mysql', ...]]
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get a database connection.
     *
     * @param string|null $name Connection name (null for default).
     * @return ConnectionInterface
     */
    public function connection(?string $name = null): ConnectionInterface
    {
        $name = $name ?? $this->defaultConnection;

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->createConnection($name);
        }

        return $this->connections[$name];
    }

    /**
     * Create a new connection instance.
     *
     * @param string $name Connection name.
     * @return ConnectionInterface
     */
    private function createConnection(string $name): ConnectionInterface
    {
        if (!isset($this->config[$name])) {
            throw new \RuntimeException("Database connection '{$name}' not configured");
        }

        return new Connection($this->config[$name]);
    }

    /**
     * Get schema builder for a connection.
     *
     * @param string|null $name Connection name.
     * @return SchemaBuilder
     */
    public function schema(?string $name = null): SchemaBuilder
    {
        return new SchemaBuilder($this->connection($name));
    }

    /**
     * Set the default connection name.
     *
     * @param string $name Connection name.
     * @return void
     */
    public function setDefaultConnection(string $name): void
    {
        $this->defaultConnection = $name;
    }

    /**
     * Disconnect all connections.
     *
     * @return void
     */
    public function disconnect(): void
    {
        foreach ($this->connections as $connection) {
            $connection->disconnect();
        }

        $this->connections = [];
    }

    /**
     * Reconnect a connection.
     *
     * @param string|null $name Connection name.
     * @return void
     */
    public function reconnect(?string $name = null): void
    {
        $name = $name ?? $this->defaultConnection;

        if (isset($this->connections[$name])) {
            $this->connections[$name]->reconnect();
        }
    }
}
