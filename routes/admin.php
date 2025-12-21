<?php

declare(strict_types=1);

/**
 * Admin API Routes
 *
 * These routes are loaded by RouteServiceProvider within the 'api' middleware group.
 * All routes here are prefixed with '/api/admin' and require authentication + admin role.
 *
 * Permission levels:
 * - 'admin' middleware: Both admin and editor can access
 * - 'admin.only' middleware: Only admin can access (excludes editors)
 *
 * Role-based access:
 * - Admin: Full access to everything
 * - Editor: Posts (own), Comments (on own posts), Dashboard (own stats)
 * - User: No admin access
 */

use Toporia\Framework\Support\Accessors\Route;
use App\Presentation\Http\Controllers\Api\Admin\DashboardController;
use App\Presentation\Http\Controllers\Api\Admin\PostAdminController;
use App\Presentation\Http\Controllers\Api\Admin\CommentAdminController;
use App\Presentation\Http\Controllers\Api\Admin\CategoryAdminController;
use App\Presentation\Http\Controllers\Api\Admin\TagAdminController;
use App\Presentation\Http\Controllers\Api\Admin\UserAdminController;
use App\Presentation\Http\Controllers\Api\Admin\SettingsAdminController;
use App\Presentation\Http\Controllers\Api\Admin\FeedbackAdminController;
use App\Presentation\Http\Controllers\Api\Admin\UploadController;
use App\Presentation\Http\Controllers\Api\Admin\ImportExportController;
use Toporia\Framework\Support\Accessors\Terminal;
use Toporia\Framework\Support\Facades\Console;

// =========================================================================
// ADMIN API ROUTES
// Base middleware: auth + admin (allows both admin and editor)
// =========================================================================

