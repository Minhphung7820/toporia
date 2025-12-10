<?php

declare(strict_types=1);

namespace App\Realtime\Middleware;

use Toporia\Framework\Realtime\Middleware\ChannelMiddlewareInterface;
use Toporia\Framework\Realtime\Contracts\ConnectionInterface;

/**
 * Premium Middleware
 *
 * Kiểm tra user có premium subscription không.
 * Chỉ user premium mới được subscribe vào premium channels.
 *
 * Example usage:
 *   ChannelRoute::channel('premium-content', fn($conn) => true)
 *       ->middleware(['auth', 'premium']);
 *
 * @package App\Realtime\Middleware
 */
final class PremiumMiddleware implements ChannelMiddlewareInterface
{
    /**
     * Handle channel authorization.
     *
     * @param ConnectionInterface $connection Current connection
     * @param string $channelName Channel being subscribed to
     * @param callable $next Next middleware in pipeline
     * @return bool True if authorized, false otherwise
     */
    public function handle(ConnectionInterface $connection, string $channelName, callable $next): bool
    {
        $userId = $connection->getUserId();

        // Must be authenticated first
        if ($userId === null) {
            error_log("[Premium Middleware] Denied: Not authenticated for channel '{$channelName}'");
            return false;
        }

        // Check if user is premium
        $isPremium = $this->checkPremiumStatus($connection);

        if (!$isPremium) {
            error_log("[Premium Middleware] Denied: User {$userId} is not premium for channel '{$channelName}'");
            return false;
        }

        // Log success
        error_log("[Premium Middleware] Allowed: User {$userId} has premium access to '{$channelName}'");

        // Pass to next middleware
        return $next($connection, $channelName);
    }

    /**
     * Check if user has premium status.
     *
     * In production, this would query database or check subscription service.
     * For demo, we check connection metadata.
     *
     * @param ConnectionInterface $connection
     * @return bool
     */
    private function checkPremiumStatus(ConnectionInterface $connection): bool
    {
        // Method 1: Check from connection metadata (set during auth)
        $isPremium = $connection->get('is_premium', false);
        if ($isPremium) {
            return true;
        }

        // Method 2: Check if user has 'premium' role
        $roles = $connection->get('roles', []);
        if (in_array('premium', $roles, true)) {
            return true;
        }

        // Method 3: In production, query database
        // Example:
        // $userId = $connection->getUserId();
        // $user = User::find($userId);
        // return $user && $user->isPremium();

        return false;
    }
}

