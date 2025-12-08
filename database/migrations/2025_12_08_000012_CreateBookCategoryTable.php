<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create book_category pivot table migration.
 */
class CreateBookCategoryTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('book_category', function ($table) {
            $table->id();
            $table->unsignedBigInteger('book_id');
            $table->unsignedBigInteger('category_id');
            $table->boolean('is_primary')->default(false);
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->foreign('book_id')->references('books', 'id')->onDelete('cascade');
            $table->foreign('category_id')->references('categories', 'id')->onDelete('cascade');
            $table->unique(['book_id', 'category_id']);
            $table->index(['category_id', 'is_primary']);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('book_category');
    }
}

