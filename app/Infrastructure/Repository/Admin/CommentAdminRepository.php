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
            ->with(['user', 'commentable'])
            ->applyFilters($filters)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, $page);
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
            ->with(['user', 'commentable'])
            ->scope(fn($q) => $q->where('is_approved', false));

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

                // Map status filter to is_approved boolean
                if (!empty($filters['status'])) {
                    if ($filters['status'] === 'approved') {
                        $query->where('is_approved', true);
                    } elseif ($filters['status'] === 'pending' || $filters['status'] === 'rejected') {
                        $query->where('is_approved', false);
                    }
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
                'approved' => $this->countWhere(['is_approved' => true]),
                'pending' => $this->countWhere(['is_approved' => false]),
            ];
        }

        // Filter stats by post author
        $postIds = PostModel::where('author_id', $postAuthorId)->pluck('id')->toArray();

        if (empty($postIds)) {
            return ['total' => 0, 'approved' => 0, 'pending' => 0];
        }

        $total = CommentModel::where('commentable_type', 'Post')
            ->whereIn('commentable_id', $postIds)
            ->count();

        $approved = CommentModel::where('commentable_type', 'Post')
            ->whereIn('commentable_id', $postIds)
            ->where('is_approved', true)
            ->count();

        $pending = CommentModel::where('commentable_type', 'Post')
            ->whereIn('commentable_id', $postIds)
            ->where('is_approved', false)
            ->count();

        return [
            'total' => $total,
            'approved' => $approved,
            'pending' => $pending,
        ];
    }

    public function approve(int $id): bool
    {
        $comment = $this->find($id);
        if ($comment) {
            $this->update($id, ['is_approved' => true]);
            return true;
        }
        return false;
    }

    public function reject(int $id): bool
    {
        $comment = $this->find($id);
        if ($comment) {
            $this->update($id, ['is_approved' => false]);
            return true;
        }
        return false;
    }

    public function bulkApprove(array $ids): int
    {
        return $this->updateWhere(['id' => $ids], ['is_approved' => true]);
    }

    public function bulkReject(array $ids): int
    {
        return $this->updateWhere(['id' => $ids], ['is_approved' => false]);
    }

    public function bulkDelete(array $ids): int
    {
        return $this->deleteWhere(['id' => $ids]);
    }
}
