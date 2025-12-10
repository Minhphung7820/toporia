<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Middleware;

use Toporia\Framework\Realtime\Contracts\ConnectionInterface;

/**
 * Role Middleware
 *
 * Checks if connection has required role(s).
 *
 * Usage: 'role:admin' or 'role:admin,moderator'
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

