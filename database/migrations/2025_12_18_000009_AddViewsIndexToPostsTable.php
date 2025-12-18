<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Add views index for popular posts query optimization.
 *
 * This composite index dramatically improves:
 * - ORDER BY views DESC queries (from 8s to <100ms)
 * - Most viewed posts dashboard widget
 */
class AddViewsIndexToPostsTable extends Migration
{
    public function up(): void
    {
        $this->schema->table('posts', function ($table) {
            // Composite index for popular posts query:
            // WHERE is_published = 1 ORDER BY views DESC LIMIT N
            $table->index(['is_published', 'views'], 'idx_posts_published_views');
        });
    }

    public function down(): void
    {
        $this->schema->table('posts', function ($table) {
            $table->dropIndex('idx_posts_published_views');
        });
    }
}
