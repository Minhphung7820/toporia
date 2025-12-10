<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime;

use Toporia\Framework\Realtime\Contracts\ConnectionInterface;

/**
 * Channel Route Builder
 *
 * Fluent API for defining realtime channel authorization and middleware.
 *
 * Usage:
 *   ChannelRoute::channel('user.{userId}', function($conn, $userId) {
 *       return $conn->getUserId() === (int) $userId;
 *   })->middleware(['auth']);
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Realtime
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
final class ChannelRoute
{
    /**
     * Channel definitions registry.
     *
     * @var array<string, array{pattern: string, callback: callable, middleware: array}>
     */
    private static array $channels = [];

    /**
     * Current channel pattern (for fluent API).
     *
     * @var string|null
     */
    private ?string $currentPattern = null;

    /**
     * Define a channel route.
     *
     * @param string $pattern Channel pattern (supports wildcards like 'user.{userId}' or 'private-*')
     * @param callable $callback Authorization callback(ConnectionInterface $connection, ...$params): bool
     * @return self
     */
    public static function channel(string $pattern, callable $callback): self
    {
        $instance = new self();
        $instance->currentPattern = $pattern;

        // Register channel with empty middleware initially
        self::$channels[$pattern] = [
            'pattern' => $pattern,
            'callback' => $callback,
            'middleware' => [],
        ];

        return $instance;
    }

    /**
     * Attach middleware to the current channel.
     *
     * @param array<string> $middleware Middleware names (e.g., ['auth', 'role:admin'])
     * @return self
     */
    public function middleware(array $middleware): self
    {
        if ($this->currentPattern !== null && isset(self::$channels[$this->currentPattern])) {
            self::$channels[$this->currentPattern]['middleware'] = $middleware;
        }

        return $this;
    }

    /**
     * Get all registered channel definitions.
     *
     * @return array<string, array{pattern: string, callback: callable, middleware: array}>
     */
    public static function getChannels(): array
    {
        return self::$channels;
    }

    /**
     * Find channel definition by name.
     *
     * Supports:
     * - Exact match: 'user.123' matches 'user.123'
     * - Wildcards: 'user.123' matches 'user.*'
     * - Parameters: 'user.123' matches 'user.{userId}'
     *
     * @param string $channelName Channel name to match
     * @return array{pattern: string, callback: callable, middleware: array, params: array}|null
     */
    public static function match(string $channelName): ?array
    {
        foreach (self::$channels as $definition) {
            $pattern = $definition['pattern'];

            // Exact match
            if ($pattern === $channelName) {
                return array_merge($definition, ['params' => []]);
            }

            // Wildcard match (e.g., 'private-*' matches 'private-chat')
            if (str_contains($pattern, '*')) {
                $regex = '/^' . str_replace('*', '.*', preg_quote($pattern, '/')) . '$/';
                if (preg_match($regex, $channelName)) {
                    return array_merge($definition, ['params' => []]);
                }
            }

            // Parameter match (e.g., 'user.{userId}' matches 'user.123')
            if (str_contains($pattern, '{')) {
                $params = self::extractParameters($pattern, $channelName);
                if ($params !== null) {
                    return array_merge($definition, ['params' => $params]);
                }
            }
        }

        return null;
    }

    /**
     * Extract parameters from channel name using pattern.
     *
     * Example:
     *   Pattern: 'user.{userId}.notifications'
     *   Channel: 'user.123.notifications'
     *   Result: ['userId' => '123']
     *
     * @param string $pattern Pattern with parameters like 'user.{userId}'
     * @param string $channelName Actual channel name like 'user.123'
     * @return array<string, string>|null Extracted parameters or null if no match
     */
    private static function extractParameters(string $pattern, string $channelName): ?array
    {
        // Convert pattern to regex
        // 'user.{userId}' -> '/^user\.([^.]+)$/'
        // 'team.{teamId}.channel.{channelId}' -> '/^team\.([^.]+)\.channel\.([^.]+)$/'

        $paramNames = [];
        $regex = preg_replace_callback('/\{(\w+)\}/', function ($matches) use (&$paramNames) {
            $paramNames[] = $matches[1];
            return '([^.]+)'; // Match anything except dot
        }, preg_quote($pattern, '/'));

        $regex = '/^' . $regex . '$/';

        if (preg_match($regex, $channelName, $matches)) {
            array_shift($matches); // Remove full match

            $params = [];
            foreach ($paramNames as $index => $paramName) {
                $params[$paramName] = $matches[$index] ?? '';
            }

            return $params;
        }

        return null;
    }

    /**
     * Clear all registered channels (useful for testing).
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$channels = [];
    }

    /**
     * Check if a channel is registered.
     *
     * @param string $pattern Channel pattern
     * @return bool
     */
    public static function has(string $pattern): bool
    {
        return isset(self::$channels[$pattern]);
    }

    /**
     * Remove a channel definition.
     *
     * @param string $pattern Channel pattern
     * @return void
     */
    public static function remove(string $pattern): void
    {
        unset(self::$channels[$pattern]);
    }
}
