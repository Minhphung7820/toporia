<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create comment_mentions table for tracking user mentions (@mentions).
 *
 * Stores mentions separately for:
 * - Fast notification queries
 * - Analytics (who mentions whom)
 * - Avoiding regex parsing on every comment display
 */
class CreateCommentMentionsTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('comment_mentions', function ($table) {
            $table->id();
            $table->unsignedBigInteger('comment_id');
            $table->unsignedBigInteger('mentioned_user_id');
            $table->unsignedBigInteger('mentioned_by_user_id')->nullable();

            // Position in content (for highlighting)
            $table->unsignedInteger('position_start')->nullable();
            $table->unsignedInteger('position_end')->nullable();

            // Notification status
            $table->boolean('notified')->default(false);
            $table->timestamp('notified_at')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('comment_id')
                ->references('comments', 'id')
                ->onDelete('cascade');

            $table->foreign('mentioned_user_id')
                ->references('users', 'id')
                ->onDelete('cascade');

            // Indexes optimized for large datasets
            $table->index('comment_id', 'idx_comment_mentions_comment');
            $table->index('mentioned_user_id', 'idx_comment_mentions_user');
            $table->index(['mentioned_user_id', 'notified'], 'idx_comment_mentions_user_notified');
            $table->index('created_at', 'idx_comment_mentions_created');

            // Unique constraint - one mention per user per comment
            $table->unique(['comment_id', 'mentioned_user_id'], 'uq_comment_mention');
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('comment_mentions');
    }
}
