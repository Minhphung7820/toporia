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
use App\Presentation\Http\Controllers\Api\BrokerTestController;
use Toporia\Framework\Realtime\Broadcast;

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

// =========================================================================
// BROKER TESTING ROUTES (Producer)
// =========================================================================

Route::post('/broker/publish', [BrokerTestController::class, 'publish']);
Route::get('/broker/health', [BrokerTestController::class, 'health']);

// =========================================================================
// BROADCASTING AUTH ROUTES
// =========================================================================

use Toporia\Framework\Realtime\Auth\BroadcastAuthController;
use Toporia\Framework\Support\Accessors\Concurrency;

// Channel authorization endpoint (Pusher-compatible)

// Internal endpoint for realtime server to verify session auth
Route::post('/broadcasting/verify-session', [BroadcastAuthController::class, 'verifySession']);

// =========================================================================
// NOTIFICATION API - Simple endpoint to send realtime notifications
// =========================================================================
Route::post('/notifications/send', function (Request $request) {
    $type = $request->input('type', 'info'); // info, success, warning, error
    $title = $request->input('title', 'Notification');
    $message = $request->input('message', '');
    $channel = $request->input('channel', 'notifications');
    $driver = $request->input('driver', config('realtime.default_broker', 'redis'));

    try {
        $success = Broadcast::via($driver)
            ->toChannel($channel)
            ->event('notification')
            ->with([
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'timestamp' => date('Y-m-d H:i:s'),
            ])
            ->now();

        if (!$success) {
            return response()->json([
                'success' => false,
                'error' => "Failed to send notification. Check broker [{$driver}] is configured.",
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification sent',
            'data' => [
                'channel' => $channel,
                'type' => $type,
                'title' => $title,
                'driver' => $driver,
            ],
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});

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

// =========================================================================
// PARALLEL LOG JOB - Demonstrates Process::runTasks() in queue
// =========================================================================
Route::post('/parallel-log', function (Request $request) {
    $jobId = $request->input('job_id', uniqid('parallel_'));

    // Dispatch the parallel log job to queue
    [$a, $b] = Concurrency::run([
        function () {
            return "task 1";
        },
        function () {
            return "task 2";
        },
    ]);
    dd($a, $b);
    return response()->json([
        'success' => true,
        'message' => 'ParallelLogJob queued successfully!',
        'job_id' => $jobId,
        'note' => 'Run "php console queue:work" to process. Check logs for parallel task output.'
    ]);
});

// 404 Handler - Global handler for unmatched routes
// This will be called automatically when no route matches the request
Route::fallback(function () {
    abort(404);
});
