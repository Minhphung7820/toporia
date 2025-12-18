<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Add parent_id column to categories table for hierarchical categories.
 */
class AddParentIdToCategories extends Migration
{
    public function up(): void
    {
        $this->schema->table('categories', function ($table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('sort_order');
            $table->foreign('parent_id')
                  ->references('categories', 'id')
                  ->onDelete('set null');
            $table->index('parent_id', 'idx_categories_parent_id');
        });
    }

    public function down(): void
    {
        $this->schema->table('categories', function ($table) {
            $table->dropForeign('categories_parent_id_foreign');
            $table->dropIndex('idx_categories_parent_id');
            $table->dropColumn('parent_id');
        });
    }
}
