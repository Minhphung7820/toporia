<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Admin;

use App\Application\Services\Admin\DashboardService;
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Dashboard Controller - Admin dashboard endpoints.
 */
final class DashboardController extends BaseController
{
    public function __construct(
        Request $request,
        Response $response,
        private readonly DashboardService $dashboardService
    ) {
        parent::__construct($request, $response);
    }

    /**
     * Get dashboard statistics.
     *
     * GET /api/admin/dashboard/statistics
     */
    public function statistics(): JsonResponseInterface
    {
        $result = $this->dashboardService->getStatistics();

        return $this->json($result);
    }

    /**
     * Get recent admin activity.
     *
     * GET /api/admin/dashboard/activity
     */
    public function activity(Request $request): JsonResponseInterface
    {
        $limit = min((int) $request->query('limit', 10), 50);

        $result = $this->dashboardService->getRecentActivity($limit);

        return $this->json($result);
    }

    /**
     * Get popular posts for dashboard.
     *
     * GET /api/admin/dashboard/popular-posts
     */
    public function popularPosts(Request $request): JsonResponseInterface
    {
        $limit = min((int) $request->query('limit', 5), 20);

        $result = $this->dashboardService->getPopularPosts($limit);

        return $this->json($result);
    }

    /**
     * Get recent comments for dashboard.
     *
     * GET /api/admin/dashboard/recent-comments
     */
    public function recentComments(Request $request): JsonResponseInterface
    {
        $limit = min((int) $request->query('limit', 5), 20);

        $result = $this->dashboardService->getRecentComments($limit);

        return $this->json($result);
    }

    /**
     * Get chart data for views.
     *
     * GET /api/admin/dashboard/charts
     */
    public function charts(Request $request): JsonResponseInterface
    {
        $period = $request->query('period', 'week');

        if (!in_array($period, ['week', 'month', 'year'])) {
            $period = 'week';
        }

        $result = $this->dashboardService->getChartData($period);

        return $this->json($result);
    }

    /**
     * Get quick stats summary.
     *
     * GET /api/admin/dashboard/quick-stats
     */
    public function quickStats(): JsonResponseInterface
    {
        $result = $this->dashboardService->getQuickStats();

        return $this->json($result);
    }
}
