<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create chapters table migration.
 */
class CreateChaptersTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('chapters', function ($table) {
            $table->id();
            $table->unsignedBigInteger('book_id');
            $table->integer('chapter_number');
            $table->string('title', 200);
            $table->text('summary')->nullable();
            $table->integer('pages_count')->default(0);
            $table->integer('words_count')->default(0);
            $table->integer('reading_time_minutes')->default(0);
            $table->boolean('is_free_preview')->default(false);
            $table->timestamps();

            $table->foreign('book_id')->references('books', 'id')->onDelete('cascade');
            $table->unique(['book_id', 'chapter_number']);
            $table->index(['book_id', 'chapter_number']);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('chapters');
    }
}

