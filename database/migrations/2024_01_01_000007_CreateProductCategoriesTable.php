<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create product_categories pivot table for many-to-many relationship.
 */
class CreateProductCategoriesTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('product_categories', function ($table) {
            $table->id();
            $table->foreignId('product_id');
            $table->foreignId('category_id');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Composite unique index
            $table->unique(['product_id', 'category_id']);

            // Foreign keys
            $table->foreign('product_id')
                  ->references('products', 'id')
                  ->onDelete('cascade');

            $table->foreign('category_id')
                  ->references('categories', 'id')
                  ->onDelete('cascade');

            // Indexes
            $table->index('product_id');
            $table->index('category_id');
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('product_categories');
    }
}
