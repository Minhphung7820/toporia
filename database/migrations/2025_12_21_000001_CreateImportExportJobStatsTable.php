<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;
use Toporia\Framework\Support\Accessors\QueryBuilder;

/**
 * Create import_export_job_stats table for pre-computed statistics per user.
 *
 * CRITICAL OPTIMIZATION for millions of records:
 * Instead of running expensive COUNT queries on the jobs table,
 * we maintain pre-computed statistics updated by database triggers.
 *
 * This reduces stats query time from seconds to < 1ms.
 *
 * Statistics are maintained in real-time via triggers on:
 * - import_export_jobs (insert, update, delete)
 */
class CreateImportExportJobStatsTable extends Migration
{
    public function up(): void
    {
        // Create stats table
        $this->schema->create('import_export_job_stats', function ($table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->unsignedInteger('total_jobs')->default(0);
            $table->unsignedInteger('total_imports')->default(0);
            $table->unsignedInteger('total_exports')->default(0);
            $table->unsignedInteger('completed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('cancelled')->default(0);
            $table->unsignedInteger('processing')->default(0);
            $table->unsignedInteger('pending')->default(0);
            $table->timestamps();

            // Foreign key
            $table->foreign('user_id')
                ->references('users', 'id')
                ->onDelete('cascade');
        });

        // Initialize with current counts
        $this->initializeStatistics();

        // Create triggers for automatic updates
        $this->createTriggers();
    }

    public function down(): void
    {
        $this->dropTriggers();
        $this->schema->dropIfExists('import_export_job_stats');
    }

    /**
     * Initialize statistics with current values from existing jobs.
     */
    private function initializeStatistics(): void
    {
        $db = QueryBuilder::getConnection();
        $now = date('Y-m-d H:i:s');

        // Populate initial data from existing jobs grouped by user
        $db->unprepared("
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

    /**
     * Create database triggers for automatic statistics updates.
     */
    private function createTriggers(): void
    {
        $db = QueryBuilder::getConnection();

        // =====================================================
        // IMPORT_EXPORT_JOBS TRIGGERS
        // =====================================================

        // After INSERT on import_export_jobs
        $db->unprepared("
            CREATE TRIGGER trg_import_export_jobs_after_insert
            AFTER INSERT ON import_export_jobs
            FOR EACH ROW
            BEGIN
                INSERT INTO import_export_job_stats (user_id, total_jobs, total_imports, total_exports, completed, failed, cancelled, processing, pending, created_at, updated_at)
                VALUES (
                    NEW.user_id,
                    1,
                    IF(NEW.type = 'import', 1, 0),
                    IF(NEW.type = 'export', 1, 0),
                    IF(NEW.status = 'completed', 1, 0),
                    IF(NEW.status = 'failed', 1, 0),
                    IF(NEW.status = 'cancelled', 1, 0),
                    IF(NEW.status = 'processing', 1, 0),
                    IF(NEW.status = 'pending', 1, 0),
                    NOW(),
                    NOW()
                )
                ON DUPLICATE KEY UPDATE
                    total_jobs = total_jobs + 1,
                    total_imports = total_imports + IF(NEW.type = 'import', 1, 0),
                    total_exports = total_exports + IF(NEW.type = 'export', 1, 0),
                    completed = completed + IF(NEW.status = 'completed', 1, 0),
                    failed = failed + IF(NEW.status = 'failed', 1, 0),
                    cancelled = cancelled + IF(NEW.status = 'cancelled', 1, 0),
                    processing = processing + IF(NEW.status = 'processing', 1, 0),
                    pending = pending + IF(NEW.status = 'pending', 1, 0),
                    updated_at = NOW();
            END
        ");

        // After UPDATE on import_export_jobs (status changes)
        $db->unprepared("
            CREATE TRIGGER trg_import_export_jobs_after_update
            AFTER UPDATE ON import_export_jobs
            FOR EACH ROW
            BEGIN
                IF OLD.status != NEW.status THEN
                    UPDATE import_export_job_stats
                    SET
                        completed = completed - IF(OLD.status = 'completed', 1, 0) + IF(NEW.status = 'completed', 1, 0),
                        failed = failed - IF(OLD.status = 'failed', 1, 0) + IF(NEW.status = 'failed', 1, 0),
                        cancelled = cancelled - IF(OLD.status = 'cancelled', 1, 0) + IF(NEW.status = 'cancelled', 1, 0),
                        processing = processing - IF(OLD.status = 'processing', 1, 0) + IF(NEW.status = 'processing', 1, 0),
                        pending = pending - IF(OLD.status = 'pending', 1, 0) + IF(NEW.status = 'pending', 1, 0),
                        updated_at = NOW()
                    WHERE user_id = NEW.user_id;
                END IF;
            END
        ");

        // After DELETE on import_export_jobs
        $db->unprepared("
            CREATE TRIGGER trg_import_export_jobs_after_delete
            AFTER DELETE ON import_export_jobs
            FOR EACH ROW
            BEGIN
                UPDATE import_export_job_stats
                SET
                    total_jobs = total_jobs - 1,
                    total_imports = total_imports - IF(OLD.type = 'import', 1, 0),
                    total_exports = total_exports - IF(OLD.type = 'export', 1, 0),
                    completed = completed - IF(OLD.status = 'completed', 1, 0),
                    failed = failed - IF(OLD.status = 'failed', 1, 0),
                    cancelled = cancelled - IF(OLD.status = 'cancelled', 1, 0),
                    processing = processing - IF(OLD.status = 'processing', 1, 0),
                    pending = pending - IF(OLD.status = 'pending', 1, 0),
                    updated_at = NOW()
                WHERE user_id = OLD.user_id;
            END
        ");
    }

    /**
     * Drop all triggers.
     */
    private function dropTriggers(): void
    {
        $db = QueryBuilder::getConnection();

        $triggers = [
            'trg_import_export_jobs_after_insert',
            'trg_import_export_jobs_after_update',
            'trg_import_export_jobs_after_delete',
        ];

        foreach ($triggers as $trigger) {
            $db->unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
}
