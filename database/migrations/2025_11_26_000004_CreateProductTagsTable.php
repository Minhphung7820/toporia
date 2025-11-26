<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create product_tags pivot table for many-to-many relationship.
 */
class CreateProductTagsTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('product_tags', function ($table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('tag_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'tag_id']);
            $table->index('created_by');

            $table->foreign('product_id')
                ->references('products', 'id')
                ->onDelete('cascade');
            $table->foreign('tag_id')
                ->references('tags', 'id')
                ->onDelete('cascade');
            $table->foreign('created_by')
                ->references('users', 'id')
                ->onDelete('set null');
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('product_tags');
    }
}
