<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Middleware;

use Toporia\Framework\Realtime\Contracts\ConnectionInterface;
use Toporia\Framework\Realtime\RateLimiter;

/**
 * Rate Limit Middleware
 *
 * Limits channel subscriptions per connection.
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
final class RateLimitMiddleware implements ChannelMiddlewareInterface
{
    private RateLimiter $rateLimiter;

    public function __construct(
        int $maxSubscriptions = 10,
        int $windowSeconds = 60
    ) {
        $this->rateLimiter = new RateLimiter(
            maxMessages: $maxSubscriptions,
            windowSeconds: $windowSeconds,
            enabled: true
        );
    }

    /**
     * {@inheritdoc}
     */
    public function handle(ConnectionInterface $connection, string $channelName, callable $next): bool
    {
        $identifier = "subscription:{$connection->getId()}";

        try {
            $this->rateLimiter->check($identifier);
        } catch (\Throwable $e) {
            error_log("[Rate Limit Middleware] Denied: Connection {$connection->getId()} exceeded subscription rate limit for channel '{$channelName}'");
            return false;
        }

        // Pass to next middleware
        return $next($connection, $channelName);
    }
}

