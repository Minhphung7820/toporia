<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime\Middleware;

use Toporia\Framework\Realtime\Middleware\ChannelMiddlewareInterface;
use Toporia\Framework\Realtime\Contracts\ConnectionInterface;

/**
 * Verified Middleware
 *
 * Kiểm tra user đã verify email chưa.
 * Chỉ verified users mới được access sensitive channels.
 *
 * Example usage:
 *   ChannelRoute::channel('verified-only', fn($conn) => true)
 *       ->middleware(['auth', 'verified']);
 *
 * @package App\Infrastructure\Realtime\Middleware
 */
final class VerifiedMiddleware implements ChannelMiddlewareInterface
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
            error_log("[Verified Middleware] Denied: Not authenticated for channel '{$channelName}'");
            return false;
        }

        // Check if user is verified
        $isVerified = $this->checkVerifiedStatus($connection);

        if (!$isVerified) {
            error_log("[Verified Middleware] Denied: User {$userId} email not verified for channel '{$channelName}'");
            return false;
        }

        // Log success
        error_log("[Verified Middleware] Allowed: User {$userId} is verified for '{$channelName}'");

        // Pass to next middleware
        return $next($connection, $channelName);
    }

    /**
     * Check if user has verified email.
     *
     * In production, this would query database.
     * For demo, we check connection metadata.
     *
     * @param ConnectionInterface $connection
     * @return bool
     */
    private function checkVerifiedStatus(ConnectionInterface $connection): bool
    {
        // Method 1: Check from connection metadata (set during auth)
        $isVerified = $connection->get('email_verified', false);
        if ($isVerified) {
            return true;
        }

        // Method 2: Check if user has 'verified' in roles
        $roles = $connection->get('roles', []);
        if (in_array('verified', $roles, true)) {
            return true;
        }

        // Method 3: In production, query database
        // Example:
        // $userId = $connection->getUserId();
        // $user = User::find($userId);
        // return $user && $user->email_verified_at !== null;

        return false;
    }
}

