<?php

declare(strict_types=1);

namespace Toporia\Framework\Database;

use Toporia\Framework\Database\Contracts\ConnectionInterface;
use Toporia\Framework\Database\Query\QueryBuilder;

/**
 * Connection Proxy for Fluent API
 *
 * Wraps ConnectionInterface to provide fluent API for QueryBuilder creation.
 * Enables syntax: DB()->connection('mysql')->table('users')
 *
 * Design Pattern: Proxy Pattern
 * - Provides simplified interface for QueryBuilder creation
 * - Maintains connection reference for performance
 *
 * SOLID Principles:
 * - Single Responsibility: Only provides table() method for QueryBuilder
 * - Dependency Inversion: Depends on ConnectionInterface abstraction
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Database
 * @since       2025-01-23
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
class ConnectionProxy
{
    /**
     * @param ConnectionInterface $connection Database connection
     */
    public function __construct(
        private readonly ConnectionInterface $connection
    ) {}

    /**
     * Create a QueryBuilder instance for the connection's table.
     *
     * Grammar is automatically selected based on connection driver.
     *
     * Usage:
     * ```php
     * DB()->connection('mysql')->table('users')->where('status', 'active')->get();
     * DB()->connection('mongodb')->table('messages')->where('user_id', 123)->get();
     * ```
     *
     * Performance: Connection is cached, Grammar is cached per connection
     *
     * @param string $table Table/collection name
     * @return QueryBuilder QueryBuilder instance ready for query building
     */
    public function table(string $table): QueryBuilder
    {
        $query = new QueryBuilder($this->connection);
        return $query->table($table);
    }

    /**
     * Get the underlying connection instance.
     *
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }
}




