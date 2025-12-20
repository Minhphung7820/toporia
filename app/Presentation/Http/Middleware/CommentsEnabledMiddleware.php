<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use App\Application\Services\SiteSettingsService;
use Toporia\Framework\Http\Contracts\MiddlewareInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Comments Enabled Middleware.
 *
 * Blocks comment-related API requests when comments are disabled in settings.
 * This prevents users from bypassing frontend restrictions using Postman/cURL.
 */
final class CommentsEnabledMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Response $response, callable $next): mixed
    {
        $settings = SiteSettingsService::getInstance();

        if (!$settings->commentsEnabled()) {
            $response->json([
                'success' => false,
                'message' => 'Comments are disabled',
            ], 403);
            return null;
        }

        return $next($request, $response);
    }
}
