<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository\Admin;

use App\Infrastructure\Persistence\Models\TagModel;
use Toporia\Framework\Repository\BaseRepository;
use Toporia\Framework\Support\Pagination\Paginator;

/**
 * Tag Admin Repository
 *
 * Extends framework BaseRepository for admin tag management.
 */
final class TagAdminRepository extends BaseRepository
{
    protected string $model = TagModel::class;

    /**
     * Get paginated tags with optional filters.
     */
    public function getPaginated(array $filters = [], int $perPage = 20, int $page = 1): Paginator
    {
        return $this
            ->applyFilters($filters)
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
            });
        }
        return $this;
    }

    public function findBySlug(string $slug): ?TagModel
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

    public function getStatistics(): array
    {
        return [
            'total' => $this->count(),
            'unused_count' => $this->raw(function ($q) {
                return $q->whereNotExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->table('taggables')
                        ->whereColumn('taggables.tag_id', 'tags.id');
                })->count();
            }),
        ];
    }

    public function findUnused(): array
    {
        return $this->raw(function ($q) {
            return $q->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('taggables')
                    ->whereColumn('taggables.tag_id', 'tags.id');
            })->get()->toArray();
        });
    }
}
