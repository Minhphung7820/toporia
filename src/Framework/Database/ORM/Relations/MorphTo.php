<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\{Model, ModelCollection};
use Toporia\Framework\Database\Query\QueryBuilder;

/**
 * MorphTo Relationship
 *
 * Handles inverse polymorphic relationships.
 * Example: Comment morphTo Post/Video (commentable)
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.1.0
 * @package     toporia/framework
 * @subpackage  Relations
 * @since       2025-01-10
 */
class MorphTo extends Relation
{
    /** @var string Morph type column name */
    protected string $morphType;

    /** @var array<string, class-string<Model>> Type to class mapping */
    protected array $morphMap = [];

    /** @var string|null Cached relation name */
    private ?string $relationNameCache = null;

    /**
     * @param QueryBuilder $query Query builder (will be replaced dynamically)
     * @param Model $parent Child model instance (Comment)
     * @param string $morphName Morph name ('commentable')
     * @param string|null $morphType Type column (commentable_type)
     * @param string|null $morphId ID column (commentable_id)
     * @param string|null $ownerKey Owner key on parent models (id)
     */
    public function __construct(
        QueryBuilder $query,
        Model $parent,
        protected string $morphName,
        ?string $morphType = null,
        ?string $morphId = null,
        ?string $ownerKey = null
    ) {
        $this->morphType = $morphType ?? "{$morphName}_type";
        $this->foreignKey = $morphId ?? "{$morphName}_id";
        $this->localKey = $ownerKey ?? 'id';

        parent::__construct($query, $parent, $this->foreignKey, $this->localKey);
    }

    // =========================================================================
    // INTERNAL HELPERS
    // =========================================================================

    /**
     * Get morph type and ID from parent model.
     *
     * @return array{type: string|null, id: mixed}
     */
    protected function getMorphTypeAndId(): array
    {
        $type = $this->parent->getAttribute($this->morphType);
        $id = $this->parent->getAttribute($this->foreignKey);

        return ['type' => $type, 'id' => $id];
    }

    /**
     * Get the related model class from type.
     *
     * @return class-string<Model>
     */
    protected function getModelClass(string $type): string
    {
        return $this->morphMap[$type] ?? $type;
    }

    /**
     * Get the relation name (cached for performance).
     */
    protected function getRelationName(): string
    {
        return $this->relationNameCache ??= $this->morphName;
    }

