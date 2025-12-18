<?php

declare(strict_types=1);

/**
 * Admin API Routes
 *
 * These routes are loaded by RouteServiceProvider within the 'api' middleware group.
 * All routes here are prefixed with '/api/admin' and require authentication + admin role.
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

// =========================================================================
// ADMIN API ROUTES
// All routes require authentication and admin role
// =========================================================================

Route::group(['prefix' => 'admin', 'middleware' => ['auth', 'admin']], function () {

    // Dashboard
    Route::get('/dashboard/statistics', [DashboardController::class, 'statistics']);
    Route::get('/dashboard/activity', [DashboardController::class, 'activity']);
    Route::get('/dashboard/popular-posts', [DashboardController::class, 'popularPosts']);
    Route::get('/dashboard/recent-comments', [DashboardController::class, 'recentComments']);
    Route::get('/dashboard/charts', [DashboardController::class, 'charts']);
    Route::get('/dashboard/quick-stats', [DashboardController::class, 'quickStats']);

    // Posts Management
    Route::get('/posts', [PostAdminController::class, 'index']);
    Route::get('/posts/{id}', [PostAdminController::class, 'show']);
    Route::post('/posts', [PostAdminController::class, 'store']);
    Route::put('/posts/{id}', [PostAdminController::class, 'update']);
    Route::delete('/posts/{id}', [PostAdminController::class, 'destroy']);
    Route::post('/posts/{id}/publish', [PostAdminController::class, 'publish']);
    Route::post('/posts/{id}/unpublish', [PostAdminController::class, 'unpublish']);
    Route::post('/posts/{id}/schedule', [PostAdminController::class, 'schedule']);
    Route::post('/posts/{id}/toggle-featured', [PostAdminController::class, 'toggleFeatured']);

    // Comments Management
    Route::get('/comments', [CommentAdminController::class, 'index']);
    Route::get('/comments/pending', [CommentAdminController::class, 'pending']);
    Route::get('/comments/statistics', [CommentAdminController::class, 'statistics']);
    Route::post('/comments/{id}/approve', [CommentAdminController::class, 'approve']);
    Route::post('/comments/{id}/reject', [CommentAdminController::class, 'reject']);
    Route::post('/comments/bulk-approve', [CommentAdminController::class, 'bulkApprove']);
    Route::post('/comments/bulk-reject', [CommentAdminController::class, 'bulkReject']);
    Route::post('/comments/bulk-delete', [CommentAdminController::class, 'bulkDelete']);
    Route::delete('/comments/{id}', [CommentAdminController::class, 'destroy']);

    // Categories Management
    Route::get('/categories', [CategoryAdminController::class, 'index']);
    Route::get('/categories/select', [CategoryAdminController::class, 'selectOptions']);
    Route::get('/categories/{id}', [CategoryAdminController::class, 'show']);
    Route::post('/categories', [CategoryAdminController::class, 'store']);
    Route::put('/categories/{id}', [CategoryAdminController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryAdminController::class, 'destroy']);
    Route::post('/categories/{id}/toggle-active', [CategoryAdminController::class, 'toggleActive']);
    Route::post('/categories/reorder', [CategoryAdminController::class, 'reorder']);

    // Tags Management
    Route::get('/tags', [TagAdminController::class, 'index']);
    Route::get('/tags/statistics', [TagAdminController::class, 'statistics']);
    Route::get('/tags/{id}', [TagAdminController::class, 'show']);
    Route::post('/tags', [TagAdminController::class, 'store']);
    Route::put('/tags/{id}', [TagAdminController::class, 'update']);
    Route::delete('/tags/{id}', [TagAdminController::class, 'destroy']);
    Route::post('/tags/bulk-delete', [TagAdminController::class, 'bulkDelete']);
    Route::post('/tags/merge', [TagAdminController::class, 'merge']);
    Route::post('/tags/cleanup', [TagAdminController::class, 'cleanup']);

    // Users Management
    Route::get('/users', [UserAdminController::class, 'index']);
    Route::get('/users/statistics', [UserAdminController::class, 'statistics']);
    Route::get('/users/{id}', [UserAdminController::class, 'show']);
    Route::post('/users', [UserAdminController::class, 'store']);
    Route::put('/users/{id}', [UserAdminController::class, 'update']);
    Route::delete('/users/{id}', [UserAdminController::class, 'destroy']);
    Route::post('/users/{id}/role', [UserAdminController::class, 'changeRole']);

    // Settings Management
    Route::get('/settings', [SettingsAdminController::class, 'index']);
    Route::get('/settings/groups', [SettingsAdminController::class, 'groups']);
    Route::get('/settings/group/{group}', [SettingsAdminController::class, 'byGroup']);
    Route::post('/settings', [SettingsAdminController::class, 'store']);
    Route::put('/settings', [SettingsAdminController::class, 'updateBatch']);
    Route::put('/settings/{key}', [SettingsAdminController::class, 'update']);
    Route::delete('/settings/{key}', [SettingsAdminController::class, 'destroy']);
    Route::post('/settings/reset', [SettingsAdminController::class, 'reset']);

    // Feedback Management
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

    // File Upload
    Route::post('/upload', [UploadController::class, 'upload']);

});
