<?php

declare(strict_types=1);

namespace Toporia\Framework\Testing\Concerns;

use PDO;
use PDOException;

/**
 * Database Testing Trait
 *
 * Provides utilities for database testing with transactions.
 *
 * Performance:
 * - Fast transaction rollback (O(1))
 * - In-memory SQLite for unit tests
 * - Efficient migration running
 */
trait InteractsWithDatabase
{
    /**
     * Database connection instance.
     */
    protected ?PDO $db = null;

    /**
     * Indicates if we're using transactions.
     */
    protected bool $usingTransactions = true;

    /**
     * Setup database for testing.
     *
     * Performance: O(1) - Connection setup
     */
    protected function setUpDatabase(): void
    {
        // Use in-memory SQLite for fast testing
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Start transaction if using transactions
        if ($this->usingTransactions) {
            $this->db->beginTransaction();
        }
    }

    /**
     * Cleanup database after test.
     *
     * Performance: O(1) - Transaction rollback
     */
    protected function tearDownDatabase(): void
    {
        if ($this->db !== null) {
            if ($this->usingTransactions) {
                $this->db->rollBack();
            }
            $this->db = null;
        }
    }

    /**
     * Get database connection.
     *
     * Performance: O(1) - Direct access
     */
    protected function getDb(): ?PDO
    {
        if ($this->db === null) {
            $this->setUpDatabase();
        }

        return $this->db;
    }

    /**
     * Run a database query.
     *
     * Performance: O(N) where N = query complexity
     */
    protected function dbQuery(string $sql, array $params = []): \PDOStatement
    {
        $db = $this->getDb();
        if ($db === null) {
            throw new \RuntimeException('Database connection not available');
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Insert data into a table.
     *
     * Performance: O(1) - Single insert
     */
    protected function dbInsert(string $table, array $data): bool
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->getDb()->prepare($sql);

        foreach ($data as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        return $stmt->execute();
    }

    /**
     * Get data from a table.
     *
     * Performance: O(N) where N = number of rows
     */
    protected function dbGet(string $table, array $where = [], string $orderBy = null): array
    {
        $sql = "SELECT * FROM {$table}";

        if (!empty($where)) {
            $conditions = [];
            foreach (array_keys($where) as $key) {
                $conditions[] = "{$key} = :{$key}";
            }
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        if ($orderBy !== null) {
            $sql .= " ORDER BY {$orderBy}";
        }

        $stmt = $this->getDb()->prepare($sql);

        foreach ($where as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Assert that a record exists in the database.
     *
     * Performance: O(1) - Single query
     */
    protected function assertDatabaseHas(string $table, array $where, string $message = ''): void
    {
        $records = $this->dbGet($table, $where);
        $this->assertNotEmpty($records, $message ?: "Failed to find record in {$table}");
    }

    /**
     * Assert that a record doesn't exist in the database.
     *
     * Performance: O(1) - Single query
     */
    protected function assertDatabaseMissing(string $table, array $where, string $message = ''): void
    {
        $records = $this->dbGet($table, $where);
        $this->assertEmpty($records, $message ?: "Found unexpected record in {$table}");
    }

    /**
     * Assert that a record count matches.
     *
     * Performance: O(1) - COUNT query
     */
    protected function assertDatabaseCount(string $table, int $expected, array $where = [], string $message = ''): void
    {
        $sql = "SELECT COUNT(*) as count FROM {$table}";

        if (!empty($where)) {
            $conditions = [];
            foreach (array_keys($where) as $key) {
                $conditions[] = "{$key} = :{$key}";
            }
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $stmt = $this->getDb()->prepare($sql);

        foreach ($where as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $actual = (int) $result['count'];

        $this->assertEquals($expected, $actual, $message ?: "Expected {$expected} records in {$table}, found {$actual}");
    }

    /**
     * Disable transactions for this test.
     */
    protected function withoutTransactions(): void
    {
        $this->usingTransactions = false;
    }
}
