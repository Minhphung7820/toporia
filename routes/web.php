<?php

declare(strict_types=1);

/**
 * Web Routes
 *
 * Define your application routes here.
 * Using static Route facade.
 */

use Toporia\Framework\Support\Accessors\Route;
use App\Presentation\Http\Controllers\AppController;
use App\Presentation\Http\Controllers\TestController;

// SPA Fallback Route - Catches all routes for Vue Router
// This allows Vue Router to handle client-side routing
// Excludes /api/* paths to allow API routes to handle 404 properly
// Note: Pattern uses negative lookahead (?!api/) to exclude paths starting with "api/"
Route::any('/{any}', [AppController::class, 'index'])->where('any', '(?!api/).*');
// routes/web.php hoặc routes/api.php
// Route::get('/test/create-product', [TestController::class, 'createProduct']);
