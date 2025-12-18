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
use App\Presentation\Http\Controllers\OAuthController;

// OAuth Success/Error Handlers (called by Socialite package)
Route::get('/auth/socialite/success', [OAuthController::class, 'success'])->name('oauth.success');
Route::get('/auth/socialite/error', [OAuthController::class, 'error'])->name('oauth.error');

// Admin SPA - Serves the admin Vue application
// Must be before the general SPA fallback to avoid being caught by it
Route::any('/admin', [AppController::class, 'admin']);
Route::any('/admin/{any}', [AppController::class, 'admin'])->where('any', '.*');

// SPA Fallback Route - Catches all routes for Vue Router
// This allows Vue Router to handle client-side routing
// Excludes /api/* paths to allow API routes to handle 404 properly
// Note: Pattern uses negative lookahead (?!api/) to exclude paths starting with "api/"
Route::any('/{any}', [AppController::class, 'index'])->where('any', '(?!api/).*');
