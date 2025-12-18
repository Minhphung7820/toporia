<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Contracts\Repository\FeedbackRepository;
use App\Domain\Entities\Feedback;
use App\Infrastructure\Persistence\Models\FeedbackModel;

/**
 * PDO Feedback Repository Implementation
 *
 * Uses Toporia ORM Model for database persistence.
 * Maps between domain Feedback entity and database FeedbackModel.
 */
final class PdoFeedbackRepository implements FeedbackRepository
{
    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Feedback
    {
        $model = FeedbackModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findPending(int $limit = 20, int $offset = 0): array
    {
        $models = FeedbackModel::pending()
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findByStatus(string $status, int $limit = 20, int $offset = 0): array
    {
        $models = FeedbackModel::query()
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn(FeedbackModel $model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findByType(string $type, int $limit = 20, int $offset = 0): array
    {
        $models = FeedbackModel::ofType($type)
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findByUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        $models = FeedbackModel::query()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn(FeedbackModel $model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findAssignedTo(int $adminId, int $limit = 20, int $offset = 0): array
    {
        $models = FeedbackModel::query()
            ->where('assigned_to', $adminId)
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn(FeedbackModel $model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findRecent(int $limit = 10): array
    {
        $models = FeedbackModel::recent($limit)->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findWithFilters(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $query = FeedbackModel::query();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (isset($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('subject', 'LIKE', $searchTerm)
                    ->orWhere('message', 'LIKE', $searchTerm)
                    ->orWhere('name', 'LIKE', $searchTerm)
                    ->orWhere('email', 'LIKE', $searchTerm);
            });
        }

        $models = $query
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn(FeedbackModel $model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function save(Feedback $feedback): Feedback
    {
        $data = [
            'user_id' => $feedback->userId,
            'name' => $feedback->name,
            'email' => $feedback->email,
            'subject' => $feedback->subject,
            'message' => $feedback->message,
            'type' => $feedback->type,
            'status' => $feedback->status,
            'priority' => $feedback->priority,
            'assigned_to' => $feedback->assignedTo,
            'admin_notes' => $feedback->adminNotes,
            'ip_address' => $feedback->ipAddress,
            'user_agent' => $feedback->userAgent,
        ];

        if ($feedback->id === null) {
            // Create new feedback
            $model = FeedbackModel::create($data);

            return $feedback->withId($model->id);
        }

        // Update existing feedback
        FeedbackModel::where('id', $feedback->id)->update($data);

        return $feedback;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Feedback $feedback): bool
    {
        if ($feedback->id === null) {
            return false;
        }

        return FeedbackModel::destroy($feedback->id) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function updateStatus(int $id, string $status, ?string $notes = null): bool
    {
        $data = ['status' => $status];
        if ($notes !== null) {
            $data['admin_notes'] = $notes;
        }

        return FeedbackModel::where('id', $id)->update($data) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function assignTo(int $id, int $adminId): bool
    {
        return FeedbackModel::where('id', $id)->update([
            'assigned_to' => $adminId,
            'status' => FeedbackModel::STATUS_REVIEWED,
        ]) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function countByStatus(): array
    {
        $result = [];
        foreach (FeedbackModel::getStatuses() as $status) {
            $result[$status] = FeedbackModel::query()
                ->where('status', $status)
                ->count();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function countByType(): array
    {
        $result = [];
        foreach (FeedbackModel::getTypes() as $type) {
            $result[$type] = FeedbackModel::query()
                ->where('type', $type)
                ->count();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function countPending(): int
    {
        return FeedbackModel::pending()->count();
    }

    /**
     * {@inheritdoc}
     */
    public function countAll(): int
    {
        return FeedbackModel::count();
    }

    /**
     * {@inheritdoc}
     */
    public function countByPriority(): array
    {
        $result = [];
        foreach (Feedback::getPriorities() as $priority) {
            $result[$priority] = FeedbackModel::query()
                ->where('priority', $priority)
                ->count();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function findByAssignee(int $adminId, int $limit = 20, int $offset = 0): array
    {
        $models = FeedbackModel::query()
            ->where('assigned_to', $adminId)
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn(FeedbackModel $model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function bulkDelete(array $ids): int
    {
        return FeedbackModel::whereIn('id', $ids)->delete();
    }

    /**
     * Map database model to domain entity.
     */
    private function toDomain(FeedbackModel $model): Feedback
    {
        return new Feedback(
            id: $model->id,
            name: $model->name,
            email: $model->email,
            subject: $model->subject,
            message: $model->message,
            type: $model->type ?? Feedback::TYPE_FEEDBACK,
            status: $model->status ?? Feedback::STATUS_PENDING,
            priority: $model->priority ?? Feedback::PRIORITY_NORMAL,
            userId: $model->user_id,
            assignedTo: $model->assigned_to,
            adminNotes: $model->admin_notes,
            ipAddress: $model->ip_address,
            userAgent: $model->user_agent,
            createdAt: $model->created_at ? new \DateTimeImmutable($model->created_at) : null,
            updatedAt: $model->updated_at ? new \DateTimeImmutable($model->updated_at) : null
        );
    }
}
