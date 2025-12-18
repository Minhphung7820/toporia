<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create admin_activity_logs table for audit trail.
 *
 * Tracks all admin actions for accountability and debugging.
 */
class CreateAdminActivityLogsTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('admin_activity_logs', function ($table) {
            $table->id();

            // Actor
            $table->unsignedBigInteger('user_id');

            // Action details
            $table->string('action', 50); // create, update, delete, publish, approve, reject, etc.
            $table->string('description', 500)->nullable();

            // Target model
            $table->string('model_type', 100)->nullable(); // Post, Comment, User, etc.
            $table->unsignedBigInteger('model_id')->nullable();

            // Change tracking
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Request context
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('url', 500)->nullable();

            $table->timestamps();

            // Indexes for queries
            $table->index('user_id', 'idx_activity_user');
            $table->index('action', 'idx_activity_action');
            $table->index(['model_type', 'model_id'], 'idx_activity_model');
            $table->index('created_at', 'idx_activity_created');

            // Foreign key
            $table->foreign('user_id')
                ->references('users', 'id')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('admin_activity_logs');
    }
}
