<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Foundation\ServiceAccessor;

/**
 * DB Service Accessor
 *
 * Provides static-like access to the database manager.
 * All methods are automatically delegated to the underlying service via __callStatic().
 *
 * @method static ConnectionProxy connection(?string $name = null) Get connection proxy
 * @method static ConnectionInterface getConnection(?string $name = null) Get connection directly
 * @method static void setDefaultConnection(string $name) Set default connection
 * @method static string getDefaultConnection() Get default connection name
 * @method static void enableQueryLog() Enable query logging
 * @method static void disableQueryLog() Disable query logging
 * @method static array getQueryLog() Get the query log
 * @method static void flushQueryLog() Clear the query log
 * @method static \Toporia\Framework\Database\DatabaseCollection raw(string $sql, array $bindings = []) Execute raw SELECT query
 * @method static int statement(string $sql, array $bindings = []) Execute raw INSERT/UPDATE/DELETE statement
 * @method static bool unprepared(string $sql) Execute unprepared SQL (DDL statements)
 *
 * @see DatabaseManager
 *
 * @example
 * // Get default connection and use it
 * DB::connection()->table('users')->get();
 *
 * // Get named connection
 * DB::connection('mysql')->table('users')->get();
 *
 * // Execute raw SQL
 * DB::raw('SELECT * FROM users WHERE status = ?', ['active']);
 * DB::statement('UPDATE users SET status = ? WHERE id = ?', ['inactive', 1]);
 * DB::unprepared('TRUNCATE TABLE cache');
 */
final class DB extends ServiceAccessor
{
    /**
     * Get the service name for this accessor.
     *
     * This is the only method needed - all other methods are automatically
     * delegated to the underlying service via __callStatic().
     *
     * @return string Service name in container
     */
    protected static function getServiceName(): string
    {
        return 'db';
    }
}
