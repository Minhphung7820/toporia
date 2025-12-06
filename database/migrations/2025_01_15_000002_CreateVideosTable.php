<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create videos table migration.
 */
class CreateVideosTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('videos', function ($table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique()->nullable();
            $table->text('description')->nullable();
            $table->string('video_url')->nullable();
            $table->integer('duration')->unsigned()->default(0); // in seconds
            $table->integer('views')->unsigned()->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index('slug');
            $table->index('is_published');
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('videos');
    }
}



