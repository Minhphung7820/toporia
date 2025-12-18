<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Contracts\Repository\CategoryRepository;
use App\Domain\Entities\Category;
use App\Infrastructure\Persistence\Models\CategoryModel;

/**
 * PDO Category Repository Implementation
 *
 * Uses Toporia ORM Model for database persistence.
 * Maps between domain Category entity and database CategoryModel.
 */
final class PdoCategoryRepository implements CategoryRepository
{
    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Category
    {
        $model = CategoryModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findBySlug(string $slug): ?Category
    {
        $model = CategoryModel::where('slug', $slug)->first();

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(): array
    {
        $models = CategoryModel::ordered()->get();

        return $models->map(fn(CategoryModel $model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findActive(): array
    {
        $models = CategoryModel::active()->ordered()->get();

        return $models->map(fn(CategoryModel $model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findRoots(): array
    {
        $models = CategoryModel::roots()->active()->ordered()->get();

        return $models->map(fn(CategoryModel $model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findChildren(int $parentId): array
    {
        $models = CategoryModel::query()
            ->where('parent_id', $parentId)
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

        return $models->map(fn(CategoryModel $model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findWithPostCount(): array
    {
        $models = CategoryModel::active()
            ->withCount('posts')
            ->ordered()
            ->get();

        $result = [];
        foreach ($models as $model) {
            $result[] = [
                'category' => $this->toDomain($model),
                'post_count' => $model->posts_count ?? 0,
            ];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function save(Category $category): Category
    {
        $data = [
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image' => $category->image,
            'is_active' => $category->isActive,
            'sort_order' => $category->sortOrder,
            'parent_id' => $category->parentId,
        ];

        if ($category->id === null) {
            // Create new category
            $model = CategoryModel::create($data);

            return $category->withId($model->id);
        }

        // Update existing category
        CategoryModel::where('id', $category->id)->update($data);

        return $category;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Category $category): bool
    {
        if ($category->id === null) {
            return false;
        }

        // Update children to have no parent
        CategoryModel::where('parent_id', $category->id)->update(['parent_id' => null]);

        return CategoryModel::destroy($category->id) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function countAll(): int
    {
        return CategoryModel::count();
    }

    /**
     * {@inheritdoc}
     */
    public function countActive(): int
    {
        return CategoryModel::active()->count();
    }

    /**
     * {@inheritdoc}
     */
    public function hasPosts(int $categoryId): bool
    {
        return $this->getPostCount($categoryId) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getPostCount(int $categoryId): int
    {
        $category = CategoryModel::withCount('posts')->find($categoryId);

        return $category ? ($category->posts_count ?? 0) : 0;
    }

    /**
     * {@inheritdoc}
     */
    public function countPostsByCategory(int $categoryId): int
    {
        return $this->getPostCount($categoryId);
    }

    /**
     * {@inheritdoc}
     */
    public function getMaxSortOrder(?int $parentId = null): int
    {
        $query = CategoryModel::query();

        if ($parentId !== null) {
            $query->where('parent_id', $parentId);
        } else {
            $query->whereNull('parent_id');
        }

        return (int) $query->max('sort_order') ?? 0;
    }

    /**
     * {@inheritdoc}
     */
    public function updateSortOrder(int $categoryId, int $sortOrder): void
    {
        CategoryModel::where('id', $categoryId)->update(['sort_order' => $sortOrder]);
    }

    /**
     * Map database model to domain entity.
     */
    private function toDomain(CategoryModel $model): Category
    {
        return new Category(
            id: $model->id,
            name: $model->name,
            slug: $model->slug ?? '',
            description: $model->description,
            image: $model->image,
            isActive: $model->is_active ?? true,
            sortOrder: $model->sort_order ?? 0,
            parentId: $model->parent_id,
            createdAt: $model->created_at ? new \DateTimeImmutable($model->created_at) : null,
            updatedAt: $model->updated_at ? new \DateTimeImmutable($model->updated_at) : null
        );
    }
}
