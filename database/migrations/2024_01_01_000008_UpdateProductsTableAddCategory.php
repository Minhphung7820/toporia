<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Update products table to add category_id and other fields.
 */
class UpdateProductsTableAddCategory extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->table('products', function ($table) {
            $table->foreignId('category_id')->nullable()->after('id');
            $table->string('slug', 255)->unique()->nullable()->after('title');
            $table->text('short_description')->nullable()->after('description');
            $table->json('images')->nullable()->after('description');
            $table->decimal('sale_price', 10, 2)->nullable()->after('price');
            $table->integer('views')->unsigned()->default(0)->after('stock');
            $table->decimal('rating', 3, 2)->default(0.00)->after('views');
            $table->integer('rating_count')->unsigned()->default(0)->after('rating');
            $table->json('specifications')->nullable()->after('rating_count');
            $table->string('status', 50)->default('active')->after('is_active');

            // Foreign key
            $table->foreign('category_id')
                  ->references('categories', 'id')
                  ->onDelete('set null');

            // Indexes
            $table->index('category_id');
            $table->index('slug');
            $table->index('status');
            $table->index('price');
            $table->index('rating');
            $table->index(['is_active', 'status']);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->table('products', function ($table) {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['category_id']);
            $table->dropIndex(['slug']);
            $table->dropIndex(['status']);
            $table->dropIndex(['price']);
            $table->dropIndex(['rating']);
            $table->dropIndex(['is_active', 'status']);

            $table->dropColumn([
                'category_id',
                'slug',
                'short_description',
                'images',
                'sale_price',
                'views',
                'rating',
                'rating_count',
                'specifications',
                'status'
            ]);
        });
    }
}
