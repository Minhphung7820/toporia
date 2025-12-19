<?php

declare(strict_types=1);

namespace App\Infrastructure\Imports;

use Toporia\Tabula\Imports\ToModelImport;
use App\Infrastructure\Persistence\Models\PostModel;

/**
 * PostsImport
 *
 * Import posts from CSV file using Tabula.
 * Optimized for large datasets (1M+ rows).
 *
 * CSV columns: title,slug,content,excerpt,author_id,category_id,is_published,is_featured,views,reading_time,published_at
 *
 * @example Sequential import:
 * $import = PostsImport::make();
 * Tabula::import($import, 'posts.csv');
 *
 * @example Parallel import (4 workers):
 * $import = PostsImport::parallel(4);
 * Tabula::import($import, 'posts.csv');
 */
final class PostsImport
{
    /**
     * Create sequential import (default).
     *
     * @return ToModelImport
     */
    public static function make(): ToModelImport
    {
        return self::createImport();
    }

    /**
     * Create parallel import with N workers.
     *
     * Uses Framework's Concurrency system for true parallelism.
     * Each worker processes every Nth line with its own DB connection.
     *
     * @param int $workers Number of parallel workers (2-16 recommended)
     * @param string $driver Concurrency driver (process, fork, sync)
     * @return ToModelImport
     */
    public static function parallel(int $workers = 4, string $driver = 'process'): ToModelImport
    {
        return self::createImport()->parallel($workers, $driver);
    }

    /**
     * Create base import configuration.
     *
     * @return ToModelImport
     */
    private static function createImport(): ToModelImport
    {
        return ToModelImport::make(PostModel::class)
            ->map(fn(array $row) => [
                'title' => $row['title'],
                'slug' => $row['slug'],
                'content' => $row['content'],
                'excerpt' => $row['excerpt'] ?? null,
                'author_id' => (int) $row['author_id'],
                'category_id' => (int) $row['category_id'],
                'is_published' => (int) (bool) ($row['is_published'] ?? 1),
                'is_featured' => (int) (bool) ($row['is_featured'] ?? 0),
                'views' => (int) ($row['views'] ?? 0),
                'reading_time' => (int) ($row['reading_time'] ?? 0),
                'published_at' => !empty($row['published_at']) ? $row['published_at'] : null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ])
            ->disableForeignKeyChecks()  // Disable FK checks during import
            ->chunk(10000)  // Read 10K rows at a time
            ->batch(2000);  // Insert 2K rows per batch (uses bulk insert)
    }
}
