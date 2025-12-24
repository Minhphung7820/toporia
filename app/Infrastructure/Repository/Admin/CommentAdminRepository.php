<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository\Admin;

use App\Infrastructure\Persistence\Models\CommentModel;
use App\Infrastructure\Persistence\Models\PostModel;
use Toporia\Framework\Repository\BaseRepository;
use Toporia\Framework\Support\Pagination\Paginator;

/**
 * Comment Admin Repository
 *
 * Extends framework BaseRepository for admin comment management.
 */
final class CommentAdminRepository extends BaseRepository
{
    protected string $model = CommentModel::class;

    /**
     * Get paginated comments with optional filters.
     * Uses morph map to resolve 'Post' → PostModel automatically.
     */
    public function getPaginated(array $filters = [], int $perPage = 20, int $page = 1): Paginator
    {
        return $this
            ->with(['user', 'commentable', 'attachments'])
            ->applyFilters($filters)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, $page);
    }

    /**
     * Get comments with cursor-based pagination.
     * Optimized for large tables - uses indexed id column instead of OFFSET.
     *
     * @param array $filters Filter criteria
     * @param int $limit Number of records per page
     * @param int|null $cursor Last comment ID (load comments before this ID)
     * @return array{data: array, next_cursor: int|null, has_more: bool}
     */
    public function getCursorPaginated(array $filters = [], int $limit = 20, ?int $cursor = null): array
    {
        $query = CommentModel::query()
            ->with(['user', 'commentable', 'attachments'])
            ->orderBy('id', 'desc');

        // Apply filters
        $this->applyCursorFilters($query, $filters);

        // Cursor-based pagination: get records with id < cursor
        if ($cursor !== null) {
            $query->where('id', '<', $cursor);
        }

        // Get one more than limit to check if there are more
        $comments = $query->limit($limit + 1)->get();

        $hasMore = $comments->count() > $limit;
        if ($hasMore) {
            $comments = $comments->slice(0, $limit);
        }

        $nextCursor = $hasMore && $comments->count() > 0
            ? $comments->last()->id
            : null;

        return [
            'data' => $comments->toArray(),
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Apply filters to cursor query.
     */
    private function applyCursorFilters($query, array $filters): void
    {
        if (isset($filters['is_approved']) && $filters['is_approved'] !== '') {
            $query->where('is_approved', (bool) $filters['is_approved']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('content', 'LIKE', "%{$search}%");
        }

        if (!empty($filters['post_id'])) {
            $query->where('commentable_id', (int) $filters['post_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['author_id'])) {
            $query->where('user_id', (int) $filters['author_id']);
        }

        if (!empty($filters['time_range'])) {
            $this->applyTimeRangeFilter($query, $filters['time_range']);
        }

        if (!empty($filters['post_author_id'])) {
            $authorId = (int) $filters['post_author_id'];
            $query->where('commentable_type', 'Post');
            $postIds = PostModel::where('author_id', $authorId)->pluck('id')->toArray();
            if (!empty($postIds)) {
                $query->whereIn('commentable_id', $postIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }
    }

    /**
     * Apply time range filter to query.
     *
     * @param mixed $query Query builder instance
     * @param string $timeRange Time range preset or custom range
     */
    private function applyTimeRangeFilter($query, string $timeRange): void
    {
        // Check if it's a custom range (format: YYYY-MM-DD_YYYY-MM-DD)
        if (str_contains($timeRange, '_')) {
            [$from, $to] = explode('_', $timeRange, 2);
            $query->whereBetween('created_at', [
                $from . ' 00:00:00',
                $to . ' 23:59:59',
            ]);
            return;
        }

        // Preset time ranges
        $now = new \DateTime();
        $startTime = clone $now;

        match ($timeRange) {
            '1h' => $startTime->modify('-1 hour'),
            '5h' => $startTime->modify('-5 hours'),
            '24h' => $startTime->modify('-24 hours'),
            '72h' => $startTime->modify('-72 hours'),
            '7d' => $startTime->modify('-7 days'),
            'older' => $startTime->modify('-30 days'),
            default => null,
        };

        if ($timeRange === 'older') {
            // Older than 7 days
            $query->where('created_at', '<', (clone $now)->modify('-7 days')->format('Y-m-d H:i:s'));
        } else {
            // Between start time and now
            $query->where('created_at', '>=', $startTime->format('Y-m-d H:i:s'));
        }
    }

    /**
     * Get pending comments (not approved).
     *
     * @param int $perPage
     * @param int $page
     * @param int|null $postAuthorId If provided, only returns pending comments on posts by this author
     */
    public function getPending(int $perPage = 20, int $page = 1, ?int $postAuthorId = null): Paginator
    {
        $repo = $this
            ->with(['user', 'commentable', 'attachments'])
            ->scope(fn($q) => $q->where('status', CommentModel::STATUS_PENDING));

        // Filter by post author if specified (for editors)
        if ($postAuthorId !== null) {
            $repo->scope(function ($query) use ($postAuthorId) {
                $query->where('commentable_type', 'Post');
                // Subquery to filter by post author_id
                $postIds = PostModel::where('author_id', $postAuthorId)->pluck('id')->toArray();
                if (!empty($postIds)) {
                    $query->whereIn('commentable_id', $postIds);
                } else {
                    // No posts by this author, return empty result
                    $query->whereRaw('1 = 0');
                }
            });
        }

        return $repo
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, $page);
    }

    protected function applyFilters(array $filters): static
    {
        if (!empty($filters)) {
            $this->scope(function ($query) use ($filters) {
                if (isset($filters['is_approved']) && $filters['is_approved'] !== '') {
                    $query->where('is_approved', (bool) $filters['is_approved']);
                }

                if (!empty($filters['search'])) {
                    $search = $filters['search'];
                    $query->where('content', 'LIKE', "%{$search}%");
                }

                if (!empty($filters['post_id'])) {
                    $query->where('commentable_id', (int) $filters['post_id']);
                }

                // Filter by status
                if (!empty($filters['status'])) {
                    $query->where('status', $filters['status']);
                }

                // Filter by post author (for editors - only see comments on their posts)
                if (!empty($filters['post_author_id'])) {
                    $authorId = (int) $filters['post_author_id'];
                    $query->where('commentable_type', 'Post');
                    $postIds = PostModel::where('author_id', $authorId)->pluck('id')->toArray();
                    if (!empty($postIds)) {
                        $query->whereIn('commentable_id', $postIds);
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                }
            });
        }
        return $this;
    }

    /**
     * Get comment statistics.
     *
     * @param int|null $postAuthorId If provided, only returns stats for comments on posts by this author
     */
    public function getStatistics(?int $postAuthorId = null): array
    {
        if ($postAuthorId === null) {
            return [
                'total' => $this->count(),
                'approved' => $this->countWhere(['status' => CommentModel::STATUS_APPROVED]),
                'pending' => $this->countWhere(['status' => CommentModel::STATUS_PENDING]),
                'rejected' => $this->countWhere(['status' => CommentModel::STATUS_REJECTED]),
            ];
        }

        // Filter stats by post author
        $postIds = PostModel::where('author_id', $postAuthorId)->pluck('id')->toArray();

        if (empty($postIds)) {
            return ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0];
        }

        $total = CommentModel::where('commentable_type', 'Post')
            ->whereIn('commentable_id', $postIds)
            ->count();

        $approved = CommentModel::where('commentable_type', 'Post')
            ->whereIn('commentable_id', $postIds)
            ->where('status', CommentModel::STATUS_APPROVED)
            ->count();

        $pending = CommentModel::where('commentable_type', 'Post')
            ->whereIn('commentable_id', $postIds)
            ->where('status', CommentModel::STATUS_PENDING)
            ->count();

        $rejected = CommentModel::where('commentable_type', 'Post')
            ->whereIn('commentable_id', $postIds)
            ->where('status', CommentModel::STATUS_REJECTED)
            ->count();

        return [
            'total' => $total,
            'approved' => $approved,
            'pending' => $pending,
            'rejected' => $rejected,
        ];
    }

    /**
     * Find comment with user, commentable and attachments relations.
     *
     * @param int $id
     * @return mixed
     */
    public function findWithRelations(int $id): mixed
    {
        return $this->with(['user', 'commentable', 'attachments'])->find($id);
    }

    public function approve(int $id): bool
    {
        $comment = $this->find($id);
        if ($comment) {
            $this->update($id, [
                'is_approved' => true,
                'status' => CommentModel::STATUS_APPROVED,
            ]);
            return true;
        }
        return false;
    }

    public function reject(int $id): bool
    {
        $comment = $this->find($id);
        if ($comment) {
            $this->update($id, [
                'is_approved' => false,
                'status' => CommentModel::STATUS_REJECTED,
            ]);
            return true;
        }
        return false;
    }

    public function bulkApprove(array $ids): int
    {
        return $this->updateWhere(['id' => $ids], [
            'is_approved' => true,
            'status' => CommentModel::STATUS_APPROVED,
        ]);
    }

    public function bulkReject(array $ids): int
    {
        return $this->updateWhere(['id' => $ids], [
            'is_approved' => false,
            'status' => CommentModel::STATUS_REJECTED,
        ]);
    }

    public function bulkDelete(array $ids): int
    {
        return $this->deleteWhere(['id' => $ids]);
    }
}
