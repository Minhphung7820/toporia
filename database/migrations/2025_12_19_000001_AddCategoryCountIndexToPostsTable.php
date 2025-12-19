<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Add optimized composite index for category post count queries.
 *
 * This index is critical for the /api/blog/categories/tree-with-counts endpoint
 * which needs to count published posts per category efficiently.
 *
 * With 3M+ posts, the previous query took 5+ seconds because it had to scan
 * the entire table. This composite index allows MySQL to:
 * 1. Filter by is_published using the leftmost index column
 * 2. Filter by published_at using range scan
 * 3. Aggregate by category_id without touching the table data (covering index)
 *
 * Expected improvement: 5s → <50ms
 */
class AddCategoryCountIndexToPostsTable extends Migration
{
    public function up(): void
    {
        $this->schema->table('posts', function ($table) {
            // Covering index for category count aggregation query:
            // SELECT category_id, COUNT(*) FROM posts
            // WHERE is_published = 1 AND published_at <= NOW() AND category_id IS NOT NULL
            // GROUP BY category_id
            //
            // Index order matters:
            // 1. is_published - equality filter (high selectivity first)
            // 2. category_id - for GROUP BY (allows index-only scan)
            // 3. published_at - for range filter (range should be last)
            $table->index(
                ['is_published', 'category_id', 'published_at'],
                'idx_posts_category_published_count'
            );
        });
    }

    public function down(): void
    {
        $this->schema->table('posts', function ($table) {
            $table->dropIndex('idx_posts_category_published_count');
        });
    }
}