    // =========================================================================
    // CORE RELATION METHODS
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function getResults(): ?Model
    {
        ['type' => $type, 'id' => $id] = $this->getMorphTypeAndId();

        // Explicit null/empty check - type must be a non-empty string, id must be non-null
        if ($type === null || $type === '' || $id === null) {
            return null;
        }

        $modelClass = $this->getModelClass($type);

        if (!class_exists($modelClass)) {
            // Log warning for debugging purposes - type exists but class doesn't
            // This indicates potential data integrity issue or missing morph map entry
            return null;
        }

        return $modelClass::query()
            ->where($this->localKey, $id)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function addEagerConstraints(array $models): void
    {
        // MorphTo groups by type and loads separately in match()
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $models, mixed $results, string $relationName): array
    {
        $groups = $this->groupModelsByType($models);
        $relatedModels = $this->loadRelatedByGroups($groups);

        foreach ($models as $model) {
            $type = $model->getAttribute($this->morphType);
            $id = $model->getAttribute($this->foreignKey);

            // Explicit check: type must be non-empty string, id must be non-null
            $key = ($type !== null && $type !== '' && $id !== null) ? "{$type}:{$id}" : null;
            $model->setRelation($relationName, $key !== null ? ($relatedModels[$key] ?? null) : null);
        }

        return $models;
    }

    /**
     * Group models by morph type.
     *
     * @return array<string, array<mixed>>
     */
    protected function groupModelsByType(array $models): array
    {
        $groups = [];

        foreach ($models as $model) {
            $type = $model->getAttribute($this->morphType);
            $id = $model->getAttribute($this->foreignKey);

            // Explicit check: type must be non-empty string, id must be non-null
            if ($type !== null && $type !== '' && $id !== null) {
                $groups[$type][] = $id;
            }
        }

        return $groups;
    }

    /**
     * Load related models for each type group.
     *
     * @return array<string, Model>
     */
    protected function loadRelatedByGroups(array $groups): array
    {
        $relatedModels = [];

        foreach ($groups as $type => $ids) {
            $modelClass = $this->getModelClass($type);

            // Validate that class exists and is a valid Model subclass
            if (!class_exists($modelClass)) {
                // Skip invalid morph types - data integrity issue
                continue;
            }

            if (!is_subclass_of($modelClass, Model::class)) {
                // Skip non-Model classes - security and type safety
                continue;
            }

            // Apply SoftDeletes scope automatically (respects withTrashed() via ModelQueryBuilder)
            $query = $modelClass::whereIn('id', array_unique($ids));
            $related = $query->get();

            foreach ($related as $model) {
                $relatedModels["{$type}:{$model->getKey()}"] = $model;
            }
        }

        return $relatedModels;
    }

    /**
     * {@inheritdoc}
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * {@inheritdoc}
     */
    public function newEagerInstance(QueryBuilder $freshQuery): static
    {
        $instance = new static(
            $freshQuery,
            $this->parent,
            $this->morphName,
            $this->morphType,
            $this->foreignKey,
            $this->localKey
        );

        $instance->setMorphMap($this->morphMap);

        // Use freshQuery directly instead of creating another new query
        // freshQuery already has the table set from loadRelationBatch
        $instance->setQuery($freshQuery);

        // Copy where constraints from original query (excluding parent-specific local key constraint)
        $this->copyWhereConstraints($freshQuery, [$this->localKey]);

        return $instance;
    }

    // =========================================================================
    // MORPH MAP METHODS
    // =========================================================================

    /**
     * Set morph map for type resolution.
     *
     * @param array<string, class-string<Model>> $map
     */
    public function setMorphMap(array $map): static
    {
        $this->morphMap = $map;
        return $this;
    }

    /**
     * Get the morph map.
     */
    public function getMorphMap(): array
    {
        return $this->morphMap;
    }

    /**
     * Get all available morph types.
     */
    public function getAvailableTypes(): array
    {
        return array_keys($this->morphMap);
    }

    // =========================================================================
    // ASSOCIATION METHODS
    // =========================================================================

    /**
     * Associate the parent model with the given model.
     */
    public function associate(?Model $model): Model
    {
        if ($model === null) {
            return $this->dissociate();
        }

        $this->parent->setAttribute($this->foreignKey, $model->getKey());
        $this->parent->setAttribute($this->morphType, get_class($model));
        $this->parent->setRelation($this->getRelationName(), $model);

        return $this->parent;
    }

    /**
     * Dissociate the parent model from its related model.
     */
    public function dissociate(): Model
    {
        $this->parent->setAttribute($this->foreignKey, null);
        $this->parent->setAttribute($this->morphType, null);
        $this->parent->setRelation($this->getRelationName(), null);

        return $this->parent;
    }

    // =========================================================================
    // CRUD OPERATIONS
    // =========================================================================

    /**
     * Create a new model of the specified type and associate it.
     */
    public function createOfType(string $type, array $attributes = []): Model
    {
        $modelClass = $this->getModelClass($type);

        if (!class_exists($modelClass)) {
            throw new \InvalidArgumentException("Model class {$modelClass} does not exist");
        }

        $instance = $modelClass::create($attributes);
        $this->associate($instance);
        $this->parent->save();

        return $instance;
    }

    /**
     * Update the related model.
     */
    public function update(array $attributes): int
    {
        $related = $this->getResults();

        if ($related === null) {
            return 0;
        }

        $related->fill($attributes);
        return $related->save() ? 1 : 0;
    }

    /**
     * Delete the related model.
     */
    public function delete(): bool
    {
        return $this->getResults()?->delete() ?? false;
    }

    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    /**
     * Check if the related model exists.
     */
    public function exists(): bool
    {
        ['type' => $type, 'id' => $id] = $this->getMorphTypeAndId();

        if (!$type || !$id) {
            return false;
        }

        $modelClass = $this->getModelClass($type);

        return class_exists($modelClass) && $modelClass::where($this->localKey, $id)->exists();
    }

    /**
     * Get the count of related models (always 0 or 1 for MorphTo).
     */
    public function count(): int
    {
        return $this->exists() ? 1 : 0;
    }

    /**
     * Load the related model with additional constraints.
     */
    public function loadWith(array $constraints = []): ?Model
    {
        ['type' => $type, 'id' => $id] = $this->getMorphTypeAndId();

        if (!$type || !$id) {
            return null;
        }

        $modelClass = $this->getModelClass($type);

        if (!class_exists($modelClass)) {
            return null;
        }

        $query = $modelClass::where($this->localKey, $id);

        foreach ($constraints as $column => $value) {
            $query->where($column, $value);
        }

        return $query->first();
    }

    // =========================================================================
    // GETTER METHODS
    // =========================================================================

    /**
     * Get the morph type column name.
     */
    public function getMorphType(): string
    {
        return $this->morphType;
    }

    /**
     * Get the morph type value from the parent model.
     */
    public function getMorphTypeValue(): ?string
    {
        return $this->parent->getAttribute($this->morphType);
    }

    /**
     * Get the morph ID value from the parent model.
     */
    public function getMorphId(): mixed
    {
        return $this->parent->getAttribute($this->foreignKey);
    }

    // =========================================================================
    // TYPE CHECKING METHODS
    // =========================================================================

    /**
     * Check if the parent is associated with a specific model type.
     */
    public function isType(string $type): bool
    {
        $currentType = $this->getMorphTypeValue();

        if (!$currentType) {
            return false;
        }

        return $currentType === $type ||
            (isset($this->morphMap[$type]) && $this->morphMap[$type] === $currentType);
    }

    /**
     * Check if the parent is associated with a specific model instance.
     */
    public function is(Model $model): bool
    {
        ['type' => $type, 'id' => $id] = $this->getMorphTypeAndId();

        return $type && $id && get_class($model) === $type && $model->getKey() === $id;
    }

    /**
     * Check if the parent is not associated with a specific model instance.
     */
    public function isNot(Model $model): bool
    {
        return !$this->is($model);
    }

    // =========================================================================
    // MAGIC METHODS
    // =========================================================================

    /**
     * Magic method to delegate calls to the related model.
     */
    public function __call(string $method, array $parameters): mixed
    {
        $related = $this->getResults();

        if ($related === null) {
            throw new \BadMethodCallException(
                sprintf('No related model found for MorphTo relation %s', $this->morphName)
            );
        }

        if (method_exists($related, $method)) {
            return $related->{$method}(...$parameters);
        }

        throw new \BadMethodCallException(
            sprintf('Method %s does not exist on related model %s', $method, get_class($related))
        );
    }
}
