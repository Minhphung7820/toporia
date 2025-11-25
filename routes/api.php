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
use App\Presentation\Http\Controllers\Api\AuthController;
use App\Presentation\Http\Controllers\Api\CsrfCookieController;
use App\Presentation\Http\Controllers\ProductController;

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
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/products/{id}/reviews', [ProductController::class, 'reviews']);
Route::get('/categories', [ProductController::class, 'categories']);

/** @var Router $router */
