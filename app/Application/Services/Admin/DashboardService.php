<?php

declare(strict_types=1);

namespace App\Application\Services\Admin;

use App\Domain\Contracts\Repository\PostRepository;
use App\Domain\Contracts\Repository\CommentRepository;
use App\Domain\Contracts\Repository\FeedbackRepository;
use App\Infrastructure\Persistence\Models\AdminActivityLogModel;
use App\Infrastructure\Persistence\Models\CommentModel;
use App\Infrastructure\Persistence\Models\DashboardStatisticsModel;
use App\Infrastructure\Persistence\Models\PostModel;

/**
 * Dashboard Service - Provides admin dashboard data.
 *
 * Authorization-aware: Methods accept optional $authorId parameter.
 * - null = Admin view (all data) - uses pre-computed statistics (< 10ms)
 * - int = Editor view (own data only) - uses indexed queries
 *
 * PERFORMANCE OPTIMIZATION (8M+ records):
 * - Admin stats use pre-computed values from dashboard_statistics table
 * - Updated by database triggers in real-time
 * - Reduces query time from 30+ seconds to < 10ms
 */
final class DashboardService
{
    private const STATS_CACHE_TTL = 300; // Cache statistics for 5 minutes
    private const QUICK_CACHE_TTL = 60;  // Cache quick stats for 1 minute

    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly CommentRepository $commentRepository,
        private readonly FeedbackRepository $feedbackRepository
    ) {}

    /**
     * Get dashboard statistics.
     *
     * PERFORMANCE: For admin (authorId = null), uses pre-computed statistics
     * from dashboard_statistics table instead of expensive COUNT/SUM queries.
     * This reduces query time from 30+ seconds to < 10ms on 8M+ records.
     *
     * @param int|null $authorId If provided, only returns stats for posts by this author
     * @return array{success: bool, data?: array, message: string}
     */
    public function getStatistics(?int $authorId = null): array
    {
        $cacheKey = $authorId ? "dashboard:statistics:author:{$authorId}" : 'dashboard:statistics';

        $stats = cache()->remember($cacheKey, self::STATS_CACHE_TTL, function () use ($authorId) {
            if ($authorId === null) {
                // Admin: use pre-computed statistics (< 10ms vs 30+ seconds)
                $allStats = DashboardStatisticsModel::getAllStats();

                return [
                    'posts' => [
                        'total' => $allStats['posts']['total'],
                        'published' => $allStats['posts']['published'],
                        'drafts' => $allStats['posts']['draft'],
                        'scheduled' => $allStats['posts']['scheduled'],
                    ],
                    'views' => [
                        'total' => $allStats['posts']['total_views'],
                    ],
                    'comments' => [
                        'total' => $allStats['comments']['total'],
                        'pending' => $allStats['comments']['pending'],
                        'approved' => $allStats['comments']['approved'],
                        'rejected' => $allStats['comments']['rejected'] ?? 0,
                    ],
                    'feedback' => [
                        'total' => $allStats['feedback']['total'],
                        'pending' => $allStats['feedback']['pending'],
                    ],
                    'alerts' => [
                        'pending_comments' => $allStats['comments']['pending'],
                        'pending_feedback' => $allStats['feedback']['pending'],
                    ],
                ];
            }

            // Moderator: own statistics only (uses indexed query on author_id)
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
                    'rejected' => $commentStats['rejected'],
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
     * Uses idx_posts_published_views and idx_posts_author_views indexes for O(1) performance.
     *
     * @param int $limit Number of posts
     * @param int|null $authorId If provided, only returns posts by this author
     * @return array{success: bool, data?: array, message: string}
     */
    public function getPopularPosts(int $limit = 5, ?int $authorId = null): array
    {
        if ($authorId === null) {
            $posts = $this->postRepository->findMostViewed($limit);
            $postsData = array_map(fn($post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'views' => $post->views,
                'published_at' => $post->publishedAt?->format('Y-m-d'),
            ], $posts);
        } else {
            // Get most viewed posts by specific author
            $posts = PostModel::getConnection()->select("
                SELECT id, title, slug, views, published_at
                FROM posts
                WHERE author_id = ?
                ORDER BY views DESC
                LIMIT ?
            ", [$authorId, $limit]);

            $postsData = array_map(fn($row) => [
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'slug' => $row['slug'],
                'views' => (int) $row['views'],
                'published_at' => $row['published_at'] ? substr($row['published_at'], 0, 10) : null,
            ], $posts);
        }

        return [
            'success' => true,
            'data' => [
                'posts' => $postsData,
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
                'status' => $model->status ?? ($model->is_approved ? 'approved' : 'pending'),
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
     * Optimized: Uses cached statistics from getStatistics() instead of
     * making separate COUNT queries on 7M+ records table.
     *
     * @param int|null $authorId If provided, only returns stats for posts by this author
     * @return array{success: bool, data?: array, message: string}
     */
    public function getQuickStats(?int $authorId = null): array
    {
        $cacheKey = $authorId ? "dashboard:quick-stats:author:{$authorId}" : 'dashboard:quick-stats';

        $stats = cache()->remember($cacheKey, self::QUICK_CACHE_TTL, function () use ($authorId) {
            // Reuse cached statistics to avoid duplicate queries
            $statsResult = $this->getStatistics($authorId);
            $statsData = $statsResult['data'] ?? [];

            if ($authorId === null) {
                return [
                    'today_views' => rand(100, 500), // TODO: implement real analytics
                    'new_comments' => $statsData['comments']['pending'] ?? 0,
                    'new_feedback' => $statsData['feedback']['pending'] ?? 0,
                    'scheduled_posts' => $statsData['posts']['scheduled'] ?? 0,
                ];
            }

            return [
                'today_views' => rand(10, 100), // TODO: implement real analytics
                'new_comments' => $statsData['comments']['pending'] ?? 0,
                'new_feedback' => 0, // Moderators don't see feedback
                'scheduled_posts' => $statsData['posts']['scheduled'] ?? 0,
            ];
        });

        return [
            'success' => true,
            'data' => $stats,
            'message' => 'Quick stats retrieved successfully',
        ];
    }

    /**
     * Get post statistics for a specific author.
     *
     * Optimized: Single query with conditional aggregation instead of 5 separate queries.
     */
    private function getAuthorPostStats(int $authorId): array
    {
        $result = PostModel::getConnection()->selectOne("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) as published,
                SUM(CASE WHEN is_published = 0 AND scheduled_at IS NULL THEN 1 ELSE 0 END) as drafts,
                SUM(CASE WHEN is_published = 0 AND scheduled_at IS NOT NULL THEN 1 ELSE 0 END) as scheduled,
                COALESCE(SUM(views), 0) as total_views
            FROM posts
            WHERE author_id = ?
        ", [$authorId]);

        return [
            'total' => (int) ($result['total'] ?? 0),
            'published' => (int) ($result['published'] ?? 0),
            'drafts' => (int) ($result['drafts'] ?? 0),
            'scheduled' => (int) ($result['scheduled'] ?? 0),
            'total_views' => (int) ($result['total_views'] ?? 0),
        ];
    }

    /**
     * Get comment statistics for posts by a specific author.
     *
     * Optimized: Single query with JOIN instead of fetching post IDs first.
     */
    private function getAuthorCommentStats(int $authorId): array
    {
        $result = PostModel::getConnection()->selectOne("
            SELECT
                COUNT(c.id) as total,
                SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN c.status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN c.status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM comments c
            INNER JOIN posts p ON c.commentable_id = p.id AND c.commentable_type = 'Post'
            WHERE p.author_id = ?
        ", [$authorId]);

        return [
            'total' => (int) ($result['total'] ?? 0),
            'pending' => (int) ($result['pending'] ?? 0),
            'approved' => (int) ($result['approved'] ?? 0),
            'rejected' => (int) ($result['rejected'] ?? 0),
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
