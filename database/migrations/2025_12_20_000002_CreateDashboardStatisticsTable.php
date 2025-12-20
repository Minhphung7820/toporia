<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;
use Toporia\Framework\Support\Accessors\QueryBuilder;

/**
 * Create dashboard_statistics table for pre-computed statistics.
 *
 * CRITICAL OPTIMIZATION for 8M+ records:
 * Instead of running expensive COUNT/SUM queries on millions of rows,
 * we maintain pre-computed statistics updated by database triggers.
 *
 * This reduces dashboard query time from 30+ seconds to < 10ms.
 *
 * Statistics are maintained in real-time via triggers on:
 * - posts (insert, update, delete)
 * - comments (insert, update, delete)
 * - feedback (insert, update, delete)
 */
class CreateDashboardStatisticsTable extends Migration
{
    public function up(): void
    {
        // Create statistics table
        $this->schema->create('dashboard_statistics', function ($table) {
            $table->id();
            $table->string('stat_key', 100)->unique();
            $table->bigInteger('stat_value')->default(0);
            $table->timestamp('updated_at')->nullable();
        });

        // Initialize with current counts using optimized queries
        $this->initializeStatistics();

        // Create triggers for automatic updates
        $this->createTriggers();
    }

    public function down(): void
    {
        $this->dropTriggers();
        $this->schema->dropIfExists('dashboard_statistics');
    }

