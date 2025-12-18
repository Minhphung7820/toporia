<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Repository;

use App\Domain\Entities\Category;

/**
 * Category Repository Interface - Domain persistence contract.
 *
 * Following Repository pattern and Dependency Inversion Principle.
 * Domain defines the contract, Infrastructure provides implementation.
 */
interface CategoryRepository
{
    /**
     * Find a category by ID.
     *
     * @param int $id Category ID
     * @return Category|null Category or null if not found
     */
    public function findById(int $id): ?Category;

    /**
     * Find a category by slug.
     *
     * @param string $slug Category slug
     * @return Category|null Category or null if not found
     */
    public function findBySlug(string $slug): ?Category;

    /**
     * Find all categories.
     *
     * @return Category[] Array of all categories
     */
    public function findAll(): array;

    /**
     * Find active categories.
     *
     * @return Category[] Array of active categories
     */
    public function findActive(): array;

    /**
     * Find root categories (no parent).
     *
     * @return Category[] Array of root categories
     */
    public function findRoots(): array;

    /**
     * Find child categories.
     *
     * @param int $parentId Parent category ID
     * @return Category[] Array of child categories
     */
    public function findChildren(int $parentId): array;

    /**
     * Find categories with post count.
     *
     * @return array<int, array{category: Category, post_count: int}> Categories with counts
     */
    public function findWithPostCount(): array;

    /**
     * Save a category (create or update).
     *
     * @param Category $category Category to save
     * @return Category Saved category with ID
     */
    public function save(Category $category): Category;

    /**
     * Delete a category.
     *
     * @param Category $category Category to delete
     * @return bool True if deleted
     */
    public function delete(Category $category): bool;

    /**
     * Count all categories.
     *
     * @return int Total count of categories
     */
    public function countAll(): int;

    /**
     * Count active categories.
     *
     * @return int Count of active categories
     */
    public function countActive(): int;

    /**
     * Check if category has posts.
     *
     * @param int $categoryId Category ID
     * @return bool True if category has posts
     */
    public function hasPosts(int $categoryId): bool;

    /**
     * Get post count for a category.
     *
     * @param int $categoryId Category ID
     * @return int Count of posts in category
     */
    public function getPostCount(int $categoryId): int;

    /**
     * Alias for getPostCount.
     *
     * @param int $categoryId Category ID
     * @return int Count of posts in category
     */
    public function countPostsByCategory(int $categoryId): int;

    /**
     * Get max sort order for categories.
     *
     * @param int|null $parentId Parent category ID
     * @return int Max sort order value
     */
    public function getMaxSortOrder(?int $parentId = null): int;

    /**
     * Update sort order for a category.
     *
     * @param int $categoryId Category ID
     * @param int $sortOrder New sort order
     * @return void
     */
    public function updateSortOrder(int $categoryId, int $sortOrder): void;
}
