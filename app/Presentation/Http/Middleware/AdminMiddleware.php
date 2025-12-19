<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use Toporia\Framework\Http\Contracts\MiddlewareInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Admin middleware.
 *
 * Ensures the authenticated user has admin role.
 * Must be used after authentication middleware.
 */
final class AdminMiddleware implements MiddlewareInterface
{
    /**
     * Allowed roles that can access admin routes.
     */
    private const ADMIN_ROLES = ['admin', 'moderator'];

    /**
     * Handle admin role check.
     *
     * @param Request $request
     * @param Response $response
     * @param callable $next
     * @return mixed
     */
    public function handle(Request $request, Response $response, callable $next): mixed
    {
        $auth = auth();

        // First check if user is authenticated
        if (!$auth->check()) {
            if ($request->expectsJson()) {
                $response->json(['error' => 'Unauthenticated', 'message' => 'Please login to continue'], 401);
                return null;
            }

            $response->redirect('/login');
            return null;
        }

        // Get user and check role
        $user = $auth->user();

        if (!$user || !$this->hasAdminAccess($user)) {
            if ($request->expectsJson()) {
                $response->json([
                    'error' => 'Forbidden',
                    'message' => 'You do not have permission to access this resource'
                ], 403);
                return null;
            }

            $response->redirect('/');
            return null;
        }

        return $next($request, $response);
    }

    /**
     * Check if user has admin access.
     *
     * @param mixed $user
     * @return bool
     */
    private function hasAdminAccess(mixed $user): bool
    {
        // Check if user has role property/method
        if (method_exists($user, 'getRole')) {
            return in_array($user->getRole(), self::ADMIN_ROLES);
        }

        if (property_exists($user, 'role')) {
            return in_array($user->role, self::ADMIN_ROLES);
        }

        // Check via array access
        if ($user instanceof \ArrayAccess && isset($user['role'])) {
            return in_array($user['role'], self::ADMIN_ROLES);
        }

        return false;
    }
}