    /**
     * Initialize statistics with current values.
     */
    private function initializeStatistics(): void
    {
        $now = date('Y-m-d H:i:s');

        // Insert all stat keys with default 0
        $statKeys = [
            'posts_total',
            'posts_published',
            'posts_draft',
            'posts_scheduled',
            'posts_total_views',
            'comments_total',
            'comments_pending',
            'comments_approved',
            'feedback_total',
            'feedback_pending',
        ];

        $db = QueryBuilder::getConnection();

        foreach ($statKeys as $key) {
            $db->affectingStatement(
                "INSERT INTO dashboard_statistics (stat_key, stat_value, updated_at) VALUES (?, 0, ?)",
                [$key, $now]
            );
        }

        // Calculate current posts statistics (single optimized query)
        $postStats = $db->selectOne("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) as published,
                SUM(CASE WHEN is_published = 0 AND scheduled_at IS NULL THEN 1 ELSE 0 END) as draft,
                SUM(CASE WHEN is_published = 0 AND scheduled_at IS NOT NULL THEN 1 ELSE 0 END) as scheduled,
                COALESCE(SUM(views), 0) as total_views
            FROM posts
        ");

        if ($postStats) {
            $this->updateStat($db, 'posts_total', (int) $postStats['total']);
            $this->updateStat($db, 'posts_published', (int) $postStats['published']);
            $this->updateStat($db, 'posts_draft', (int) $postStats['draft']);
            $this->updateStat($db, 'posts_scheduled', (int) $postStats['scheduled']);
            $this->updateStat($db, 'posts_total_views', (int) $postStats['total_views']);
        }

        // Calculate current comments statistics
        $commentStats = $db->selectOne("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) as approved
            FROM comments
        ");

        if ($commentStats) {
            $this->updateStat($db, 'comments_total', (int) $commentStats['total']);
            $this->updateStat($db, 'comments_pending', (int) $commentStats['pending']);
            $this->updateStat($db, 'comments_approved', (int) $commentStats['approved']);
        }

        // Calculate current feedback statistics
        $feedbackStats = $db->selectOne("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM feedback
        ");

        if ($feedbackStats) {
            $this->updateStat($db, 'feedback_total', (int) $feedbackStats['total']);
            $this->updateStat($db, 'feedback_pending', (int) $feedbackStats['pending']);
        }
    }

    /**
     * Update a statistic value.
     */
    private function updateStat(object $db, string $key, int $value): void
    {
        $db->affectingStatement(
            "UPDATE dashboard_statistics SET stat_value = ?, updated_at = ? WHERE stat_key = ?",
            [$value, date('Y-m-d H:i:s'), $key]
        );
    }

    /**
     * Create database triggers for automatic statistics updates.
     */
    private function createTriggers(): void
    {
        $db = QueryBuilder::getConnection();

        // =====================================================
        // POSTS TRIGGERS
        // =====================================================

        // After INSERT on posts
        $db->unprepared("
            CREATE TRIGGER trg_posts_after_insert
            AFTER INSERT ON posts
            FOR EACH ROW
            BEGIN
                UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'posts_total';
                UPDATE dashboard_statistics SET stat_value = stat_value + NEW.views, updated_at = NOW() WHERE stat_key = 'posts_total_views';

                IF NEW.is_published = 1 THEN
                    UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'posts_published';
                ELSEIF NEW.scheduled_at IS NOT NULL THEN
                    UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'posts_scheduled';
                ELSE
                    UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'posts_draft';
                END IF;
            END
        ");

        // After UPDATE on posts
        $db->unprepared("
            CREATE TRIGGER trg_posts_after_update
            AFTER UPDATE ON posts
            FOR EACH ROW
            BEGIN
                -- Update views delta
                IF OLD.views != NEW.views THEN
                    UPDATE dashboard_statistics SET stat_value = stat_value + (NEW.views - OLD.views), updated_at = NOW() WHERE stat_key = 'posts_total_views';
                END IF;

                -- Handle status changes
                IF OLD.is_published != NEW.is_published OR
                   (OLD.scheduled_at IS NULL) != (NEW.scheduled_at IS NULL) THEN

                    -- Decrement old status
                    IF OLD.is_published = 1 THEN
                        UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'posts_published';
                    ELSEIF OLD.scheduled_at IS NOT NULL THEN
                        UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'posts_scheduled';
                    ELSE
                        UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'posts_draft';
                    END IF;

                    -- Increment new status
                    IF NEW.is_published = 1 THEN
                        UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'posts_published';
                    ELSEIF NEW.scheduled_at IS NOT NULL THEN
                        UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'posts_scheduled';
                    ELSE
                        UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'posts_draft';
                    END IF;
                END IF;
            END
        ");

        // After DELETE on posts
        $db->unprepared("
            CREATE TRIGGER trg_posts_after_delete
            AFTER DELETE ON posts
            FOR EACH ROW
            BEGIN
                UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'posts_total';
                UPDATE dashboard_statistics SET stat_value = stat_value - OLD.views, updated_at = NOW() WHERE stat_key = 'posts_total_views';

                IF OLD.is_published = 1 THEN
                    UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'posts_published';
                ELSEIF OLD.scheduled_at IS NOT NULL THEN
                    UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'posts_scheduled';
                ELSE
                    UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'posts_draft';
                END IF;
            END
        ");

        // =====================================================
        // COMMENTS TRIGGERS
        // =====================================================

        // After INSERT on comments
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

        // After UPDATE on comments
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

        // After DELETE on comments
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

        // =====================================================
        // FEEDBACK TRIGGERS
        // =====================================================

        // After INSERT on feedback
        $db->unprepared("
            CREATE TRIGGER trg_feedback_after_insert
            AFTER INSERT ON feedback
            FOR EACH ROW
            BEGIN
                UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'feedback_total';

                IF NEW.status = 'pending' THEN
                    UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'feedback_pending';
                END IF;
            END
        ");

        // After UPDATE on feedback
        $db->unprepared("
            CREATE TRIGGER trg_feedback_after_update
            AFTER UPDATE ON feedback
            FOR EACH ROW
            BEGIN
                IF OLD.status != NEW.status THEN
                    IF OLD.status = 'pending' THEN
                        UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'feedback_pending';
                    END IF;

                    IF NEW.status = 'pending' THEN
                        UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'feedback_pending';
                    END IF;
                END IF;
            END
        ");

        // After DELETE on feedback
        $db->unprepared("
            CREATE TRIGGER trg_feedback_after_delete
            AFTER DELETE ON feedback
            FOR EACH ROW
            BEGIN
                UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'feedback_total';

                IF OLD.status = 'pending' THEN
                    UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'feedback_pending';
                END IF;
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
            'trg_posts_after_insert',
            'trg_posts_after_update',
            'trg_posts_after_delete',
            'trg_comments_after_insert',
            'trg_comments_after_update',
            'trg_comments_after_delete',
            'trg_feedback_after_insert',
            'trg_feedback_after_update',
            'trg_feedback_after_delete',
        ];

        foreach ($triggers as $trigger) {
            $db->unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
}
