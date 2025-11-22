<?php

declare(strict_types=1);

namespace Tests\Unit\Database\ORM;

use Toporia\Framework\Testing\TestCase;
use Toporia\Framework\Database\{Connection, DatabaseManager};
use Toporia\Framework\Database\Contracts\ConnectionInterface;
use Toporia\Framework\Database\ORM\Model;
use PDO;

/**
 * Base TestCase for ORM tests with database setup
 *
 * ✅ STATUS: Base class for all ORM tests - provides database connection and helper methods
 * ✅ Last verified: 2025-01-22
 * ✅ Features:
 *    - Automatic database connection setup (MySQL)
 *    - Transaction-based test isolation (rollback after each test)
 *    - Helper methods for table creation, queries, and assertions
 *    - Support for test database configuration via environment variables
 *
 * Provides database connection setup and helper methods for ORM testing.
 * All ORM test classes should extend this class to get database functionality.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
abstract class DatabaseTestCase extends TestCase
{
    protected ?ConnectionInterface $connection = null;
    protected ?PDO $pdo = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup MySQL test database
        $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306;
        $database = $_ENV['DB_TEST_DATABASE'] ?? getenv('DB_TEST_DATABASE') ?: $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'toporia_test';
        $username = $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root';
        $password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';

        // Create PDO connection
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        try {
            $this->pdo = new PDO($dsn, $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            throw new \RuntimeException(
                "Failed to connect to MySQL test database. " .
                    "Please ensure MySQL is running and configured. " .
                    "Error: " . $e->getMessage() . "\n" .
                    "Config: host={$host}, port={$port}, database={$database}, username={$username}"
            );
        }

        // Create Connection instance
        $this->connection = new Connection([
            'driver' => 'mysql',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8mb4',
        ]);

        // Set as default connection for models
        Model::setConnection($this->connection);

        // Start transaction for test isolation
        $this->pdo->beginTransaction();

        // Create tables for test models
        $this->createTables();
    }

    protected function tearDown(): void
    {
        // Rollback transaction to undo all changes
        if ($this->pdo && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        // Clean up test tables
        $this->dropTables();

        // Reset model connection (clear it if possible)
        // Note: We can't actually unset the static connection,
        // but it will be replaced in the next test's setUp()

        parent::tearDown();
    }

    /**
     * Create test database tables
     */
    protected function createTables(): void
    {
        // Override in child classes
    }

    /**
     * Drop test database tables
     */
    protected function dropTables(): void
    {
        // Override in child classes
    }

    /**
     * Helper to create a table
     */
    protected function createTable(string $table, string $schema): void
    {
        // Drop table first if exists to avoid conflicts
        $this->dropTable($table);
        $this->pdo->exec($schema);
    }

    /**
     * Helper to drop a table
     */
    protected function dropTable(string $table): void
    {
        $this->pdo->exec("DROP TABLE IF EXISTS `{$table}`");
    }

    /**
     * Execute a raw SQL query
     */
    protected function executeQuery(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Get all rows from a table
     */
    protected function getTableRows(string $table): array
    {
        $stmt = $this->pdo->query("SELECT * FROM `{$table}`");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get count of rows in a table
     */
    protected function getTableCount(string $table, array $where = []): int
    {
        $sql = "SELECT COUNT(*) as count FROM `{$table}`";
        $params = [];

        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = "`{$key}` = :{$key}";
                $params[":{$key}"] = $value;
            }
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $result['count'];
    }

    /**
     * Assert table has a record
     */
    protected function assertTableHas(string $table, array $where, string $message = ''): void
    {
        $count = $this->getTableCount($table, $where);
        $this->assertGreaterThan(0, $count, $message ?: "Expected record in table `{$table}`");
    }

    /**
     * Assert table doesn't have a record
     */
    protected function assertTableMissing(string $table, array $where, string $message = ''): void
    {
        $count = $this->getTableCount($table, $where);
        $this->assertEquals(0, $count, $message ?: "Unexpected record in table `{$table}`");
    }

    /**
     * Assert table count
     */
    protected function assertTableCount(string $table, int $expected, array $where = [], string $message = ''): void
    {
        $actual = $this->getTableCount($table, $where);
        $this->assertEquals($expected, $actual, $message ?: "Expected {$expected} records in table `{$table}`, found {$actual}");
    }

    /**
     * Helper to set eagerLoaded on a model using reflection
     */
    protected function setEagerLoaded(Model $model, string $relation, bool $loaded = true): void
    {
        $reflection = new \ReflectionClass($model);
        $property = $reflection->getProperty('eagerLoaded');
        $property->setAccessible(true);
        $eagerLoaded = $property->getValue($model);
        if ($loaded) {
            $eagerLoaded[$relation] = true;
        } else {
            unset($eagerLoaded[$relation]);
        }
        $property->setValue($model, $eagerLoaded);
    }
}
