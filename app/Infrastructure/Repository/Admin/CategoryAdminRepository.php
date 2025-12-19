<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository\Admin;

use App\Infrastructure\Persistence\Models\CategoryModel;
use Toporia\Framework\Repository\BaseRepository;
use Toporia\Framework\Support\Pagination\Paginator;

/**
 * Category Admin Repository
 *
 * Extends framework BaseRepository for admin category management.
 */
final class CategoryAdminRepository extends BaseRepository
{
    protected string $model = CategoryModel::class;

    /**
     * Get paginated categories with optional filters.
     */
    public function getPaginated(array $filters = [], int $perPage = 20, int $page = 1): Paginator
    {
        return $this
            ->withCount('posts')
            ->applyFilters($filters)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->paginate($perPage, $page);
    }

    protected function applyFilters(array $filters): static
    {
        if (!empty($filters)) {
            $this->scope(function ($query) use ($filters) {
                if (!empty($filters['search'])) {
                    $search = $filters['search'];
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('slug', 'LIKE', "%{$search}%");
                    });
                }

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
