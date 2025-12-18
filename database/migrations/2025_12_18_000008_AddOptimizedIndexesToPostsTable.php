<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Add optimized indexes to posts table for admin listing performance.
 *
 * With 1M+ records, these indexes are critical for:
 * - Fast pagination (composite index on id DESC)
 * - Quick title search
 * - Efficient status filtering
 */
class AddOptimizedIndexesToPostsTable extends Migration
{
    public function up(): void
    {
        $this->schema->table('posts', function ($table) {
            // Composite index for common admin listing query:
            // ORDER BY id DESC with is_published filter
            $table->index(['is_published', 'id'], 'idx_posts_published_id');

            // Index for title search (LIKE 'search%' can use this index)
            $table->index('title', 'idx_posts_title');

            // Composite index for scheduled posts query
            $table->index(['is_published', 'scheduled_at'], 'idx_posts_scheduled_filter');
        });
    }

    public function down(): void
    {
        $this->schema->table('posts', function ($table) {
            $table->dropIndex('idx_posts_published_id');
            $table->dropIndex('idx_posts_title');
            $table->dropIndex('idx_posts_scheduled_filter');
        });
    }
}
