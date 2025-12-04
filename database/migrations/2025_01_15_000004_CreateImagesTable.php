<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create images table migration for polymorphic relationships.
 */
class CreateImagesTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('images', function ($table) {
            $table->id();
            $table->string('imageable_type')->nullable(); // Post or Video
            $table->unsignedBigInteger('imageable_id')->nullable();
            $table->string('url');
            $table->string('alt_text')->nullable();
            $table->integer('width')->unsigned()->default(0);
            $table->integer('height')->unsigned()->default(0);
            $table->integer('size')->unsigned()->default(0); // in bytes
            $table->timestamps();

            // Index for polymorphic relationship
            $table->index(['imageable_type', 'imageable_id']);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('images');
    }
}


