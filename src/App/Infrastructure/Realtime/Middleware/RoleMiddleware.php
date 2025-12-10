<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime\Middleware;

use Toporia\Framework\Realtime\Middleware\ChannelMiddlewareInterface;
use Toporia\Framework\Realtime\Contracts\ConnectionInterface;

/**
 * Role Middleware
 *
 * Kiểm tra xem connection có required role(s) không.
 *
 * Usage: 'role:admin' or 'role:admin,moderator'
 *
 * @package App\Infrastructure\Realtime\Middleware
 */
final class RoleMiddleware implements ChannelMiddlewareInterface
{
    /**
     * @param array<string> $requiredRoles Required roles (e.g., ['admin', 'moderator'])
     */
    public function __construct(
        private readonly array $requiredRoles = []
    ) {}

    /**
     * {@inheritdoc}
     */
    public function handle(ConnectionInterface $connection, string $channelName, callable $next): bool
    {
        // Get user roles from connection
        $userRoles = $connection->get('roles', []);

        if (!is_array($userRoles)) {
            $userRoles = [];
        }

        // Check if user has at least one of the required roles
        $hasRole = !empty(array_intersect($this->requiredRoles, $userRoles));

        if (!$hasRole) {
            error_log("[Role Middleware] Denied: Connection {$connection->getId()} missing required roles [" . implode(',', $this->requiredRoles) . "] for channel '{$channelName}'");
            return false;
        }

        // Pass to next middleware
        return $next($connection, $channelName);
    }
}

