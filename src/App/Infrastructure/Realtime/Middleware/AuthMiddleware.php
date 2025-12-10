<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime\Middleware;

use Toporia\Framework\Realtime\Middleware\ChannelMiddlewareInterface;
use Toporia\Framework\Realtime\Contracts\ConnectionInterface;

/**
 * Authentication Middleware
 *
 * Kiểm tra xem connection đã authenticated chưa.
 *
 * @package App\Infrastructure\Realtime\Middleware
 */
final class AuthMiddleware implements ChannelMiddlewareInterface
{
    /**
     * {@inheritdoc}
     */
    public function handle(ConnectionInterface $connection, string $channelName, callable $next): bool
    {
        // Check if user is authenticated (has user_id)
        if ($connection->getUserId() === null) {
            error_log("[Auth Middleware] Denied: Connection {$connection->getId()} not authenticated for channel '{$channelName}'");
            return false;
        }

        // Pass to next middleware
        return $next($connection, $channelName);
    }
}

