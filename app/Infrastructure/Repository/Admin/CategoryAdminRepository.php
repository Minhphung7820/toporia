<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository\Admin;

use App\Infrastructure\Persistence\Models\CategoryModel;
use App\Infrastructure\Persistence\Models\CategoryPostCountModel;
use Toporia\Framework\Repository\BaseRepository;
use Toporia\Framework\Support\Pagination\Paginator;

/**
 * Category Admin Repository
 *
 * Extends framework BaseRepository for admin category management.
 *
 * Performance optimizations:
 * - Uses pre-computed counts from category_post_counts table (via triggers)
 * - Avoids N+1 withCount() queries on 8M+ posts table
 * - Single query for all counts instead of COUNT subquery per category
 */
final class CategoryAdminRepository extends BaseRepository
{
    protected string $model = CategoryModel::class;

    /**
     * Get paginated categories with optional filters.
     *
     * Uses pre-computed post counts for performance.
     * Previous: withCount('posts') = N+1 queries (20+ queries per page)
     * Current: Single query to category_post_counts table
     */
    public function getPaginated(array $filters = [], int $perPage = 20, int $page = 1): array
    {
        // Get paginated categories with parent eager loading
        $paginator = $this
            ->with('parent:id,name,slug')
            ->applyFilters($filters)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->paginate($perPage, $page);

        // Get pre-computed counts in single query (from category_post_counts table)
        // This replaces N+1 withCount queries with 1 efficient query
        $countMap = CategoryPostCountModel::getAllCounts();

        // Attach counts to each category
        $data = $paginator->toArray();
        foreach ($data['data'] as &$category) {
            $counts = $countMap[$category['id']] ?? ['published' => 0, 'total' => 0];
            $category['posts_count'] = $counts['total'];
            $category['published_posts_count'] = $counts['published'];
        }

        return $data;
    }

    protected function applyFilters(array $filters): static
    {
        if (!empty($filters)) {
            $this->scope(function ($query) use ($filters) {
                // Search filter
                if (!empty($filters['search'])) {
                    $search = $filters['search'];
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('slug', 'LIKE', "%{$search}%");
                    });
                }

                // Parent ID filter (for hierarchical views)
                if (isset($filters['parent_id'])) {
                    if ($filters['parent_id'] === 'root' || $filters['parent_id'] === '0') {
                        // Show only root categories (no parent)
                        $query->whereNull('parent_id');
                    } else {
                        // Show children of specific parent
                        $query->where('parent_id', (int) $filters['parent_id']);
                    }
                }

                // Active status filter
                if (isset($filters['is_active']) && $filters['is_active'] !== '') {
                    $query->where('is_active', (bool) $filters['is_active']);
                }
            });
        }
        return $this;
    }

    public function findBySlug(string $slug): ?CategoryModel
    {
        return $this->findBy('slug', $slug);
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = $this->getQuery()->where('slug', $slug);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->exists();
    }

    /**
     * Get categories for select dropdown (excluding specified ID).
     */
    public function getSelectOptions(?int $excludeId = null): array
    {
        $query = $this->getQuery()
            ->select(['id', 'name'])
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc');

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get()->toArray();
    }

    public function toggleActive(int $id): ?CategoryModel
    {
        $category = $this->find($id);
        if ($category) {
            return $this->update($id, ['is_active' => !$category->is_active]);
        }
        return null;
    }

    public function reorder(array $orderedIds): bool
    {
        foreach ($orderedIds as $order => $id) {
            $this->update($id, ['sort_order' => $order]);
        }
        return true;
    }
}
