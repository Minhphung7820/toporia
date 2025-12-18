<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Add indexes to comments and feedback tables for dashboard statistics optimization.
 *
 * These indexes improve COUNT queries with WHERE clauses on status columns.
 */
class AddIndexesToCommentsAndFeedbackTables extends Migration
{
    public function up(): void
    {
        // Index for comments.is_approved (used in COUNT queries)
        // Check if index exists before creating
        $this->addIndexIfNotExists('comments', 'is_approved', 'idx_comments_is_approved');

        // Composite index for posts statistics query
        $this->addIndexIfNotExists('posts', ['is_published', 'scheduled_at'], 'idx_posts_status_scheduled');

        // Re-add views index if it was dropped
        $this->addIndexIfNotExists('posts', ['is_published', 'views'], 'idx_posts_published_views');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('comments', 'idx_comments_is_approved');
        $this->dropIndexIfExists('posts', 'idx_posts_status_scheduled');
        // Don't drop idx_posts_published_views as it's from another migration
    }

    private function addIndexIfNotExists(string $table, string|array $columns, string $indexName): void
    {
        $columns = is_array($columns) ? $columns : [$columns];

        try {
            $this->schema->table($table, function ($t) use ($columns, $indexName) {
                $t->index($columns, $indexName);
            });
        } catch (\Exception $e) {
            // Index already exists, skip
            if (!str_contains($e->getMessage(), 'Duplicate key name')) {
                throw $e;
            }
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        try {
            $this->schema->table($table, function ($t) use ($indexName) {
                $t->dropIndex($indexName);
            });
        } catch (\Exception $e) {
            // Index doesn't exist, skip
        }
    }
}
