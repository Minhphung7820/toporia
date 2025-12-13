<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap
 *
 * Initializes the testing environment before running tests.
 * Performance: Minimal overhead, only essential setup.
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define test environment
define('TESTING', true);

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load framework helpers if needed (loaded via composer autoload from toporia/framework)

// Set timezone for consistent test results
date_default_timezone_set('UTC');
