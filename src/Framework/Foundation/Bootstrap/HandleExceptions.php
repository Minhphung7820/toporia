<?php

declare(strict_types=1);

namespace Toporia\Framework\Foundation\Bootstrap;

use Toporia\Framework\Error\ErrorHandler;

/**
 * Handle Exceptions
 *
 * Registers the error and exception handler for the application.
 * This should be called early to catch all errors during bootstrap.
 *
 * Clean Architecture:
 * - Single Responsibility: Only handles error handler registration
 * - Early execution: Called right after environment variables are loaded
 * - Performance: O(1) - simple registration
 */
final class HandleExceptions
{
    /**
     * Bootstrap exception handling.
     *
     * @return void
     */
    public static function bootstrap(): void
    {
        $debug = ($_ENV['APP_DEBUG'] ?? 'true') === 'true';
        $errorHandler = new ErrorHandler($debug);
        $errorHandler->register();
    }
}
