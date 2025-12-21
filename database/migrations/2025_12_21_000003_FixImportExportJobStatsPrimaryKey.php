<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;
use Toporia\Framework\Support\Accessors\QueryBuilder;

/**
 * Fix import_export_job_stats table primary key.
 *
 * The original migration didn't create the PRIMARY KEY correctly,
 * causing duplicate rows for the same user_id.
 * This migration:
 * 1. Removes duplicate rows (keeping the one with correct stats)
 * 2. Adds the PRIMARY KEY constraint on user_id
 */
class FixImportExportJobStatsPrimaryKey extends Migration
{
    public function up(): void
    {
        $db = QueryBuilder::getConnection();

        // First, merge duplicates by keeping max values per user_id
        // Create a temp table with correct aggregated data
        $db->unprepared("
            CREATE TEMPORARY TABLE temp_job_stats AS
            SELECT
                user_id,
                MAX(total_jobs) as total_jobs,
                MAX(total_imports) as total_imports,
                MAX(total_exports) as total_exports,
                MAX(completed) as completed,
                MAX(failed) as failed,
                MAX(cancelled) as cancelled,
                MAX(processing) as processing,
                MAX(pending) as pending,
                MIN(created_at) as created_at,
                MAX(updated_at) as updated_at
            FROM import_export_job_stats
            GROUP BY user_id
        ");

        // Drop foreign key first (required before modifying the index)
        $db->unprepared("ALTER TABLE import_export_job_stats DROP FOREIGN KEY import_export_job_stats_ibfk_1");

        // Delete all rows from the stats table
        $db->unprepared("DELETE FROM import_export_job_stats");

        // Re-insert deduplicated data
        $db->unprepared("
            INSERT INTO import_export_job_stats
            SELECT * FROM temp_job_stats
        ");

        // Drop temporary table
        $db->unprepared("DROP TEMPORARY TABLE temp_job_stats");

        // Drop the existing index on user_id
        $db->unprepared("ALTER TABLE import_export_job_stats DROP INDEX user_id");

        // Add PRIMARY KEY
        $db->unprepared("ALTER TABLE import_export_job_stats ADD PRIMARY KEY (user_id)");

        // Re-add foreign key constraint
        $db->unprepared("
            ALTER TABLE import_export_job_stats
            ADD CONSTRAINT import_export_job_stats_user_id_foreign
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ");
    }

    public function down(): void
    {
        $db = QueryBuilder::getConnection();

        // Drop foreign key
        $db->unprepared("ALTER TABLE import_export_job_stats DROP FOREIGN KEY import_export_job_stats_user_id_foreign");

        // Remove PRIMARY KEY and restore regular index
        $db->unprepared("ALTER TABLE import_export_job_stats DROP PRIMARY KEY");
        $db->unprepared("ALTER TABLE import_export_job_stats ADD INDEX user_id (user_id)");

        // Re-add original foreign key
        $db->unprepared("
            ALTER TABLE import_export_job_stats
            ADD CONSTRAINT import_export_job_stats_ibfk_1
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ");
    }
}
