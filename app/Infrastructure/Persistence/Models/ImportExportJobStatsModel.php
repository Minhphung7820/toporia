<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Import/Export Job Statistics Model.
 *
 * Pre-computed statistics per user for import/export jobs.
 * Updated by database triggers on import_export_jobs table.
 *
 * This eliminates expensive COUNT queries when job table grows to millions of records.
 */
class ImportExportJobStatsModel extends Model
{
    protected static string $table = 'import_export_job_stats';

    protected static string $primaryKey = 'user_id';

    protected static bool $incrementing = false;

    protected static array $fillable = [
        'user_id',
        'total_jobs',
        'total_imports',
        'total_exports',
        'completed',
        'failed',
        'cancelled',
        'processing',
        'pending',
    ];

    protected static array $casts = [
        'user_id' => 'int',
        'total_jobs' => 'int',
        'total_imports' => 'int',
        'total_exports' => 'int',
        'completed' => 'int',
        'failed' => 'int',
        'cancelled' => 'int',
        'processing' => 'int',
        'pending' => 'int',
    ];

    /**
     * Get statistics for a user.
     *
     * @param int $userId
     * @return array{total_jobs: int, total_imports: int, total_exports: int, completed: int, failed: int, cancelled: int, processing: int, pending: int}
     */
    public static function getStatsForUser(int $userId): array
    {
        $stats = static::where('user_id', $userId)->first();

        if (!$stats) {
            return [
                'total_jobs' => 0,
                'total_imports' => 0,
                'total_exports' => 0,
                'completed' => 0,
                'failed' => 0,
                'cancelled' => 0,
                'processing' => 0,
                'pending' => 0,
            ];
        }

        return [
            'total_jobs' => (int) $stats->total_jobs,
            'total_imports' => (int) $stats->total_imports,
            'total_exports' => (int) $stats->total_exports,
            'completed' => (int) $stats->completed,
            'failed' => (int) $stats->failed,
            'cancelled' => (int) $stats->cancelled,
            'processing' => (int) $stats->processing,
            'pending' => (int) $stats->pending,
        ];
    }

    /**
     * Manually recalculate statistics for a user.
     *
     * Use this if triggers fail or for validation.
     * Note: This queries the main jobs table.
     *
     * @param int $userId
     */
    public static function recalculateForUser(int $userId): void
    {
        $connection = static::getConnection();
        $now = date('Y-m-d H:i:s');

        $stats = $connection->selectOne("
            SELECT
                COUNT(*) as total_jobs,
                SUM(CASE WHEN type = 'import' THEN 1 ELSE 0 END) as total_imports,
                SUM(CASE WHEN type = 'export' THEN 1 ELSE 0 END) as total_exports,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM import_export_jobs
            WHERE user_id = ?
        ", [$userId]);

        if ($stats) {
            $connection->affectingStatement("
                INSERT INTO import_export_job_stats (user_id, total_jobs, total_imports, total_exports, completed, failed, cancelled, processing, pending, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    total_jobs = VALUES(total_jobs),
                    total_imports = VALUES(total_imports),
                    total_exports = VALUES(total_exports),
                    completed = VALUES(completed),
                    failed = VALUES(failed),
                    cancelled = VALUES(cancelled),
                    processing = VALUES(processing),
                    pending = VALUES(pending),
                    updated_at = VALUES(updated_at)
            ", [
                $userId,
                (int) $stats['total_jobs'],
                (int) $stats['total_imports'],
                (int) $stats['total_exports'],
                (int) $stats['completed'],
                (int) $stats['failed'],
                (int) $stats['cancelled'],
                (int) $stats['processing'],
                (int) $stats['pending'],
                $now,
                $now,
            ]);
        }
    }

    /**
     * Recalculate statistics for all users.
     *
     * Use with caution on large datasets.
     */
    public static function recalculateAll(): void
    {
        $connection = static::getConnection();
        $now = date('Y-m-d H:i:s');

        // Truncate and repopulate
        $connection->affectingStatement("TRUNCATE TABLE import_export_job_stats");

        $connection->unprepared("
            INSERT INTO import_export_job_stats (user_id, total_jobs, total_imports, total_exports, completed, failed, cancelled, processing, pending, created_at, updated_at)
            SELECT
                user_id,
                COUNT(*) as total_jobs,
                SUM(CASE WHEN type = 'import' THEN 1 ELSE 0 END) as total_imports,
                SUM(CASE WHEN type = 'export' THEN 1 ELSE 0 END) as total_exports,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                '{$now}',
                '{$now}'
            FROM import_export_jobs
            GROUP BY user_id
        ");
    }
}
