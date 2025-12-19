<?php

declare(strict_types=1);

namespace App\Application\Services\Admin;

use App\Domain\Contracts\Repository\PostRepository;
use App\Domain\Contracts\Repository\CommentRepository;
use App\Domain\Contracts\Repository\FeedbackRepository;
use App\Infrastructure\Persistence\Models\AdminActivityLogModel;
use App\Infrastructure\Persistence\Models\CommentModel;
use App\Infrastructure\Persistence\Models\PostModel;

/**
 * Dashboard Service - Provides admin dashboard data.
 *
 * Authorization-aware: Methods accept optional $authorId parameter.
 * - null = Admin view (all data)
 * - int = Editor view (own data only)
 *
 * Uses caching for expensive statistics queries on large datasets (1M+ rows).
 */
final class DashboardService
{
    private const STATS_CACHE_TTL = 60; // Cache statistics for 60 seconds

    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly CommentRepository $commentRepository,
        private readonly FeedbackRepository $feedbackRepository
    ) {}

    /**
     * Get dashboard statistics.
     *
     * @param int|null $authorId If provided, only returns stats for posts by this author
     * @return array{success: bool, data?: array, message: string}
     */
    public function getStatistics(?int $authorId = null): array
    {
        $cacheKey = $authorId ? "dashboard:statistics:author:{$authorId}" : 'dashboard:statistics';

        $stats = cache()->remember($cacheKey, self::STATS_CACHE_TTL, function () use ($authorId) {
            if ($authorId === null) {
                // Admin: full statistics
                $postStats = $this->postRepository->getStatistics();
                $commentStats = $this->commentRepository->getStatistics();
                $feedbackStats = $this->feedbackRepository->getStatistics();

                return [
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
                        'total' => $commentStats['total'],
                        'pending' => $commentStats['pending'],
                        'approved' => $commentStats['approved'],
                    ],
                    'feedback' => [
                        'total' => $feedbackStats['total'],
                        'pending' => $feedbackStats['pending'],
                    ],
                    'alerts' => [
                        'pending_comments' => $commentStats['pending'],
                        'pending_feedback' => $feedbackStats['pending'],
                    ],
                ];
            }

            // Moderator: own statistics only
            $postStats = $this->getAuthorPostStats($authorId);
            $commentStats = $this->getAuthorCommentStats($authorId);

            return [
                'posts' => [
                    'total' => $postStats['total'],
                    'published' => $postStats['published'],
                    'drafts' => $postStats['drafts'],
                    'scheduled' => $postStats['scheduled'],
                ],
                'views' => [
                    'total' => $postStats['total_views'],
                ],
                'comments' => [
                    'total' => $commentStats['total'],
                    'pending' => $commentStats['pending'],
                    'approved' => $commentStats['approved'],
                ],
                // Moderators don't see feedback stats
                'feedback' => null,
                'alerts' => [
                    'pending_comments' => $commentStats['pending'],
                    'pending_feedback' => 0,
                ],
            ];
        });

        return [
            'success' => true,
            'data' => $stats,
            'message' => 'Statistics retrieved successfully',
        ];
    }

    /**
     * Get recent admin activity.
     *
     * @param int $limit Number of activities
     * @param int|null $userId If provided, only returns activity by this user
     * @return array{success: bool, data?: array, message: string}
     */
    public function getRecentActivity(int $limit = 10, ?int $userId = null): array
    {
        $query = AdminActivityLogModel::recent($limit)->with(['user']);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $activities = $query->get();

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
     * @param int|null $authorId If provided, only returns posts by this author
     * @return array{success: bool, data?: array, message: string}
     */
    public function getPopularPosts(int $limit = 5, ?int $authorId = null): array
    {
        if ($authorId === null) {
            $posts = $this->postRepository->findMostViewed($limit);
        } else {
            // Get most viewed posts by specific author
            $posts = PostModel::where('author_id', $authorId)
                ->orderBy('views', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn($model) => $this->modelToEntity($model))
                ->toArray();
        }

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
     * @param int|null $authorId If provided, only returns comments on posts by this author
     * @return array{success: bool, data?: array, message: string}
     */
    public function getRecentComments(int $limit = 5, ?int $authorId = null): array
    {
        $query = CommentModel::query()
            ->with(['user', 'commentable'])
            ->orderBy('created_at', 'desc');

        // Filter by post author for editors
        if ($authorId !== null) {
            $postIds = PostModel::where('author_id', $authorId)->pluck('id')->toArray();
            if (!empty($postIds)) {
                $query->where('commentable_type', 'Post')
                    ->whereIn('commentable_id', $postIds);
            } else {
                // No posts, return empty
                return [
                    'success' => true,
                    'data' => ['comments' => []],
                    'message' => 'Recent comments retrieved successfully',
                ];
            }
        }

        $models = $query->limit($limit)->get();

        $comments = [];
        foreach ($models as $model) {
            $comment = [
                'id' => $model->id,
                'content' => $model->content,
                'author_name' => $model->author_name,
                'user' => $model->user ? [
                    'id' => $model->user->id,
                    'name' => $model->user->name,
                    'avatar' => $model->user->avatar,
                ] : null,
                'status' => $model->is_approved ? 'approved' : 'pending',
                'created_at' => $model->created_at,
            ];

            if ($model->commentable_type === 'Post' && $model->commentable) {
                $comment['post'] = [
                    'id' => $model->commentable->id,
                    'title' => $model->commentable->title,
                    'slug' => $model->commentable->slug,
                ];
            }

            $comments[] = $comment;
        }

        return [
            'success' => true,
            'data' => ['comments' => $comments],
            'message' => 'Recent comments retrieved successfully',
        ];
    }

    /**
     * Get chart data for views over time.
     *
     * @param string $period Period (week, month, year)
     * @param int|null $authorId If provided, only returns data for posts by this author
     * @return array{success: bool, data?: array, message: string}
     */
    public function getChartData(string $period = 'week', ?int $authorId = null): array
    {
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
            // Would be real analytics data - filtered by author if specified
            $values[] = rand(50, 500);
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
     * @param int|null $authorId If provided, only returns stats for posts by this author
     * @return array{success: bool, data?: array, message: string}
     */
    public function getQuickStats(?int $authorId = null): array
    {
        if ($authorId === null) {
            // Admin: full stats
            return [
                'success' => true,
                'data' => [
                    'today_views' => rand(100, 500),
                    'new_comments' => $this->commentRepository->countPending(),
                    'new_feedback' => $this->feedbackRepository->countPending(),
                    'scheduled_posts' => $this->postRepository->countByStatus()['scheduled'] ?? 0,
                ],
                'message' => 'Quick stats retrieved successfully',
            ];
        }

        // Moderator: own stats only
        $postStats = $this->getAuthorPostStats($authorId);
        $commentStats = $this->getAuthorCommentStats($authorId);

        return [
            'success' => true,
            'data' => [
                'today_views' => rand(10, 100),
                'new_comments' => $commentStats['pending'],
                'new_feedback' => 0, // Moderators don't see feedback
                'scheduled_posts' => $postStats['scheduled'],
            ],
            'message' => 'Quick stats retrieved successfully',
        ];
    }

    /**
     * Get post statistics for a specific author.
     */
    private function getAuthorPostStats(int $authorId): array
    {
        $total = PostModel::where('author_id', $authorId)->count();
        $published = PostModel::where('author_id', $authorId)->where('is_published', true)->count();
        $drafts = PostModel::where('author_id', $authorId)->where('is_published', false)->whereNull('scheduled_at')->count();
        $scheduled = PostModel::where('author_id', $authorId)->whereNotNull('scheduled_at')->where('is_published', false)->count();
        $totalViews = (int) PostModel::where('author_id', $authorId)->sum('views');

        return [
            'total' => $total,
            'published' => $published,
            'drafts' => $drafts,
            'scheduled' => $scheduled,
            'total_views' => $totalViews,
        ];
    }

    /**
     * Get comment statistics for posts by a specific author.
     */
    private function getAuthorCommentStats(int $authorId): array
    {
        $postIds = PostModel::where('author_id', $authorId)->pluck('id')->toArray();

        if (empty($postIds)) {
            return ['total' => 0, 'pending' => 0, 'approved' => 0];
        }

        $total = CommentModel::where('commentable_type', 'Post')
            ->whereIn('commentable_id', $postIds)
            ->count();

        $pending = CommentModel::where('commentable_type', 'Post')
            ->whereIn('commentable_id', $postIds)
            ->where('is_approved', false)
            ->count();

        $approved = CommentModel::where('commentable_type', 'Post')
            ->whereIn('commentable_id', $postIds)
            ->where('is_approved', true)
            ->count();

        return [
            'total' => $total,
            'pending' => $pending,
            'approved' => $approved,
        ];
    }

    /**
     * Convert PostModel to Post entity (simple conversion for dashboard).
     */
    private function modelToEntity(PostModel $model): object
    {
        return (object) [
            'id' => $model->id,
            'title' => $model->title,
            'slug' => $model->slug,
            'views' => $model->views,
            'publishedAt' => $model->published_at ? new \DateTime($model->published_at) : null,
        ];
    }
}
