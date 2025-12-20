<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository\Admin;

use App\Infrastructure\Persistence\Models\TaggableModel;
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

    /**
     * Merge multiple tags into a target tag.
     *
     * Process:
     * 1. Update all taggables from source tags to target tag
     * 2. Handle duplicates (same taggable_id + taggable_type can't have duplicate tag_id)
     * 3. Delete source tags
     *
     * @param int $targetTagId The tag to merge into
     * @param array<int> $sourceTagIds Tags to merge (will be deleted)
     * @return array{merged_count: int, deleted_tags: int}
     */
    public function mergeTags(int $targetTagId, array $sourceTagIds): array
    {
        // Remove target from sources if accidentally included
        $sourceTagIds = array_filter($sourceTagIds, fn($id) => $id !== $targetTagId);

        if (empty($sourceTagIds)) {
            return ['merged_count' => 0, 'deleted_tags' => 0];
        }

        $mergedCount = 0;

        // Get all taggables that need to be moved to target tag
        $taggablesToMove = TaggableModel::query()
            ->whereIn('tag_id', $sourceTagIds)
            ->get();

        // Get existing taggables for target tag (to check duplicates)
        $existingTargetTaggables = TaggableModel::query()
            ->where('tag_id', $targetTagId)
            ->get()
            ->keyBy(fn($t) => "{$t->taggable_type}:{$t->taggable_id}");

        foreach ($taggablesToMove as $taggable) {
            $key = "{$taggable->taggable_type}:{$taggable->taggable_id}";

            // If target tag already has this taggable, delete the duplicate
            if (isset($existingTargetTaggables[$key])) {
                $taggable->delete();
            } else {
                // Move to target tag
                $taggable->tag_id = $targetTagId;
                $taggable->save();
                $mergedCount++;
            }
        }

        // Delete source tags
        $deletedCount = $this->deleteWhere(['id' => $sourceTagIds]);

        return [
            'merged_count' => $mergedCount,
            'deleted_tags' => $deletedCount,
        ];
    }

    /**
     * Get tags with post counts for display.
     *
     * @param array<int> $tagIds
     * @return array<int, array{id: int, name: string, posts_count: int}>
     */
    public function getTagsWithCounts(array $tagIds): array
    {
        if (empty($tagIds)) {
            return [];
        }

        return $this->raw(function ($q) use ($tagIds) {
            $tags = $q->whereIn('id', $tagIds)
                ->select(['id', 'name', 'slug'])
                ->get();

            $result = [];
            foreach ($tags as $tag) {
                $count = TaggableModel::query()
                    ->where('tag_id', $tag->id)
                    ->count();
                $result[] = [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'posts_count' => $count,
                ];
            }

            return $result;
        });
    }
}
