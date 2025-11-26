<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create product_relations table for self-referencing many-to-many relationship.
 */
class CreateProductRelationsTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('product_relations', function ($table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('related_product_id');
            $table->enum('relation_type', ['similar', 'complementary', 'alternative', 'accessory'])->default('similar');
            $table->decimal('strength', 3, 2)->default(1.00); // Relationship strength 0.00-1.00
            $table->timestamps();

            $table->unique(['product_id', 'related_product_id']);
            $table->index(['relation_type', 'strength']);

            $table->foreign('product_id')
                ->references('products', 'id')
                ->onDelete('cascade');
            $table->foreign('related_product_id')
                ->references('products', 'id')
                ->onDelete('cascade');
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('product_relations');
    }
}
