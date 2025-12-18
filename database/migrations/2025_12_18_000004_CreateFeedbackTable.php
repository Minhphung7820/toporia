<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create feedback table for user suggestions and feedback.
 */
class CreateFeedbackTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('feedback', function ($table) {
            $table->id();

            // User reference (optional for guests)
            $table->unsignedBigInteger('user_id')->nullable();

            // Contact information
            $table->string('name', 100);
            $table->string('email', 255);

            // Feedback content
            $table->string('subject', 255);
            $table->text('message');

            // Classification
            $table->enum('type', ['feedback', 'suggestion', 'bug_report', 'feature_request', 'other'])
                ->default('feedback');

            // Workflow status
            $table->enum('status', ['pending', 'reviewed', 'in_progress', 'resolved', 'dismissed'])
                ->default('pending');

            // Admin assignment
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->text('admin_notes')->nullable();

            // Tracking
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamps();

            // Indexes
            $table->index('user_id', 'idx_feedback_user');
            $table->index('status', 'idx_feedback_status');
            $table->index('type', 'idx_feedback_type');
            $table->index('assigned_to', 'idx_feedback_assigned');
            $table->index('created_at', 'idx_feedback_created');

            // Foreign keys
            $table->foreign('user_id')
                ->references('users', 'id')
                ->onDelete('set null');

            $table->foreign('assigned_to')
                ->references('users', 'id')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('feedback');
    }
}
