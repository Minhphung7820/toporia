<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create post_categories pivot table.
 *
 * Enables many-to-many relationship between posts and categories.
 */
class CreatePostCategoriesTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('post_categories', function ($table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('category_id');
            $table->timestamps();

            // Unique constraint to prevent duplicates
            $table->unique(['post_id', 'category_id'], 'uq_post_category');

            // Individual indexes for queries
            $table->index('post_id', 'idx_pc_post');
            $table->index('category_id', 'idx_pc_category');

            // Foreign keys with cascade delete
            $table->foreign('post_id')
                ->references('posts', 'id')
                ->onDelete('cascade');

            $table->foreign('category_id')
                ->references('categories', 'id')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('post_categories');
    }
}
