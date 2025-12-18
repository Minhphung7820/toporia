<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Repository;

use App\Domain\Entities\Feedback;

/**
 * Feedback Repository Interface - Domain persistence contract.
 *
 * Following Repository pattern and Dependency Inversion Principle.
 * Domain defines the contract, Infrastructure provides implementation.
 */
interface FeedbackRepository
{
    /**
     * Find feedback by ID.
     *
     * @param int $id Feedback ID
     * @return Feedback|null Feedback or null if not found
     */
    public function findById(int $id): ?Feedback;

    /**
     * Find pending feedback.
     *
     * @param int $limit Number of items to return
     * @param int $offset Offset for pagination
     * @return Feedback[] Array of pending feedback
     */
    public function findPending(int $limit = 20, int $offset = 0): array;

    /**
     * Find feedback by status.
     *
     * @param string $status Status to filter by
     * @param int $limit Number of items to return
     * @param int $offset Offset for pagination
     * @return Feedback[] Array of feedback
     */
    public function findByStatus(string $status, int $limit = 20, int $offset = 0): array;

    /**
     * Find feedback by type.
     *
     * @param string $type Type to filter by
     * @param int $limit Number of items to return
     * @param int $offset Offset for pagination
     * @return Feedback[] Array of feedback
     */
    public function findByType(string $type, int $limit = 20, int $offset = 0): array;

    /**
     * Find feedback by user.
     *
     * @param int $userId User ID
     * @param int $limit Number of items to return
     * @param int $offset Offset for pagination
     * @return Feedback[] Array of feedback
     */
    public function findByUser(int $userId, int $limit = 20, int $offset = 0): array;

    /**
     * Find feedback assigned to admin.
     *
     * @param int $adminId Admin user ID
     * @param int $limit Number of items to return
     * @param int $offset Offset for pagination
     * @return Feedback[] Array of feedback
     */
    public function findAssignedTo(int $adminId, int $limit = 20, int $offset = 0): array;

    /**
     * Find recent feedback.
     *
     * @param int $limit Number of items to return
     * @return Feedback[] Array of recent feedback
     */
    public function findRecent(int $limit = 10): array;

    /**
     * Find all feedback with filters.
     *
     * @param array<string, mixed> $filters Filter criteria
     * @param int $limit Number of items to return
     * @param int $offset Offset for pagination
     * @return Feedback[] Array of feedback
     */
    public function findWithFilters(array $filters = [], int $limit = 20, int $offset = 0): array;

    /**
     * Save feedback (create or update).
     *
     * @param Feedback $feedback Feedback to save
     * @return Feedback Saved feedback with ID
     */
    public function save(Feedback $feedback): Feedback;

    /**
     * Delete feedback.
     *
     * @param Feedback $feedback Feedback to delete
     * @return bool True if deleted
     */
    public function delete(Feedback $feedback): bool;

    /**
     * Update feedback status.
     *
     * @param int $id Feedback ID
     * @param string $status New status
     * @param string|null $notes Admin notes
     * @return bool True if updated
     */
    public function updateStatus(int $id, string $status, ?string $notes = null): bool;

    /**
     * Assign feedback to admin.
     *
     * @param int $id Feedback ID
     * @param int $adminId Admin user ID
     * @return bool True if assigned
     */
    public function assignTo(int $id, int $adminId): bool;

    /**
     * Count feedback by status.
     *
     * @return array<string, int> Counts keyed by status
     */
    public function countByStatus(): array;

    /**
     * Count feedback by type.
     *
     * @return array<string, int> Counts keyed by type
     */
    public function countByType(): array;

    /**
     * Count pending feedback.
     *
     * @return int Count of pending feedback
     */
    public function countPending(): int;

    /**
     * Count all feedback.
     *
     * @return int Total count of feedback
     */
    public function countAll(): int;

    /**
     * Count feedback by priority.
     *
     * @return array<string, int> Counts keyed by priority
     */
    public function countByPriority(): array;

    /**
     * Find feedback by assignee.
     *
     * @param int $adminId Admin user ID
     * @param int $limit Number of items to return
     * @param int $offset Offset for pagination
     * @return Feedback[] Array of feedback
     */
    public function findByAssignee(int $adminId, int $limit = 20, int $offset = 0): array;

    /**
     * Bulk delete feedback by IDs.
     *
     * @param array<int> $ids Feedback IDs to delete
     * @return int Number of deleted items
     */
    public function bulkDelete(array $ids): int;
}
