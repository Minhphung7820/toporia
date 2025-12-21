<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Dashboard Statistics Model.
 *
 * Pre-computed statistics for dashboard performance optimization.
 * Updated by database triggers on posts, comments, feedback tables.
 *
 * This eliminates expensive COUNT/SUM queries on 8M+ records tables.
 */
class DashboardStatisticsModel extends Model
{
    protected static string $table = 'dashboard_statistics';

    protected static string $primaryKey = 'id';

    protected static array $fillable = [
        'stat_key',
        'stat_value',
    ];

    protected static array $casts = [
        'stat_value' => 'int',
    ];

    /**
     * Get a statistic value by key.
     */
    public static function getValue(string $key): int
    {
        $result = static::where('stat_key', $key)->first();

        return $result ? (int) $result->stat_value : 0;
    }

    /**
     * Get multiple statistics at once (single query).
     *
     * @param array<string> $keys
     * @return array<string, int>
     */
    public static function getValues(array $keys): array
    {
        $results = static::whereIn('stat_key', $keys)->get();

        $values = [];
        foreach ($keys as $key) {
            $values[$key] = 0;
        }

        foreach ($results as $row) {
            $values[$row->stat_key] = (int) $row->stat_value;
        }

        return $values;
    }

    /**
     * Get all post statistics.
     *
     * @return array{total: int, published: int, draft: int, scheduled: int, total_views: int}
     */
    public static function getPostStats(): array
    {
        $stats = static::getValues([
            'posts_total',
            'posts_published',
            'posts_draft',
            'posts_scheduled',
            'posts_total_views',
        ]);

        return [
            'total' => $stats['posts_total'],
            'published' => $stats['posts_published'],
            'draft' => $stats['posts_draft'],
            'scheduled' => $stats['posts_scheduled'],
            'total_views' => $stats['posts_total_views'],
        ];
    }

    /**
     * Get all comment statistics.
     *
     * @return array{total: int, pending: int, approved: int, rejected: int}
     */
    public static function getCommentStats(): array
    {
        $stats = static::getValues([
            'comments_total',
            'comments_pending',
            'comments_approved',
            'comments_rejected',
        ]);

        return [
            'total' => $stats['comments_total'],
            'pending' => $stats['comments_pending'],
            'approved' => $stats['comments_approved'],
            'rejected' => $stats['comments_rejected'],
        ];
    }

    /**
     * Get all feedback statistics.
     *
     * @return array{total: int, pending: int}
     */
    public static function getFeedbackStats(): array
    {
        $stats = static::getValues([
            'feedback_total',
            'feedback_pending',
        ]);

        return [
            'total' => $stats['feedback_total'],
            'pending' => $stats['feedback_pending'],
        ];
    }

    /**
     * Get all dashboard statistics in a single query.
     *
     * @return array{posts: array, comments: array, feedback: array}
     */
    public static function getAllStats(): array
    {
        $allStats = static::getValues([
            'posts_total',
            'posts_published',
            'posts_draft',
            'posts_scheduled',
            'posts_total_views',
            'comments_total',
            'comments_pending',
            'comments_approved',
            'comments_rejected',
            'feedback_total',
            'feedback_pending',
        ]);

        return [
            'posts' => [
                'total' => $allStats['posts_total'],
                'published' => $allStats['posts_published'],
                'draft' => $allStats['posts_draft'],
                'scheduled' => $allStats['posts_scheduled'],
                'total_views' => $allStats['posts_total_views'],
            ],
            'comments' => [
                'total' => $allStats['comments_total'],
                'pending' => $allStats['comments_pending'],
                'approved' => $allStats['comments_approved'],
                'rejected' => $allStats['comments_rejected'],
            ],
            'feedback' => [
                'total' => $allStats['feedback_total'],
                'pending' => $allStats['feedback_pending'],
            ],
        ];
    }

    /**
     * Manually recalculate all statistics.
     *
     * Use this if triggers fail or for periodic validation.
     * Note: This is an expensive operation on large tables.
     */
    public static function recalculateAll(): void
    {
        $connection = static::getConnection();
        $now = date('Y-m-d H:i:s');

        // Recalculate post stats
        $postStats = $connection->selectOne("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) as published,
                SUM(CASE WHEN is_published = 0 AND scheduled_at IS NULL THEN 1 ELSE 0 END) as draft,
                SUM(CASE WHEN is_published = 0 AND scheduled_at IS NOT NULL THEN 1 ELSE 0 END) as scheduled,
                COALESCE(SUM(views), 0) as total_views
            FROM posts
        ");

        if ($postStats) {
            static::updateStat('posts_total', (int) $postStats['total'], $now);
            static::updateStat('posts_published', (int) $postStats['published'], $now);
            static::updateStat('posts_draft', (int) $postStats['draft'], $now);
            static::updateStat('posts_scheduled', (int) $postStats['scheduled'], $now);
            static::updateStat('posts_total_views', (int) $postStats['total_views'], $now);
        }

        // Recalculate comment stats using status field
        $commentStats = $connection->selectOne("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM comments
        ");

        if ($commentStats) {
            static::updateStat('comments_total', (int) $commentStats['total'], $now);
            static::updateStat('comments_pending', (int) $commentStats['pending'], $now);
            static::updateStat('comments_approved', (int) $commentStats['approved'], $now);
            static::updateStat('comments_rejected', (int) $commentStats['rejected'], $now);
        }

        // Recalculate feedback stats
        $feedbackStats = $connection->selectOne("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM feedback
        ");

        if ($feedbackStats) {
            static::updateStat('feedback_total', (int) $feedbackStats['total'], $now);
            static::updateStat('feedback_pending', (int) $feedbackStats['pending'], $now);
        }
    }

    /**
     * Update a single statistic.
     */
    private static function updateStat(string $key, int $value, string $timestamp): void
    {
        static::where('stat_key', $key)->update([
            'stat_value' => $value,
            'updated_at' => $timestamp,
        ]);
    }
}
