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
use Toporia\Framework\Bus\Bus;
use App\Presentation\Http\Controllers\Api\AuthController;
use App\Presentation\Http\Controllers\Api\CsrfCookieController;
use App\Presentation\Http\Controllers\ProductController;
use App\Presentation\Http\Controllers\RelationshipTestController;
use App\Infrastructure\Jobs\SendEmailJob;

// CSRF Cookie endpoint for SPA authentication (must be called before login/register)
// CSRF cookie endpoint for SPA authentication
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

// Product API Routes
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/stats', [ProductController::class, 'stats']);
Route::get('/products/complex', [ProductController::class, 'complex']);
Route::get('/products/performance', [ProductController::class, 'performance']);
Route::get('/products/top-rated', [ProductController::class, 'topRated']);

// HTTP Methods Test Route
Route::get('/products/test-methods', [ProductController::class, 'testMethods']);
Route::post('/products/test-methods', [ProductController::class, 'testMethods']);
Route::put('/products/test-methods', [ProductController::class, 'testMethods']);
Route::patch('/products/test-methods', [ProductController::class, 'testMethods']);
Route::delete('/products/test-methods', [ProductController::class, 'testMethods']);

Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/products/{id}/reviews', [ProductController::class, 'reviews']);
Route::get('/categories', [ProductController::class, 'categories']);

// Product CRUD and Relationship Testing Routes
Route::post('/products', [ProductController::class, 'store']);
Route::get('/products/{id}/relationships', [ProductController::class, 'getAllRelationships']);
Route::put('/products/{id}/relationships', [ProductController::class, 'updateRelationships']);

// Polymorphic Relationship Testing Routes
Route::get('/products/{id}/polymorphic-tags', [ProductController::class, 'getPolymorphicTags']);
Route::post('/products/{id}/polymorphic-tags', [ProductController::class, 'attachPolymorphicTags']);
Route::put('/products/{id}/polymorphic-tags', [ProductController::class, 'syncPolymorphicTags']);
Route::delete('/products/{id}/polymorphic-tags', [ProductController::class, 'detachPolymorphicTags']);

// Tag API Routes
Route::post('/tags', [ProductController::class, 'createTag']);
Route::get('/tags', [ProductController::class, 'getTags']);
Route::get('/tags/{id}', [ProductController::class, 'getTag']);

// Complex Relationship Testing Routes
Route::get('/products/test-belongs-to-many', [ProductController::class, 'testBelongsToMany']);
Route::get('/products/test-has-relationships', [ProductController::class, 'testHasRelationships']);
Route::get('/products/test-belongs-to', [ProductController::class, 'testBelongsTo']);
Route::get('/products/test-complex-queries', [ProductController::class, 'testComplexQueries']);
Route::post('/products/test-sync-operations', [ProductController::class, 'testSyncOperations']);
Route::get('/products/test-performance', [ProductController::class, 'testPerformance']);
Route::get('/products/test-pivot-validation', [ProductController::class, 'testPivotValidation']);

// =========================================================================
// POLYMORPHIC RELATIONSHIPS TESTING ROUTES
// =========================================================================

// Test individual polymorphic relationship types
Route::get('/polymorphic/test-morph-one', [ProductController::class, 'testMorphOne']);
Route::get('/polymorphic/test-morph-many', [ProductController::class, 'testMorphMany']);
Route::get('/polymorphic/test-morph-to', [ProductController::class, 'testMorphTo']);
Route::get('/polymorphic/test-morph-to-many', [ProductController::class, 'testMorphToMany']);

// Test all polymorphic relationships together
Route::get('/polymorphic/test-all', [ProductController::class, 'testAllPolymorphic']);

// CRUD operations for polymorphic models
Route::post('/polymorphic/posts', [ProductController::class, 'createPost']);
Route::post('/polymorphic/videos', [ProductController::class, 'createVideo']);
Route::post('/polymorphic/comments', [ProductController::class, 'createComment']);

// Utility endpoints
Route::get('/polymorphic/available-ids', [ProductController::class, 'getAvailableIds']);
Route::get('/polymorphic/sample-data', [ProductController::class, 'getSampleData']);
Route::post('/polymorphic/seed-data', [ProductController::class, 'seedPolymorphicData']);

// =========================================================================
// COMPREHENSIVE RELATIONSHIP TESTING ROUTES
// =========================================================================

// Main test endpoint - Tests ALL relationship types
Route::get('/relationships/test-all', [RelationshipTestController::class, 'testAllRelationships']);

// Individual relationship type tests
Route::get('/relationships/has-one', [RelationshipTestController::class, 'testHasOne']);
Route::get('/relationships/has-many', [RelationshipTestController::class, 'testHasMany']);
Route::get('/relationships/has-many-through', [RelationshipTestController::class, 'testHasManyThrough']);
Route::get('/relationships/belongs-to-many', [RelationshipTestController::class, 'testBelongsToMany']);

// Simple Queue + Mail Test
Route::post('/send-email', function (Request $request) {
    $to = $request->input('to', 'minhphung485@gmail.com');
    $subject = $request->input('subject', 'Test Email from Toporia');
    $message = $request->input('message', 'This is a test email sent via queue.');

    // Dispatch job - automatically queued because it implements ShouldQueueInterface
    SendEmailJob::dispatch($to, $subject, $message);

    return response()->json([
        'success' => true,
        'message' => 'Email queued successfully!',
        'recipient' => $to,
        'note' => 'Run "php console queue:work" to process the job'
    ]);
});

// 404 Handler - Global handler for unmatched routes
// This will be called automatically when no route matches the request
Route::fallback(function () {
    abort(404);
});
