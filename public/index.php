<?php

declare(strict_types=1);

/**
 * Application Entry Point
 *
 * This file is the entry point for all HTTP requests.
 * It bootstraps the application and handles the incoming request.
 */

// Error reporting for development
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Note: Session is started lazily by SessionMiddleware when needed
// DO NOT call session_start() here - it adds ~5-10ms overhead per request

/*
|--------------------------------------------------------------------------
| Register The Autoloader
|--------------------------------------------------------------------------
*/

require __DIR__ . '/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Bootstrap The Application
|--------------------------------------------------------------------------
|
| Load the application with all service providers registered.
|
*/

/** @var \Toporia\Framework\Foundation\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Dispatch the HTTP request through the router.
| Helper functions are already loaded in bootstrap/app.php
|
*/

$app->make(\Toporia\Framework\Routing\Router::class)->dispatch();
