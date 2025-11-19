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
// Route::any('/{any}', [AppController::class, 'index'])->where('any', '.*');
// routes/web.php hoặc routes/api.php
Route::get('/test/create-product', [TestController::class, 'createProduct']);
