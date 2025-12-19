<?php

declare(strict_types=1);

namespace App\Application\Services\Blog;

use App\Domain\Contracts\Repository\CategoryRepository;

/**
 * Category Service - Handles category operations for the public site.
 *
 * Following Service pattern with consistent return format.
 * Includes caching for expensive tree operations with 3M+ posts.
 */
final class CategoryService
{
    /**
     * Cache TTL for category tree with counts (5 minutes).
     * This is acceptable because post counts don't need real-time accuracy.
     */
    private const CACHE_TTL_TREE_COUNTS = 300;

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
        $categories = $this->categoryRepository->findWithPostCount();

        return [
            'success' => true,
            'data' => [
                'categories' => $categories,
            ],
            'message' => 'Categories with post counts retrieved successfully',
        ];
    }

    /**
     * Get categories with published posts only.
     *
     * Returns categories that have at least one published post,
     * sorted by post count (most posts first).
     *
     * @param int|null $limit Max categories to return
     * @return array{success: bool, data?: array, message: string}
     */
    public function getCategoriesWithPublishedPosts(?int $limit = null): array
    {
        $categoriesData = $this->categoryRepository->findWithPublishedPostCount($limit);

        $categories = array_map(function ($item) {
            $categoryArray = $item['category']->toArray();
            $categoryArray['post_count'] = $item['published_post_count'];
            return $categoryArray;
        }, $categoriesData);

        return [
            'success' => true,
            'data' => [
                'categories' => $categories,
                'total' => count($categoriesData),
            ],
            'message' => 'Categories retrieved successfully',
        ];
    }

    /**
     * Get categories tree with published post counts.
     *
     * Returns hierarchical tree where each node has post_count.
     * Only includes categories with published posts (or parents of such categories).
     *
     * Performance: Cached for 5 minutes to avoid expensive queries on 3M+ posts table.
     *
     * @return array{success: bool, data?: array, message: string}
     */
    public function getCategoriesTreeWithCounts(): array
    {
        // Try cache first (5 minute TTL is acceptable for post counts)
        $cacheKey = 'blog:categories:tree_with_counts';
        $cached = cache($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $categoriesData = $this->categoryRepository->findAllWithPublishedPostCount();

        // Build lookup maps
        $categoryMap = [];
        $countMap = [];
        foreach ($categoriesData as $item) {
            $categoryMap[$item['category']->id] = $item['category'];
            $countMap[$item['category']->id] = $item['published_post_count'];
        }

        // Build tree structure
        $tree = $this->buildTreeWithCounts($categoryMap, $countMap);

        $result = [
            'success' => true,
            'data' => [
                'tree' => $tree,
            ],
            'message' => 'Categories tree retrieved successfully',
        ];

        // Cache for 5 minutes
        cache($cacheKey, $result, self::CACHE_TTL_TREE_COUNTS);

        return $result;
    }

    /**
     * Build category tree recursively.
     *
     * Returns a flat structure with children embedded for Vue compatibility.
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

        // Flatten structure: merge category data with children
        return array_merge($category->toArray(), [
            'children' => $childrenTree,
        ]);
    }

    /**
     * Build tree with post counts from flat category map.
     *
     * @param array<int, \App\Domain\Entities\Category> $categoryMap
     * @param array<int, int> $countMap
     * @return array
     */
    private function buildTreeWithCounts(array $categoryMap, array $countMap): array
    {
        // Group by parent_id
        // Categories without valid parent in map are treated as roots
        $byParent = [];
        foreach ($categoryMap as $id => $category) {
            $parentId = $category->parentId;

            // If parent doesn't exist in map or is circular, treat as root
            if ($parentId === null || !isset($categoryMap[$parentId]) || $parentId === $id) {
                $parentId = 0; // Root level
            }

            $byParent[$parentId][$id] = $category;
        }

        // Recursively build tree starting from roots (parent_id = 0)
        return $this->buildChildrenWithCounts($byParent, $countMap, 0, []);
    }

    /**
     * Build children array with counts recursively.
     *
     * @param array $byParent Categories grouped by parent_id
     * @param array<int, int> $countMap Post counts by category_id
     * @param int $parentId Current parent ID
     * @param array $visited Track visited IDs to prevent infinite loops
     * @return array
     */
    private function buildChildrenWithCounts(array $byParent, array $countMap, int $parentId, array $visited): array
    {
        $result = [];

        if (!isset($byParent[$parentId])) {
            return $result;
        }

        foreach ($byParent[$parentId] as $id => $category) {
            // Prevent circular references
            if (isset($visited[$id])) {
                continue;
            }

            $newVisited = $visited;
            $newVisited[$id] = true;

            $children = $this->buildChildrenWithCounts($byParent, $countMap, $id, $newVisited);

            // Calculate total count (own posts + children's posts)
            $ownCount = $countMap[$id] ?? 0;
            $childrenCount = array_sum(array_column($children, 'total_count'));
            $totalCount = $ownCount + $childrenCount;

            // Only include if has posts (directly or via children)
            if ($totalCount > 0) {
                $node = $category->toArray();
                $node['post_count'] = $ownCount;
                $node['total_count'] = $totalCount;
                $node['children'] = $children;
                $result[] = $node;
            }
        }

        return $result;
    }
}
