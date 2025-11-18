<?php

declare(strict_types=1);

/**
 * Web Routes
 *
 * Define your application routes here.
 * Using static Route facade.
 */

use Toporia\Framework\Support\Accessors\Route;
use App\Presentation\Http\Middleware\Authenticate;
use App\Presentation\Http\Controllers\AuthController;
use App\Presentation\Http\Controllers\AppController;

// Authentication routes
Route::get('/login', [AuthController::class, 'showLoginForm']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegisterForm']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout']);

// API routes - Authentication
Route::post('/api/login', [AuthController::class, 'login']);
Route::post('/api/register', [AuthController::class, 'register']);
Route::post('/api/logout', [AuthController::class, 'logout']);
Route::get('/api/me', [AuthController::class, 'me'])->middleware(['auth:api']);

// SPA Fallback Route - Must be last to catch all non-API routes
// This allows Vue Router to handle client-side routing
// Route::any() matches all HTTP methods (GET, POST, PUT, DELETE, etc.)
Route::any('/{any}', [AppController::class, 'index'])->where('any', '.*');
