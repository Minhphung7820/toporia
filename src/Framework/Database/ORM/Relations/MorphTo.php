<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\{Model, ModelCollection};
use Toporia\Framework\Database\Query\QueryBuilder;

/**
 * MorphTo Relationship
 *
 * Handles inverse polymorphic relationships.
 * Example: Image morphTo Post/Video (imageable)
 *
 * This implementation follows Laravel's MorphTo architecture:
 * 1. addEagerConstraints() - Build dictionary grouping models by type
 * 2. getEager() - Main eager loading entry point (replaces getResults for eager loading)
 * 3. getResultsByType() - Create FRESH query per morph type
 * 4. matchToMorphParents() - Match results to parent models
 *
 * IMPORTANT: MorphTo creates completely fresh queries for each morph type.
 * No constraints from parent queries leak into these type-specific queries.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     2.0.0
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

    /** @var array<string, callable> Constraints for each morph type (via constrain()) */
    protected array $morphableConstraints = [];

    /** @var array<string, array> Eager loads for each morph type (via morphWith()) */
    protected array $morphableEagerLoads = [];

    /**
     * Dictionary of models grouped by morph type and foreign key.
     * Structure: [morphType => [foreignKey => [model1, model2, ...]]]
     *
     * @var array<string, array<string|int, array<Model>>>
     */
    protected array $dictionary = [];

    /**
     * Models being eager loaded.
     *
     * @var ModelCollection|null
     */
    protected ?ModelCollection $models = null;

    /**
     * The relation name being loaded.
     *
     * @var string
     */
    protected string $relationName = '';

    /**
     * @param QueryBuilder $query Query builder (placeholder - MorphTo creates queries dynamically)
     * @param Model $parent Child model instance (e.g., Image)
     * @param string $morphName Morph name ('imageable')
     * @param string|null $morphType Type column (imageable_type)
     * @param string|null $morphId ID column (imageable_id)
     * @param string|null $ownerKey Owner key on parent models (id)
     */
    public function __construct(
        QueryBuilder $query,
        Model $parent,
        protected string $morphName,
        ?string $morphType = null,
        ?string $morphId = null,
        protected ?string $ownerKey = null
    ) {
        $this->morphType = $morphType ?? "{$morphName}_type";
        $this->foreignKey = $morphId ?? "{$morphName}_id";
        $this->localKey = $ownerKey ?? 'id';

        parent::__construct($query, $parent, $this->foreignKey, $this->localKey);
    }

    // =========================================================================
    // EAGER LOADING - Laravel-compatible implementation
    // =========================================================================

    /**
     * Set the constraints for an eager load of the relation.
     *
     * Builds a dictionary grouping models by morph type and foreign key
     * for efficient batch loading.
     *
     * @param array<Model> $models
     * @return void
     */
    public function addEagerConstraints(array $models): void
    {
        $this->models = new ModelCollection($models);
        $this->dictionary = [];

        $this->buildDictionary($this->models);
    }

    /**
     * Build the dictionary of models grouped by morph type.
     *
     * Structure: [morphType => [foreignKey => [model1, model2, ...]]]
     *
     * @param ModelCollection $models
     * @return void
     */
    protected function buildDictionary(ModelCollection $models): void
    {
        foreach ($models as $model) {
            $morphType = $model->getAttribute($this->morphType);
            $foreignKey = $model->getAttribute($this->foreignKey);

            // Skip if morph type or foreign key is empty
            if ($morphType === null || $morphType === '' || $foreignKey === null) {
                continue;
            }

            // Use string keys for consistent lookup
            $typeKey = (string) $morphType;
            $foreignKeyKey = is_numeric($foreignKey) ? (int) $foreignKey : (string) $foreignKey;

            $this->dictionary[$typeKey][$foreignKeyKey][] = $model;
        }
    }

    /**
     * Get the results of the relationship for eager loading.
     *
     * This is the main entry point for MorphTo eager loading.
     * It iterates through each morph type and loads results separately.
     *
     * IMPORTANT: This method creates FRESH queries for each type.
     * No constraints from parent queries leak into these queries.
     *
     * @return ModelCollection
     */
    public function getEager(): ModelCollection
    {
        foreach (array_keys($this->dictionary) as $type) {
            $this->matchToMorphParents($type, $this->getResultsByType($type));
        }

        return $this->models ?? new ModelCollection([]);
    }

    /**
     * Get all the relation results for a specific morph type.
     *
     * Creates a COMPLETELY FRESH query for the morph type.
     * This ensures no constraints from parent queries leak in.
     *
     * @param string $type Morph type (model class name)
     * @return ModelCollection
     */
    protected function getResultsByType(string $type): ModelCollection
    {
        $modelClass = $this->getModelClass($type);

        // Validate model class
        if (!class_exists($modelClass)) {
            return new ModelCollection([]);
        }

        if (!is_subclass_of($modelClass, Model::class)) {
            return new ModelCollection([]);
        }

        // Create FRESH query - no inheritance from parent
        $ownerKey = $this->ownerKey ?? $modelClass::getKeyName();

        // Start with a completely fresh query using static method
        $query = $modelClass::query();

        // Apply eager loads for this type (from morphWith())
        $eagerLoads = $this->morphableEagerLoads[$modelClass] ?? [];
        if (!empty($eagerLoads)) {
            $query->with($eagerLoads);
        }

        // Apply constraints for this type (from constrain())
        $constraint = $this->morphableConstraints[$modelClass] ?? null;
        if ($constraint !== null && is_callable($constraint)) {
            $constraint($query);
        }

        // Get unique IDs for this type
        $ids = $this->gatherKeysByType($type);

        if (empty($ids)) {
            return new ModelCollection([]);
        }

        // Execute query with WHERE IN
        // Use table-qualified column to avoid ambiguity
        $table = $modelClass::getTableName();
        $qualifiedColumn = $table ? "{$table}.{$ownerKey}" : $ownerKey;

        return $query->whereIn($qualifiedColumn, $ids)->get();
    }

    /**
     * Gather all unique foreign keys for a given morph type.
     *
     * @param string $type Morph type
     * @return array<int|string>
     */
    protected function gatherKeysByType(string $type): array
    {
        if (!isset($this->dictionary[$type])) {
            return [];
        }

        return array_keys($this->dictionary[$type]);
    }

    /**
     * Match results to their parent models.
     *
     * @param string $type Morph type
     * @param ModelCollection $results
     * @return void
     */
    protected function matchToMorphParents(string $type, ModelCollection $results): void
    {
        foreach ($results as $result) {
            $ownerKey = $this->ownerKey ?? $result->getKeyName();
            $key = $result->getAttribute($ownerKey);

            // Handle both numeric and string keys
            $lookupKey = is_numeric($key) ? (int) $key : (string) $key;

            if (isset($this->dictionary[$type][$lookupKey])) {
                foreach ($this->dictionary[$type][$lookupKey] as $model) {
                    $model->setRelation($this->relationName, $result);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * For MorphTo, match() is called by the eager loading system but
     * the actual matching happens in getEager() -> matchToMorphParents().
     *
     * This method just ensures models without matches get null relations.
     */
    public function match(array $models, mixed $results, string $relationName): array
    {
        // Store relation name for use in matchToMorphParents
        $this->relationName = $relationName;

        // Set null relations for models that weren't matched
        foreach ($models as $model) {
            if (!$model->relationLoaded($relationName)) {
                $model->setRelation($relationName, null);
            }
        }

        return $models;
    }

    /**
     * Get the results of the relationship (lazy loading).
     *
     * Used when accessing the relation property directly (not eager loading).
     *
     * @return Model|null
     */
    public function getResults(): mixed
    {
        // For eager loading, return the already-matched models
        if ($this->models !== null) {
            return $this->getEager();
        }

        // Lazy loading - load single related model
        return $this->loadSingleResult();
    }

    /**
     * Load single result for lazy loading.
     *
     * @return Model|null
     */
    protected function loadSingleResult(): ?Model
    {
        $type = $this->parent->getAttribute($this->morphType);
        $id = $this->parent->getAttribute($this->foreignKey);

        // Explicit null/empty check
        if ($type === null || $type === '' || $id === null) {
            return null;
        }

        $modelClass = $this->getModelClass($type);

        if (!class_exists($modelClass)) {
            return null;
        }

        if (!is_subclass_of($modelClass, Model::class)) {
            return null;
        }

        $ownerKey = $this->ownerKey ?? $modelClass::getKeyName();

        $query = $modelClass::query()->where($ownerKey, $id);

        // Apply constraint for this morph type if set
        $constraint = $this->morphableConstraints[$modelClass] ?? null;
        if ($constraint !== null && is_callable($constraint)) {
            $constraint($query);
        }

        return $query->first();
    }

    // =========================================================================
    // CONSTRAINT METHODS
    // =========================================================================

    /**
     * Set constraints for each morph type.
     *
     * Allows applying different query constraints to different morph types
     * when eager loading polymorphic relationships.
     *
     * @param array<string, callable> $callbacks Array mapping model class names to constraint callables
     * @return $this
     *
     * @example
     * ```php
     * ->with(['image.imageable' => function (MorphTo $morphTo) {
     *     $morphTo->constrain([
     *         PostModel::class => function ($query) {
     *             $query->where('is_published', true);
     *         },
     *         VideoModel::class => function ($query) {
     *             $query->where('duration', '>', 60);
     *         },
     *     ]);
     * }])
     * ```
     */
    public function constrain(array $callbacks): static
    {
        $this->morphableConstraints = array_merge(
            $this->morphableConstraints,
            $callbacks
        );

        return $this;
    }

    /**
     * Set eager loads for each morph type.
     *
     * Allows loading different relationships for different morph types.
     *
     * @param array<string, array> $with Array mapping model class names to eager load arrays
     * @return $this
     *
     * @example
     * ```php
     * ->with(['image.imageable' => function (MorphTo $morphTo) {
     *     $morphTo->morphWith([
     *         PostModel::class => ['author', 'tags'],
     *         VideoModel::class => ['channel', 'comments'],
     *     ]);
     * }])
     * ```
     */
    public function morphWith(array $with): static
    {
        $this->morphableEagerLoads = array_merge(
            $this->morphableEagerLoads,
            $with
        );

        return $this;
    }

    // =========================================================================
    // FACTORY METHOD FOR EAGER LOADING
    // =========================================================================

    /**
     * {@inheritdoc}
     *
     * Create a new instance for eager loading.
     *
     * IMPORTANT: MorphTo doesn't use the freshQuery parameter.
     * All queries are created fresh in getResultsByType().
     */
    public function newEagerInstance(QueryBuilder $freshQuery): static
    {
        $instance = new static(
            $freshQuery, // Not actually used - queries created fresh per type
            $this->parent,
            $this->morphName,
            $this->morphType,
            $this->foreignKey,
            $this->ownerKey
        );

        // Copy configuration
        $instance->setMorphMap($this->morphMap);
        $instance->morphableConstraints = $this->morphableConstraints;
        $instance->morphableEagerLoads = $this->morphableEagerLoads;

        return $instance;
    }

    // =========================================================================
    // INTERNAL HELPERS
    // =========================================================================

    /**
     * Get the related model class from type.
     *
     * @param string $type
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

        // Use morphMap if available, otherwise use full class name
        $morphType = $this->getMorphTypeForModel($model);

        $this->parent->setAttribute($this->foreignKey, $model->getKey());
        $this->parent->setAttribute($this->morphType, $morphType);
        $this->parent->setRelation($this->getRelationName(), $model);

        return $this->parent;
    }

    /**
     * Get morph type for a model, using morphMap if available.
     */
    protected function getMorphTypeForModel(Model $model): string
    {
        $modelClass = get_class($model);

        // Check if there's a reverse mapping in morphMap (value => key)
        foreach ($this->morphMap as $type => $class) {
            if ($class === $modelClass) {
                return $type;
            }
        }

        // Fallback to full class name
        return $modelClass;
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

        if (!is_subclass_of($modelClass, Model::class)) {
            throw new \InvalidArgumentException("Class {$modelClass} is not a valid Model subclass");
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
        $related = $this->loadSingleResult();

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
        return $this->loadSingleResult()?->delete() ?? false;
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

        if ($type === null || $type === '' || $id === null) {
            return false;
        }

        $modelClass = $this->getModelClass($type);

        if (!class_exists($modelClass)) {
            return false;
        }

        if (!is_subclass_of($modelClass, Model::class)) {
            return false;
        }

        $ownerKey = $this->ownerKey ?? $modelClass::getKeyName();

        return $modelClass::where($ownerKey, $id)->exists();
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

        if ($type === null || $type === '' || $id === null) {
            return null;
        }

        $modelClass = $this->getModelClass($type);

        if (!class_exists($modelClass)) {
            return null;
        }

        if (!is_subclass_of($modelClass, Model::class)) {
            return null;
        }

        $ownerKey = $this->ownerKey ?? $modelClass::getKeyName();

        $query = $modelClass::query()->where($ownerKey, $id);

        foreach ($constraints as $column => $value) {
            $query->where($column, $value);
        }

        return $query->first();
    }

    // =========================================================================
    // GETTER METHODS
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

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
        $related = $this->loadSingleResult();

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
