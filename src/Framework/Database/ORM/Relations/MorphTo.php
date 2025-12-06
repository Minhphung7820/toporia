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
     * Nested eager loads to apply to all morph types (from dot notation like 'imageable.comments').
     * These are applied AFTER type-specific morphableEagerLoads.
     *
     * @var array<string, mixed>
     */
    protected array $nestedEagerLoads = [];

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
     * Flag to enable/disable UNION ALL batch loading optimization.
     *
     * @var bool
     */
    protected bool $useBatchLoading = true;

    /**
     * Minimum number of types to trigger UNION ALL batch loading.
     * Below this threshold, individual queries are used.
     *
     * @var int
     */
    protected int $batchLoadingThreshold = 2;

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
        $types = array_keys($this->dictionary);

        // Check if we can use UNION ALL batch loading optimization
        if ($this->canUseBatchLoading($types)) {
            return $this->getEagerWithUnionAll($types);
        }

        // Standard loading: one query per type
        foreach ($types as $type) {
            // Use extended method that supports withCount if configured
            if ($this->hasMorphWithCounts()) {
                $this->matchToMorphParents($type, $this->getResultsByTypeWithCount($type));
            } else {
                $this->matchToMorphParents($type, $this->getResultsByType($type));
            }
        }

        return $this->models ?? new ModelCollection([]);
    }

    /**
     * Check if UNION ALL batch loading can be used.
     *
     * Batch loading is only possible when:
     * - useBatchLoading is enabled
     * - Number of types >= batchLoadingThreshold
     * - No type-specific eager loads (morphWith)
     * - No type-specific constraints (constrain)
     * - No morphWithCount configured
     * - All types have same primary key column
     *
     * @param array<string> $types Morph types
     * @return bool
     */
    protected function canUseBatchLoading(array $types): bool
    {
        // Check basic conditions
        if (!$this->useBatchLoading) {
            return false;
        }

        if (count($types) < $this->batchLoadingThreshold) {
            return false;
        }

        // Check for type-specific configurations that prevent batching
        if (!empty($this->morphableEagerLoads)) {
            return false;
        }

        if (!empty($this->morphableConstraints)) {
            return false;
        }

        if ($this->hasMorphWithCounts()) {
            return false;
        }

        // Verify all types are valid models with same key column
        $keyColumn = null;
        foreach ($types as $type) {
            $modelClass = $this->getModelClass($type);

            if (!class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
                return false;
            }

            $typeKeyColumn = $modelClass::getKeyName();

            if ($keyColumn === null) {
                $keyColumn = $typeKeyColumn;
            } elseif ($keyColumn !== $typeKeyColumn) {
                // Different primary key columns, cannot batch
                return false;
            }
        }

        return true;
    }

    /**
     * Get eager load results using UNION ALL optimization.
     *
     * This method combines queries for all morph types into a single UNION ALL query,
     * reducing the number of database round-trips from N to 1.
     *
     * Performance benefit is most significant when:
     * - There are many morph types (3+)
     * - Each type has relatively few records
     * - Network latency is high
     *
     * @param array<string> $types Morph types to load
     * @return ModelCollection
     */
    protected function getEagerWithUnionAll(array $types): ModelCollection
    {
        $unionParts = [];
        $allBindings = [];

        foreach ($types as $type) {
            $modelClass = $this->getModelClass($type);
            $table = $modelClass::getTableName();
            $ownerKey = $this->ownerKey ?? $modelClass::getKeyName();
            $ids = $this->gatherKeysByType($type);

            if (empty($ids)) {
                continue;
            }

            // Resolve morph type alias
            $morphTypeValue = Relation::getMorphAlias($modelClass);

            // Build SELECT with morph type column for identification
            $quotedMorphType = $this->quoteValue($morphTypeValue);
            $placeholders = implode(', ', array_fill(0, count($ids), '?'));

            // Select all columns plus morph type identifier
            $selectSql = "SELECT {$table}.*, {$quotedMorphType} AS __morph_type FROM {$table} WHERE {$table}.{$ownerKey} IN ({$placeholders})";

            // Apply soft delete scope if model uses it
            if (method_exists($modelClass, 'usesSoftDeletes') && $modelClass::usesSoftDeletes()) {
                $deletedAtColumn = method_exists($modelClass, 'getDeletedAtColumn')
                    ? $modelClass::getDeletedAtColumn()
                    : 'deleted_at';
                $selectSql .= " AND {$table}.{$deletedAtColumn} IS NULL";
            }

            $unionParts[] = "({$selectSql})";
            $allBindings = array_merge($allBindings, $ids);
        }

        if (empty($unionParts)) {
            return $this->models ?? new ModelCollection([]);
        }

        // Execute UNION ALL query
        $unionSql = implode(' UNION ALL ', $unionParts);

        // Get database connection from parent model's query builder
        // Use the query builder's connection since Model::getConnection() is protected static
        $connection = $this->query->getConnection();
        $results = $connection->select($unionSql, $allBindings);

        // Group results by morph type and hydrate
        $resultsByType = [];
        foreach ($results as $row) {
            // Handle both object and array results from database
            if (is_object($row)) {
                $rowArray = (array) $row;
            } else {
                $rowArray = $row;
            }

            $morphType = $rowArray['__morph_type'] ?? null;
            if ($morphType === null) {
                continue;
            }

            // Remove the identifier column
            unset($rowArray['__morph_type']);

            // Convert back to object for hydration consistency
            $resultsByType[$morphType][] = (object) $rowArray;
        }

        // Match results to parents
        foreach ($resultsByType as $morphType => $typeResults) {
            // Find the original type key in dictionary
            $originalType = $this->findOriginalTypeKey($morphType);
            if ($originalType === null) {
                continue;
            }

            $modelClass = $this->getModelClass($originalType);

            // Hydrate results into models
            /** @var callable $hydrate */
            $hydrate = [$modelClass, 'hydrate'];
            $hydratedModels = $hydrate($typeResults);

            // Load universal nested eager loads if any
            if (!empty($this->nestedEagerLoads)) {
                /** @var callable $eagerLoadRelations */
                $eagerLoadRelations = [$modelClass, 'eagerLoadRelations'];
                $eagerLoadRelations($hydratedModels, $this->nestedEagerLoads);
            }

            $this->matchToMorphParents($originalType, $hydratedModels);
        }

        return $this->models ?? new ModelCollection([]);
    }

    /**
     * Find the original type key in the dictionary from a morph alias.
     *
     * @param string $morphAlias The morph alias (from __morph_type)
     * @return string|null Original type key or null if not found
     */
    protected function findOriginalTypeKey(string $morphAlias): ?string
    {
        foreach (array_keys($this->dictionary) as $typeKey) {
            $modelClass = $this->getModelClass($typeKey);
            $alias = Relation::getMorphAlias($modelClass);

            if ($alias === $morphAlias || $modelClass === $morphAlias) {
                return $typeKey;
            }
        }

        return null;
    }

    /**
     * Quote a value for SQL.
     *
     * @param mixed $value Value to quote
     * @return string Quoted value
     */
    protected function quoteValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        // Escape single quotes
        return "'" . str_replace("'", "''", (string) $value) . "'";
    }

    /**
     * Enable or disable UNION ALL batch loading.
     *
     * @param bool $enable Whether to enable batch loading
     * @return $this
     */
    public function withBatchLoading(bool $enable = true): static
    {
        $this->useBatchLoading = $enable;
        return $this;
    }

    /**
     * Set the minimum number of types to trigger batch loading.
     *
     * @param int $threshold Minimum number of types
     * @return $this
     */
    public function setBatchLoadingThreshold(int $threshold): static
    {
        $this->batchLoadingThreshold = max(2, $threshold);
        return $this;
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

        // Build combined eager loads:
        // 1. Type-specific eager loads from morphWith() (highest priority)
        // 2. Universal nested eager loads from dot notation (applies to all types)
        $typeSpecificLoads = $this->morphableEagerLoads[$modelClass] ?? [];
        $universalLoads = $this->nestedEagerLoads;

        // Merge: type-specific takes priority over universal
        $combinedEagerLoads = array_merge($universalLoads, $typeSpecificLoads);

        if (!empty($combinedEagerLoads)) {
            $query->with($combinedEagerLoads);
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

    /**
     * Set nested eager loads that apply to ALL morph types.
     *
     * This is used internally by the eager loading system when dot notation
     * is used to load nested relations through MorphTo.
     *
     * Example: 'image.imageable.comments' will set ['comments' => constraint]
     * as a nested eager load that applies to whatever model type imageable resolves to.
     *
     * @param array<string, mixed> $nestedLoads Array of relation names to eager load
     * @return $this
     *
     * @internal Used by HasEagerLoading trait
     */
    public function setNestedEagerLoads(array $nestedLoads): static
    {
        $this->nestedEagerLoads = array_merge(
            $this->nestedEagerLoads,
            $nestedLoads
        );

        return $this;
    }

    /**
     * Get the nested eager loads.
     *
     * @return array<string, mixed>
     */
    public function getNestedEagerLoads(): array
    {
        return $this->nestedEagerLoads;
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
        $instance->nestedEagerLoads = $this->nestedEagerLoads;
        $instance->morphWithCounts = $this->morphWithCounts;
        $instance->useBatchLoading = $this->useBatchLoading;
        $instance->batchLoadingThreshold = $this->batchLoadingThreshold;

        return $instance;
    }

    // =========================================================================
    // INTERNAL HELPERS
    // =========================================================================

    /**
     * Get the related model class from type.
     *
     * Resolution order (first match wins):
     * 1. Instance-level morphMap (set via setMorphMap())
     * 2. Global morph map (set via Relation::morphMap())
     * 3. Use type as-is (assumed to be full class name)
     *
     * @param string $type Morph type alias or class name
     * @return class-string<Model>
     */
    protected function getModelClass(string $type): string
    {
        // 1. Check instance-level morph map first (highest priority)
        if (isset($this->morphMap[$type])) {
            return $this->morphMap[$type];
        }

        // 2. Check global morph map
        $globalMapped = Relation::getMorphedModel($type);
        if ($globalMapped !== $type) {
            return $globalMapped;
        }

        // 3. Return type as-is (assumed to be full class name)
        return $type;
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
    // MORPH WITH COUNT METHODS
    // =========================================================================

    /**
     * Eager loads for counting nested relations of morph parents.
     *
     * Structure: [morphType => [relation => constraint, ...], ...]
     *
     * @var array<string, array<string, callable|null>>
     */
    protected array $morphWithCounts = [];

    /**
     * Set eager load counts for each morph type.
     *
     * Allows counting different relationships for different morph types
     * when eager loading through MorphTo.
     *
     * @param array<string, string|array> $relations Array mapping model class names to relations to count
     * @return $this
     *
     * @example
     * ```php
     * // Count different relations for each morph type
     * Image::with(['imageable' => function (MorphTo $morphTo) {
     *     $morphTo->morphWithCount([
     *         Post::class => ['comments', 'likes'],
     *         Video::class => ['views', 'comments'],
     *     ]);
     * }])->get();
     *
     * // Access counts:
     * // $image->imageable->comments_count (if imageable is Post or Video)
     * // $image->imageable->likes_count (if imageable is Post)
     * // $image->imageable->views_count (if imageable is Video)
     * ```
     */
    public function morphWithCount(array $relations): static
    {
        foreach ($relations as $type => $typeRelations) {
            // Normalize to array
            $typeRelations = is_string($typeRelations) ? [$typeRelations] : $typeRelations;

            // Store with proper format
            $this->morphWithCounts[$type] = [];
            foreach ($typeRelations as $key => $value) {
                if (is_int($key)) {
                    // Simple relation name
                    $this->morphWithCounts[$type][$value] = null;
                } else {
                    // Relation with callback
                    $this->morphWithCounts[$type][$key] = $value;
                }
            }
        }

        return $this;
    }

    /**
     * Get all relation results for a specific morph type (with withCount support).
     *
     * Creates a COMPLETELY FRESH query for the morph type.
     * This ensures no constraints from parent queries leak in.
     * Also applies morphWithCount if configured.
     *
     * @param string $type Morph type (model class name)
     * @return ModelCollection
     */
    protected function getResultsByTypeWithCount(string $type): ModelCollection
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

        // Build combined eager loads:
        // 1. Type-specific eager loads from morphWith() (highest priority)
        // 2. Universal nested eager loads from dot notation (applies to all types)
        $typeSpecificLoads = $this->morphableEagerLoads[$modelClass] ?? [];
        $universalLoads = $this->nestedEagerLoads;

        // Merge: type-specific takes priority over universal
        $combinedEagerLoads = array_merge($universalLoads, $typeSpecificLoads);

        if (!empty($combinedEagerLoads)) {
            $query->with($combinedEagerLoads);
        }

        // Apply withCount for this morph type
        if (isset($this->morphWithCounts[$modelClass]) && !empty($this->morphWithCounts[$modelClass])) {
            $countRelations = [];
            foreach ($this->morphWithCounts[$modelClass] as $relation => $callback) {
                if ($callback === null) {
                    $countRelations[] = $relation;
                } else {
                    $countRelations[$relation] = $callback;
                }
            }
            $query->withCount($countRelations);
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
    public function getEagerWithCount(): ModelCollection
    {
        foreach (array_keys($this->dictionary) as $type) {
            // Use extended method that supports withCount
            $this->matchToMorphParents($type, $this->getResultsByTypeWithCount($type));
        }

        return $this->models ?? new ModelCollection([]);
    }

    /**
     * Check if morphWithCount is configured.
     *
     * @return bool
     */
    public function hasMorphWithCounts(): bool
    {
        return !empty($this->morphWithCounts);
    }

    /**
     * Get the morph with counts configuration.
     *
     * @return array<string, array<string, callable|null>>
     */
    public function getMorphWithCounts(): array
    {
        return $this->morphWithCounts;
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
