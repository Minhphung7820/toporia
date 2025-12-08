<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create books table migration.
 */
class CreateBooksTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('books', function ($table) {
            $table->id();
            $table->unsignedBigInteger('author_id');
            $table->unsignedBigInteger('publisher_id')->nullable();
            $table->string('title', 200);
            $table->string('isbn', 20)->unique()->nullable();
            $table->text('description')->nullable();
            $table->integer('pages_count')->default(0);
            $table->decimal('price', 10, 2)->default(0);
            $table->year('published_year')->nullable();
            $table->integer('stock')->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('reviews_count')->default(0);
            $table->boolean('is_bestseller')->default(false);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('author_id')->references('authors', 'id')->onDelete('cascade');
            $table->foreign('publisher_id')->references('publishers', 'id')->onDelete('set null');
            $table->index(['author_id', 'is_available']);
            $table->index(['publisher_id', 'published_year']);
            $table->index(['is_bestseller', 'rating']);
            $table->fullText(['title', 'description']);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('books');
    }
}

