<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository\Admin;

use App\Infrastructure\Persistence\Models\CommentModel;
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
     */
    public function getPending(int $perPage = 20, int $page = 1): Paginator
    {
        return $this
            ->with(['user', 'commentable'])
            ->scope(fn($q) => $q->where('is_approved', false))
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
                    $query->where('commentable_type', 'App\\Infrastructure\\Persistence\\Models\\PostModel')
                          ->where('commentable_id', (int) $filters['post_id']);
                }
            });
        }
        return $this;
    }

    public function getStatistics(): array
    {
        return [
            'total' => $this->count(),
            'approved' => $this->countWhere(['is_approved' => true]),
            'pending' => $this->countWhere(['is_approved' => false]),
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
