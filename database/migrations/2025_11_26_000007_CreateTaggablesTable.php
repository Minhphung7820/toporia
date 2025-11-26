<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create taggables table for polymorphic many-to-many relationship.
 */
class CreateTaggablesTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('taggables', function ($table) {
            $table->id();
            $table->unsignedBigInteger('tag_id');
            $table->string('taggable_type');
            $table->unsignedBigInteger('taggable_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['taggable_type', 'taggable_id']);
            $table->unique(['tag_id', 'taggable_type', 'taggable_id']);

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
        $this->schema->dropIfExists('taggables');
    }
}