Route::group(['prefix' => 'admin', 'middleware' => ['auth', 'admin']], function () {

    // =========================================================================
    // SHARED ROUTES (Admin & Editor)
    // =========================================================================

    // Dashboard - Controller handles role-based data filtering
    Route::get('/dashboard/statistics', [DashboardController::class, 'statistics']);
    Route::get('/dashboard/activity', [DashboardController::class, 'activity']);
    Route::get('/dashboard/popular-posts', [DashboardController::class, 'popularPosts']);
    Route::get('/dashboard/recent-comments', [DashboardController::class, 'recentComments']);
    Route::get('/dashboard/charts', [DashboardController::class, 'charts']);
    Route::get('/dashboard/quick-stats', [DashboardController::class, 'quickStats']);

    // Import/Export - Must be BEFORE /posts/{id} to avoid route conflict
    Route::post('/posts/import', [ImportExportController::class, 'import']);
    Route::post('/posts/export', [ImportExportController::class, 'export']);
    Route::get('/posts/jobs', [ImportExportController::class, 'jobs']);
    Route::get('/posts/jobs/active', [ImportExportController::class, 'activeJobs']);
    Route::get('/posts/jobs/history', [ImportExportController::class, 'history']);
    Route::get('/posts/jobs/stats', [ImportExportController::class, 'stats']);
    Route::get('/posts/jobs/{id}', [ImportExportController::class, 'status']);
    Route::post('/posts/jobs/{id}/cancel', [ImportExportController::class, 'cancel']);
    Route::get('/posts/jobs/{id}/download', [ImportExportController::class, 'download']);

    // Posts Management - Controller handles ownership validation for editors
    Route::get('/posts', [PostAdminController::class, 'index']);
    Route::get('/posts/{id}', [PostAdminController::class, 'show']);
    Route::post('/posts', [PostAdminController::class, 'store']);
    Route::put('/posts/{id}', [PostAdminController::class, 'update']);
    Route::delete('/posts/{id}', [PostAdminController::class, 'destroy']);
    Route::post('/posts/{id}/publish', [PostAdminController::class, 'publish']);
    Route::post('/posts/{id}/unpublish', [PostAdminController::class, 'unpublish']);
    Route::post('/posts/{id}/schedule', [PostAdminController::class, 'schedule']);

    // Comments Management - Controller handles ownership validation for editors
    Route::get('/comments', [CommentAdminController::class, 'index']);
    Route::get('/comments/pending', [CommentAdminController::class, 'pending']);
    Route::get('/comments/statistics', [CommentAdminController::class, 'statistics']);
    Route::post('/comments/{id}/approve', [CommentAdminController::class, 'approve']);
    Route::post('/comments/{id}/reject', [CommentAdminController::class, 'reject']);
    Route::post('/comments/bulk-approve', [CommentAdminController::class, 'bulkApprove']);
    Route::post('/comments/bulk-reject', [CommentAdminController::class, 'bulkReject']);
    Route::post('/comments/bulk-delete', [CommentAdminController::class, 'bulkDelete']);
    Route::delete('/comments/{id}', [CommentAdminController::class, 'destroy']);

    // Categories - View only for editors (for post creation)
    Route::get('/categories', [CategoryAdminController::class, 'index']);
    Route::get('/categories/select', [CategoryAdminController::class, 'selectOptions']);

    // Tags - View only for editors (for post creation)
    Route::get('/tags', [TagAdminController::class, 'index']);

    // File Upload - Both admin and editor can upload
    Route::post('/upload', [UploadController::class, 'upload']);

    // =========================================================================
    // ADMIN-ONLY ROUTES
    // =========================================================================
    Route::group(['middleware' => ['admin.only']], function () {

        // Posts - Toggle featured (admin only)
        Route::post('/posts/{id}/toggle-featured', [PostAdminController::class, 'toggleFeatured']);

        // Categories Management (admin only)
        Route::get('/categories/{id}', [CategoryAdminController::class, 'show']);
        Route::post('/categories', [CategoryAdminController::class, 'store']);
        Route::put('/categories/{id}', [CategoryAdminController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryAdminController::class, 'destroy']);
        Route::post('/categories/{id}/toggle-active', [CategoryAdminController::class, 'toggleActive']);
        Route::post('/categories/reorder', [CategoryAdminController::class, 'reorder']);

        // Tags Management (admin only)
        Route::get('/tags/statistics', [TagAdminController::class, 'statistics']);
        Route::get('/tags/{id}', [TagAdminController::class, 'show']);
        Route::post('/tags', [TagAdminController::class, 'store']);
        Route::put('/tags/{id}', [TagAdminController::class, 'update']);
        Route::delete('/tags/{id}', [TagAdminController::class, 'destroy']);
        Route::post('/tags/bulk-delete', [TagAdminController::class, 'bulkDelete']);
        Route::post('/tags/merge', [TagAdminController::class, 'merge']);
        Route::post('/tags/cleanup', [TagAdminController::class, 'cleanup']);

        // Users Management (admin only)
        Route::get('/users', [UserAdminController::class, 'index']);
        Route::get('/users/statistics', [UserAdminController::class, 'statistics']);
        Route::get('/users/{id}', [UserAdminController::class, 'show']);
        Route::post('/users', [UserAdminController::class, 'store']);
        Route::put('/users/{id}', [UserAdminController::class, 'update']);
        Route::delete('/users/{id}', [UserAdminController::class, 'destroy']);
        Route::post('/users/{id}/role', [UserAdminController::class, 'changeRole']);

        // Settings Management (admin only)
        Route::get('/settings', [SettingsAdminController::class, 'index']);
        Route::get('/settings/groups', [SettingsAdminController::class, 'groups']);
        Route::get('/settings/group/{group}', [SettingsAdminController::class, 'byGroup']);
        Route::post('/settings', [SettingsAdminController::class, 'store']);
        Route::put('/settings', [SettingsAdminController::class, 'updateBatch']);
        Route::put('/settings/{key}', [SettingsAdminController::class, 'update']);
        Route::delete('/settings/{key}', [SettingsAdminController::class, 'destroy']);
        Route::post('/settings/reset', [SettingsAdminController::class, 'reset']);

        // Feedback Management (admin only)
        Route::get('/feedback', [FeedbackAdminController::class, 'index']);
        Route::get('/feedback/pending', [FeedbackAdminController::class, 'pending']);
        Route::get('/feedback/statistics', [FeedbackAdminController::class, 'statistics']);
        Route::get('/feedback/my-assigned', [FeedbackAdminController::class, 'myAssigned']);
        Route::get('/feedback/{id}', [FeedbackAdminController::class, 'show']);
        Route::post('/feedback/{id}/status', [FeedbackAdminController::class, 'updateStatus']);
        Route::post('/feedback/{id}/priority', [FeedbackAdminController::class, 'updatePriority']);
        Route::post('/feedback/{id}/assign', [FeedbackAdminController::class, 'assign']);
        Route::post('/feedback/{id}/notes', [FeedbackAdminController::class, 'addNotes']);
        Route::delete('/feedback/{id}', [FeedbackAdminController::class, 'destroy']);
        Route::post('/feedback/bulk-delete', [FeedbackAdminController::class, 'bulkDelete']);
    });
});
