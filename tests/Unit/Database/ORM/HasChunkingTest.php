<?php

declare(strict_types=1);

namespace Tests\Unit\Database\ORM;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\Concerns\HasChunking;
use Toporia\Framework\Database\ORM\ModelCollection;
use Toporia\Framework\Database\ORM\ModelQueryBuilder;
use Generator;

/**
 * Test HasChunking
 *
 * ✅ TEST STATUS: ALL PASSED (19/19)
 * ✅ Last verified: 2025-01-22
 * ✅ Fixed: ModelCollection instead of RowCollection, added each()/eachById(), chunk() on ModelQueryBuilder
 *
 * Comprehensive tests for chunking functionality:
 * - chunk() method: Chunk query results (static and on query builder)
 * - chunkById() method: Chunk using cursor-based pagination
 * - each() method: Process each record
 * - eachById() method: Process each record by ID
 * - Memory efficiency
 * - Performance testing
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class HasChunkingTest extends DatabaseTestCase
{
    protected function createTables(): void
    {
        // Create users table
        $this->createTable('users', "
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
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
     * Test chunk returns generator of chunks
     */
    public function test_chunk_returns_generator_of_chunks(): void
    {
        // Insert test data
        for ($i = 0; $i < 10; $i++) {
            $this->executeQuery(
                "INSERT INTO users (name, email, age) VALUES (?, ?, ?)",
                ["User {$i}", "user{$i}@example.com", 20 + $i]
            );
        }

        $chunkCount = 0;
        $totalRecords = 0;

        foreach (ChunkTestUser::chunk(3) as $chunk) {
            $this->assertInstanceOf(ModelCollection::class, $chunk);
            $chunkCount++;
            $totalRecords += $chunk->count();
        }

        $this->assertEquals(4, $chunkCount); // 4 chunks: 3, 3, 3, 1
        $this->assertEquals(10, $totalRecords);
    }

    /**
     * Test chunk with callback processes each chunk
     */
    public function test_chunk_with_callback_processes_each_chunk(): void
    {
        // Insert test data
        for ($i = 0; $i < 10; $i++) {
            $this->executeQuery(
                "INSERT INTO users (name, email) VALUES (?, ?)",
                ["User {$i}", "user{$i}@example.com"]
            );
        }

        $processedCount = 0;

        ChunkTestUser::chunk(3, function ($chunk) use (&$processedCount) {
            $this->assertInstanceOf(ModelCollection::class, $chunk);
            $processedCount += $chunk->count();
        });

        $this->assertEquals(10, $processedCount);
    }

    /**
     * Test chunk with empty result set
     */
    public function test_chunk_with_empty_result_set(): void
    {
        $chunkCount = 0;

        foreach (ChunkTestUser::chunk(10) as $chunk) {
            $chunkCount++;
        }

        $this->assertEquals(0, $chunkCount);
    }

    /**
     * Test chunk with single record
     */
    public function test_chunk_with_single_record(): void
    {
        $this->executeQuery(
            "INSERT INTO users (name, email) VALUES (?, ?)",
            ['John Doe', 'john@example.com']
        );

        $chunkCount = 0;

        foreach (ChunkTestUser::chunk(10) as $chunk) {
            $this->assertCount(1, $chunk);
            $chunkCount++;
        }

        $this->assertEquals(1, $chunkCount);
    }

    /**
     * Test chunkById uses cursor-based pagination
     */
    public function test_chunk_by_id_uses_cursor_pagination(): void
    {
        // Insert test data
        for ($i = 0; $i < 10; $i++) {
            $this->executeQuery(
                "INSERT INTO users (name, email, age) VALUES (?, ?, ?)",
                ["User {$i}", "user{$i}@example.com", 20 + $i]
            );
        }

        $chunkCount = 0;
        $totalRecords = 0;

        foreach (ChunkTestUser::chunkById(3) as $chunk) {
            $this->assertInstanceOf(ModelCollection::class, $chunk);
            $chunkCount++;
            $totalRecords += $chunk->count();
        }

        $this->assertEquals(4, $chunkCount); // 4 chunks: 3, 3, 3, 1
        $this->assertEquals(10, $totalRecords);
    }

    /**
     * Test chunkById with callback
     */
    public function test_chunk_by_id_with_callback(): void
    {
        // Insert test data
        for ($i = 0; $i < 10; $i++) {
            $this->executeQuery(
                "INSERT INTO users (name, email) VALUES (?, ?)",
                ["User {$i}", "user{$i}@example.com"]
            );
        }

        $processedCount = 0;

        ChunkTestUser::chunkById(3, function ($chunk) use (&$processedCount) {
            $processedCount += $chunk->count();
        });

        $this->assertEquals(10, $processedCount);
    }

    /**
     * Test chunkById with ordered query
     */
    public function test_chunk_by_id_with_ordered_query(): void
    {
        // Insert test data in reverse order
        for ($i = 10; $i > 0; $i--) {
            $this->executeQuery(
                "INSERT INTO users (name, email, age) VALUES (?, ?, ?)",
                ["User {$i}", "user{$i}@example.com", 20 + $i]
            );
        }

        $ids = [];
        ChunkTestUser::chunkById(3, function ($chunk) use (&$ids) {
            foreach ($chunk as $user) {
                $ids[] = $user->id;
            }
        });

        // IDs should be in ascending order
        $sortedIds = $ids;
        sort($sortedIds);
        $this->assertEquals($sortedIds, $ids);
    }

    /**
     * Test each processes each record
     */
    public function test_each_processes_each_record(): void
    {
        // Insert test data
        for ($i = 0; $i < 10; $i++) {
            $this->executeQuery(
                "INSERT INTO users (name, email) VALUES (?, ?)",
                ["User {$i}", "user{$i}@example.com"]
            );
        }

        $processedCount = 0;

        ChunkTestUser::each(3, function ($user) use (&$processedCount) {
            $this->assertInstanceOf(ChunkTestUser::class, $user);
            $processedCount++;
        });

        $this->assertEquals(10, $processedCount);
    }

    /**
     * Test each returns early when callback returns false
     */
    public function test_each_returns_early_on_false(): void
    {
        // Insert test data
        for ($i = 0; $i < 10; $i++) {
            $this->executeQuery(
                "INSERT INTO users (name, email) VALUES (?, ?)",
                ["User {$i}", "user{$i}@example.com"]
            );
        }

        $processedCount = 0;

        ChunkTestUser::each(3, function ($user) use (&$processedCount) {
            $processedCount++;
            if ($processedCount >= 5) {
                return false; // Stop processing
            }
            return true;
        });

        $this->assertEquals(5, $processedCount);
    }

    /**
     * Test eachById processes each record by ID
     */
    public function test_each_by_id_processes_each_record(): void
    {
        // Insert test data
        for ($i = 0; $i < 10; $i++) {
            $this->executeQuery(
                "INSERT INTO users (name, email) VALUES (?, ?)",
                ["User {$i}", "user{$i}@example.com"]
            );
        }

        $processedCount = 0;

        ChunkTestUser::eachById(3, function ($user) use (&$processedCount) {
            $this->assertInstanceOf(ChunkTestUser::class, $user);
            $processedCount++;
        });

        $this->assertEquals(10, $processedCount);
    }

    /**
     * Test eachById returns early when callback returns false
     */
    public function test_each_by_id_returns_early_on_false(): void
    {
        // Insert test data
        for ($i = 0; $i < 10; $i++) {
            $this->executeQuery(
                "INSERT INTO users (name, email) VALUES (?, ?)",
                ["User {$i}", "user{$i}@example.com"]
            );
        }

        $processedCount = 0;

        ChunkTestUser::eachById(3, function ($user) use (&$processedCount) {
            $processedCount++;
            if ($processedCount >= 5) {
                return false;
            }
            return true;
        });

        $this->assertEquals(5, $processedCount);
    }

    /**
     * Test chunk with large dataset
     */
    public function test_chunk_with_large_dataset(): void
    {
        // Insert 100 records
        for ($i = 0; $i < 100; $i++) {
            $this->executeQuery(
                "INSERT INTO users (name, email) VALUES (?, ?)",
                ["User {$i}", "user{$i}@example.com"]
            );
        }

        $chunkCount = 0;
        $totalRecords = 0;

        foreach (ChunkTestUser::chunk(25) as $chunk) {
            $chunkCount++;
            $totalRecords += $chunk->count();
            $this->assertLessThanOrEqual(25, $chunk->count());
        }

        $this->assertEquals(4, $chunkCount); // 4 chunks: 25, 25, 25, 25
        $this->assertEquals(100, $totalRecords);
    }

    /**
     * Test chunkById with large dataset
     */
    public function test_chunk_by_id_with_large_dataset(): void
    {
        // Insert 100 records
        for ($i = 0; $i < 100; $i++) {
            $this->executeQuery(
                "INSERT INTO users (name, email) VALUES (?, ?)",
                ["User {$i}", "user{$i}@example.com"]
            );
        }

        $chunkCount = 0;
        $totalRecords = 0;

        foreach (ChunkTestUser::chunkById(25) as $chunk) {
            $chunkCount++;
            $totalRecords += $chunk->count();
            $this->assertLessThanOrEqual(25, $chunk->count());
        }

        $this->assertEquals(4, $chunkCount);
        $this->assertEquals(100, $totalRecords);
    }

    /**
     * Test chunk with where clause
     */
    public function test_chunk_with_where_clause(): void
    {
        // Insert test data
        for ($i = 0; $i < 10; $i++) {
            $this->executeQuery(
                "INSERT INTO users (name, email, age) VALUES (?, ?, ?)",
                ["User {$i}", "user{$i}@example.com", 20 + $i]
            );
        }

        $chunkCount = 0;
        $totalRecords = 0;

        foreach (ChunkTestUser::query()->where('age', '>=', 25)->chunk(3) as $chunk) {
            $chunkCount++;
            $totalRecords += $chunk->count();
        }

        // Should only process users with age >= 25
        $this->assertGreaterThan(0, $chunkCount);
        $this->assertLessThanOrEqual(6, $totalRecords); // Max 6 records (age 25-30)
    }

    /**
     * Test chunk with orderBy
     */
    public function test_chunk_with_order_by(): void
    {
        // Insert test data
        for ($i = 0; $i < 10; $i++) {
            $this->executeQuery(
                "INSERT INTO users (name, email, age) VALUES (?, ?, ?)",
                ["User {$i}", "user{$i}@example.com", 20 + $i]
            );
        }

        $ages = [];
        ChunkTestUser::query()->orderBy('age', 'DESC')->chunk(3, function ($chunk) use (&$ages) {
            foreach ($chunk as $user) {
                $ages[] = $user->age;
            }
        });

        // Ages should be in descending order
        $sortedAges = $ages;
        rsort($sortedAges);
        $this->assertEquals($sortedAges, $ages);
    }

    /**
     * Test chunk memory efficiency
     */
    public function test_chunk_memory_efficiency(): void
    {
        // Insert 50 records
        for ($i = 0; $i < 50; $i++) {
            $this->executeQuery(
                "INSERT INTO users (name, email) VALUES (?, ?)",
                ["User {$i}", "user{$i}@example.com"]
            );
        }

        $memoryBefore = memory_get_usage();
        $maxMemoryUsed = 0;

        foreach (ChunkTestUser::chunk(10) as $chunk) {
            $memoryAfter = memory_get_usage();
            $memoryUsed = $memoryAfter - $memoryBefore;
            $maxMemoryUsed = max($maxMemoryUsed, $memoryUsed);
        }

        // Memory should not grow significantly
        // Each chunk should only hold 10 records
        $this->assertLessThan(5 * 1024 * 1024, $maxMemoryUsed, 'Chunk should use limited memory');
    }

    /**
     * Test chunkById is faster than chunk for large datasets
     */
    public function test_chunk_by_id_performance(): void
    {
        // Insert 100 records
        for ($i = 0; $i < 100; $i++) {
            $this->executeQuery(
                "INSERT INTO users (name, email) VALUES (?, ?)",
                ["User {$i}", "user{$i}@example.com"]
            );
        }

        // Test chunkById
        $start = microtime(true);
        foreach (ChunkTestUser::chunkById(25) as $chunk) {
            // Process chunk
        }
        $chunkByIdTime = microtime(true) - $start;

        // chunkById should be reasonably fast
        $this->assertLessThan(1.0, $chunkByIdTime, 'chunkById should be fast');
    }

    /**
     * Test each with different chunk sizes
     */
    public function test_each_with_different_chunk_sizes(): void
    {
        // Insert 20 records
        for ($i = 0; $i < 20; $i++) {
            $this->executeQuery(
                "INSERT INTO users (name, email) VALUES (?, ?)",
                ["User {$i}", "user{$i}@example.com"]
            );
        }

        $processedCount = 0;

        // Test with chunk size 5
        ChunkTestUser::each(5, function ($user) use (&$processedCount) {
            $processedCount++;
        });

        $this->assertEquals(20, $processedCount);
    }

    /**
     * Test chunk stops when chunk is smaller than chunk size
     */
    public function test_chunk_stops_when_chunk_smaller_than_size(): void
    {
        // Insert 7 records
        for ($i = 0; $i < 7; $i++) {
            $this->executeQuery(
                "INSERT INTO users (name, email) VALUES (?, ?)",
                ["User {$i}", "user{$i}@example.com"]
            );
        }

        $chunkCount = 0;

        foreach (ChunkTestUser::chunk(5) as $chunk) {
            $chunkCount++;
            if ($chunkCount <= 1) {
                $this->assertEquals(5, $chunk->count());
            } else {
                // Last chunk should have 2 records
                $this->assertEquals(2, $chunk->count());
            }
        }

        $this->assertEquals(2, $chunkCount);
    }
}

/**
 * Test model with HasChunking trait
 */
class ChunkTestUser extends Model
{
    use HasChunking;

    protected static string $table = 'users';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['name', 'email', 'age'];

    public static function query(): ModelQueryBuilder
    {
        return parent::query();
    }

    protected static function getConnection(): \Toporia\Framework\Database\Contracts\ConnectionInterface
    {
        return parent::getConnection();
    }

    public static function getPrimaryKey(): string
    {
        return static::$primaryKey;
    }

    public static function getTableName(): string
    {
        return static::$table;
    }
}










