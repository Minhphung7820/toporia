<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Category Post Count Model.
 *
 * Pre-computed post counts per category for performance optimization.
 * Updated by database triggers on posts table changes.
 *
 * This eliminates expensive GROUP BY COUNT(*) on 8M+ posts table.
 */
class CategoryPostCountModel extends Model
{
    protected static string $table = 'category_post_counts';

    protected static string $primaryKey = 'category_id';

    /**
     * Disable auto-incrementing since we use category_id as PK.
     */
    public bool $incrementing = false;

    protected static array $fillable = [
        'category_id',
        'published_count',
        'total_count',
    ];

    protected static array $casts = [
        'category_id' => 'int',
        'published_count' => 'int',
        'total_count' => 'int',
    ];

    /**
     * Get published count for a category.
     */
    public static function getPublishedCount(int $categoryId): int
    {
        $result = static::find($categoryId);

        return $result ? (int) $result->published_count : 0;
    }

    /**
     * Get all published counts as category_id => count map.
     *
     * @return array<int, int>
     */
    public static function getAllPublishedCounts(): array
    {
        $results = static::query()->get();

        $counts = [];
        foreach ($results as $row) {
            $counts[(int) $row->category_id] = (int) $row->published_count;
        }

        return $counts;
    }

    /**
     * Get all counts with both published and total.
     *
     * @return array<int, array{published: int, total: int}>
     */
    public static function getAllCounts(): array
    {
        $results = static::query()->get();

        $counts = [];
        foreach ($results as $row) {
            $counts[(int) $row->category_id] = [
                'published' => (int) $row->published_count,
                'total' => (int) $row->total_count,
            ];
        }

        return $counts;
    }

    /**
     * Manually recalculate all category counts.
     *
     * Use this if triggers fail or for periodic validation.
     * WARNING: This is expensive on large tables.
     */
    public static function recalculateAll(): void
    {
        $connection = static::getConnection();

        // Step 1: Insert records for all categories that don't exist yet
        $connection->unprepared("
            INSERT IGNORE INTO category_post_counts (category_id, published_count, total_count, updated_at)
            SELECT id, 0, 0, NOW()
            FROM categories
        ");

        // Step 2: Update published counts and total counts
        $connection->unprepared("
            UPDATE category_post_counts cpc
            SET
                published_count = (
                    SELECT COUNT(*)
                    FROM posts p
                    WHERE p.category_id = cpc.category_id
                      AND p.is_published = 1
                      AND (p.published_at IS NULL OR p.published_at <= NOW())
                ),
                total_count = (
                    SELECT COUNT(*)
                    FROM posts p
                    WHERE p.category_id = cpc.category_id
                ),
                updated_at = NOW()
        ");
    }

    /**
     * Recalculate count for a single category.
     */
    public static function recalculateForCategory(int $categoryId): void
    {
        $connection = static::getConnection();

        $counts = $connection->selectOne("
            SELECT
                SUM(CASE WHEN is_published = 1 AND (published_at IS NULL OR published_at <= NOW()) THEN 1 ELSE 0 END) as published_count,
                COUNT(*) as total_count
            FROM posts
            WHERE category_id = ?
        ", [$categoryId]);

        static::where('category_id', $categoryId)->update([
            'published_count' => (int) ($counts['published_count'] ?? 0),
            'total_count' => (int) ($counts['total_count'] ?? 0),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
