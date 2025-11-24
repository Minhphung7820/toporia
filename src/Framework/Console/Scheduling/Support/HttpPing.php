<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Scheduling\Support;

use Toporia\Framework\Http\Contracts\HttpClientInterface;

/**
 * HTTP Ping Helper
 *
 * Utility class for sending HTTP pings.
 * Follows Single Responsibility Principle.
 *
 * Performance:
 * - O(1) per ping
 * - Non-blocking (doesn't wait for response)
 * - Lightweight cURL execution
 *
 * @package Toporia\Framework\Console\Scheduling\Support
 */
final class HttpPing
{
    /**
     * Send HTTP ping request.
     *
     * Non-blocking: Does not wait for response, fire-and-forget.
     * Performance: O(1) - Single HTTP request
     *
     * @param string $url URL to ping
     * @param array $data Optional data to send
     * @param HttpClientInterface|null $client Optional HTTP client
     * @return void
     */
    public static function send(string $url, array $data = [], ?HttpClientInterface $client = null): void
    {
        if ($client !== null) {
            // Use provided client (for dependency injection)
            try {
                $client->post($url, $data);
            } catch (\Throwable $e) {
                // Silent fail - ping should not break task execution
                error_log("Schedule ping failed: {$e->getMessage()}");
            }
            return;
        }

        // Fallback: Use cURL directly (fire-and-forget)
        self::sendAsync($url, $data);
    }

    /**
     * Send async HTTP ping (fire-and-forget).
     *
     * Uses background cURL execution to avoid blocking.
     *
     * @param string $url
     * @param array $data
     * @return void
     */
    private static function sendAsync(string $url, array $data): void
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5, // Short timeout
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: Toporia-Schedule/1.0'
            ],
        ]);

        // Execute in background (non-blocking)
        if (function_exists('curl_exec')) {
            curl_exec($ch);
            curl_close($ch);
        }
    }
}
