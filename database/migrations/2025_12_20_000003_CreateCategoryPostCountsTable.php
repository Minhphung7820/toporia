<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;
use Toporia\Framework\Support\Accessors\QueryBuilder;

/**
 * Create category_post_counts table for pre-computed category post counts.
 *
 * CRITICAL OPTIMIZATION for 8M+ posts:
 * Instead of running GROUP BY COUNT(*) on millions of rows,
 * we maintain pre-computed counts updated by database triggers.
 *
 * This reduces /api/blog/categories/tree-with-counts from 30+ seconds to < 10ms.
 *
 * Triggers automatically update counts when:
 * - Post is created (with category_id and is_published = 1)
 * - Post is updated (category_id or is_published changes)
 * - Post is deleted
 */
class CreateCategoryPostCountsTable extends Migration
{
    public function up(): void
    {
        // Create pre-computed counts table
        $this->schema->create('category_post_counts', function ($table) {
            $table->unsignedBigInteger('category_id')->primary();
            $table->unsignedInteger('published_count')->default(0);
            $table->unsignedInteger('total_count')->default(0);
            $table->timestamp('updated_at')->nullable();

            $table->foreign('category_id')
                ->references('categories', 'id')
                ->onDelete('cascade');
        });

        // Initialize with current counts
        $this->initializeCounts();

        // Create triggers
        $this->createTriggers();
    }

    public function down(): void
    {
        $this->dropTriggers();
        $this->schema->dropIfExists('category_post_counts');
    }

    /**
     * Initialize counts with current values.
     */
    private function initializeCounts(): void
    {
        $db = QueryBuilder::getConnection();

        // Insert all categories with 0 counts first
        $db->unprepared("
            INSERT INTO category_post_counts (category_id, published_count, total_count, updated_at)
            SELECT id, 0, 0, NOW()
            FROM categories
        ");

        // Update with actual counts from posts table
        // This may take time on large tables, but only runs once during migration
        $db->unprepared("
            UPDATE category_post_counts cpc
            SET
                published_count = (
                    SELECT COUNT(*)
                    FROM posts p
                    WHERE p.category_id = cpc.category_id
                      AND p.is_published = 1
                      AND p.published_at <= NOW()
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
     * Create triggers for automatic count updates.
     */
    private function createTriggers(): void
    {
        $db = QueryBuilder::getConnection();

        // =====================================================
        // AFTER INSERT: Increment counts for new post
        // =====================================================
        $db->unprepared("
            CREATE TRIGGER trg_category_counts_after_insert
            AFTER INSERT ON posts
            FOR EACH ROW
            BEGIN
                IF NEW.category_id IS NOT NULL THEN
                    -- Ensure category exists in counts table
                    INSERT IGNORE INTO category_post_counts (category_id, published_count, total_count, updated_at)
                    VALUES (NEW.category_id, 0, 0, NOW());

                    -- Increment total count
                    UPDATE category_post_counts
                    SET total_count = total_count + 1,
                        updated_at = NOW()
                    WHERE category_id = NEW.category_id;

                    -- Increment published count if applicable
                    IF NEW.is_published = 1 AND (NEW.published_at IS NULL OR NEW.published_at <= NOW()) THEN
                        UPDATE category_post_counts
                        SET published_count = published_count + 1,
                            updated_at = NOW()
                        WHERE category_id = NEW.category_id;
                    END IF;
                END IF;
            END
        ");

        // =====================================================
        // AFTER UPDATE: Handle category and status changes
        // =====================================================
        $db->unprepared("
            CREATE TRIGGER trg_category_counts_after_update
            AFTER UPDATE ON posts
            FOR EACH ROW
            BEGIN
                DECLARE old_was_published BOOLEAN;
                DECLARE new_is_published BOOLEAN;

                SET old_was_published = (OLD.is_published = 1 AND (OLD.published_at IS NULL OR OLD.published_at <= NOW()));
                SET new_is_published = (NEW.is_published = 1 AND (NEW.published_at IS NULL OR NEW.published_at <= NOW()));

                -- Handle category change
                IF OLD.category_id != NEW.category_id OR
                   (OLD.category_id IS NULL AND NEW.category_id IS NOT NULL) OR
                   (OLD.category_id IS NOT NULL AND NEW.category_id IS NULL) THEN

                    -- Decrement old category counts
                    IF OLD.category_id IS NOT NULL THEN
                        UPDATE category_post_counts
                        SET total_count = GREATEST(total_count - 1, 0),
                            updated_at = NOW()
                        WHERE category_id = OLD.category_id;

                        IF old_was_published THEN
                            UPDATE category_post_counts
                            SET published_count = GREATEST(published_count - 1, 0),
                                updated_at = NOW()
                            WHERE category_id = OLD.category_id;
                        END IF;
                    END IF;

                    -- Increment new category counts
                    IF NEW.category_id IS NOT NULL THEN
                        INSERT IGNORE INTO category_post_counts (category_id, published_count, total_count, updated_at)
                        VALUES (NEW.category_id, 0, 0, NOW());

                        UPDATE category_post_counts
                        SET total_count = total_count + 1,
                            updated_at = NOW()
                        WHERE category_id = NEW.category_id;

                        IF new_is_published THEN
                            UPDATE category_post_counts
                            SET published_count = published_count + 1,
                                updated_at = NOW()
                            WHERE category_id = NEW.category_id;
                        END IF;
                    END IF;

                -- Same category, but published status changed
                ELSEIF NEW.category_id IS NOT NULL AND old_was_published != new_is_published THEN
                    IF new_is_published THEN
                        UPDATE category_post_counts
                        SET published_count = published_count + 1,
                            updated_at = NOW()
                        WHERE category_id = NEW.category_id;
                    ELSE
                        UPDATE category_post_counts
                        SET published_count = GREATEST(published_count - 1, 0),
                            updated_at = NOW()
                        WHERE category_id = NEW.category_id;
                    END IF;
                END IF;
            END
        ");

        // =====================================================
        // AFTER DELETE: Decrement counts for deleted post
        // =====================================================
        $db->unprepared("
            CREATE TRIGGER trg_category_counts_after_delete
            AFTER DELETE ON posts
            FOR EACH ROW
            BEGIN
                IF OLD.category_id IS NOT NULL THEN
                    -- Decrement total count
                    UPDATE category_post_counts
                    SET total_count = GREATEST(total_count - 1, 0),
                        updated_at = NOW()
                    WHERE category_id = OLD.category_id;

                    -- Decrement published count if was published
                    IF OLD.is_published = 1 AND (OLD.published_at IS NULL OR OLD.published_at <= NOW()) THEN
                        UPDATE category_post_counts
                        SET published_count = GREATEST(published_count - 1, 0),
                            updated_at = NOW()
                        WHERE category_id = OLD.category_id;
                    END IF;
                END IF;
            END
        ");

        // =====================================================
        // AFTER INSERT on categories: Create count row
        // =====================================================
        $db->unprepared("
            CREATE TRIGGER trg_category_counts_category_insert
            AFTER INSERT ON categories
            FOR EACH ROW
            BEGIN
                INSERT INTO category_post_counts (category_id, published_count, total_count, updated_at)
                VALUES (NEW.id, 0, 0, NOW());
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
            'trg_category_counts_after_insert',
            'trg_category_counts_after_update',
            'trg_category_counts_after_delete',
            'trg_category_counts_category_insert',
        ];

        foreach ($triggers as $trigger) {
            $db->unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
}
