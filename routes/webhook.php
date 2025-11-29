<?php

declare(strict_types=1);

/**
 * Webhook Routes
 *
 * Routes for webhook functionality (inbound webhooks).
 */

use Toporia\Framework\Support\Accessors\Route;
use Toporia\Framework\Webhook\Controllers\WebhookController;

// Inbound webhook endpoint
// POST /webhook/{provider?}
Route::post('/webhook/{provider?}', [WebhookController::class, 'handle'])
    ->where('provider', '[a-z0-9_-]+');

