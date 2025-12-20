<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Contracts\Repository\CategoryRepository;
use App\Domain\Entities\Category;
use App\Infrastructure\Persistence\Models\CategoryModel;
use App\Infrastructure\Persistence\Models\CategoryPostCountModel;
use Toporia\Framework\Support\Accessors\QueryBuilder;

/**
 * PDO Category Repository Implementation
 *
 * Uses Toporia ORM Model for database persistence.
 * Maps between domain Category entity and database CategoryModel.
 *
 * PERFORMANCE OPTIMIZATION (8M+ posts):
 * Post counts use pre-computed values from category_post_counts table
 * instead of expensive GROUP BY COUNT(*) queries.
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
        $models = CategoryModel::query()
            ->orderBy('sort_order', 'asc')
            ->get();

        return $models->map(fn(CategoryModel $model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findActive(): array
    {
        $models = CategoryModel::query()
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

        return $models->map(fn(CategoryModel $model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findRoots(): array
    {
        $models = CategoryModel::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

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
        $models = CategoryModel::query()
            ->where('is_active', true)
            ->withCount('posts')
            ->orderBy('sort_order', 'asc')
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
        return CategoryModel::query()
            ->where('is_active', true)
            ->count();
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
     * {@inheritdoc}
     */
    public function findWithPublishedPostCount(?int $limit = null): array
    {
        // Step 1: Get distinct category_ids that have published posts
        // This is fast because it uses idx_posts_category and idx_posts_published_id indexes
        $limitClause = $limit !== null ? "LIMIT {$limit}" : '';

        $categoryIds = QueryBuilder::getConnection()->select("
            SELECT DISTINCT category_id
            FROM posts
            WHERE is_published = 1
              AND published_at <= NOW()
              AND category_id IS NOT NULL
            {$limitClause}
        ");

        if (empty($categoryIds)) {
            return [];
        }

        // Extract IDs
        $ids = array_column($categoryIds, 'category_id');

        // Step 2: Get categories by IDs (very fast, uses PRIMARY key)
        $models = CategoryModel::query()
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->orderBy('sort_order', 'asc')
            ->get();

        $result = [];
        foreach ($models as $model) {
            $result[] = [
                'category' => $this->toDomain($model),
                'published_post_count' => 0,
            ];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * OPTIMIZED: Uses pre-computed counts from category_post_counts table.
     * With 8M+ posts: < 10ms instead of 30+ seconds.
     *
     * Counts are maintained by database triggers on posts table.
     */
    public function findAllWithPublishedPostCount(): array
    {
        // Step 1: Get pre-computed counts (single query on small table, < 5ms)
        $countMap = CategoryPostCountModel::getAllPublishedCounts();

        // Step 2: Get all active categories (fast, typically < 100 rows)
        $models = CategoryModel::query()
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        $result = [];
        foreach ($models as $model) {
            $result[] = [
                'category' => $this->toDomain($model),
                'published_post_count' => $countMap[$model->id] ?? 0,
            ];
        }

        return $result;
    }

    /**
     * Map database model to domain entity.
     */
    private function toDomain(object $model): Category
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
