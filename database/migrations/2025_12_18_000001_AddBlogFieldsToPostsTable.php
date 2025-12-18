<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Add blog-specific fields to posts table.
 *
 * Adds author, category, featured image, SEO fields, and scheduling support.
 */
class AddBlogFieldsToPostsTable extends Migration
{
    public function up(): void
    {
        $this->schema->table('posts', function ($table) {
            // Author and category relationships
            $table->unsignedBigInteger('author_id')->nullable()->after('id');
            $table->unsignedBigInteger('category_id')->nullable()->after('author_id');

            // Content enhancements
            $table->string('excerpt', 500)->nullable()->after('content');
            $table->string('featured_image', 500)->nullable()->after('excerpt');

            // Publishing controls
            $table->boolean('is_featured')->default(false)->after('is_published');
            $table->timestamp('published_at')->nullable()->after('is_featured');
            $table->timestamp('scheduled_at')->nullable()->after('published_at');

            // Reading metrics
            $table->integer('reading_time')->unsigned()->default(0)->after('views');

            // SEO fields
            $table->string('meta_title', 255)->nullable();
            $table->text('meta_description')->nullable();
            $table->json('meta_keywords')->nullable();

            // Indexes for performance
            $table->index('author_id', 'idx_posts_author');
            $table->index('category_id', 'idx_posts_category');
            $table->index('is_featured', 'idx_posts_featured');
            $table->index('published_at', 'idx_posts_published_at');
            $table->index('scheduled_at', 'idx_posts_scheduled_at');

            // Foreign keys
            $table->foreign('author_id')
                ->references('users', 'id')
                ->onDelete('set null');

            $table->foreign('category_id')
                ->references('categories', 'id')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        $this->schema->table('posts', function ($table) {
            // Drop foreign keys first
            $table->dropForeign(['author_id']);
            $table->dropForeign(['category_id']);

            // Drop indexes
            $table->dropIndex('idx_posts_author');
            $table->dropIndex('idx_posts_category');
            $table->dropIndex('idx_posts_featured');
            $table->dropIndex('idx_posts_published_at');
            $table->dropIndex('idx_posts_scheduled_at');

            // Drop columns
            $table->dropColumn([
                'author_id',
                'category_id',
                'excerpt',
                'featured_image',
                'is_featured',
                'published_at',
                'scheduled_at',
                'reading_time',
                'meta_title',
                'meta_description',
                'meta_keywords',
            ]);
        });
    }
}
