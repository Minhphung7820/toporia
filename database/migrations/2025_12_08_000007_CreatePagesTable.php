<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create pages table migration.
 */
class CreatePagesTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('pages', function ($table) {
            $table->id();
            $table->unsignedBigInteger('chapter_id');
            $table->integer('page_number');
            $table->text('content');
            $table->integer('words_count')->default(0);
            $table->boolean('has_images')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('chapter_id')->references('chapters', 'id')->onDelete('cascade');
            $table->unique(['chapter_id', 'page_number']);
            $table->index(['chapter_id', 'page_number']);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('pages');
    }
}

