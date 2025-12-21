<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;
use Toporia\Framework\Support\Accessors\QueryBuilder;

/**
 * Add optimized indexes for job history cursor pagination.
 *
 * Cursor pagination queries need composite indexes for efficient:
 * - ORDER BY created_at DESC, id DESC
 * - WHERE user_id = ? AND type = ? AND status = ?
 * - WHERE created_at < ? OR (created_at = ? AND id < ?)
 */
class AddIndexesForJobHistoryPagination extends Migration
{
    public function up(): void
    {
        $db = QueryBuilder::getConnection();

        // Composite index for cursor pagination with all filters
        // Covers: user_id filter + type filter + status filter + created_at/id ordering
        $db->unprepared("
            CREATE INDEX idx_user_created_id
            ON import_export_jobs (user_id, created_at DESC, id DESC)
        ");

        // Composite index for user + type filter with pagination
        $db->unprepared("
            CREATE INDEX idx_user_type_created_id
            ON import_export_jobs (user_id, type, created_at DESC, id DESC)
        ");

        // Composite index for user + status filter with pagination
        $db->unprepared("
            CREATE INDEX idx_user_status_created_id
            ON import_export_jobs (user_id, status, created_at DESC, id DESC)
        ");

        // Full composite index for all filters (most selective)
        $db->unprepared("
            CREATE INDEX idx_user_type_status_created_id
            ON import_export_jobs (user_id, type, status, created_at DESC, id DESC)
        ");
    }

    public function down(): void
    {
        $db = QueryBuilder::getConnection();

        $db->unprepared("DROP INDEX IF EXISTS idx_user_created_id ON import_export_jobs");
        $db->unprepared("DROP INDEX IF EXISTS idx_user_type_created_id ON import_export_jobs");
        $db->unprepared("DROP INDEX IF EXISTS idx_user_status_created_id ON import_export_jobs");
        $db->unprepared("DROP INDEX IF EXISTS idx_user_type_status_created_id ON import_export_jobs");
    }
}
