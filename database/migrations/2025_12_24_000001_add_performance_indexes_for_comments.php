<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;
use Toporia\Framework\Database\Schema\Blueprint;
use Toporia\Framework\Database\Schema\Schema;

/**
 * Add Performance Indexes for Comments System
 *
 * Optimized for millions of records with composite indexes
 * covering the most common query patterns.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Comments table indexes for filtering
        Schema::table('comments', function (Blueprint $table) {
            // Composite index for time range + status filtering
            // Covers: WHERE created_at >= ? AND status = ?
            $table->index(['created_at', 'status'], 'idx_comments_created_status');

            // Composite index for user comments filtering
            // Covers: WHERE user_id = ? AND created_at >= ?
            $table->index(['user_id', 'created_at'], 'idx_comments_user_created');

            // Composite index for post comments filtering
            // Covers: WHERE commentable_id = ? AND commentable_type = ?
            $table->index(['commentable_id', 'commentable_type', 'created_at'], 'idx_comments_commentable');

            // Covering index for cursor pagination with filters
            // Covers: WHERE status = ? AND id < ? ORDER BY id DESC
            $table->index(['status', 'id'], 'idx_comments_status_id');

            // Index for approved comments (most common query)
            $table->index(['is_approved', 'created_at'], 'idx_comments_approved');
        });

        // Posts table indexes for search dropdown
        Schema::table('posts', function (Blueprint $table) {
            // Composite index for published posts search
            // Covers: WHERE is_published = 1 AND id < ? ORDER BY id DESC
            $table->index(['is_published', 'id'], 'idx_posts_published_id');
        });

        // Users table indexes for author search dropdown
        Schema::table('users', function (Blueprint $table) {
            // Composite index for user search
            // Covers: WHERE role = ? AND id < ? ORDER BY id DESC
            $table->index(['role', 'id'], 'idx_users_role_id');

            // Index for email search
            $table->index(['email'], 'idx_users_email');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex('idx_comments_created_status');
            $table->dropIndex('idx_comments_user_created');
            $table->dropIndex('idx_comments_commentable');
            $table->dropIndex('idx_comments_status_id');
            $table->dropIndex('idx_comments_approved');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('idx_posts_published_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_role_id');
            $table->dropIndex('idx_users_email');
        });
    }
};
