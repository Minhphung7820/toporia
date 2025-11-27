<?php

declare(strict_types=1);

namespace Toporia\Framework\Database;

use Toporia\Framework\Database\Contracts\ConnectionInterface;
use Toporia\Framework\Database\Contracts\GrammarInterface;
use Toporia\Framework\Database\Grammar\{MySQLGrammar, PostgreSQLGrammar, SQLiteGrammar, MongoDBGrammar};
use Toporia\Framework\Database\Query\QueryBuilder;
use PDO;
use PDOException;
use Toporia\Framework\Database\Exception\{ConnectionException, QueryException};


/**
 * Class Connection
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
class Connection implements ConnectionInterface
{
    /**
     * @var PDO|null PDO instance.
     */
    private ?PDO $pdo = null;

    /**
     * @var array<string, mixed> Connection configuration.
     */
    private array $config;

    /**
     * @var GrammarInterface|null SQL Grammar instance.
     */
    private ?GrammarInterface $grammar = null;

    /**
     * @param array $config Connection configuration.
     *        Required keys: driver, host, database, username, password
     *        Optional keys: port, charset, options
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    /**
     * {@inheritdoc}
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->reconnect();
        }

        return $this->pdo;
    }

    /**
     * Ensure connection is alive, reconnect if needed.
     *
     * This method checks if the connection is still valid by attempting a simple query.
     * If the connection is dead (e.g., "MySQL server has gone away"), it reconnects.
     *
     * @return void
     */
    public function ensureConnected(): void
    {
        if ($this->pdo === null) {
            $this->reconnect();
            return;
        }

        try {
            // Try a simple query to check if connection is alive
            $this->pdo->query('SELECT 1');
        } catch (PDOException $e) {
            // Connection is dead, reconnect
            if ($this->isConnectionLost($e)) {
                $this->reconnect();
            } else {
                throw $e;
            }
        }
    }

    /**
     * Check if exception indicates connection was lost.
     *
     * @param PDOException $e
     * @return bool
     */
    private function isConnectionLost(PDOException $e): bool
    {
        $message = $e->getMessage();
        $code = $e->getCode();

        // MySQL: "MySQL server has gone away" (2006) or "Lost connection" (2013)
        if ($code === 2006 || $code === 2013) {
            return true;
        }

        // Check error message for common connection lost patterns
        $lostPatterns = [
            'server has gone away',
            'lost connection',
            'connection was killed',
            'connection was closed',
            'broken pipe',
        ];

        foreach ($lostPatterns as $pattern) {
            if (stripos($message, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(string $query, array $bindings = []): \PDOStatement
    {
        try {
            $statement = $this->getPdo()->prepare($query);

            // Bind parameters
            foreach ($bindings as $key => $value) {
                // Convert arrays/objects to JSON strings for JSON columns
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }

                $type = $this->getPdoType($value);
                $statement->bindValue(
                    is_int($key) ? $key + 1 : $key,
                    $value,
                    $type
                );
            }

            $statement->execute();

            return $statement;
        } catch (PDOException $e) {
            // If connection was lost, reconnect and retry once
            if ($this->isConnectionLost($e)) {
                $this->reconnect();

                // Retry the query
                $statement = $this->getPdo()->prepare($query);
                foreach ($bindings as $key => $value) {
                    $type = $this->getPdoType($value);
                    $statement->bindValue(
                        is_int($key) ? $key + 1 : $key,
                        $value,
                        $type
                    );
                }
                $statement->execute();
                return $statement;
            }

            throw new QueryException(
                "Query execution failed: {$e->getMessage()}",
                $query,
                $bindings,
                $e
            );
        }
    }

    /**
     * Execute query and return results as array.
     *
     * @param string $query SQL query
     * @param array $bindings Query bindings
     * @return array
     */
    public function query(string $query, array $bindings = []): array
    {
        $statement = $this->execute($query, $bindings);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(): bool
    {
        try {
            return $this->getPdo()->beginTransaction();
        } catch (PDOException $e) {
            // If connection was lost, reconnect and retry
            if ($this->isConnectionLost($e)) {
                $this->reconnect();
                return $this->getPdo()->beginTransaction();
            }
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): bool
    {
        return $this->getPdo()->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function rollback(): bool
    {
        return $this->getPdo()->rollBack();
    }

    /**
     * {@inheritdoc}
     */
    public function inTransaction(): bool
    {
        return $this->getPdo()->inTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId(?string $name = null): string
    {
        return $this->getPdo()->lastInsertId($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getDriverName(): string
    {
        return $this->config['driver'] ?? 'mysql';
    }

    /**
     * Get SQL Grammar instance for the connection.
     *
     * Lazily creates Grammar based on driver type.
     * Grammar instance is cached for performance.
     *
     * @return GrammarInterface
     */
    public function getGrammar(): GrammarInterface
    {
        return $this->grammar ??= $this->createGrammar();
    }

    /**
     * Create SQL Grammar instance based on driver.
     *
     * Factory method that instantiates the appropriate Grammar
     * implementation based on the configured database driver.
     *
     * @return GrammarInterface
     * @throws ConnectionException If driver is not supported
     */
    protected function createGrammar(): GrammarInterface
    {
        $driver = $this->getDriverName();

        return match ($driver) {
            'mysql' => new MySQLGrammar(),
            'pgsql' => new PostgreSQLGrammar(),
            'sqlite' => new SQLiteGrammar(),
            'mongodb' => new MongoDBGrammar(),
            default => throw new ConnectionException("Unsupported driver for Grammar: {$driver}")
        };
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect(): void
    {
        $this->pdo = null;
    }

    /**
     * {@inheritdoc}
     */
    public function reconnect(): void
    {
        $this->disconnect();
        $this->connect();
    }

    /**
     * Establish database connection.
     *
     * @return void
     * @throws ConnectionException
     */
    private function connect(): void
    {
        try {
            $dsn = $this->buildDsn();
            $username = $this->config['username'] ?? null;
            $password = $this->config['password'] ?? null;
            $options = $this->getDefaultOptions();

            $this->pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new ConnectionException(
                "Failed to connect to database: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Build DSN string based on driver.
     *
     * @return string
     */
    private function buildDsn(): string
    {
        $driver = $this->config['driver'] ?? 'mysql';

        return match ($driver) {
            'mysql' => $this->buildMysqlDsn(),
            'pgsql' => $this->buildPgsqlDsn(),
            'sqlite' => $this->buildSqliteDsn(),
            default => throw new ConnectionException("Unsupported driver: {$driver}")
        };
    }

    /**
     * Build MySQL DSN.
     *
     * @return string
     */
    private function buildMysqlDsn(): string
    {
        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? 3306;
        $database = $this->config['database'];
        $charset = $this->config['charset'] ?? 'utf8mb4';

        return "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
    }

    /**
     * Build PostgreSQL DSN.
     *
     * @return string
     */
    private function buildPgsqlDsn(): string
    {
        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? 5432;
        $database = $this->config['database'];

        return "pgsql:host={$host};port={$port};dbname={$database}";
    }

    /**
     * Build SQLite DSN.
     *
     * @return string
     */
    private function buildSqliteDsn(): string
    {
        $database = $this->config['database'];
        return "sqlite:{$database}";
    }

    /**
     * Get default PDO options.
     *
     * @return array<int, mixed>
     */
    private function getDefaultOptions(): array
    {
        $defaults = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        return array_merge($defaults, $this->config['options'] ?? []);
    }

    /**
     * Get PDO parameter type for value.
     *
     * @param mixed $value
     * @return int PDO::PARAM_* constant
     */
    private function getPdoType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            is_null($value) => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    /**
     * Get connection configuration.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Execute query in streaming mode for large datasets.
     *
     * This method enables unbuffered queries to reduce memory usage
     * when processing large result sets.
     *
     * @param string $query SQL query
     * @param array $bindings Query bindings
     * @return \Generator<array> Generator yielding rows one by one
     * @throws QueryException
     */
    public function executeStreaming(string $query, array $bindings = []): \Generator
    {
        $this->ensureConnected();

        $originalBuffered = null;

        try {
            // Store original buffered query setting
            $originalBuffered = $this->pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);

            // Enable unbuffered queries for streaming
            $this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

            // Execute query
            $statement = $this->execute($query, $bindings);

            // Yield rows one by one
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                yield $row;
            }

        } catch (PDOException $e) {
            throw new QueryException(
                "Streaming query execution failed: {$e->getMessage()}",
                $query,
                $bindings,
                $e
            );
        } finally {
            // Restore original buffered query setting
            if ($originalBuffered !== null) {
                $this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $originalBuffered);
            }
        }
    }

    /**
     * Check if streaming is supported for current driver.
     *
     * @return bool
     */
    public function supportsStreaming(): bool
    {
        $driver = $this->config['driver'] ?? 'mysql';

        return match ($driver) {
            'mysql' => true,
            'pgsql' => true,  // PostgreSQL supports cursors
            'sqlite' => false, // SQLite doesn't benefit from streaming
            default => false
        };
    }

    /**
     * Execute a SELECT query and return all results.
     *
     * @param string $query SQL query.
     * @param array $bindings Parameter bindings.
     * @return array<array>
     */
    public function select(string $query, array $bindings = []): array
    {
        $statement = $this->execute($query, $bindings);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute a SELECT query and return first result.
     *
     * @param string $query SQL query.
     * @param array $bindings Parameter bindings.
     * @return array|null
     */
    public function selectOne(string $query, array $bindings = []): ?array
    {
        $statement = $this->execute($query, $bindings);
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Execute an INSERT/UPDATE/DELETE query.
     *
     * @param string $query SQL query.
     * @param array $bindings Parameter bindings.
     * @return int Number of affected rows.
     */
    public function affectingStatement(string $query, array $bindings = []): int
    {
        $statement = $this->execute($query, $bindings);
        return $statement->rowCount();
    }

    /**
     * Get a query builder for the given table.
     *
     * This enables fluent query building:
     * $users = $connection->table('users')->where('active', true)->get();
     *
     * @param string $table Table name.
     * @return QueryBuilder
     */
    public function table(string $table): QueryBuilder
    {
        return (new QueryBuilder($this))->table($table);
    }
}
