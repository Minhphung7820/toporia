<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use Toporia\Framework\Http\Contracts\MiddlewareInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use App\Application\Services\AuthorizationService;

/**
 * Admin-only middleware.
 *
 * Restricts access to admin role only (excludes editors).
 * Use for: users management, settings, categories, tags, feedback.
 */
final class AdminOnlyMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthorizationService $authService
    ) {}

    public function handle(Request $request, Response $response, callable $next): mixed
    {
        $user = auth()->user();

        if (!$user || !$this->authService->isAdmin($user)) {
            if ($request->expectsJson()) {
                $response->json([
                    'success' => false,
                    'error' => 'Forbidden',
                    'message' => 'Only administrators can access this resource'
                ], 403);
                return null;
            }

            $response->redirect('/admin');
            return null;
        }

        return $next($request, $response);
    }
}
