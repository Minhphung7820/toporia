<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository\Admin;

use App\Infrastructure\Persistence\Models\FeedbackModel;
use Toporia\Framework\Repository\BaseRepository;
use Toporia\Framework\Support\Pagination\Paginator;

/**
 * Feedback Admin Repository
 *
 * Extends framework BaseRepository for admin feedback management.
 */
final class FeedbackAdminRepository extends BaseRepository
{
    protected string $model = FeedbackModel::class;

    /**
     * Get paginated feedback with optional filters.
     */
    public function getPaginated(array $filters = [], int $perPage = 20, int $page = 1): Paginator
    {
        return $this
            ->with(['user', 'assignedTo'])
            ->applyFilters($filters)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, $page);
    }

    /**
     * Get pending feedback.
     */
    public function getPending(int $perPage = 20, int $page = 1): Paginator
    {
        return $this
            ->with(['user'])
            ->scope(fn($q) => $q->where('status', 'pending'))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, $page);
    }

    /**
     * Get feedback assigned to a user.
     */
    public function getAssignedTo(int $userId, int $perPage = 20, int $page = 1): Paginator
    {
        return $this
            ->with(['user'])
            ->scope(fn($q) => $q->where('assigned_to', $userId))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, $page);
    }

    protected function applyFilters(array $filters): static
    {
        if (!empty($filters)) {
            $this->scope(function ($query) use ($filters) {
                if (!empty($filters['search'])) {
                    $search = $filters['search'];
                    $query->where(function ($q) use ($search) {
                        $q->where('title', 'LIKE', "%{$search}%")
                          ->orWhere('content', 'LIKE', "%{$search}%");
                    });
                }

                if (!empty($filters['status'])) {
                    $query->where('status', $filters['status']);
                }

                if (!empty($filters['priority'])) {
                    $query->where('priority', $filters['priority']);
                }

                if (!empty($filters['type'])) {
                    $query->where('type', $filters['type']);
                }

                if (!empty($filters['assigned_to'])) {
                    $query->where('assigned_to', (int) $filters['assigned_to']);
                }
            });
        }
        return $this;
    }

    public function getStatistics(): array
    {
        return [
            'total' => $this->count(),
            'pending' => $this->countWhere(['status' => 'pending']),
            'in_progress' => $this->countWhere(['status' => 'in_progress']),
            'resolved' => $this->countWhere(['status' => 'resolved']),
            'closed' => $this->countWhere(['status' => 'closed']),
        ];
    }

    public function updateStatus(int $id, string $status): ?FeedbackModel
    {
        $feedback = $this->find($id);
        if ($feedback) {
            $data = ['status' => $status];
            if ($status === 'resolved') {
                $data['resolved_at'] = date('Y-m-d H:i:s');
            }
            return $this->update($id, $data);
        }
        return null;
    }

    public function updatePriority(int $id, string $priority): ?FeedbackModel
    {
        $feedback = $this->find($id);
        if ($feedback) {
            return $this->update($id, ['priority' => $priority]);
        }
        return null;
    }

    public function assign(int $id, ?int $userId): ?FeedbackModel
    {
        $feedback = $this->find($id);
        if ($feedback) {
            return $this->update($id, ['assigned_to' => $userId]);
        }
        return null;
    }

    public function addNotes(int $id, string $notes): ?FeedbackModel
    {
        $feedback = $this->find($id);
        if ($feedback) {
            return $this->update($id, ['admin_notes' => $notes]);
        }
        return null;
    }

    public function bulkDelete(array $ids): int
    {
        return $this->deleteWhere(['id' => $ids]);
    }
}
