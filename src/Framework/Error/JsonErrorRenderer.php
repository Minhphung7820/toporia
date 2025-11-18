<?php

declare(strict_types=1);

namespace Toporia\Framework\Error;

use Toporia\Framework\Error\Contracts\ErrorRendererInterface;
use Throwable;

/**
 * JSON Error Renderer
 *
 * Renders exceptions as JSON responses for API requests.
 *
 * Features:
 * - Clean JSON format
 * - Stack trace in debug mode
 * - Simple message in production
 * - PSR-7 compatible structure
 *
 * Performance: O(N) where N = stack frames (only in debug mode)
 *
 * @package Toporia\Framework\Error
 */
final class JsonErrorRenderer implements ErrorRendererInterface
{
    public function __construct(
        private bool $debug = true
    ) {
        // Security: Force debug=false in production to prevent information disclosure
        // Use $_ENV directly since env() helper may not be loaded yet
        $appEnv = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'local';
        if ($appEnv === 'production') {
            // Always disable debug in production (security)
            $this->debug = false;
        }
        // In non-production, respect the $debug parameter passed in
    }

    /**
     * {@inheritdoc}
     */
    public function render(Throwable $exception): void
    {
        http_response_code($this->getStatusCode($exception));
        header('Content-Type: application/json; charset=UTF-8');

        echo json_encode($this->formatException($exception), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Format exception as array.
     *
     * @param Throwable $exception
     * @return array
     */
    private function formatException(Throwable $exception): array
    {
        // Handle ValidationException specially
        if ($exception instanceof \Toporia\Framework\Http\ValidationException) {
            return $exception->toArray();
        }

        if ($this->debug) {
            return [
                'error' => [
                    'message' => $exception->getMessage(),
                    'exception' => get_class($exception),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $this->formatTrace($exception->getTrace())
                ]
            ];
        }

        // Production: minimal information
        return [
            'error' => [
                'message' => 'Internal Server Error',
                'code' => 500
            ]
        ];
    }

    /**
     * Format stack trace.
     *
     * @param array $trace
     * @return array
     */
    private function formatTrace(array $trace): array
    {
        return array_map(function ($frame) {
            return [
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'function' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? ''),
            ];
        }, $trace);
    }

    /**
     * Get HTTP status code for exception.
     *
     * @param Throwable $exception
     * @return int
     */
    private function getStatusCode(Throwable $exception): int
    {
        // ValidationException returns 422
        if ($exception instanceof \Toporia\Framework\Http\ValidationException) {
            return 422;
        }

        // Use exception code if it's a valid HTTP status
        $code = $exception->getCode();
        if ($code >= 400 && $code < 600) {
            return $code;
        }

        return 500;
    }
}
