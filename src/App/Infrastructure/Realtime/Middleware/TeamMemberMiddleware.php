<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime\Middleware;

use Toporia\Framework\Realtime\Middleware\ChannelMiddlewareInterface;
use Toporia\Framework\Realtime\Contracts\ConnectionInterface;

/**
 * Team Member Middleware
 *
 * Kiểm tra user có phải là member của team không.
 * Extract team ID từ channel name và verify membership.
 *
 * Example usage:
 *   ChannelRoute::channel('team.{teamId}', fn($conn, $teamId) => true)
 *       ->middleware(['auth', 'team']);
 *
 * @package App\Infrastructure\Realtime\Middleware
 */
final class TeamMemberMiddleware implements ChannelMiddlewareInterface
{
    /**
     * Handle channel authorization.
     *
     * @param ConnectionInterface $connection Current connection
     * @param string $channelName Channel being subscribed to (e.g., 'team.123')
     * @param callable $next Next middleware in pipeline
     * @return bool True if authorized, false otherwise
     */
    public function handle(ConnectionInterface $connection, string $channelName, callable $next): bool
    {
        $userId = $connection->getUserId();

        // Must be authenticated first
        if ($userId === null) {
            error_log("[Team Middleware] Denied: Not authenticated for channel '{$channelName}'");
            return false;
        }

        // Extract team ID from channel name
        $teamId = $this->extractTeamId($channelName);

        if ($teamId === null) {
            error_log("[Team Middleware] Denied: Invalid channel pattern '{$channelName}'");
            return false;
        }

        // Check if user is team member
        if (!$this->isTeamMember($userId, $teamId)) {
            error_log("[Team Middleware] Denied: User {$userId} is not member of team {$teamId}");
            return false;
        }

        // Log success
        error_log("[Team Middleware] Allowed: User {$userId} is member of team {$teamId}");

        // Pass to next middleware
        return $next($connection, $channelName);
    }

    /**
     * Extract team ID from channel name.
     *
     * Patterns supported:
     * - 'team.123' -> 123
     * - 'team.456.chat' -> 456
     *
     * @param string $channelName
     * @return int|null
     */
    private function extractTeamId(string $channelName): ?int
    {
        // Match pattern: team.{teamId} or team.{teamId}.something
        if (preg_match('/^team\.(\d+)(?:\.|$)/', $channelName, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Check if user is member of team.
     *
     * In production, this would query database.
     * For demo, we use mock data.
     *
     * @param int $userId
     * @param int $teamId
     * @return bool
     */
    private function isTeamMember(int $userId, int $teamId): bool
    {
        // In production, query database:
        // return DB::table('team_members')
        //     ->where('team_id', $teamId)
        //     ->where('user_id', $userId)
        //     ->exists();

        // Demo: Allow for testing (always true)
        // In real app, replace with actual database query
        return true;
    }
}

