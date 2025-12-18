<?php

declare(strict_types=1);

namespace App\Application\Services\Admin;

use App\Infrastructure\Repository\Admin\CategoryAdminRepository;
use App\Infrastructure\Persistence\Models\AdminActivityLogModel;
use Toporia\Framework\Support\Str;

/**
 * Category Admin Service
 *
 * Handles admin category operations with repository pattern.
 */
final class CategoryAdminService
{
    public function __construct(
        private readonly CategoryAdminRepository $categoryRepository
    ) {}

    /**
     * Get paginated categories with filters.
     */
    public function getPaginated(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $paginator = $this->categoryRepository->getPaginated($filters, $perPage, $page);

        return [
            'success' => true,
            'data' => $paginator->toArray(),
            'message' => 'Categories retrieved successfully',
        ];
    }

    /**
     * Get a single category.
     */
    public function getCategory(int $categoryId): array
    {
        $category = $this->categoryRepository->find($categoryId);

        if (!$category) {
            return ['success' => false, 'message' => 'Category not found'];
        }

        return [
            'success' => true,
            'data' => $category->toArray(),
            'message' => 'Category retrieved successfully',
        ];
    }

    /**
     * Create a new category.
     */
    public function createCategory(array $data, int $userId): array
    {
        if (empty($data['name'])) {
            return ['success' => false, 'message' => 'Name is required'];
        }

        $slug = $data['slug'] ?? Str::slug($data['name']);

        if ($this->categoryRepository->slugExists($slug)) {
            $slug = $slug . '-' . time();
        }

        $category = $this->categoryRepository->create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'image' => $data['image'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
            'parent_id' => !empty($data['parent_id']) ? (int) $data['parent_id'] : null,
        ]);

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_CREATE,
            "Created category: {$category->name}",
            'Category',
            $category->id
        );

        return [
            'success' => true,
            'data' => $category->toArray(),
            'message' => 'Category created successfully',
        ];
    }

    /**
     * Update an existing category.
     */
    public function updateCategory(int $categoryId, array $data, int $userId): array
    {
        $existingCategory = $this->categoryRepository->find($categoryId);

        if (!$existingCategory) {
            return ['success' => false, 'message' => 'Category not found'];
        }

        if (empty($data['name'])) {
            return ['success' => false, 'message' => 'Name is required'];
        }

        $slug = $data['slug'] ?? $existingCategory->slug;
        if ($slug !== $existingCategory->slug && $this->categoryRepository->slugExists($slug, $categoryId)) {
            $slug = $slug . '-' . time();
        }

        $parentId = isset($data['parent_id']) ? ($data['parent_id'] ?: null) : $existingCategory->parent_id;
        if ($parentId !== null && (int) $parentId === $categoryId) {
            return ['success' => false, 'message' => 'Category cannot be its own parent'];
        }

        $category = $this->categoryRepository->update($categoryId, [
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? $existingCategory->description,
            'image' => $data['image'] ?? $existingCategory->image,
            'is_active' => $data['is_active'] ?? $existingCategory->is_active,
            'sort_order' => $data['sort_order'] ?? $existingCategory->sort_order,
            'parent_id' => $parentId !== null ? (int) $parentId : null,
        ]);

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Updated category: {$category->name}",
            'Category',
            $categoryId
        );

        return [
            'success' => true,
            'data' => $category->toArray(),
            'message' => 'Category updated successfully',
        ];
    }

    /**
     * Delete a category.
     */
    public function deleteCategory(int $categoryId, int $userId): array
    {
        $category = $this->categoryRepository->find($categoryId);

        if (!$category) {
            return ['success' => false, 'message' => 'Category not found'];
        }

        $this->categoryRepository->delete($categoryId);

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
     */
    public function toggleActive(int $categoryId, int $userId): array
    {
        $category = $this->categoryRepository->toggleActive($categoryId);

        if (!$category) {
            return ['success' => false, 'message' => 'Category not found'];
        }

        $status = $category->is_active ? 'activated' : 'deactivated';

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Category {$status}: {$category->name}",
            'Category',
            $categoryId
        );

        return [
            'success' => true,
            'data' => $category->toArray(),
            'message' => "Category {$status} successfully",
        ];
    }

    /**
     * Reorder categories.
     */
    public function reorderCategories(array $orderedIds, int $userId): array
    {
        $this->categoryRepository->reorder($orderedIds);

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
     */
    public function getCategoriesForSelect(?int $excludeId = null): array
    {
        $options = $this->categoryRepository->getSelectOptions($excludeId);

        return [
            'success' => true,
            'data' => ['options' => $options],
            'message' => 'Category options retrieved successfully',
        ];
    }
}
