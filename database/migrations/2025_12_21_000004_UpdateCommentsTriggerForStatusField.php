<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;
use Toporia\Framework\Support\Accessors\QueryBuilder;

/**
 * Update comments triggers to use status field instead of is_approved.
 *
 * This adds support for the 'rejected' status alongside 'pending' and 'approved'.
 */
class UpdateCommentsTriggerForStatusField extends Migration
{
    public function up(): void
    {
        $db = QueryBuilder::getConnection();

        // 1. Add comments_rejected stat key
        $db->affectingStatement(
            "INSERT IGNORE INTO dashboard_statistics (stat_key, stat_value, updated_at) VALUES (?, 0, ?)",
            ['comments_rejected', date('Y-m-d H:i:s')]
        );

        // 2. Drop old triggers
        $db->unprepared("DROP TRIGGER IF EXISTS trg_comments_after_insert");
        $db->unprepared("DROP TRIGGER IF EXISTS trg_comments_after_update");
        $db->unprepared("DROP TRIGGER IF EXISTS trg_comments_after_delete");

        // 3. Create new triggers using status field

        // After INSERT on comments
        $db->unprepared("
            CREATE TRIGGER trg_comments_after_insert
            AFTER INSERT ON comments
            FOR EACH ROW
            BEGIN
                UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'comments_total';

                IF NEW.status = 'approved' THEN
                    UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'comments_approved';
                ELSEIF NEW.status = 'rejected' THEN
                    UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'comments_rejected';
                ELSE
                    UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'comments_pending';
                END IF;
            END
        ");

        // After UPDATE on comments
        $db->unprepared("
            CREATE TRIGGER trg_comments_after_update
            AFTER UPDATE ON comments
            FOR EACH ROW
            BEGIN
                IF OLD.status != NEW.status OR OLD.status IS NULL THEN
                    -- Decrement old status count
                    IF OLD.status = 'approved' THEN
                        UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'comments_approved';
                    ELSEIF OLD.status = 'rejected' THEN
                        UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'comments_rejected';
                    ELSE
                        UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'comments_pending';
                    END IF;

                    -- Increment new status count
                    IF NEW.status = 'approved' THEN
                        UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'comments_approved';
                    ELSEIF NEW.status = 'rejected' THEN
                        UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'comments_rejected';
                    ELSE
                        UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'comments_pending';
                    END IF;
                END IF;
            END
        ");

        // After DELETE on comments
        $db->unprepared("
            CREATE TRIGGER trg_comments_after_delete
            AFTER DELETE ON comments
            FOR EACH ROW
            BEGIN
                UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'comments_total';

                IF OLD.status = 'approved' THEN
                    UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'comments_approved';
                ELSEIF OLD.status = 'rejected' THEN
                    UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'comments_rejected';
                ELSE
                    UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'comments_pending';
                END IF;
            END
        ");

        // 4. Recalculate current comment statistics using status field
        $commentStats = $db->selectOne("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM comments
        ");

        if ($commentStats) {
            $now = date('Y-m-d H:i:s');
            $db->affectingStatement(
                "UPDATE dashboard_statistics SET stat_value = ?, updated_at = ? WHERE stat_key = ?",
                [(int) $commentStats['total'], $now, 'comments_total']
            );
            $db->affectingStatement(
                "UPDATE dashboard_statistics SET stat_value = ?, updated_at = ? WHERE stat_key = ?",
                [(int) $commentStats['pending'], $now, 'comments_pending']
            );
            $db->affectingStatement(
                "UPDATE dashboard_statistics SET stat_value = ?, updated_at = ? WHERE stat_key = ?",
                [(int) $commentStats['approved'], $now, 'comments_approved']
            );
            $db->affectingStatement(
                "UPDATE dashboard_statistics SET stat_value = ?, updated_at = ? WHERE stat_key = ?",
                [(int) $commentStats['rejected'], $now, 'comments_rejected']
            );
        }
    }

    public function down(): void
    {
        $db = QueryBuilder::getConnection();

        // Drop new triggers
        $db->unprepared("DROP TRIGGER IF EXISTS trg_comments_after_insert");
        $db->unprepared("DROP TRIGGER IF EXISTS trg_comments_after_update");
        $db->unprepared("DROP TRIGGER IF EXISTS trg_comments_after_delete");

        // Restore old triggers using is_approved
        $db->unprepared("
            CREATE TRIGGER trg_comments_after_insert
            AFTER INSERT ON comments
            FOR EACH ROW
            BEGIN
                UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'comments_total';

                IF NEW.is_approved = 1 THEN
                    UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'comments_approved';
                ELSE
                    UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'comments_pending';
                END IF;
            END
        ");

        $db->unprepared("
            CREATE TRIGGER trg_comments_after_update
            AFTER UPDATE ON comments
            FOR EACH ROW
            BEGIN
                IF OLD.is_approved != NEW.is_approved THEN
                    IF NEW.is_approved = 1 THEN
                        UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'comments_pending';
                        UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'comments_approved';
                    ELSE
                        UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'comments_pending';
                        UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'comments_approved';
                    END IF;
                END IF;
            END
        ");

        $db->unprepared("
            CREATE TRIGGER trg_comments_after_delete
            AFTER DELETE ON comments
            FOR EACH ROW
            BEGIN
                UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'comments_total';

                IF OLD.is_approved = 1 THEN
                    UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'comments_approved';
                ELSE
                    UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'comments_pending';
                END IF;
            END
        ");

        // Remove comments_rejected stat
        $db->affectingStatement("DELETE FROM dashboard_statistics WHERE stat_key = ?", ['comments_rejected']);
    }
}
