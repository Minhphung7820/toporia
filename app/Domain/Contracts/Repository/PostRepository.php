<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Repository;

use App\Domain\Entities\Post;

/**
 * Post Repository Interface - Domain persistence contract.
 *
 * Following Repository pattern and Dependency Inversion Principle.
 * Domain defines the contract, Infrastructure provides implementation.
 */
interface PostRepository
{
    /**
     * Find a post by ID.
     *
     * @param int $id Post ID
     * @return Post|null Post or null if not found
     */
    public function findById(int $id): ?Post;

    /**
     * Find a post by slug.
     *
     * @param string $slug Post slug
     * @return Post|null Post or null if not found
     */
    public function findBySlug(string $slug): ?Post;

    /**
     * Find published posts with pagination.
     *
     * @param int $limit Number of posts to return
     * @param int $offset Offset for pagination
     * @return Post[] Array of posts
     */
    public function findPublished(int $limit = 10, int $offset = 0): array;

    /**
     * Find featured posts.
     *
     * @param int $limit Number of posts to return
     * @return Post[] Array of featured posts
     */
    public function findFeatured(int $limit = 5): array;

    /**
     * Find most viewed posts.
     *
     * @param int $limit Number of posts to return
     * @return Post[] Array of most viewed posts
     */
    public function findMostViewed(int $limit = 10): array;

    /**
     * Find latest posts.
     *
     * @param int $limit Number of posts to return
     * @return Post[] Array of latest posts
     */
    public function findLatest(int $limit = 10): array;

    /**
     * Find posts by category.
     *
     * @param int $categoryId Category ID
     * @param int $limit Number of posts to return
     * @param int $offset Offset for pagination
     * @return Post[] Array of posts in category
     */
    public function findByCategory(int $categoryId, int $limit = 10, int $offset = 0): array;

    /**
     * Find posts by tag.
     *
     * @param int $tagId Tag ID
     * @param int $limit Number of posts to return
     * @param int $offset Offset for pagination
     * @return Post[] Array of posts with tag
     */
    public function findByTag(int $tagId, int $limit = 10, int $offset = 0): array;

    /**
     * Find posts by author.
     *
     * @param int $authorId Author user ID
     * @param int $limit Number of posts to return
     * @param int $offset Offset for pagination
     * @return Post[] Array of author's posts
     */
    public function findByAuthor(int $authorId, int $limit = 10, int $offset = 0): array;

    /**
     * Find related posts based on category and tags.
     *
     * @param int $postId Current post ID
     * @param int $limit Number of posts to return
     * @return Post[] Array of related posts
     */
    public function findRelated(int $postId, int $limit = 5): array;

    /**
     * Search posts by keyword.
     *
     * @param string $query Search query
     * @param int $limit Number of posts to return
     * @param int $offset Offset for pagination
     * @return Post[] Array of matching posts
     */
    public function search(string $query, int $limit = 10, int $offset = 0): array;

    /**
     * Find posts with filters (for admin).
     *
     * @param array<string, mixed> $filters Filter criteria
     * @param int $limit Number of posts to return
     * @param int $offset Offset for pagination
     * @return Post[] Array of posts
     */
    public function findWithFilters(array $filters = [], int $limit = 20, int $offset = 0): array;

    /**
     * Find scheduled posts ready to be published.
     *
     * @return Post[] Array of posts ready to publish
     */
    public function findScheduledReady(): array;

    /**
     * Save a post (create or update).
     *
     * @param Post $post Post to save
     * @return Post Saved post with ID
     */
    public function save(Post $post): Post;

    /**
     * Delete a post.
     *
     * @param Post $post Post to delete
     * @return bool True if deleted
     */
    public function delete(Post $post): bool;

    /**
     * Increment view count.
     *
     * @param int $postId Post ID
     * @return void
     */
    public function incrementViews(int $postId): void;

    /**
     * Count published posts.
     *
     * @return int Count of published posts
     */
    public function countPublished(): int;

    /**
     * Count all posts.
     *
     * @return int Total count of posts
     */
    public function countAll(): int;

    /**
     * Count posts by status.
     *
     * @return array<string, int> Counts by status (published, draft, scheduled)
     */
    public function countByStatus(): array;

    /**
     * Get total views across all posts.
     *
     * @return int Total view count
     */
    public function getTotalViews(): int;

    /**
     * Get posts statistics.
     *
     * @return array<string, mixed> Statistics data
     */
    public function getStatistics(): array;

    /**
     * Sync tags for a post.
     *
     * @param int $postId Post ID
     * @param int[] $tagIds Array of tag IDs
     * @return void
     */
    public function syncTags(int $postId, array $tagIds): void;

    /**
     * Sync categories for a post.
     *
     * @param int $postId Post ID
     * @param int[] $categoryIds Array of category IDs
     * @return void
     */
    public function syncCategories(int $postId, array $categoryIds): void;

    /**
     * Find published posts with cursor pagination (optimized for large datasets).
     *
     * @param int $limit Number of posts per page
     * @param string|null $cursor Cursor for pagination (published_at timestamp)
     * @param string $direction 'next' or 'prev'
     * @return array{posts: Post[], next_cursor: string|null, prev_cursor: string|null, has_more: bool}
     */
    public function findPublishedWithCursor(int $limit = 10, ?string $cursor = null, string $direction = 'next'): array;

    /**
     * Find posts by category with cursor pagination.
     *
     * @param int $categoryId Category ID
     * @param int $limit Number of posts per page
     * @param string|null $cursor Cursor for pagination
     * @param string $direction 'next' or 'prev'
     * @return array{posts: Post[], next_cursor: string|null, prev_cursor: string|null, has_more: bool}
     */
    public function findByCategoryWithCursor(int $categoryId, int $limit = 10, ?string $cursor = null, string $direction = 'next'): array;

    /**
     * Find posts by tag with cursor pagination.
     *
     * @param int $tagId Tag ID
     * @param int $limit Number of posts per page
     * @param string|null $cursor Cursor for pagination
     * @param string $direction 'next' or 'prev'
     * @return array{posts: Post[], next_cursor: string|null, prev_cursor: string|null, has_more: bool}
     */
    public function findByTagWithCursor(int $tagId, int $limit = 10, ?string $cursor = null, string $direction = 'next'): array;
}
