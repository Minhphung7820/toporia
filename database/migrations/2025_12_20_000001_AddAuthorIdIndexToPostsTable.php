<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Add index on author_id for dashboard author statistics.
 *
 * This index optimizes:
 * - Author post count queries in DashboardService
 * - Author's popular posts (ORDER BY views DESC WHERE author_id = ?)
 * - Author statistics aggregation
 *
 * With 7M+ posts, this prevents full table scans when filtering by author.
 */
class AddAuthorIdIndexToPostsTable extends Migration
{
    public function up(): void
    {
        $this->schema->table('posts', function ($table) {
            // Index for author filtering and statistics
            $table->index('author_id', 'idx_posts_author_id');

            // Composite index for author's popular posts:
            // WHERE author_id = ? ORDER BY views DESC LIMIT N
            $table->index(['author_id', 'views'], 'idx_posts_author_views');
        });
    }

    public function down(): void
    {
        $this->schema->table('posts', function ($table) {
            $table->dropIndex('idx_posts_author_id');
            $table->dropIndex('idx_posts_author_views');
        });
    }
}
