<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Add author_id + views composite index for popular posts by author query.
 *
 * Optimizes: WHERE author_id = ? ORDER BY views DESC
 */
class AddAuthorViewsIndexToPostsTable extends Migration
{
    public function up(): void
    {
        $this->schema->table('posts', function ($table) {
            $table->index(['author_id', 'views'], 'idx_posts_author_views');
        });
    }

    public function down(): void
    {
        $this->schema->table('posts', function ($table) {
            $table->dropIndex('idx_posts_author_views');
        });
    }
}
