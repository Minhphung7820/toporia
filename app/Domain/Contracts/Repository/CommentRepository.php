<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Repository;

use App\Domain\Entities\Comment;

/**
 * Comment Repository Interface - Domain persistence contract.
 *
 * Following Repository pattern and Dependency Inversion Principle.
 * Domain defines the contract, Infrastructure provides implementation.
 */
interface CommentRepository
{
    /**
     * Find a comment by ID.
     *
     * @param int $id Comment ID
     * @return Comment|null Comment or null if not found
     */
    public function findById(int $id): ?Comment;

    /**
     * Find comments for a post (with nested replies).
     *
     * @param int $postId Post ID
     * @param bool $approvedOnly Only return approved comments
     * @return Comment[] Array of root comments (with replies nested)
     */
    public function findByPost(int $postId, bool $approvedOnly = true): array;

    /**
     * Find replies to a comment.
     *
     * @param int $parentId Parent comment ID
     * @param bool $approvedOnly Only return approved replies
     * @return Comment[] Array of reply comments
     */
    public function findReplies(int $parentId, bool $approvedOnly = true): array;

    /**
     * Find pending comments (for moderation).
     *
     * @param int $limit Number of comments to return
     * @param int $offset Offset for pagination
     * @return Comment[] Array of pending comments
     */
    public function findPending(int $limit = 20, int $offset = 0): array;

    /**
     * Find approved comments.
     *
     * @param int $limit Number of comments to return
     * @param int $offset Offset for pagination
     * @return Comment[] Array of approved comments
     */
    public function findApproved(int $limit = 20, int $offset = 0): array;

    /**
     * Find comments by user.
     *
     * @param int $userId User ID
     * @param int $limit Number of comments to return
     * @param int $offset Offset for pagination
     * @return Comment[] Array of user's comments
     */
    public function findByUser(int $userId, int $limit = 20, int $offset = 0): array;

    /**
     * Find recent comments (for dashboard).
     *
     * @param int $limit Number of comments to return
     * @return Comment[] Array of recent comments
     */
    public function findRecent(int $limit = 10): array;

    /**
     * Find all comments with filters (for admin).
     *
     * @param array<string, mixed> $filters Filter criteria
     * @param int $limit Number of comments to return
     * @param int $offset Offset for pagination
     * @return Comment[] Array of comments
     */
    public function findWithFilters(array $filters = [], int $limit = 20, int $offset = 0): array;

    /**
     * Save a comment (create or update).
     *
     * @param Comment $comment Comment to save
     * @return Comment Saved comment with ID
     */
    public function save(Comment $comment): Comment;

    /**
     * Delete a comment (and its replies).
     *
     * @param Comment $comment Comment to delete
     * @return bool True if deleted
     */
    public function delete(Comment $comment): bool;

    /**
     * Approve a comment.
     *
     * @param int $commentId Comment ID
     * @return bool True if approved
     */
    public function approve(int $commentId): bool;

    /**
     * Reject (unapprove) a comment.
     *
     * @param int $commentId Comment ID
     * @return bool True if rejected
     */
    public function reject(int $commentId): bool;

    /**
     * Bulk approve comments.
     *
     * @param int[] $ids Comment IDs
     * @return int Number of approved comments
     */
    public function bulkApprove(array $ids): int;

    /**
     * Bulk reject comments.
     *
     * @param int[] $ids Comment IDs
     * @return int Number of rejected comments
     */
    public function bulkReject(array $ids): int;

    /**
     * Bulk delete comments.
     *
     * @param int[] $ids Comment IDs
     * @return int Number of deleted comments
     */
    public function bulkDelete(array $ids): int;

    /**
     * Increment like count.
     *
     * @param int $commentId Comment ID
     * @return void
     */
    public function incrementLikes(int $commentId): void;

    /**
     * Count pending comments.
     *
     * @return int Count of pending comments
     */
    public function countPending(): int;

    /**
     * Count approved comments.
     *
     * @return int Count of approved comments
     */
    public function countApproved(): int;

    /**
     * Count all comments.
     *
     * @return int Total count of comments
     */
    public function countAll(): int;

    /**
     * Count comments for a post.
     *
     * @param int $postId Post ID
     * @param bool $approvedOnly Only count approved comments
     * @return int Count of comments
     */
    public function countByPost(int $postId, bool $approvedOnly = true): int;
}
