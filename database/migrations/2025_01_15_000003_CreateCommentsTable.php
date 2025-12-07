<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create comments table migration for polymorphic relationships.
 */
class CreateCommentsTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('comments', function ($table) {
            $table->id();
            $table->string('commentable_type')->nullable(); // Post or Video
            $table->unsignedBigInteger('commentable_id')->nullable();
            $table->text('content');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->timestamps();

            // Index for polymorphic relationship
            $table->index(['commentable_type', 'commentable_id']);
            $table->index('user_id');
            $table->index('is_approved');
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('comments');
    }
}





