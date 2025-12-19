<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Admin;

use App\Application\Services\Admin\DashboardService;
use App\Application\Services\AuthorizationService;
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use Toporia\Framework\Support\Accessors\Auth;

/**
 * Dashboard Controller - Admin dashboard endpoints.
 *
 * Authorization:
 * - Admin: Sees all stats (global view)
 * - Moderator: Sees only their own posts and related stats
 */
final class DashboardController extends BaseController
{
    public function __construct(
        Request $request,
        Response $response,
        private readonly DashboardService $dashboardService,
        private readonly AuthorizationService $authService
    ) {
        parent::__construct($request, $response);
    }

    /**
     * Get dashboard statistics.
     * Admin: global stats, Moderator: own stats
     *
     * GET /api/admin/dashboard/statistics
     */
    public function statistics(): JsonResponseInterface
    {
        $user = Auth::user();
        $authorId = $this->authService->isModerator($user) ? $this->authService->getUserId($user) : null;

        $result = $this->dashboardService->getStatistics($authorId);

        return $this->json($result);
    }

    /**
     * Get recent admin activity.
     * Admin: all activity, Moderator: own activity
     *
     * GET /api/admin/dashboard/activity
     */
    public function activity(Request $request): JsonResponseInterface
    {
        $user = Auth::user();
        $userId = $this->authService->isModerator($user) ? $this->authService->getUserId($user) : null;
        $limit = min((int) $request->query('limit', 10), 50);

        $result = $this->dashboardService->getRecentActivity($limit, $userId);

        return $this->json($result);
    }

    /**
     * Get popular posts for dashboard.
     * Admin: all posts, Moderator: own posts
     *
     * GET /api/admin/dashboard/popular-posts
     */
    public function popularPosts(Request $request): JsonResponseInterface
    {
        $user = Auth::user();
        $authorId = $this->authService->isModerator($user) ? $this->authService->getUserId($user) : null;
        $limit = min((int) $request->query('limit', 5), 20);

        $result = $this->dashboardService->getPopularPosts($limit, $authorId);

        return $this->json($result);
    }

    /**
     * Get recent comments for dashboard.
     * Admin: all comments, Moderator: comments on own posts
     *
     * GET /api/admin/dashboard/recent-comments
     */
    public function recentComments(Request $request): JsonResponseInterface
    {
        $user = Auth::user();
        $authorId = $this->authService->isModerator($user) ? $this->authService->getUserId($user) : null;
        $limit = min((int) $request->query('limit', 5), 20);

        $result = $this->dashboardService->getRecentComments($limit, $authorId);

        return $this->json($result);
    }

    /**
     * Get chart data for views.
     * Admin: all posts, Moderator: own posts
     *
     * GET /api/admin/dashboard/charts
     */
    public function charts(Request $request): JsonResponseInterface
    {
        $user = Auth::user();
        $authorId = $this->authService->isModerator($user) ? $this->authService->getUserId($user) : null;
        $period = $request->query('period', 'week');

        if (!in_array($period, ['week', 'month', 'year'])) {
            $period = 'week';
        }

        $result = $this->dashboardService->getChartData($period, $authorId);

        return $this->json($result);
    }

    /**
     * Get quick stats summary.
     * Admin: global stats, Moderator: own stats
     *
     * GET /api/admin/dashboard/quick-stats
     */
    public function quickStats(): JsonResponseInterface
    {
        $user = Auth::user();
        $authorId = $this->authService->isModerator($user) ? $this->authService->getUserId($user) : null;

        $result = $this->dashboardService->getQuickStats($authorId);

        return $this->json($result);
    }
}
