<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Add nested comments support to comments table.
 *
 * Enables reply functionality with parent_id and depth tracking.
 */
class AddNestedCommentsSupport extends Migration
{
    public function up(): void
    {
        $this->schema->table('comments', function ($table) {
            // Parent reference for nested comments
            $table->unsignedBigInteger('parent_id')->nullable()->after('user_id');

            // Guest comment author info
            $table->string('author_name', 100)->nullable()->after('parent_id');
            $table->string('author_email', 255)->nullable()->after('author_name');
            $table->string('author_ip', 45)->nullable()->after('author_email');

            // Engagement metrics
            $table->integer('likes_count')->unsigned()->default(0)->after('is_approved');

            // Nesting depth (max 3 levels recommended)
            $table->tinyInteger('depth')->unsigned()->default(0)->after('likes_count');

            // Index for nested queries
            $table->index('parent_id', 'idx_comments_parent');
            $table->index('likes_count', 'idx_comments_likes');
            $table->index('depth', 'idx_comments_depth');

            // Self-referencing foreign key
            $table->foreign('parent_id')
                ->references('comments', 'id')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        $this->schema->table('comments', function ($table) {
            // Drop foreign key first
            $table->dropForeign(['parent_id']);

            // Drop indexes
            $table->dropIndex('idx_comments_parent');
            $table->dropIndex('idx_comments_likes');
            $table->dropIndex('idx_comments_depth');

            // Drop columns
            $table->dropColumn([
                'parent_id',
                'author_name',
                'author_email',
                'author_ip',
                'likes_count',
                'depth',
            ]);
        });
    }
}
