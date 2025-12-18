<?php

declare(strict_types=1);

namespace App\Application\Services\Blog;

use App\Domain\Contracts\Repository\CategoryRepository;

/**
 * Category Service - Handles category operations for the public site.
 *
 * Following Service pattern with consistent return format.
 */
final class CategoryService
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository
    ) {}

    /**
     * Get all active categories.
     *
     * @return array{success: bool, data?: array, message: string}
     */
    public function getAllCategories(): array
    {
        $categories = $this->categoryRepository->findActive();

        return [
            'success' => true,
            'data' => [
                'categories' => array_map(fn($category) => $category->toArray(), $categories),
            ],
            'message' => 'Categories retrieved successfully',
        ];
    }

    /**
     * Get root categories only.
     *
     * @return array{success: bool, data?: array, message: string}
     */
    public function getRootCategories(): array
    {
        $categories = $this->categoryRepository->findRoots();

        return [
            'success' => true,
            'data' => [
                'categories' => array_map(fn($category) => $category->toArray(), $categories),
            ],
            'message' => 'Root categories retrieved successfully',
        ];
    }

    /**
     * Get category by slug with children.
     *
     * @param string $slug Category slug
     * @return array{success: bool, data?: array, message: string}
     */
    public function getCategoryBySlug(string $slug): array
    {
        $category = $this->categoryRepository->findBySlug($slug);

        if (!$category) {
            return [
                'success' => false,
                'message' => 'Category not found',
            ];
        }

        if (!$category->isActive) {
            return [
                'success' => false,
                'message' => 'Category not available',
            ];
        }

        $children = $this->categoryRepository->findChildren($category->id);

        return [
            'success' => true,
            'data' => [
                'category' => $category->toArray(),
                'children' => array_map(fn($child) => $child->toArray(), $children),
            ],
            'message' => 'Category retrieved successfully',
        ];
    }

    /**
     * Get categories tree structure.
     *
     * @return array{success: bool, data?: array, message: string}
     */
    public function getCategoriesTree(): array
    {
        $roots = $this->categoryRepository->findRoots();
        $tree = [];

        foreach ($roots as $root) {
            $tree[] = $this->buildCategoryTree($root);
        }

        return [
            'success' => true,
            'data' => [
                'tree' => $tree,
            ],
            'message' => 'Categories tree retrieved successfully',
        ];
    }

    /**
     * Get categories with post counts.
     *
     * @return array{success: bool, data?: array, message: string}
     */
    public function getCategoriesWithPostCounts(): array
    {
        $categories = $this->categoryRepository->findWithPostCounts();

        return [
            'success' => true,
            'data' => [
                'categories' => $categories,
            ],
            'message' => 'Categories with post counts retrieved successfully',
        ];
    }

    /**
     * Build category tree recursively.
     */
    private function buildCategoryTree(\App\Domain\Entities\Category $category): array
    {
        $children = $this->categoryRepository->findChildren($category->id);
        $childrenTree = [];

        foreach ($children as $child) {
            if ($child->isActive) {
                $childrenTree[] = $this->buildCategoryTree($child);
            }
        }

        return [
            'category' => $category->toArray(),
            'children' => $childrenTree,
        ];
    }
}
