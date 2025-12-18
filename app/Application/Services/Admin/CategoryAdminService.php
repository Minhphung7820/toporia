<?php

declare(strict_types=1);

namespace App\Application\Services\Admin;

use App\Domain\Contracts\Repository\CategoryRepository;
use App\Domain\Entities\Category;
use App\Infrastructure\Persistence\Models\AdminActivityLogModel;
use Toporia\Framework\Support\Str;

/**
 * Category Admin Service - Handles admin category operations.
 *
 * Following Service pattern with consistent return format.
 */
final class CategoryAdminService
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository
    ) {}

    /**
     * Get all categories with hierarchy.
     *
     * @param array $filters Filter criteria
     * @return array{success: bool, data?: array, message: string}
     */
    public function getAllCategories(array $filters = []): array
    {
        $categories = $this->categoryRepository->findAll();

        return [
            'success' => true,
            'data' => [
                'categories' => array_map(fn($category) => $category->toArray(), $categories),
                'total' => count($categories),
            ],
            'message' => 'Categories retrieved successfully',
        ];
    }

    /**
     * Get a single category for editing.
     *
     * @param int $categoryId Category ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function getCategory(int $categoryId): array
    {
        $category = $this->categoryRepository->findById($categoryId);

        if (!$category) {
            return ['success' => false, 'message' => 'Category not found'];
        }

        $children = $this->categoryRepository->findChildren($categoryId);
        $postCount = $this->categoryRepository->countPostsByCategory($categoryId);

        return [
            'success' => true,
            'data' => [
                'category' => $category->toArray(),
                'children' => array_map(fn($child) => $child->toArray(), $children),
                'post_count' => $postCount,
            ],
            'message' => 'Category retrieved successfully',
        ];
    }

    /**
     * Create a new category.
     *
     * @param array $data Category data
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function createCategory(array $data, int $userId): array
    {
        // Validate required fields
        if (empty($data['name'])) {
            return ['success' => false, 'message' => 'Name is required'];
        }

        // Generate slug if not provided
        $slug = $data['slug'] ?? Str::slug($data['name']);

        // Check slug uniqueness
        if ($this->categoryRepository->findBySlug($slug)) {
            $slug = $slug . '-' . time();
        }

        // Determine sort order
        $parentId = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;
        $sortOrder = $data['sort_order'] ?? $this->categoryRepository->getMaxSortOrder($parentId) + 1;

        $category = new Category(
            id: null,
            name: $data['name'],
            slug: $slug,
            description: $data['description'] ?? null,
            image: $data['image'] ?? null,
            isActive: $data['is_active'] ?? true,
            sortOrder: (int) $sortOrder,
            parentId: $parentId,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );

        $savedCategory = $this->categoryRepository->save($category);

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_CREATE,
            "Created category: {$savedCategory->name}",
            'Category',
            $savedCategory->id
        );

        return [
            'success' => true,
            'data' => ['category' => $savedCategory->toArray()],
            'message' => 'Category created successfully',
        ];
    }

    /**
     * Update an existing category.
     *
     * @param int $categoryId Category ID
     * @param array $data Category data
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function updateCategory(int $categoryId, array $data, int $userId): array
    {
        $existingCategory = $this->categoryRepository->findById($categoryId);

        if (!$existingCategory) {
            return ['success' => false, 'message' => 'Category not found'];
        }

        // Validate required fields
        if (empty($data['name'])) {
            return ['success' => false, 'message' => 'Name is required'];
        }

        // Handle slug update
        $slug = $data['slug'] ?? $existingCategory->slug;
        if ($slug !== $existingCategory->slug) {
            $existing = $this->categoryRepository->findBySlug($slug);
            if ($existing && $existing->id !== $categoryId) {
                $slug = $slug . '-' . time();
            }
        }

        // Prevent circular parent reference
        $parentId = isset($data['parent_id']) ? ($data['parent_id'] ?: null) : $existingCategory->parentId;
        if ($parentId !== null && (int) $parentId === $categoryId) {
            return ['success' => false, 'message' => 'Category cannot be its own parent'];
        }

        $updatedCategory = new Category(
            id: $categoryId,
            name: $data['name'],
            slug: $slug,
            description: $data['description'] ?? $existingCategory->description,
            image: $data['image'] ?? $existingCategory->image,
            isActive: $data['is_active'] ?? $existingCategory->isActive,
            sortOrder: (int) ($data['sort_order'] ?? $existingCategory->sortOrder),
            parentId: $parentId !== null ? (int) $parentId : null,
            createdAt: $existingCategory->createdAt,
            updatedAt: new \DateTimeImmutable()
        );

        $savedCategory = $this->categoryRepository->save($updatedCategory);

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Updated category: {$savedCategory->name}",
            'Category',
            $categoryId
        );

        return [
            'success' => true,
            'data' => ['category' => $savedCategory->toArray()],
            'message' => 'Category updated successfully',
        ];
    }

    /**
     * Delete a category.
     *
     * @param int $categoryId Category ID
     * @param int $userId Admin user ID
     * @return array{success: bool, message: string}
     */
    public function deleteCategory(int $categoryId, int $userId): array
    {
        $category = $this->categoryRepository->findById($categoryId);

        if (!$category) {
            return ['success' => false, 'message' => 'Category not found'];
        }

        // Check if category has children
        $children = $this->categoryRepository->findChildren($categoryId);
        if (!empty($children)) {
            return ['success' => false, 'message' => 'Cannot delete category with subcategories'];
        }

        // Check if category has posts
        $postCount = $this->categoryRepository->countPostsByCategory($categoryId);
        if ($postCount > 0) {
            return ['success' => false, 'message' => 'Cannot delete category with posts. Reassign posts first.'];
        }

        $this->categoryRepository->delete($category);

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_DELETE,
            "Deleted category: {$category->name}",
            'Category',
            $categoryId
        );

        return [
            'success' => true,
            'message' => 'Category deleted successfully',
        ];
    }

    /**
     * Toggle category active status.
     *
     * @param int $categoryId Category ID
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function toggleActive(int $categoryId, int $userId): array
    {
        $category = $this->categoryRepository->findById($categoryId);

        if (!$category) {
            return ['success' => false, 'message' => 'Category not found'];
        }

        $toggledCategory = $category->isActive ? $category->deactivate() : $category->activate();
        $this->categoryRepository->save($toggledCategory);

        $status = $toggledCategory->isActive ? 'activated' : 'deactivated';

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Category {$status}: {$category->name}",
            'Category',
            $categoryId
        );

        return [
            'success' => true,
            'data' => ['category' => $toggledCategory->toArray()],
            'message' => "Category {$status} successfully",
        ];
    }

    /**
     * Reorder categories.
     *
     * @param array $order Array of [id => sort_order]
     * @param int $userId Admin user ID
     * @return array{success: bool, message: string}
     */
    public function reorderCategories(array $order, int $userId): array
    {
        foreach ($order as $id => $sortOrder) {
            $this->categoryRepository->updateSortOrder((int) $id, (int) $sortOrder);
        }

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            'Reordered categories'
        );

        return [
            'success' => true,
            'message' => 'Categories reordered successfully',
        ];
    }

    /**
     * Get categories for dropdown/select.
     *
     * @param int|null $excludeId Category ID to exclude
     * @return array{success: bool, data?: array, message: string}
     */
    public function getCategoriesForSelect(?int $excludeId = null): array
    {
        $categories = $this->categoryRepository->findAll();

        $options = [];
        foreach ($categories as $category) {
            if ($excludeId !== null && $category->id === $excludeId) {
                continue;
            }

            $prefix = $category->parentId ? '— ' : '';
            $options[] = [
                'value' => $category->id,
                'label' => $prefix . $category->name,
                'parent_id' => $category->parentId,
            ];
        }

        return [
            'success' => true,
            'data' => ['options' => $options],
            'message' => 'Category options retrieved successfully',
        ];
    }
}
