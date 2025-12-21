<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create comment_attachments table for storing file attachments.
 *
 * Supports images and files up to 15MB, max 9 files per comment.
 * Optimized indexes for queries on large datasets.
 */
class CreateCommentAttachmentsTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('comment_attachments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('comment_id');

            // File metadata
            $table->string('filename', 255);           // Original filename
            $table->string('path', 500);               // Storage path
            $table->string('mime_type', 100);          // MIME type
            $table->unsignedBigInteger('size');        // File size in bytes
            $table->enum('type', ['image', 'file'])->default('file');

            // Image-specific metadata (nullable for non-images)
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('thumbnail_path', 500)->nullable();

            // Sort order within comment
            $table->tinyInteger('sort_order')->unsigned()->default(0);

            $table->timestamps();

            // Foreign key
            $table->foreign('comment_id')
                ->references('comments', 'id')
                ->onDelete('cascade');

            // Indexes for performance
            $table->index('comment_id', 'idx_comment_attachments_comment');
            $table->index('type', 'idx_comment_attachments_type');
            $table->index('created_at', 'idx_comment_attachments_created');
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('comment_attachments');
    }
}
