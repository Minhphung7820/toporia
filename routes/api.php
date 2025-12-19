<?php

declare(strict_types=1);

/**
 * API Routes
 *
 * These routes are loaded by RouteServiceProvider within the 'api' middleware group.
 * All routes here are automatically prefixed with '/api'.
 * All routes receive the middleware from the 'api' group in config/middleware.php.
 */

use Toporia\Framework\Support\Accessors\Route;
use Toporia\Framework\Http\Request;
use App\Presentation\Http\Controllers\Api\AuthController;
use App\Presentation\Http\Controllers\Api\CsrfCookieController;
use Toporia\Framework\Realtime\Broadcast;

// Blog Controllers
use App\Presentation\Http\Controllers\Api\Blog\PostController as BlogPostController;
use App\Presentation\Http\Controllers\Api\Blog\CommentController as BlogCommentController;
use App\Presentation\Http\Controllers\Api\Blog\CategoryController as BlogCategoryController;
use App\Presentation\Http\Controllers\Api\Blog\TagController as BlogTagController;
use App\Presentation\Http\Controllers\Api\Blog\FeedbackController;

// CSRF Cookie endpoint for SPA authentication (must be called before login/register)
Route::get('/csrf-cookie', CsrfCookieController::class);

// Authentication routes with HttpOnly cookies
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/auth/user', [AuthController::class, 'user']);
Route::post('/auth/logout', [AuthController::class, 'logout']);
Route::post('/auth/refresh', [AuthController::class, 'refresh']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

// Posts
Route::get('/blog/posts', [BlogPostController::class, 'index']);
Route::get('/blog/posts/featured', [BlogPostController::class, 'featured']);
Route::get('/blog/posts/popular', [BlogPostController::class, 'popular']);
Route::get('/blog/posts/latest', [BlogPostController::class, 'latest']);
Route::get('/blog/search', [BlogPostController::class, 'search']);
Route::get('/blog/posts/{slug}', [BlogPostController::class, 'show']);
Route::get('/blog/posts/{id}/related', [BlogPostController::class, 'related']);
Route::post('/blog/posts/{id}/views', [BlogPostController::class, 'incrementViews']);

// Comments
Route::get('/blog/posts/{postId}/comments', [BlogCommentController::class, 'index']);
Route::post('/blog/posts/{postId}/comments', [BlogCommentController::class, 'store']);
Route::post('/blog/comments/{commentId}/reply', [BlogCommentController::class, 'reply']);
Route::post('/blog/comments/{commentId}/like', [BlogCommentController::class, 'like']);

// Categories
Route::get('/blog/categories', [BlogCategoryController::class, 'index']);
Route::get('/blog/categories/tree', [BlogCategoryController::class, 'tree']);
Route::get('/blog/categories/tree-with-counts', [BlogCategoryController::class, 'treeWithCounts']);
Route::get('/blog/categories/with-counts', [BlogCategoryController::class, 'withCounts']);
Route::get('/blog/categories/with-posts', [BlogCategoryController::class, 'withPosts']);
Route::get('/blog/categories/{slug}', [BlogCategoryController::class, 'show']);
Route::get('/blog/categories/{slug}/posts', [BlogPostController::class, 'byCategory']);

// Tags
Route::get('/blog/tags', [BlogTagController::class, 'index']);
Route::get('/blog/tags/popular', [BlogTagController::class, 'popular']);
Route::get('/blog/tags/cloud', [BlogTagController::class, 'cloud']);
Route::get('/blog/tags/search', [BlogTagController::class, 'search']);
Route::get('/blog/tags/{slug}', [BlogTagController::class, 'show']);
Route::get('/blog/tags/{slug}/posts', [BlogPostController::class, 'byTag']);

// Feedback (Public)
Route::post('/feedback', [FeedbackController::class, 'store']);
Route::get('/feedback/my', [FeedbackController::class, 'myFeedback']);
Route::get('/feedback/{id}/status', [FeedbackController::class, 'status']);

// 404 Handler - Global handler for unmatched routes
Route::fallback(function () {
    abort(404);
});
