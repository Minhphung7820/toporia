<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Concerns;

use Toporia\Framework\Database\ORM\ModelCollection;
use Generator;


/**
 * Trait HasChunking
 *
 * Trait providing reusable functionality for HasChunking in the Concerns
 * layer of the Toporia Framework.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Concerns
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
trait HasChunking
{
    /**
     * Chunk query results into smaller batches.
     *
     * Processes records in chunks to avoid loading all into memory.
     *
     * Performance: O(n/chunkSize) queries
     * Memory: O(chunkSize) - Only one chunk in memory at a time
     *
     * @param int $chunkSize Number of records per chunk
     * @param callable|null $callback Optional callback to process each chunk
     * @return Generator<ModelCollection<static>> Generator of chunks
     *
     * @example
     * ```php
     * // Process in chunks of 100
     * foreach (UserModel::chunk(100) as $chunk) {
     *     foreach ($chunk as $user) {
     *         // Process user
     *     }
     * }
     *
     * // With callback
     * UserModel::chunk(100, function ($chunk) {
     *     // Process chunk
     * });
     * ```
     */
    public static function chunk(int $chunkSize, ?callable $callback = null): Generator
    {
        $offset = 0;

        while (true) {
            $chunk = static::query()
                ->limit($chunkSize)
                ->offset($offset)
                ->get();

            if ($chunk->isEmpty()) {
                break;
            }

            if ($callback !== null) {
                $callback($chunk);
            } else {
                yield $chunk;
            }

            // If chunk is smaller than chunkSize, we're done
            if ($chunk->count() < $chunkSize) {
                break;
            }

            $offset += $chunkSize;

            // Force garbage collection to free memory
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
    }

    /**
     * Chunk query results using cursor-based pagination (more efficient).
     *
     * Uses WHERE id > lastId instead of OFFSET for better performance.
     * Requires ordered by primary key.
     *
     * Performance: O(n/chunkSize) queries, but faster than OFFSET-based
     * Memory: O(chunkSize) - Only one chunk in memory at a time
     *
     * @param int $chunkSize Number of records per chunk
     * @param callable|null $callback Optional callback to process each chunk
     * @return Generator<ModelCollection<static>> Generator of chunks
     *
     * @example
     * ```php
     * // More efficient for large datasets
     * foreach (UserModel::chunkById(100) as $chunk) {
     *     foreach ($chunk as $user) {
     *         // Process user
     *     }
     * }
     * ```
     */
    public static function chunkById(int $chunkSize, ?callable $callback = null): Generator
    {
        $primaryKey = static::getPrimaryKey();
        $lastId = 0;

        while (true) {
            $chunk = static::query()
                ->where($primaryKey, '>', $lastId)
                ->orderBy($primaryKey, 'ASC')
                ->limit($chunkSize)
                ->get();

            if ($chunk->isEmpty()) {
                break;
            }

            if ($callback !== null) {
                $callback($chunk);
            } else {
                yield $chunk;
            }

            // Get last ID from chunk
            $lastModel = $chunk->last();
            $lastId = $lastModel->getKey();

            // If chunk is smaller than chunkSize, we're done
            if ($chunk->count() < $chunkSize) {
                break;
            }

            // Force garbage collection to free memory
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
    }

    /**
     * Process query results in chunks with lazy evaluation.
     *
     * Returns a Generator that yields one model at a time.
     * Most memory-efficient option for very large datasets.
     *
     * Performance: O(n) queries (one per record, but memory efficient)
     * Memory: O(1) - Only one record in memory at a time
     *
     * @return Generator<static> Generator of models
     *
     * @example
     * ```php
     * // Most memory efficient
     * foreach (UserModel::lazy() as $user) {
     *     // Process one user at a time
     * }
     * ```
     */
    public static function lazy(): Generator
    {
        $chunkSize = 1000; // Process in chunks internally

        foreach (static::chunk($chunkSize) as $chunk) {
            foreach ($chunk as $model) {
                yield $model;
            }
        }
    }

    /**
     * Process query results in chunks by ID with lazy evaluation.
     *
     * Most efficient for large datasets with ordered primary keys.
     *
     * @return Generator<static> Generator of models
     */
    public static function lazyById(): Generator
    {
        $chunkSize = 1000; // Process in chunks internally

        foreach (static::chunkById($chunkSize) as $chunk) {
            foreach ($chunk as $model) {
                yield $model;
            }
        }
    }

    /**
     * Get the primary key column name.
     *
     * @return string
     */
    abstract public static function getPrimaryKey(): string;

    /**
     * Get a query builder instance.
     *
     * @return \Toporia\Framework\Database\Query\QueryBuilder
     */
    abstract public static function query(): \Toporia\Framework\Database\Query\QueryBuilder;

    /**
     * Get the primary key value.
     *
     * @return mixed
     */
    abstract public function getKey(): mixed;
}
