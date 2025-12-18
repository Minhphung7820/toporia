<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Repository;

use App\Domain\Entities\Tag;

/**
 * Tag Repository Interface - Domain persistence contract.
 *
 * Following Repository pattern and Dependency Inversion Principle.
 * Domain defines the contract, Infrastructure provides implementation.
 */
interface TagRepository
{
    /**
     * Find a tag by ID.
     *
     * @param int $id Tag ID
     * @return Tag|null Tag or null if not found
     */
    public function findById(int $id): ?Tag;

    /**
     * Find a tag by slug.
     *
     * @param string $slug Tag slug
     * @return Tag|null Tag or null if not found
     */
    public function findBySlug(string $slug): ?Tag;

    /**
     * Find a tag by name.
     *
     * @param string $name Tag name
     * @return Tag|null Tag or null if not found
     */
    public function findByName(string $name): ?Tag;

    /**
     * Find all tags.
     *
     * @return Tag[] Array of all tags
     */
    public function findAll(): array;

    /**
     * Find active tags.
     *
     * @return Tag[] Array of active tags
     */
    public function findActive(): array;

    /**
     * Find popular tags.
     *
     * @param int $limit Number of tags to return
     * @return Tag[] Array of popular tags (by usage count)
     */
    public function findPopular(int $limit = 20): array;

    /**
     * Find tags for a post.
     *
     * @param int $postId Post ID
     * @return Tag[] Array of tags attached to post
     */
    public function findByPost(int $postId): array;

    /**
     * Search tags by name.
     *
     * @param string $query Search query
     * @param int $limit Number of tags to return
     * @return Tag[] Array of matching tags
     */
    public function search(string $query, int $limit = 10): array;

    /**
     * Save a tag (create or update).
     *
     * @param Tag $tag Tag to save
     * @return Tag Saved tag with ID
     */
    public function save(Tag $tag): Tag;

    /**
     * Delete a tag.
     *
     * @param Tag $tag Tag to delete
     * @return bool True if deleted
     */
    public function delete(Tag $tag): bool;

    /**
     * Attach tag to post.
     *
     * @param int $postId Post ID
     * @param int $tagId Tag ID
     * @return void
     */
    public function attachToPost(int $postId, int $tagId): void;

    /**
     * Detach tag from post.
     *
     * @param int $postId Post ID
     * @param int $tagId Tag ID
     * @return void
     */
    public function detachFromPost(int $postId, int $tagId): void;

    /**
     * Sync tags for a post.
     *
     * @param int $postId Post ID
     * @param int[] $tagIds Array of tag IDs
     * @return void
     */
    public function syncPostTags(int $postId, array $tagIds): void;

    /**
     * Increment usage count.
     *
     * @param int $tagId Tag ID
     * @return void
     */
    public function incrementUsage(int $tagId): void;

    /**
     * Decrement usage count.
     *
     * @param int $tagId Tag ID
     * @return void
     */
    public function decrementUsage(int $tagId): void;

    /**
     * Count all tags.
     *
     * @return int Total count of tags
     */
    public function countAll(): int;

    /**
     * Count active tags.
     *
     * @return int Count of active tags
     */
    public function countActive(): int;

    /**
     * Find or create a tag by name.
     *
     * @param string $name Tag name
     * @return Tag Existing or new tag
     */
    public function findOrCreate(string $name): Tag;

    /**
     * Find tags with filters.
     *
     * @param array $filters Filter criteria
     * @param int $limit Number of tags to return
     * @param int $offset Offset for pagination
     * @return Tag[] Array of filtered tags
     */
    public function findWithFilters(array $filters = [], int $limit = 50, int $offset = 0): array;

    /**
     * Count used tags (usage_count > 0).
     *
     * @return int Count of used tags
     */
    public function countUsed(): int;

    /**
     * Count unused tags (usage_count = 0).
     *
     * @return int Count of unused tags
     */
    public function countUnused(): int;

    /**
     * Bulk delete tags by IDs.
     *
     * @param int[] $ids Tag IDs
     * @return int Number of deleted tags
     */
    public function bulkDelete(array $ids): int;

    /**
     * Merge source tags into target tag.
     *
     * @param int $targetId Target tag ID
     * @param int[] $sourceIds Source tag IDs to merge
     * @return int Number of merged tags
     */
    public function mergeTags(int $targetId, array $sourceIds): int;

    /**
     * Delete unused tags.
     *
     * @return int Number of deleted tags
     */
    public function deleteUnused(): int;
}
