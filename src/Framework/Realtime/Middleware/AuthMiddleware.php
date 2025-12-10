<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Middleware;

use Toporia\Framework\Realtime\Contracts\ConnectionInterface;

/**
 * Authentication Middleware
 *
 * Ensures that the connection is authenticated before allowing channel subscription.
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
