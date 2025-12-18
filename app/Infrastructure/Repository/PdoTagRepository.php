<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Contracts\Repository\TagRepository;
use App\Domain\Entities\Tag;
use App\Infrastructure\Persistence\Models\PostModel;
use App\Infrastructure\Persistence\Models\TagModel;
use Toporia\Framework\Support\Accessors\QueryBuilder;
use Toporia\Framework\Support\Str;

/**
 * PDO Tag Repository Implementation
 *
 * Uses Toporia ORM Model for database persistence.
 * Maps between domain Tag entity and database TagModel.
 */
final class PdoTagRepository implements TagRepository
{
    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Tag
    {
        $model = TagModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findBySlug(string $slug): ?Tag
    {
        $model = TagModel::where('slug', $slug)->first();

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findByName(string $name): ?Tag
    {
        $model = TagModel::where('name', $name)->first();

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(): array
    {
        $models = TagModel::orderBy('name', 'asc')->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findActive(): array
    {
        $models = TagModel::active()->orderBy('name', 'asc')->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findPopular(int $limit = 20): array
    {
        $models = TagModel::popular($limit)->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findByPost(int $postId): array
    {
        // Get tags through the taggables polymorphic pivot table using raw SQL
        // since TagModel doesn't have a direct 'posts' relationship
        $taggableType = PostModel::class;

        $rows = QueryBuilder::getConnection()->select(
            "SELECT t.* FROM tags t
             INNER JOIN taggables tg ON tg.tag_id = t.id
             WHERE tg.taggable_type = ? AND tg.taggable_id = ?
             ORDER BY t.name ASC",
            [$taggableType, $postId]
        );

        return array_map(fn(QueryBuilder $row) => $this->rowToDomain($row), $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function search(string $query, int $limit = 10): array
    {
        $searchTerm = '%' . $query . '%';

        $models = TagModel::query()
            ->where('name', 'LIKE', $searchTerm)
            ->where('is_active', true)
            ->orderBy('usage_count', 'desc')
            ->limit($limit)
            ->get();

        return $models->map(fn(TagModel $model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function save(Tag $tag): Tag
    {
        $data = [
            'name' => $tag->name,
            'slug' => $tag->slug,
            'description' => $tag->description,
            'color' => $tag->color,
            'is_active' => $tag->isActive,
            'usage_count' => $tag->usageCount,
        ];

        if ($tag->id === null) {
            // Create new tag
            $model = TagModel::create($data);

            return $tag->withId($model->id);
        }

        // Update existing tag
        TagModel::where('id', $tag->id)->update($data);

        return $tag;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Tag $tag): bool
    {
        if ($tag->id === null) {
            return false;
        }

        return TagModel::destroy($tag->id) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function attachToPost(int $postId, int $tagId): void
    {
        // This would use the taggables pivot table
        // Implementation depends on how the relationship is set up
        $tag = TagModel::find($tagId);
        if ($tag) {
            // Attach through polymorphic relationship
            // The Post model should have a tags() morphToMany relationship
            $tag->incrementUsage();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function detachFromPost(int $postId, int $tagId): void
    {
        $tag = TagModel::find($tagId);
        if ($tag) {
            $tag->decrementUsage();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function syncPostTags(int $postId, array $tagIds): void
    {
        // Get current tag IDs for the post to calculate diff
        $currentTags = $this->findByPost($postId);
        $currentIds = array_column($currentTags, 'id');

        // Decrement usage for removed tags
        $removedIds = array_diff($currentIds, $tagIds);
        foreach ($removedIds as $tagId) {
            $this->decrementUsage($tagId);
        }

        // Increment usage for new tags
        $newIds = array_diff($tagIds, $currentIds);
        foreach ($newIds as $tagId) {
            $this->incrementUsage($tagId);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function incrementUsage(int $tagId): void
    {
        TagModel::where('id', $tagId)->increment('usage_count');
    }

    /**
     * {@inheritdoc}
     */
    public function decrementUsage(int $tagId): void
    {
        TagModel::where('id', $tagId)
            ->where('usage_count', '>', 0)
            ->decrement('usage_count');
    }

    /**
     * {@inheritdoc}
     */
    public function countAll(): int
    {
        return TagModel::count();
    }

    /**
     * {@inheritdoc}
     */
    public function countActive(): int
    {
        return TagModel::active()->count();
    }

    /**
     * {@inheritdoc}
     */
    public function findOrCreate(string $name): Tag
    {
        $existing = $this->findByName($name);
        if ($existing) {
            return $existing;
        }

        $tag = new Tag(
            id: null,
            name: $name,
            slug: Str::slug($name),
            description: null,
            color: '#6366f1',
            isActive: true,
            usageCount: 0,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );

        return $this->save($tag);
    }

    /**
     * {@inheritdoc}
     */
    public function findWithFilters(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = TagModel::query();

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', $searchTerm)
                    ->orWhere('description', 'LIKE', $searchTerm);
            });
        }

        if (isset($filters['used'])) {
            if ($filters['used']) {
                $query->where('usage_count', '>', 0);
            } else {
                $query->where('usage_count', '=', 0);
            }
        }

        $models = $query
            ->orderBy('name', 'asc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn(TagModel $model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function countUsed(): int
    {
        return TagModel::where('usage_count', '>', 0)->count();
    }

    /**
     * {@inheritdoc}
     */
    public function countUnused(): int
    {
        return TagModel::where('usage_count', '=', 0)->count();
    }

    /**
     * {@inheritdoc}
     */
    public function bulkDelete(array $ids): int
    {
        return TagModel::whereIn('id', $ids)->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function mergeTags(int $targetId, array $sourceIds): int
    {
        // Update taggables pivot table to point to target tag
        // Then delete source tags
        $count = count($sourceIds);

        // Sum up usage counts from source tags
        $sourceUsage = TagModel::whereIn('id', $sourceIds)->sum('usage_count');

        // Update target usage count
        TagModel::where('id', $targetId)->increment('usage_count', (int) $sourceUsage);

        // Delete source tags
        TagModel::whereIn('id', $sourceIds)->delete();

        return $count;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteUnused(): int
    {
        return TagModel::where('usage_count', '=', 0)->delete();
    }

    /**
     * Map database model to domain entity.
     */
    private function toDomain(TagModel $model): Tag
    {
        return new Tag(
            id: $model->id,
            name: $model->name,
            slug: $model->slug ?? '',
            description: $model->description,
            color: $model->color ?? '#6366f1',
            isActive: $model->is_active ?? true,
            usageCount: $model->usage_count ?? 0,
            createdAt: $model->created_at ? new \DateTimeImmutable($model->created_at) : null,
            updatedAt: $model->updated_at ? new \DateTimeImmutable($model->updated_at) : null
        );
    }

    /**
     * Map raw database row to domain entity.
     */
    private function rowToDomain(object $row): Tag
    {
        return new Tag(
            id: (int) $row->id,
            name: $row->name,
            slug: $row->slug ?? '',
            description: $row->description ?? null,
            color: $row->color ?? '#6366f1',
            isActive: (bool) ($row->is_active ?? true),
            usageCount: (int) ($row->usage_count ?? 0),
            createdAt: isset($row->created_at) ? new \DateTimeImmutable($row->created_at) : null,
            updatedAt: isset($row->updated_at) ? new \DateTimeImmutable($row->updated_at) : null
        );
    }
}
