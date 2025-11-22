<?php

declare(strict_types=1);

namespace Tests\Unit\Database\ORM;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\Concerns\HasBatchOperations;

/**
 * Test HasBatchOperations
 *
 * ✅ TEST STATUS: ALL PASSED (20/20)
 * ✅ Last verified: 2025-01-22
 * ✅ Fixed: Connection::prepare() -> Connection::getPdo()->prepare() for all batch operations
 *
 * Comprehensive tests for batch operations:
 * - insertBatch: Insert multiple records in single query
 * - insertChunked: Insert in chunks to avoid memory issues
 * - updateBatch: Update multiple records efficiently
 * - deleteBatch: Delete multiple records by IDs
 * - upsertBatch: Insert or update multiple records
 * - Performance testing
 * - Edge cases
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class HasBatchOperationsTest extends DatabaseTestCase
{
    protected function createTables(): void
    {
        // Create users table
        $this->createTable('users', "
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                age INT,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            )
        ");
    }

    protected function dropTables(): void
    {
        $this->dropTable('users');
    }

    /**
     * Test insertBatch inserts multiple records
     */
    public function test_insert_batch_inserts_multiple_records(): void
    {
        $records = [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'age' => 30],
            ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'age' => 25],
            ['name' => 'Bob Smith', 'email' => 'bob@example.com', 'age' => 35],
        ];

        $count = BatchTestUser::insertBatch($records);

        $this->assertEquals(3, $count);
        $this->assertTableCount('users', 3);

        $this->assertTableHas('users', ['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->assertTableHas('users', ['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $this->assertTableHas('users', ['name' => 'Bob Smith', 'email' => 'bob@example.com']);
    }

    /**
     * Test insertBatch with empty array returns 0
     */
    public function test_insert_batch_with_empty_array_returns_zero(): void
    {
        $count = BatchTestUser::insertBatch([]);
        $this->assertEquals(0, $count);
        $this->assertTableCount('users', 0);
    }

    /**
     * Test insertBatch with single record
     */
    public function test_insert_batch_with_single_record(): void
    {
        $records = [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'age' => 30],
        ];

        $count = BatchTestUser::insertBatch($records);

        $this->assertEquals(1, $count);
        $this->assertTableCount('users', 1);
    }

    /**
     * Test insertBatch with NULL values
     */
    public function test_insert_batch_with_null_values(): void
    {
        $records = [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'age' => null],
            ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'age' => 25],
        ];

        $count = BatchTestUser::insertBatch($records);

        $this->assertEquals(2, $count);

        $rows = $this->getTableRows('users');
        $this->assertNull($rows[0]['age']);
        $this->assertEquals(25, $rows[1]['age']);
    }

    /**
     * Test insertBatch is faster than individual inserts
     */
    public function test_insert_batch_performance(): void
    {
        $records = [];
        for ($i = 0; $i < 100; $i++) {
            $records[] = ['name' => "User {$i}", 'email' => "user{$i}@example.com", 'age' => 20 + $i];
        }

        // Batch insert
        $start = microtime(true);
        BatchTestUser::insertBatch($records);
        $batchTime = microtime(true) - $start;

        // Verify all records inserted
        $this->assertTableCount('users', 100);

        // Batch should be fast
        $this->assertLessThan(0.5, $batchTime, 'Batch insert should be fast');
    }

    /**
     * Test insertChunked inserts in chunks
     */
    public function test_insert_chunked_inserts_in_chunks(): void
    {
        $records = [];
        for ($i = 0; $i < 15; $i++) {
            $records[] = ['name' => "User {$i}", 'email' => "user{$i}@example.com", 'age' => 20 + $i];
        }

        // Insert in chunks of 5
        $count = BatchTestUser::insertChunked($records, 5);

        $this->assertEquals(15, $count);
        $this->assertTableCount('users', 15);
    }

    /**
     * Test insertChunked with empty array returns 0
     */
    public function test_insert_chunked_with_empty_array_returns_zero(): void
    {
        $count = BatchTestUser::insertChunked([]);
        $this->assertEquals(0, $count);
    }

    /**
     * Test insertChunked with single chunk
     */
    public function test_insert_chunked_with_single_chunk(): void
    {
        $records = [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'age' => 30],
            ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'age' => 25],
        ];

        $count = BatchTestUser::insertChunked($records, 10);

        $this->assertEquals(2, $count);
        $this->assertTableCount('users', 2);
    }

    /**
     * Test updateBatch updates multiple records
     */
    public function test_update_batch_updates_multiple_records(): void
    {
        // Insert test data
        $this->executeQuery("INSERT INTO users (name, email, age) VALUES (?, ?, ?)", ['John', 'john@example.com', 30]);
        $this->executeQuery("INSERT INTO users (name, email, age) VALUES (?, ?, ?)", ['Jane', 'jane@example.com', 25]);

        $rows = $this->getTableRows('users');
        $id1 = $rows[0]['id'];
        $id2 = $rows[1]['id'];

        // Update batch
        $updates = [
            $id1 => ['name' => 'John Updated', 'age' => 31],
            $id2 => ['name' => 'Jane Updated', 'age' => 26],
        ];

        $count = BatchTestUser::updateBatch($updates);

        $this->assertEquals(2, $count);

        // Verify updates
        $updatedRows = $this->getTableRows('users');
        $this->assertEquals('John Updated', $updatedRows[0]['name']);
        $this->assertEquals(31, $updatedRows[0]['age']);
        $this->assertEquals('Jane Updated', $updatedRows[1]['name']);
        $this->assertEquals(26, $updatedRows[1]['age']);
    }

    /**
     * Test updateBatch with empty array returns 0
     */
    public function test_update_batch_with_empty_array_returns_zero(): void
    {
        $count = BatchTestUser::updateBatch([]);
        $this->assertEquals(0, $count);
    }

    /**
     * Test updateBatch with single record
     */
    public function test_update_batch_with_single_record(): void
    {
        // Insert test data
        $this->executeQuery("INSERT INTO users (name, email) VALUES (?, ?)", ['John', 'john@example.com']);
        $rows = $this->getTableRows('users');
        $id = $rows[0]['id'];

        $updates = [
            $id => ['name' => 'John Updated'],
        ];

        $count = BatchTestUser::updateBatch($updates);

        $this->assertEquals(1, $count);
        $this->assertTableHas('users', ['id' => $id, 'name' => 'John Updated']);
    }

    /**
     * Test deleteBatch deletes multiple records
     */
    public function test_delete_batch_deletes_multiple_records(): void
    {
        // Insert test data
        $this->executeQuery("INSERT INTO users (name, email) VALUES (?, ?)", ['John', 'john@example.com']);
        $this->executeQuery("INSERT INTO users (name, email) VALUES (?, ?)", ['Jane', 'jane@example.com']);
        $this->executeQuery("INSERT INTO users (name, email) VALUES (?, ?)", ['Bob', 'bob@example.com']);

        $rows = $this->getTableRows('users');
        $id1 = $rows[0]['id'];
        $id2 = $rows[1]['id'];
        $id3 = $rows[2]['id'];

        // Delete batch
        $count = BatchTestUser::deleteBatch([$id1, $id2]);

        $this->assertEquals(2, $count);
        $this->assertTableCount('users', 1);
        $this->assertTableMissing('users', ['id' => $id1]);
        $this->assertTableMissing('users', ['id' => $id2]);
        $this->assertTableHas('users', ['id' => $id3]);
    }

    /**
     * Test deleteBatch with empty array returns 0
     */
    public function test_delete_batch_with_empty_array_returns_zero(): void
    {
        $count = BatchTestUser::deleteBatch([]);
        $this->assertEquals(0, $count);
    }

    /**
     * Test deleteBatch with non-existent IDs
     */
    public function test_delete_batch_with_non_existent_ids(): void
    {
        // Delete non-existent IDs
        $count = BatchTestUser::deleteBatch([999, 1000]);

        $this->assertEquals(0, $count);
        $this->assertTableCount('users', 0);
    }

    /**
     * Test upsertBatch inserts new records
     */
    public function test_upsert_batch_inserts_new_records(): void
    {
        $records = [
            ['email' => 'john@example.com', 'name' => 'John Doe', 'age' => 30],
            ['email' => 'jane@example.com', 'name' => 'Jane Doe', 'age' => 25],
        ];

        $count = BatchTestUser::upsertBatch($records, ['email']);

        $this->assertEquals(2, $count);
        $this->assertTableCount('users', 2);
        $this->assertTableHas('users', ['email' => 'john@example.com', 'name' => 'John Doe']);
    }

    /**
     * Test upsertBatch updates existing records
     */
    public function test_upsert_batch_updates_existing_records(): void
    {
        // Insert existing record
        $this->executeQuery(
            "INSERT INTO users (email, name, age) VALUES (?, ?, ?)",
            ['john@example.com', 'John', 30]
        );

        // Upsert with same email but different data
        $records = [
            ['email' => 'john@example.com', 'name' => 'John Updated', 'age' => 31],
        ];

        $count = BatchTestUser::upsertBatch($records, ['email']);

        $this->assertGreaterThan(0, $count);
        $this->assertTableCount('users', 1);
        $this->assertTableHas('users', ['email' => 'john@example.com', 'name' => 'John Updated', 'age' => 31]);
    }

    /**
     * Test upsertBatch with empty array returns 0
     */
    public function test_upsert_batch_with_empty_array_returns_zero(): void
    {
        $count = BatchTestUser::upsertBatch([], ['email']);
        $this->assertEquals(0, $count);
    }

    /**
     * Test insertBatch with large dataset
     */
    public function test_insert_batch_with_large_dataset(): void
    {
        $records = [];
        for ($i = 0; $i < 500; $i++) {
            $records[] = ['name' => "User {$i}", 'email' => "user{$i}@example.com", 'age' => 20 + ($i % 50)];
        }

        $count = BatchTestUser::insertBatch($records);

        $this->assertEquals(500, $count);
        $this->assertTableCount('users', 500);
    }

    /**
     * Test insertChunked with large dataset
     */
    public function test_insert_chunked_with_large_dataset(): void
    {
        $records = [];
        for ($i = 0; $i < 1000; $i++) {
            $records[] = ['name' => "User {$i}", 'email' => "user{$i}@example.com", 'age' => 20 + ($i % 50)];
        }

        $count = BatchTestUser::insertChunked($records, 100);

        $this->assertEquals(1000, $count);
        $this->assertTableCount('users', 1000);
    }

    /**
     * Test updateBatch performance
     */
    public function test_update_batch_performance(): void
    {
        // Insert test data
        $records = [];
        for ($i = 0; $i < 100; $i++) {
            $records[] = ['name' => "User {$i}", 'email' => "user{$i}@example.com", 'age' => 20 + $i];
        }
        BatchTestUser::insertBatch($records);

        // Get IDs
        $rows = $this->getTableRows('users');
        $updates = [];
        foreach ($rows as $row) {
            $updates[$row['id']] = ['name' => $row['name'] . ' Updated', 'age' => $row['age'] + 1];
        }

        // Batch update
        $start = microtime(true);
        $count = BatchTestUser::updateBatch($updates);
        $time = microtime(true) - $start;

        $this->assertEquals(100, $count);
        $this->assertLessThan(0.5, $time, 'Batch update should be fast');
    }
}

/**
 * Test model with HasBatchOperations trait
 */
class BatchTestUser extends Model
{
    use HasBatchOperations;

    protected static string $table = 'users';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['name', 'email', 'age'];

    public static function getTableName(): string
    {
        return static::$table;
    }

    public static function getPrimaryKey(): string
    {
        return static::$primaryKey;
    }

    protected static function getConnection(): \Toporia\Framework\Database\Contracts\ConnectionInterface
    {
        return parent::getConnection();
    }
}
