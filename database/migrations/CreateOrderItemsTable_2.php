<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create order_items table migration.
 */
class CreateOrderItemsTable_2 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('order_items', function ($table) {
            $table->id();
            $table->foreignId('order_id');
            $table->foreignId('product_id');
            $table->string('product_name', 255);
            $table->string('product_sku', 100)->nullable();
            $table->integer('quantity')->unsigned();
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->json('product_data')->nullable(); // Snapshot of product at time of order
            $table->timestamps();

            // Foreign keys
            $table->foreign('order_id')
                ->references('orders', 'id')
                ->onDelete('cascade');

            $table->foreign('product_id')
                ->references('products', 'id')
                ->onDelete('restrict');

            // Indexes
            $table->index('order_id');
            $table->index('product_id');
            $table->index(['order_id', 'product_id']);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('order_items');
    }
}
