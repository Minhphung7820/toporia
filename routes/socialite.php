<?php

declare(strict_types=1);

/**
 * Socialite Routes
 *
 * Routes for OAuth social authentication.
 */

use Toporia\Framework\Support\Accessors\Route;
use Toporia\Socialite\Controllers\SocialiteController;

// OAuth redirect routes
Route::get('/auth/socialite/{provider}/redirect', [SocialiteController::class, 'redirect'])
    ->where('provider', '[a-z]+');

// OAuth callback routes
Route::get('/auth/socialite/{provider}/callback', [SocialiteController::class, 'callback'])
    ->where('provider', '[a-z]+');

