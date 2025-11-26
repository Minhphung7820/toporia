<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Update product_categories table to add pivot columns for testing.
 */
class UpdateProductCategoriesTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->table('product_categories', function ($table) {
            $table->integer('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);

            $table->index(['sort_order', 'is_featured']);
            $table->index('is_active');
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->table('product_categories', function ($table) {
            $table->dropColumn(['sort_order', 'is_featured', 'is_active']);
        });
    }
}
