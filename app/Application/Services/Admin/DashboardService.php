<?php

declare(strict_types=1);

namespace App\Application\Services\Admin;

use App\Domain\Contracts\Repository\PostRepository;
use App\Domain\Contracts\Repository\CommentRepository;
use App\Domain\Contracts\Repository\FeedbackRepository;
use App\Domain\Contracts\Repository\UserRepository;
use App\Infrastructure\Persistence\Models\AdminActivityLogModel;

/**
 * Dashboard Service - Provides admin dashboard data.
 *
 * Following Service pattern with consistent return format.
 */
final class DashboardService
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly CommentRepository $commentRepository,
        private readonly FeedbackRepository $feedbackRepository
    ) {}

    /**
     * Get dashboard statistics.
     *
     * @return array{success: bool, data?: array, message: string}
     */
    public function getStatistics(): array
    {
        $postStats = $this->postRepository->getStatistics();
        $pendingComments = $this->commentRepository->countPending();
        $pendingFeedback = $this->feedbackRepository->countPending();

        return [
            'success' => true,
            'data' => [
                'posts' => [
                    'total' => $postStats['total'],
                    'published' => $postStats['published'],
                    'drafts' => $postStats['by_status']['draft'] ?? 0,
                    'scheduled' => $postStats['by_status']['scheduled'] ?? 0,
                ],
                'views' => [
                    'total' => $postStats['total_views'],
                ],
                'comments' => [
                    'total' => $this->commentRepository->countAll(),
                    'pending' => $pendingComments,
                    'approved' => $this->commentRepository->countApproved(),
                ],
                'feedback' => [
                    'total' => $this->feedbackRepository->countAll(),
                    'pending' => $pendingFeedback,
                ],
                'alerts' => [
                    'pending_comments' => $pendingComments,
                    'pending_feedback' => $pendingFeedback,
                ],
            ],
            'message' => 'Statistics retrieved successfully',
        ];
    }

    /**
     * Get recent admin activity.
     *
     * @param int $limit Number of activities
     * @return array{success: bool, data?: array, message: string}
     */
    public function getRecentActivity(int $limit = 10): array
    {
        $activities = AdminActivityLogModel::recent($limit)
            ->with(['user'])
            ->get();

        $formattedActivities = [];
        foreach ($activities as $activity) {
            $formattedActivities[] = [
                'id' => $activity->id,
                'user' => $activity->user ? [
                    'id' => $activity->user->id,
                    'name' => $activity->user->name,
                ] : null,
                'action' => $activity->action,
                'description' => $activity->description,
                'model_type' => $activity->model_type,
                'model_id' => $activity->model_id,
                'created_at' => $activity->created_at,
            ];
        }

        return [
            'success' => true,
            'data' => [
                'activities' => $formattedActivities,
            ],
            'message' => 'Activity retrieved successfully',
        ];
    }

    /**
     * Get popular posts for dashboard.
     *
     * @param int $limit Number of posts
     * @return array{success: bool, data?: array, message: string}
     */
    public function getPopularPosts(int $limit = 5): array
    {
        $posts = $this->postRepository->findMostViewed($limit);

        return [
            'success' => true,
            'data' => [
                'posts' => array_map(fn($post) => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'slug' => $post->slug,
                    'views' => $post->views,
                    'published_at' => $post->publishedAt?->format('Y-m-d'),
                ], $posts),
            ],
            'message' => 'Popular posts retrieved successfully',
        ];
    }

    /**
     * Get recent comments for dashboard.
     *
     * @param int $limit Number of comments
     * @return array{success: bool, data?: array, message: string}
     */
    public function getRecentComments(int $limit = 5): array
    {
        $comments = $this->commentRepository->findRecent($limit);

        return [
            'success' => true,
            'data' => [
                'comments' => array_map(fn($comment) => $comment->toArray(), $comments),
            ],
            'message' => 'Recent comments retrieved successfully',
        ];
    }

    /**
     * Get chart data for views over time.
     *
     * @param string $period Period (week, month, year)
     * @return array{success: bool, data?: array, message: string}
     */
    public function getChartData(string $period = 'week'): array
    {
        // This would typically query a views_log or analytics table
        // For now, return placeholder data structure
        $labels = [];
        $values = [];

        $days = match ($period) {
            'week' => 7,
            'month' => 30,
            'year' => 12,
            default => 7,
        };

        for ($i = $days - 1; $i >= 0; $i--) {
            if ($period === 'year') {
                $date = new \DateTime("-{$i} months");
                $labels[] = $date->format('M Y');
            } else {
                $date = new \DateTime("-{$i} days");
                $labels[] = $date->format('M d');
            }
            $values[] = rand(50, 500); // Placeholder - would be real data
        }

        return [
            'success' => true,
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Views',
                        'data' => $values,
                    ],
                ],
            ],
            'message' => 'Chart data retrieved successfully',
        ];
    }

    /**
     * Get quick stats summary.
     *
     * @return array{success: bool, data?: array, message: string}
     */
    public function getQuickStats(): array
    {
        return [
            'success' => true,
            'data' => [
                'today_views' => rand(100, 500), // Would be from analytics
                'new_comments' => $this->commentRepository->countPending(),
                'new_feedback' => $this->feedbackRepository->countPending(),
                'scheduled_posts' => $this->postRepository->countByStatus()['scheduled'] ?? 0,
            ],
            'message' => 'Quick stats retrieved successfully',
        ];
    }
}
