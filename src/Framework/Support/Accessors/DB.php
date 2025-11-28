<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Foundation\ServiceAccessor;
use Toporia\Framework\Database\Contracts\ConnectionInterface;
use Toporia\Framework\Database\Query\QueryBuilder;
use Toporia\Framework\Database\DatabaseManager;


/**
 * Class DB
 *
 * Core class for the Accessors layer providing essential functionality for
 * the Toporia Framework.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Accessors
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
class DB extends ServiceAccessor
{
    /**
     * Get a specific database connection by name.
     *
     * This method follows SOLID principles:
     * - Single Responsibility: Only retrieves connections, doesn't create them
     * - Dependency Inversion: Depends on DatabaseManager abstraction via container
     * - Open/Closed: Can be overridden in subclasses for custom behavior
     *
     * @param string|null $name Connection name from config/database.php.
     *                          If null, returns the default connection.
     * @return ConnectionInterface Database connection instance.
     *
     * @example
     * // Get default connection
     * $conn = DB::connection();
     * $conn->table('users')->get();
     *
     * // Get named connection
     * $mysql = DB::connection('mysql');
     * $postgres = DB::connection('analytics');
     * $redis = DB::connection('redis');
     */
    public static function connection(?string $name = null): ConnectionInterface
    {
        return static::getDatabaseManager()->connection($name);
    }

    /**
     * Get the DatabaseManager instance from the container.
     *
     * Separated into its own method following SOLID principles:
     * - Single Responsibility: Encapsulates container access logic
     * - Open/Closed: Can be overridden for testing or custom implementations
     * - Dependency Inversion: Isolates container dependency in one place
     *
     * Benefits:
     * - Testability: Easy to mock in unit tests
     * - Reusability: Can be used by subclasses
     * - Maintainability: Single point of change for container access
     *
     * @return DatabaseManager
     */
    protected static function getDatabaseManager(): DatabaseManager
    {
        return container(DatabaseManager::class);
    }

    /**
     * {@inheritdoc}
     *
     * Returns the service name for the default connection.
     * This enables static method delegation via ServiceAccessor.
     */
    protected static function getServiceName(): string
    {
        return 'db';
    }

    /**
     * Enable query logging.
     *
     * All executed queries will be logged with their SQL, bindings, and execution time.
     *
     * @return void
     *
     * @example
     * ```php
     * DB::enableQueryLog();
     * $users = DB::table('users')->get();
     * $queries = DB::getQueryLog();
     * ```
     */
    public static function enableQueryLog(): void
    {
        QueryBuilder::enableQueryLog();
    }

    /**
     * Disable query logging.
     *
     * @return void
     */
    public static function disableQueryLog(): void
    {
        QueryBuilder::disableQueryLog();
    }

    /**
     * Get the query log.
     *
     * Returns array of executed queries with:
     * - query: SQL query string
     * - bindings: Parameter bindings
     * - time: Execution time in milliseconds
     *
     * @return array<array{query: string, bindings: array, time: float}>
     *
     * @example
     * ```php
     * DB::enableQueryLog();
     * $users = DB::table('users')->get();
     * $queries = DB::getQueryLog();
     * // [
     * //     [
     * //         'query' => 'SELECT * FROM users',
     * //         'bindings' => [],
     * //         'time' => 0.5
     * //     ]
     * // ]
     * ```
     */
    public static function getQueryLog(): array
    {
        return QueryBuilder::getQueryLog();
    }

    /**
     * Clear the query log.
     *
     * @return void
     */
    public static function flushQueryLog(): void
    {
        QueryBuilder::flushQueryLog();
    }
}
