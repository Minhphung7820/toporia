<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\{Model, ModelCollection, MorphPivot, Pivot};
use Toporia\Framework\Database\Query\{QueryBuilder, RowCollection};
use Toporia\Framework\Support\Str;

/**
 * MorphToMany Relationship
 *
 * Handles polymorphic many-to-many relationships.
 * Example: Post/Video morphToMany Tags
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.1.0
 * @package     toporia/framework
 * @subpackage  Relations
 * @since       2025-01-10
 */
class MorphToMany extends Relation
{
    /** @var string Pivot table name */
    protected string $pivotTable;

    /** @var string Morph type column */
    protected string $morphType;

    /** @var string Related pivot key */
    protected string $relatedPivotKey;

    /** @var string Related key */
    protected string $relatedKey;

    /** @var array<string> Additional pivot columns to select */
    protected array $pivotColumns = [];

    /** @var bool Whether to include all pivot columns (lazy loaded) */
    protected bool $withPivotAll = false;

    /** @var bool Whether to include timestamps in pivot table */
    protected bool $withTimestamps = false;

    /** @var string Custom pivot accessor name */
    protected string $pivotAccessor = 'pivot';

    /** @var array<array{column: string, operator: string, value: mixed}> Pivot where constraints */
    protected array $pivotWheres = [];

    /** @var array<array{column: string, values: array}> Pivot whereIn constraints */
    protected array $pivotWhereIns = [];

    /** @var array<array{column: string, direction: string}> Pivot order by clauses */
    protected array $pivotOrderBy = [];

    /** @var class-string|null Custom pivot model class */
    protected ?string $pivotClass = null;

    /** @var string|null Cached related table name */
    private ?string $relatedTableCache = null;

    /** @var array<string, array{columns: array<string>, timestamp: int}> Schema cache for table columns */
    private static array $schemaCache = [];

    /** @var int Schema cache TTL in seconds (5 minutes) */
    private static int $schemaCacheTtl = 300;

    /**
     * @param QueryBuilder $query Query builder
     * @param Model $parent Parent model (Post or Video)
     * @param class-string<Model> $relatedClass Related model class (Tag)
     * @param string $morphName Morph name ('taggable')
     * @param string|null $pivotTable Pivot table name (taggables)
     * @param string|null $morphType Type column (taggable_type)
     * @param string|null $morphId ID column (taggable_id)
     * @param string|null $relatedKey Related key (tag_id)
     * @param string|null $parentKey Parent key (id)
     * @param string|null $relatedPrimaryKey Related primary key (id)
     */
    public function __construct(
        QueryBuilder $query,
        Model $parent,
        protected string $relatedClass,
        protected string $morphName,
        ?string $pivotTable = null,
        ?string $morphType = null,
        ?string $morphId = null,
        ?string $relatedKey = null,
        ?string $parentKey = null,
        ?string $relatedPrimaryKey = null
    ) {
        $this->pivotTable = $pivotTable ?? $this->guessPivotTable();
        $this->morphType = $morphType ?? "{$morphName}_type";
        $this->foreignKey = $morphId ?? "{$morphName}_id";
        $this->relatedPivotKey = $relatedKey ?? $this->guessRelatedKey();
        $this->localKey = $parentKey ?? $parent::getPrimaryKey();
        $this->relatedKey = $relatedPrimaryKey ?? $this->relatedClass::getPrimaryKey();

        parent::__construct($query, $parent, $this->foreignKey, $this->localKey);

        $this->performJoin();
    }

    /**
     * {@inheritdoc}
     */
    public function getRelatedClass(): string
    {
        return $this->relatedClass;
    }

    // =========================================================================
    // TABLE NAME HELPERS (with caching)
    // =========================================================================

    /**
     * Get cached related table name.
     */
    protected function getRelatedTable(): string
    {
        return $this->relatedTableCache ??= $this->relatedClass::getTableName();
    }

    /**
     * Guess pivot table name.
     */
    protected function guessPivotTable(): string
    {
        return $this->morphName . 's';
    }

    /**
     * Guess related key name.
     */
    protected function guessRelatedKey(): string
    {
        $parts = explode('\\', $this->relatedClass);
        $className = end($parts);

        // Strip "Model" suffix if present (e.g., TagModel -> tag_id)
        if (Str::endsWith($className, 'Model')) {
            $className = substr($className, 0, -5); // Remove "Model"
        }

        return strtolower($className) . '_id';
    }

    /**
     * Get morph class name/alias for parent.
     *
     * Resolution order (first match wins):
     * 1. Model's getMorphClass() method if defined
     * 2. Global morph map alias (if registered via Relation::morphMap())
     * 3. Full namespace class name (e.g., "App\Models\Product")
     *
     * @return string Morph alias or full namespace class name
     */
    protected function getMorphClass(): string
    {
        // 1. Allow parent model to override getMorphClass() method
        if (method_exists($this->parent, 'getMorphClass')) {
            return $this->parent->getMorphClass();
        }

        // 2. Check global morph map for alias
        $className = get_class($this->parent);
        $alias = Relation::getMorphAlias($className);

        // 3. Return alias if found, otherwise return full class name
        return $alias;
    }

    // =========================================================================
    // INTERNAL HELPERS
    // =========================================================================

    /**
     * Create a new pivot query builder.
     */
    protected function newPivotQuery(): QueryBuilder
    {
        return (new QueryBuilder($this->query->getConnection()))
            ->table($this->pivotTable)
            ->where($this->foreignKey, $this->parent->getAttribute($this->localKey))
            ->where($this->morphType, $this->getMorphClass());
    }

    /**
     * Build dictionary for eager loading matching.
     *
     * OPTIMIZED: Uses pivot data from main query (with pivot_ prefix) instead of separate query.
     *
     * @return array<string, array<Model>>
     */
    protected function buildDictionary(ModelCollection $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            // Get morphType and foreignKey from pivot attributes (with pivot_ prefix)
            // These are selected in newEagerInstance() with alias pivot_*
            $type = $result->getAttribute("pivot_{$this->morphType}");
            $id = $result->getAttribute("pivot_{$this->foreignKey}");

            if ($type !== null && $id !== null) {
                $dictionary["{$type}:{$id}"][] = $result;
            }
        }

        return $dictionary;
    }

    // =========================================================================
    // CORE RELATION METHODS
    // =========================================================================

    /**
     * Perform join with pivot table.
     */
    protected function performJoin(): void
    {
        $relatedTable = $this->getRelatedTable();

        $this->query->join(
            $this->pivotTable,
            "{$relatedTable}.{$this->relatedKey}",
            '=',
            "{$this->pivotTable}.{$this->relatedPivotKey}"
        );

        if ($this->parent->exists()) {
            $this->query->where("{$this->pivotTable}.{$this->morphType}", $this->getMorphClass());
            $this->query->where("{$this->pivotTable}.{$this->foreignKey}", $this->parent->getAttribute($this->localKey));
        }

        // Apply soft delete scope if related model uses soft deletes
        $this->applySoftDeleteScope($this->query, $this->relatedClass, $relatedTable);
    }

    /**
     * {@inheritdoc}
     */
    public function getResults(): ModelCollection
    {
        $relatedTable = $this->getRelatedTable();

        if ($this->parent->exists()) {
            $freshQuery = $this->query->newQuery()->table($relatedTable);

            $freshQuery->join(
                $this->pivotTable,
                "{$relatedTable}.{$this->relatedKey}",
                '=',
                "{$this->pivotTable}.{$this->relatedPivotKey}"
            );

            $freshQuery->where("{$this->pivotTable}.{$this->morphType}", $this->getMorphClass());
            $freshQuery->where("{$this->pivotTable}.{$this->foreignKey}", $this->parent->getAttribute($this->localKey));

            // Always select related table columns
            $freshQuery->select("{$relatedTable}.*");

            // Always select pivot columns for MorphToMany (required for pivot data)
            $freshQuery->selectRaw("{$this->pivotTable}.{$this->morphType} as pivot_{$this->morphType}");
            $freshQuery->selectRaw("{$this->pivotTable}.{$this->foreignKey} as pivot_{$this->foreignKey}");
            $freshQuery->selectRaw("{$this->pivotTable}.{$this->relatedPivotKey} as pivot_{$this->relatedPivotKey}");

            // Select additional pivot columns if needed
            if ($this->shouldIncludePivot()) {
                // Lazy load pivot columns only when withPivot('*') was used
                if ($this->withPivotAll) {
                    $this->ensurePivotColumnsLoaded();
                }

                $selectedColumns = [
                    $this->morphType => true,
                    $this->foreignKey => true,
                    $this->relatedPivotKey => true,
                ];

                foreach ($this->pivotColumns as $column) {
                    if (!isset($selectedColumns[$column])) {
                        $freshQuery->selectRaw("{$this->pivotTable}.{$column} as pivot_{$column}");
                        $selectedColumns[$column] = true;
                    }
                }
            }

            $rowCollection = $freshQuery->get();
        } else {
            $this->query->select("{$relatedTable}.*");

            // Always select pivot columns for MorphToMany (required for pivot data)
            $this->query->selectRaw("{$this->pivotTable}.{$this->morphType} as pivot_{$this->morphType}");
            $this->query->selectRaw("{$this->pivotTable}.{$this->foreignKey} as pivot_{$this->foreignKey}");
            $this->query->selectRaw("{$this->pivotTable}.{$this->relatedPivotKey} as pivot_{$this->relatedPivotKey}");

            // Select additional pivot columns if needed
            if ($this->shouldIncludePivot()) {
                // Lazy load pivot columns only when withPivot('*') was used
                if ($this->withPivotAll) {
                    $this->ensurePivotColumnsLoaded();
                }

                $selectedColumns = [
                    $this->morphType => true,
                    $this->foreignKey => true,
                    $this->relatedPivotKey => true,
                ];

                foreach ($this->pivotColumns as $column) {
                    if (!isset($selectedColumns[$column])) {
                        $this->query->selectRaw("{$this->pivotTable}.{$column} as pivot_{$column}");
                        $selectedColumns[$column] = true;
                    }
                }
            }

            $rowCollection = $this->query->get();
        }

        // If already a ModelCollection, return it directly
        if ($rowCollection instanceof ModelCollection) {
            return $rowCollection;
        }

        // Convert RowCollection to array
        $rows = $rowCollection instanceof RowCollection ? $rowCollection->all() : $rowCollection;

        // Ensure $rows is an array before passing to hydrate
        if (!is_array($rows)) {
            $rows = [];
        }

        return empty($rows) ? new ModelCollection([]) : $this->relatedClass::hydrate($rows);
    }

    /**
     * {@inheritdoc}
     */
    public function addEagerConstraints(array $models): void
    {
        $relatedTable = $this->getRelatedTable();

        $types = [];
        foreach ($models as $model) {
            // Use getMorphClass() if available, otherwise use get_class()
            $type = method_exists($model, 'getMorphClass')
                ? $model->getMorphClass()
                : get_class($model);
            $types[$type][] = $model->getAttribute($this->localKey);
        }

        $this->query = $this->query->newQuery()->table($relatedTable);

        $this->query->join(
            $this->pivotTable,
            "{$relatedTable}.{$this->relatedKey}",
            '=',
            "{$this->pivotTable}.{$this->relatedPivotKey}"
        );

        $pivotTable = $this->pivotTable;
        $morphType = $this->morphType;
        $foreignKey = $this->foreignKey;

        $this->query->where(function ($q) use ($types, $pivotTable, $morphType, $foreignKey) {
            $first = true;
            foreach ($types as $type => $ids) {
                $callback = fn($subQ) => $subQ->where("{$pivotTable}.{$morphType}", $type)
                    ->whereIn("{$pivotTable}.{$foreignKey}", $ids);

                $first ? $q->where($callback) : $q->orWhere($callback);
                $first = false;
            }
        });

        // Select related table columns and pivot columns with alias for consistency
        $this->query->select("{$relatedTable}.*");

        // Always select morphType, foreignKey, and relatedPivotKey for matching (required for eager loading)
        $this->query->selectRaw("{$this->pivotTable}.{$this->morphType} as pivot_{$this->morphType}");
        $this->query->selectRaw("{$this->pivotTable}.{$this->foreignKey} as pivot_{$this->foreignKey}");
        $this->query->selectRaw("{$this->pivotTable}.{$this->relatedPivotKey} as pivot_{$this->relatedPivotKey}");

        // Only select additional pivot columns if we should include pivot object
        if ($this->shouldIncludePivot()) {
            // Lazy load pivot columns only when withPivot('*') was used
            if ($this->withPivotAll) {
                $this->ensurePivotColumnsLoaded();
            }

            // Track which columns have already been selected to avoid duplicates
            $selectedColumns = [
                $this->morphType => true,
                $this->foreignKey => true,
                $this->relatedPivotKey => true,
            ];

            // Add other pivot columns (withTimestamps() already adds created_at/updated_at to $pivotColumns)
            foreach ($this->pivotColumns as $column) {
                // Skip if already added (morphType, foreignKey, or relatedPivotKey might be in pivotColumns)
                if (!isset($selectedColumns[$column])) {
                    $this->query->selectRaw("{$this->pivotTable}.{$column} as pivot_{$column}");
                    $selectedColumns[$column] = true;
                }
            }
        }

        // Apply soft delete scope if related model uses soft deletes
        $this->applySoftDeleteScope($this->query, $this->relatedClass, $relatedTable);
    }

    /**
     * {@inheritdoc}
     *
     * Match eagerly loaded results to their parent models.
     *
     * PERFORMANCE OPTIMIZATION: Uses pivot data already selected in the main eager query
     * instead of querying the pivot table again. This reduces queries from 2 to 1.
     *
     * Performance: O(n + m) where n = parents, m = results
     * - Single query to get pivot mappings
     * - Dictionary-based matching with O(1) lookups
     */
    public function match(array $models, mixed $results, string $relationName): array
    {
        if (!$results instanceof ModelCollection || $results->isEmpty()) {
            // No results - set empty collections for all models
            foreach ($models as $model) {
                $model->setRelation($relationName, new ModelCollection([]));
            }
            return $models;
        }

        // PERFORMANCE NOTE: If pivot constraints are simple and performance is critical,
        // we could optimize this by selecting parent type/id directly in the main eager query
        // This would eliminate the need for a separate pivot query
        if ($this->shouldUseOptimizedMatching()) {
            return $this->matchOptimized($models, $results, $relationName);
        }

        // Standard matching using pivot data from main query (OPTIMIZED - 1 query instead of 2)
        return $this->matchWithPivotQuery($models, $results, $relationName);
    }

    /**
     * Check if we can use optimized matching (single query).
     *
     * Optimized matching works when:
     * - No complex pivot constraints (JSON functions, date functions, etc.)
     * - Simple WHERE/IN constraints only
     * - Parent type/id is available in the result set
     * - Pivot table structure is validated
     *
     * @return bool
     */
    protected function shouldUseOptimizedMatching(): bool
    {
        // Check if we have complex pivot constraints that require separate pivot query
        foreach ($this->pivotWheres as $where) {
            // If column contains SQL functions, we need separate pivot query
            if (Str::contains($where['column'], '(') || Str::contains($where['column'], ')')) {
                return false;
            }
        }

        // TEMPORARILY DISABLED: Need to validate pivot table structure first
        // TODO: Re-enable after implementing proper column existence validation
        // The issue is that we're trying to select pivot columns that may not exist
        // or have different names than expected
        return false;

        // Future implementation should validate:
        // 1. Pivot table exists
        // 2. Morph type column exists
        // 3. Foreign key column exists
        // 4. Related key column exists
        // 5. No naming conflicts with selected columns
    }

    /**
     * Optimized matching using parent type/id from main query (1 query total).
     *
     * @param array $models Parent models
     * @param ModelCollection $results Related models with parent type/id
     * @param string $relationName Relation name
     * @return array
     */
    protected function matchOptimized(array $models, ModelCollection $results, string $relationName): array
    {
        // Build dictionary: "type:id" => [related_models]
        $dictionary = [];
        foreach ($results as $result) {
            // Parent type/id should be available from the main query's SELECT
            $pivotMorphType = $result->getAttribute("pivot_{$this->morphType}");
            $pivotForeignKey = $result->getAttribute("pivot_{$this->foreignKey}");

            if ($pivotMorphType !== null && $pivotForeignKey !== null) {
                $parentKey = "{$pivotMorphType}:{$pivotForeignKey}";
                if (!isset($dictionary[$parentKey])) {
                    $dictionary[$parentKey] = [];
                }
                $dictionary[$parentKey][] = $result;
            }
        }

        // Match to parents
        foreach ($models as $model) {
            $key = get_class($model) . ':' . $model->getAttribute($this->localKey);
            $matched = $dictionary[$key] ?? [];
            $model->setRelation($relationName, new ModelCollection($matched));
        }

        return $models;
    }

    /**
     * Standard matching using pivot data from main query (OPTIMIZED - 1 query instead of 2).
     *
     * PERFORMANCE OPTIMIZATION: Uses pivot data already selected in the main eager query
     * instead of querying the pivot table again. This reduces queries from 2 to 1.
     *
     * @param array $models Parent models
     * @param ModelCollection $results Related models with pivot data in attributes
     * @param string $relationName Relation name
     * @return array
     */
    protected function matchWithPivotQuery(array $models, ModelCollection $results, string $relationName): array
    {
        // Step 1: Extract pivot data from query results BEFORE removing pivot attributes
        // The main query already selected pivot columns with alias "pivot_{column}"
        // We extract this data to build the parent-to-related mapping dictionary
        // OPTIMIZED: Extract pivot data first, then remove attributes from models
        $dictionary = [];

        foreach ($results as $result) {
            // Get pivot data from model attributes (with prefix "pivot_")
            $pivotMorphType = $result->getAttribute("pivot_{$this->morphType}");
            $pivotForeignKey = $result->getAttribute("pivot_{$this->foreignKey}");
            $pivotRelatedKey = $result->getAttribute("pivot_{$this->relatedPivotKey}");

            if ($pivotMorphType === null || $pivotForeignKey === null || $pivotRelatedKey === null) {
                // Skip if pivot keys are missing (shouldn't happen in eager loading)
                continue;
            }

            // Normalize to string for consistent comparison
            $parentKey = "{$pivotMorphType}:{$pivotForeignKey}";
            $relatedIdKey = (string) $pivotRelatedKey;

            // Only build pivot data if we should include pivot object
            $pivotData = null;
            if ($this->shouldIncludePivot()) {
                // Lazy load pivot columns only when withPivot('*') was used
                if ($this->withPivotAll) {
                    $this->ensurePivotColumnsLoaded();
                }

                // Build pivot data from all pivot_* attributes
                // Priority: Use database values first, fallback to parent model values only if null
                // This fixes corrupted data where morphType/foreignKey might be null in old records
                $pivotData = [
                    // Always use DB value if available, fallback to parent model for polymorphic integrity
                    $this->morphType => $pivotMorphType ?? $this->getMorphClass(),
                    $this->foreignKey => $pivotForeignKey,
                    $this->relatedPivotKey => $pivotRelatedKey,
                ];

                // Add other pivot columns from $this->pivotColumns
                // Note: morphType, foreignKey, and relatedPivotKey are excluded from $this->pivotColumns when withPivot('*') is called
                // but they are always selected separately, so we handle them above
                foreach ($this->pivotColumns as $column) {
                    // Skip columns that are already handled above
                    if ($column !== $this->morphType && $column !== $this->foreignKey && $column !== $this->relatedPivotKey) {
                        $pivotValue = $result->getAttribute("pivot_{$column}");
                        // Only include non-null values (consistent with BelongsToMany)
                        // This avoids polluting pivot data with null values
                        if ($pivotValue !== null) {
                            $pivotData[$column] = $pivotValue;
                        }
                    }
                }

                // Add timestamps if enabled (include even if null for completeness)
                if ($this->withTimestamps) {
                    $createdAt = $result->getAttribute('pivot_created_at');
                    $updatedAt = $result->getAttribute('pivot_updated_at');
                    // Include timestamps even if null (they may be null in database)
                    if ($createdAt !== null) {
                        $pivotData['created_at'] = $createdAt;
                    }
                    if ($updatedAt !== null) {
                        $pivotData['updated_at'] = $updatedAt;
                    }
                }
            }

            // Build dictionary: "type:id" => [relatedId => pivotData or null]
            // pivotData is null if we don't need to create pivot object
            if (!isset($dictionary[$parentKey])) {
                $dictionary[$parentKey] = [];
            }
            $dictionary[$parentKey][$relatedIdKey] = $pivotData;
        }

        // Step 2: Build index of related models by ID for O(1) lookup
        // Normalize IDs to strings to avoid type mismatch issues (int vs string)
        // Note: If same related model appears multiple times (for different parents),
        // we keep the first instance (pivot data is already extracted to dictionary)
        // OPTIMIZED: Remove pivot_* attributes from models to keep them clean
        $relatedIndex = [];
        foreach ($results as $result) {
            $relatedId = $result->getAttribute($this->relatedKey);
            if ($relatedId !== null) {
                $relatedIdKey = (string) $relatedId;
                // Only keep first instance if duplicate (pivot data already in dictionary)
                if (!isset($relatedIndex[$relatedIdKey])) {
                    // Remove pivot_* attributes from the first instance to keep it clean
                    /** @var Model $result */
                    $this->removePivotAttributes($result);
                    $relatedIndex[$relatedIdKey] = $result;
                }
            }
        }

        // Step 3: Match related models to parents using dictionary and attach pivot data
        // CRITICAL: Clone related models for each parent to avoid pivot data being shared/reused
        // When multiple parents share the same related model, each parent needs its own instance with correct pivot data
        // PERFORMANCE: Pre-normalize parent keys to avoid repeated string conversion
        foreach ($models as $model) {
            // Use getMorphClass() if available, otherwise use get_class()
            $morphType = method_exists($model, 'getMorphClass')
                ? $model->getMorphClass()
                : get_class($model);
            $key = $morphType . ':' . $model->getAttribute($this->localKey);

            // CRITICAL: Only match if dictionary has entries for THIS specific parent
            if (!isset($dictionary[$key])) {
                // No pivot data for this parent - set empty collection
                $model->setRelation($relationName, new ModelCollection([]));
                continue;
            }

            // Get related IDs with pivot data for THIS parent from dictionary
            $relatedDataForParent = $dictionary[$key];

            // Initialize matched array (PHP handles dynamic growth efficiently)
            $matched = [];

            // Look up actual models by ID and attach pivot data
            // OPTIMIZED: Single loop with validation - combines validation and matching
            foreach ($relatedDataForParent as $relatedIdStr => $pivotData) {
                // Normalize to string for consistent comparison
                $relatedIdKey = (string) $relatedIdStr;

                if (isset($relatedIndex[$relatedIdKey])) {
                    // CRITICAL: Clone the related model to avoid sharing pivot data between parents
                    // When multiple parents share the same related model, each needs its own instance
                    $originalRelatedModel = $relatedIndex[$relatedIdKey];

                    // Clone the model to create a fresh instance for this parent
                    $relatedModel = clone $originalRelatedModel;

                    // Remove pivot_* attributes from model (they should only be in pivot relation)
                    // This ensures clean model attributes without pivot_ prefix pollution
                    /** @var Model $relatedModel */
                    $this->removePivotAttributes($relatedModel);

                    // Only create and attach pivot object if we have pivot data
                    if ($pivotData !== null && $this->shouldIncludePivot()) {
                        // Clear any existing relations on the cloned model
                        $relatedModel->setRelation($this->pivotAccessor, null);

                        // Attach pivot data to the cloned model
                        // PERFORMANCE: Use array spread for faster copying, then override keys
                        // This creates a fresh copy to avoid reference issues while being faster than foreach
                        $cleanPivotData = [...$pivotData];

                        // CRITICAL: Force set keys to match THIS parent's values
                        // This ensures pivot data always has the correct morphType and foreignKey
                        // Use the pre-calculated $morphType for consistency and performance
                        $cleanPivotData[$this->morphType] = $morphType;
                        $cleanPivotData[$this->foreignKey] = $model->getAttribute($this->localKey);
                        $cleanPivotData[$this->relatedPivotKey] = $relatedIdKey;

                        $pivotModel = $this->newPivot($cleanPivotData, true);
                        $relatedModel->setRelation($this->pivotAccessor, $pivotModel);
                    }

                    $matched[] = $relatedModel;
                }
            }

            // Set relation with matched models (or empty collection)
            $model->setRelation($relationName, new ModelCollection($matched));
        }

        return $models;
    }

    /**
     * Check if pivot object should be included in the relationship.
     *
     * Pivot object is only included when:
     * - withPivot() is called with columns
     * - withTimestamps() is called
     *
     * @return bool
     */
    protected function shouldIncludePivot(): bool
    {
        return !empty($this->pivotColumns) || $this->withPivotAll || $this->withTimestamps;
    }

    /**
     * Lazy load all pivot columns when actually needed (not during whereHas/exists).
     *
     * IMPORTANT: This method ONLY executes SHOW COLUMNS when:
     * 1. withPivot('*') was called (withPivotAll = true)
     * 2. AND pivot columns haven't been loaded yet (empty pivotColumns)
     *
     * When using withPivot('field1', 'field2'), this method is never called,
     * so SHOW COLUMNS is never executed.
     *
     * This avoids unnecessary SHOW COLUMNS queries.
     */
    protected function ensurePivotColumnsLoaded(): void
    {
        // Only load columns if withPivot('*') was used AND columns not loaded yet
        if ($this->withPivotAll && empty($this->pivotColumns)) {
            // Get all columns from pivot table (with caching for performance)
            $connection = $this->query->getConnection();
            $allColumns = $this->getCachedTableColumns($this->pivotTable, $connection);

            // Exclude morph type, foreign key, and related pivot key (they're always selected separately)
            $excludeColumns = [$this->morphType, $this->foreignKey, $this->relatedPivotKey];

            // Exclude timestamps if withTimestamps() will be called separately
            if (!$this->withTimestamps) {
                $excludeColumns[] = 'created_at';
                $excludeColumns[] = 'updated_at';
            }

            // Filter out excluded columns
            $pivotColumns = array_diff($allColumns ?? [], $excludeColumns);
            $this->pivotColumns = array_values($pivotColumns);
        }
    }

    /**
     * Remove pivot_* attributes from model.
     *
     * These attributes are only needed during matching and should not appear
     * in the final model attributes. Pivot data should only be in the pivot relation.
     *
     * OPTIMIZED: Uses Model's removeAttributesByPattern() method instead of reflection
     * for better performance.
     *
     * @param \Toporia\Framework\Database\ORM\Model $model Model instance to clean
     * @return void
     */
    protected function removePivotAttributes(\Toporia\Framework\Database\ORM\Model $model): void
    {
        // Use Model's protected method to remove attributes efficiently
        // This avoids reflection overhead and is much faster
        $model->removeAttributesByPattern('pivot_');
    }

    /**
     * Create a new pivot model instance.
     *
     * Creates a MorphPivot model instance with full ORM capabilities.
     * If a custom pivot class is specified via using(), it will be instantiated instead.
     *
     * The pivot model has access to:
     * - Accessors/Mutators
     * - Custom methods
     * - Relationships from pivot
     * - Event hooks (creating, created, updating, updated, deleting, deleted)
     * - Save/update/delete operations
     * - Morph type information for polymorphic relationships
     *
     * @param array<string, mixed> $attributes Pivot attributes
     * @param bool $exists Whether the pivot exists in database
     * @return Pivot Pivot model instance (MorphPivot or custom class)
     */
    protected function newPivot(array $attributes = [], bool $exists = false): Pivot
    {
        // Determine the pivot class to use
        // Priority: custom class via using() > MorphPivot (default for polymorphic)
        $pivotClass = $this->pivotClass ?? MorphPivot::class;

        // Check if the class is a MorphPivot or subclass
        $isMorphPivot = is_a($pivotClass, MorphPivot::class, true);

        if ($isMorphPivot) {
            // Use fromMorphAttributes for MorphPivot instances
            /** @var MorphPivot $pivot */
            $pivot = $pivotClass::fromMorphAttributes(
                $this->parent,
                $attributes,
                $this->pivotTable,
                $exists,
                $this->morphType,
                $this->getMorphClass()
            );
        } else {
            // Use fromRawAttributes for regular Pivot instances
            /** @var Pivot $pivot */
            $pivot = $pivotClass::fromRawAttributes(
                $this->parent,
                $attributes,
                $this->pivotTable,
                $exists
            );
        }

        // Set the foreign and related keys for proper save/delete operations
        $pivot->setForeignKey($this->foreignKey);
        $pivot->setRelatedKey($this->relatedPivotKey);

        // Enable timestamps if configured
        if ($this->withTimestamps) {
            $pivot->withTimestamps();
        }

        return $pivot;
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
            $this->relatedClass,
            $this->morphName,
            $this->pivotTable,
            $this->morphType,
            $this->foreignKey,
            $this->relatedPivotKey,
            $this->localKey,
            $this->relatedKey
        );

        $instance->pivotColumns = $this->pivotColumns;
        $instance->withPivotAll = $this->withPivotAll;
        $instance->withTimestamps = $this->withTimestamps;
        $instance->pivotAccessor = $this->pivotAccessor;

        // Set up the query with proper JOIN but without parent WHERE constraints
        $relatedTable = $this->relatedClass::getTableName();

        $cleanQuery = $this->relatedClass::query()
            ->join(
                $this->pivotTable,
                "{$relatedTable}.{$this->relatedKey}",
                '=',
                "{$this->pivotTable}.{$this->relatedPivotKey}"
            );

        // Build SELECT clause with pivot columns using selectRaw for proper alias handling
        // This ensures pivot columns are selected with correct aliases
        $cleanQuery->select("{$relatedTable}.*");

        // Always select morphType, foreignKey, and relatedPivotKey from pivot table (required for matching)
        $cleanQuery->selectRaw("{$this->pivotTable}.{$this->morphType} as pivot_{$this->morphType}");
        $cleanQuery->selectRaw("{$this->pivotTable}.{$this->foreignKey} as pivot_{$this->foreignKey}");
        $cleanQuery->selectRaw("{$this->pivotTable}.{$this->relatedPivotKey} as pivot_{$this->relatedPivotKey}");

        // Only select additional pivot columns if we should include pivot object
        if ($this->shouldIncludePivot()) {
            // Lazy load pivot columns only when withPivot('*') was used
            if ($this->withPivotAll) {
                $this->ensurePivotColumnsLoaded();
            }

            // Track which columns have already been selected to avoid duplicates
            $selectedColumns = [
                $this->morphType => true,
                $this->foreignKey => true,
                $this->relatedPivotKey => true,
            ];

            // Add other pivot columns (withTimestamps() already adds created_at/updated_at to $pivotColumns)
            foreach ($this->pivotColumns as $column) {
                // Skip if already added (morphType, foreignKey, or relatedPivotKey might be in pivotColumns)
                if (!isset($selectedColumns[$column])) {
                    $cleanQuery->selectRaw("{$this->pivotTable}.{$column} as pivot_{$column}");
                    $selectedColumns[$column] = true;
                }
            }
        }

        $instance->setQuery($cleanQuery);

        // Copy where constraints from original query (excluding pivot and parent-specific constraints)
        $pivotTablePrefix = $this->pivotTable . '.';
        $this->copyWhereConstraints($cleanQuery, [
            $this->morphType,
            $this->foreignKey,
            fn($col) => Str::startsWith($col, $pivotTablePrefix) ||
                $col === $this->morphType ||
                $col === $this->foreignKey ||
                Str::endsWith($col, '.' . $this->morphType) ||
                Str::endsWith($col, '.' . $this->foreignKey)
        ]);

        // Apply pivot constraints separately
        $instance->applyPivotConstraintsToQuery($instance->getQuery());

        // Apply soft delete scope if related model uses soft deletes
        $instance->applySoftDeleteScope($cleanQuery, $this->relatedClass, $relatedTable);

        return $instance;
    }

    // =========================================================================
    // PIVOT CONFIGURATION METHODS
    // =========================================================================

    /**
     * Specify additional pivot columns to include.
     */
    public function withPivot(string ...$columns): static
    {
        // Handle wildcard '*' to select all pivot columns
        if (in_array('*', $columns, true)) {
            // Lazy load: Mark that we need all columns, but don't fetch them yet
            // This avoids SHOW COLUMNS query when relation is only used for whereHas/exists checks
            $this->withPivotAll = true;

            // Remove '*' from columns array
            $columns = array_filter($columns, fn($col) => $col !== '*');

            // Merge with existing pivot columns
            $this->pivotColumns = array_values(array_unique([...$this->pivotColumns, ...$columns]));
        } else {
            // Normal case: just add specified columns
            // Deduplicate columns to avoid duplicate alias in SQL queries
            $this->pivotColumns = array_values(array_unique([...$this->pivotColumns, ...$columns]));
        }

        return $this;
    }

    /**
     * Include timestamps in pivot table.
     */
    public function withTimestamps(): static
    {
        $this->withTimestamps = true;
        return $this->withPivot('created_at', 'updated_at');
    }

    /**
     * Customize the pivot accessor name.
     */
    public function as(string $accessor): static
    {
        $this->pivotAccessor = $accessor;
        return $this;
    }

    /**
     * Specify a custom pivot model class.
     */
    public function using(string $class): static
    {
        $this->pivotClass = $class;
        return $this;
    }

    // =========================================================================
    // PIVOT CONSTRAINT METHODS
    // =========================================================================

    /**
     * Add a where constraint on the pivot table.
     */
    public function wherePivot(string $column, mixed $operator, mixed $value = null): static
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->pivotWheres[] = ['column' => $column, 'operator' => $operator, 'value' => $value];
        $this->applyPivotWhere($column, $operator, $value);

        return $this;
    }

    /**
     * Apply a pivot where constraint to the query.
     */
    protected function applyPivotWhere(string $column, string $operator, mixed $value): void
    {
        $qualifiedColumn = "{$this->pivotTable}.{$column}";

        match (true) {
            $operator === 'BETWEEN' && is_array($value) => $this->query->whereBetween($qualifiedColumn, $value),
            $operator === 'NOT BETWEEN' && is_array($value) => $this->query->whereNotBetween($qualifiedColumn, $value),
            $operator === 'IS' && $value === null => $this->query->whereNull($qualifiedColumn),
            $operator === 'IS NOT' && $value === null => $this->query->whereNotNull($qualifiedColumn),
            default => $this->query->where($qualifiedColumn, $operator, $value)
        };
    }

    /**
     * Add a whereIn constraint on the pivot table.
     */
    public function wherePivotIn(string $column, array $values): static
    {
        $this->pivotWhereIns[] = ['column' => $column, 'values' => $values];
        $this->query->whereIn("{$this->pivotTable}.{$column}", $values);

        return $this;
    }

    /**
     * Add a whereNotIn constraint on the pivot table.
     */
    public function wherePivotNotIn(string $column, array $values): static
    {
        $this->pivotWhereIns[] = ['column' => $column, 'values' => $values, 'not' => true];
        $this->query->whereNotIn("{$this->pivotTable}.{$column}", $values);

        return $this;
    }

    /**
     * Add an order by clause on the pivot table.
     */
    public function orderByPivot(string $column, string $direction = 'asc'): static
    {
        $direction = strtolower($direction);
        $this->pivotOrderBy[] = ['column' => $column, 'direction' => $direction];
        $this->query->orderBy("{$this->pivotTable}.{$column}", $direction);

        return $this;
    }

    /**
     * Add date-based pivot constraint.
     */
    public function wherePivotDate(string $column, string $operator, string $value): static
    {
        $this->query->whereRaw("DATE({$this->pivotTable}.{$column}) {$operator} ?", [$value]);
        $this->pivotWheres[] = ['column' => "DATE({$column})", 'operator' => $operator, 'value' => $value];

        return $this;
    }

    /**
     * Add month-based pivot constraint.
     */
    public function wherePivotMonth(string $column, string $operator, int $value): static
    {
        $this->query->whereRaw("MONTH({$this->pivotTable}.{$column}) {$operator} ?", [$value]);
        $this->pivotWheres[] = ['column' => "MONTH({$column})", 'operator' => $operator, 'value' => $value];

        return $this;
    }

    /**
     * Add year-based pivot constraint.
     */
    public function wherePivotYear(string $column, string $operator, int $value): static
    {
        $this->query->whereRaw("YEAR({$this->pivotTable}.{$column}) {$operator} ?", [$value]);
        $this->pivotWheres[] = ['column' => "YEAR({$column})", 'operator' => $operator, 'value' => $value];

        return $this;
    }

    // =========================================================================
    // CRUD OPERATIONS
    // =========================================================================

    /**
     * Create a new related model and attach it.
     */
    public function create(array $attributes = [], array $pivotData = []): Model
    {
        $instance = $this->relatedClass::create($attributes);
        $this->attach($instance->getAttribute($this->relatedKey), $pivotData);

        return $instance;
    }

    /**
     * Save a related model and attach it.
     */
    public function save(Model $model, array $pivotData = []): Model
    {
        $model->save();
        $this->attach($model->getAttribute($this->relatedKey), $pivotData);

        return $model;
    }

    /**
     * Attach models to the relationship.
     */
    public function attach(int|string|array $id, array $pivotData = []): array|bool
    {
        if (is_array($id)) {
            return $this->attachMany($id);
        }

        $data = [
            $this->relatedPivotKey => $id,
            $this->foreignKey => $this->parent->getAttribute($this->localKey),
            $this->morphType => $this->getMorphClass(),
            ...$pivotData
        ];

        if ($this->withTimestamps) {
            // Use same timestamp format as Model class for consistency
            $now = now()->toDateTimeString();
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
        }

        (new QueryBuilder($this->query->getConnection()))
            ->table($this->pivotTable)
            ->insert($data);

        return true;
    }

    /**
     * Attach multiple related models.
     */
    protected function attachMany(array $ids): array
    {
        $attached = [];
        $insertData = [];
        // Use same timestamp format as Model class for consistency
        $now = $this->withTimestamps ? now()->toDateTimeString() : null;

        foreach ($ids as $key => $value) {
            [$relatedId, $pivotData] = is_numeric($key)
                ? [$value, []]
                : [$key, is_array($value) ? $value : []];

            $data = [
                $this->relatedPivotKey => $relatedId,
                $this->foreignKey => $this->parent->getAttribute($this->localKey),
                $this->morphType => $this->getMorphClass(),
                ...$pivotData
            ];

            if ($now) {
                $data['created_at'] = $now;
                $data['updated_at'] = $now;
            }

            $insertData[] = $data;
            $attached[] = $relatedId;
        }

        if ($insertData !== []) {
            (new QueryBuilder($this->query->getConnection()))
                ->table($this->pivotTable)
                ->insert($insertData);
        }

        return $attached;
    }

    /**
     * Detach models from the relationship.
     */
    public function detach(mixed $ids = null): int
    {
        $query = $this->newPivotQuery();

        if ($ids !== null) {
            $ids = is_array($ids) ? $ids : [$ids];
            $query->whereIn($this->relatedPivotKey, $ids);
        }

        return $query->delete();
    }

    /**
     * Soft delete related models through this relationship.
     *
     * If related model uses soft deletes, this will soft delete the related models
     * and detach them from the pivot table. Otherwise, it will only detach.
     *
     * Performance: O(n) - Single UPDATE query for soft delete + DELETE for pivot
     *
     * @param int|string|array|null $ids Related model ID(s) (null = soft delete all)
     * @return int Number of models soft deleted
     *
     * @example
     * ```php
     * // Soft delete a specific tag from post
     * $post->tags()->softDelete(1);
     *
     * // Soft delete multiple tags
     * $post->tags()->softDelete([1, 2, 3]);
     *
     * // Soft delete all tags
     * $post->tags()->softDelete();
     * ```
     */
    public function softDelete(int|string|array|null $ids = null): int
    {
        // If related model doesn't use soft deletes, just detach
        if (!$this->relatedModelUsesSoftDeletes($this->relatedClass)) {
            return $this->detach($ids);
        }

        // Get related model IDs to soft delete
        $relatedIds = [];
        if ($ids === null) {
            // Get all related IDs from pivot table
            $qb = new QueryBuilder($this->query->getConnection());
            $pivotRows = $qb->table($this->pivotTable)
                ->where($this->foreignKey, $this->parent->getAttribute($this->localKey))
                ->where($this->morphType, $this->getMorphClass())
                ->pluck($this->relatedPivotKey);
            $relatedIds = $pivotRows->toArray();
        } else {
            $relatedIds = is_array($ids) ? $ids : [$ids];
        }

        if (empty($relatedIds)) {
            return 0;
        }

        // Soft delete related models
        $deletedAtColumn = $this->getDeletedAtColumn($this->relatedClass);
        $relatedKey = $this->relatedKey;

        // Use same timestamp format as Model class for consistency
        $softDeleted = $this->relatedClass::query()
            ->whereIn($relatedKey, $relatedIds)
            ->whereNull($deletedAtColumn) // Only soft delete non-deleted records
            ->update([$deletedAtColumn => now()->toDateTimeString()]);

        // Detach from pivot table
        if ($softDeleted > 0) {
            $this->detach($relatedIds);
        }

        return $softDeleted;
    }

    /**
     * Restore soft-deleted related models through this relationship.
     *
     * Restores soft-deleted related models and optionally re-attaches them to pivot table.
     *
     * Performance: O(n) - Single UPDATE query for restore
     *
     * @param int|string|array $ids Related model ID(s) to restore
     * @param bool $reattach Whether to re-attach to pivot table after restore
     * @return int Number of models restored
     *
     * @example
     * ```php
     * // Restore a tag
     * $post->tags()->restore(1);
     *
     * // Restore and re-attach
     * $post->tags()->restore(1, true);
     * ```
     */
    public function restore(int|string|array $ids, bool $reattach = false): int
    {
        // If related model doesn't use soft deletes, return 0
        if (!$this->relatedModelUsesSoftDeletes($this->relatedClass)) {
            return 0;
        }

        $relatedIds = is_array($ids) ? $ids : [$ids];
        if (empty($relatedIds)) {
            return 0;
        }

        // Restore related models
        $deletedAtColumn = $this->getDeletedAtColumn($this->relatedClass);
        $restored = $this->relatedClass::withTrashed()
            ->whereIn($this->relatedKey, $relatedIds)
            ->whereNotNull($deletedAtColumn) // Only restore soft-deleted records
            ->update([$deletedAtColumn => null]);

        // Re-attach to pivot table if requested
        if ($restored > 0 && $reattach) {
            $this->attachMany($relatedIds);
        }

        return $restored;
    }

    /**
     * Sync the relationship with the given IDs.
     */
    public function sync(array $ids, bool $detaching = true): array
    {
        $changes = ['attached' => [], 'detached' => [], 'updated' => []];
        $current = $this->getCurrentPivotIds();
        $records = $this->formatSyncRecords($ids);
        $syncIds = array_keys($records);

        if ($detaching) {
            $detach = array_diff($current, $syncIds);
            if ($detach !== []) {
                $this->detach($detach);
                $changes['detached'] = array_values($detach);
            }
        }

        foreach ($records as $id => $pivotData) {
            if (in_array($id, $current)) {
                // Always update existing records to ensure morphType and foreignKey are set correctly
                // This fixes any existing records that might have null values
                $this->updateExistingPivot($id, $pivotData);
                $changes['updated'][] = $id;
            } else {
                $this->attach($id, $pivotData);
                $changes['attached'][] = $id;
            }
        }

        return $changes;
    }

    /**
     * Toggle the attachment of related models.
     */
    public function toggle(array|int|string $ids): array
    {
        $ids = is_array($ids) ? $ids : [$ids];
        $changes = ['attached' => [], 'detached' => []];
        $current = $this->getCurrentPivotIds();

        foreach ($ids as $id) {
            if (in_array($id, $current)) {
                $this->detach($id);
                $changes['detached'][] = $id;
            } else {
                $this->attach($id);
                $changes['attached'][] = $id;
            }
        }

        return $changes;
    }

    /**
     * Update an existing pivot record.
     */
    public function updateExistingPivot(int|string $id, array $pivotData): bool
    {
        // Always ensure morphType and foreignKey are set correctly for polymorphic relationships
        // This fixes any existing records that might have null values
        $pivotData[$this->morphType] = $this->getMorphClass();
        $pivotData[$this->foreignKey] = $this->parent->getAttribute($this->localKey);

        if ($this->withTimestamps && !isset($pivotData['updated_at'])) {
            // Use same timestamp format as Model class for consistency
            $pivotData['updated_at'] = now()->toDateTimeString();
        }

        $affected = $this->newPivotQuery()
            ->where($this->relatedPivotKey, $id)
            ->update($pivotData);

        return $affected > 0;
    }

    /**
     * Sync with additional pivot values for all records.
     */
    public function syncWithPivotValues(array $ids, array $pivotValues, bool $detaching = true): array
    {
        $records = array_fill_keys($ids, $pivotValues);

        return $this->sync($records, $detaching);
    }

    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    /**
     * Check if any related models exist.
     */
    public function exists(): bool
    {
        return $this->parent->exists() && $this->query->exists();
    }

    /**
     * Get the count of related models.
     */
    public function count(): int
    {
        return $this->parent->exists() ? $this->query->count() : 0;
    }

    /**
     * Get the first related model or create a new one.
     */
    public function firstOrCreate(array $attributes = [], array $pivotData = []): Model
    {
        return $this->query->first() ?? $this->create($attributes, $pivotData);
    }

    /**
     * Process records in chunks.
     *
     * PERFORMANCE WARNING: Uses OFFSET/LIMIT which can be slow on large tables.
     * For better performance on large datasets, use chunkById() instead.
     *
     * CRITICAL: Always orders by primary key to ensure consistent pagination
     * and prevent skipped/duplicate records when data changes during chunking.
     */
    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;
        $relatedKey = $this->relatedKey;
        $relatedTable = $this->getRelatedTable();

        do {
            // Clone query to avoid mutating the base query
            $query = clone $this->query;

            // CRITICAL: Always order by primary key to ensure consistent pagination
            // This prevents skipped/duplicate records when data changes during chunking
            // Use qualified column name to avoid ambiguity in JOIN queries
            $query->orderBy("{$relatedTable}.{$relatedKey}", 'ASC');

            $results = $query->limit($count)->offset(($page - 1) * $count)->get();

            if ($results->isEmpty()) {
                break;
            }

            $models = $this->relatedClass::hydrate($results->toArray());

            if ($callback($models, $page) === false) {
                return false;
            }

            $page++;
        } while ($results->count() === $count);

        return true;
    }

    /**
     * Process records in chunks ordered by ID.
     */
    public function chunkById(int $count, callable $callback, ?string $column = null, ?string $alias = null): bool
    {
        $column ??= $this->relatedKey;
        $alias ??= $column;
        $lastId = null;

        do {
            $clone = clone $this->query;

            if ($lastId !== null) {
                $clone->where($column, '>', $lastId);
            }

            $results = $clone->orderBy($column)->limit($count)->get();

            if ($results->isEmpty()) {
                break;
            }

            $models = $this->relatedClass::hydrate($results->toArray());

            if ($callback($models) === false) {
                return false;
            }

            $lastId = $models->last()->getAttribute($alias);
        } while ($results->count() === $count);

        return true;
    }

    // =========================================================================
    // PIVOT QUERY METHODS
    // =========================================================================

    /**
     * Get current pivot IDs for the parent model.
     */
    protected function getCurrentPivotIds(): array
    {
        return $this->newPivotQuery()->pluck($this->relatedPivotKey)->toArray();
    }

    /**
     * Format sync records from various input formats.
     */
    protected function formatSyncRecords(array $records): array
    {
        $formatted = [];

        foreach ($records as $key => $value) {
            // Check if this is a simple array format: [1, 2, 3]
            // This happens when key is numeric AND value is scalar
            if (is_numeric($key) && is_scalar($value)) {
                // Simple array: [1, 2, 3] -> convert to [1 => [], 2 => [], 3 => []]
                $formatted[$value] = [];
            } elseif (is_scalar($key)) {
                // Associative array format: [1 => ['role' => 'admin'], 2 => ['role' => 'user']]
                // or ['a' => [...], 'b' => [...]]
                // Key must be scalar (int/string) to use as array key
                $formatted[$key] = is_array($value) ? $value : [];
            } else {
                // Skip invalid keys (arrays/objects cannot be used as keys)
                continue;
            }
        }

        return $formatted;
    }

    /**
     * Get the pivot table query builder.
     */
    public function pivotQuery(): QueryBuilder
    {
        return $this->newPivotQuery();
    }

    /**
     * Check if a specific pivot relationship exists.
     *
     * Performance: O(log n) - Uses optimized EXISTS query (SELECT 1 ... LIMIT 1)
     * Clean Architecture: Expressive existence check
     *
     * @param int|string $id Related model ID
     * @param array $pivotConstraints Additional pivot constraints
     * @return bool
     */
    public function pivotExists(int|string $id, array $pivotConstraints = []): bool
    {
        // Use optimized EXISTS pattern: SELECT 1 FROM ... WHERE ... LIMIT 1
        // This is much faster than COUNT(*) as it stops at first match
        $query = $this->newPivotQuery()
            ->where($this->relatedPivotKey, $id)
            ->selectRaw('1')
            ->limit(1);

        foreach ($pivotConstraints as $column => $value) {
            $query->where($column, $value);
        }

        return $query->exists();
    }

    /**
     * Find a related model by pivot attributes.
     */
    public function findByPivot(array $pivotAttributes, array $columns = ['*']): ?Model
    {
        foreach ($pivotAttributes as $column => $value) {
            $this->wherePivot($column, $value);
        }

        return $this->select($columns)->first();
    }

    /**
     * Get all related models with specific pivot attributes.
     */
    public function getByPivot(array $pivotAttributes, array $columns = ['*']): ModelCollection
    {
        foreach ($pivotAttributes as $column => $value) {
            $this->wherePivot($column, $value);
        }

        return $this->select($columns)->get();
    }

    // =========================================================================
    // PIVOT AGGREGATION METHODS
    // =========================================================================

    /**
     * Get sum of a pivot column.
     */
    public function sumPivot(string $column): float|int
    {
        return $this->newPivotQuery()->sum($column) ?? 0;
    }

    /**
     * Get average of a pivot column.
     */
    public function avgPivot(string $column): float|int
    {
        return $this->newPivotQuery()->avg($column) ?? 0;
    }

    /**
     * Get minimum value of a pivot column.
     */
    public function minPivot(string $column): mixed
    {
        return $this->newPivotQuery()->min($column);
    }

    /**
     * Get maximum value of a pivot column.
     */
    public function maxPivot(string $column): mixed
    {
        return $this->newPivotQuery()->max($column);
    }

    /**
     * Get distinct values from a pivot column.
     *
     * Performance: O(log n) - Uses database DISTINCT optimization
     * Clean Architecture: Expressive method for pivot column analysis
     *
     * @param string $column Pivot column name
     * @return array Array of distinct values
     */
    public function distinctPivot(string $column): array
    {
        // Build query once: SELECT DISTINCT column_name FROM pivot_table WHERE ...
        // This ensures we get distinct values for the column, not distinct full rows
        $query = $this->newPivotQuery()
            ->select($column)
            ->distinct();

        // Execute query once and return as array using ->all() as specified
        return $query->pluck($column)->all();
    }

    // =========================================================================
    // GETTER METHODS
    // =========================================================================

    /**
     * Get the morph name.
     */
    public function getMorphName(): string
    {
        return $this->morphName;
    }

    /**
     * Get the pivot table name.
     */
    public function getPivotTable(): string
    {
        return $this->pivotTable;
    }

    /**
     * Get the related pivot key.
     */
    public function getRelatedPivotKey(): string
    {
        return $this->relatedPivotKey;
    }

    /**
     * Get the morph type column name.
     */
    public function getMorphType(): string
    {
        return $this->morphType;
    }

    // =========================================================================
    // LEGACY METHODS (for backward compatibility)
    // =========================================================================

    /**
     * @deprecated Use attach() instead
     */
    public function attachOriginal(mixed $ids): bool
    {
        $this->attach(is_array($ids) ? $ids : [$ids]);

        return true;
    }

    /**
     * @deprecated Use sync() instead
     */
    public function syncOriginal(mixed $ids): void
    {
        $this->detach();
        $this->attach(is_array($ids) ? $ids : [$ids]);
    }

    // =========================================================================
    // PIVOT CONSTRAINT HELPERS
    // =========================================================================

    /**
     * Qualify a column name with the pivot table prefix.
     */
    protected function qualifyPivotColumn(string $column): string
    {
        return "{$this->pivotTable}.{$column}";
    }

    /**
     * Apply pivot constraints to a separate pivot query.
     *
     * This method applies wherePivot and wherePivotIn constraints to the given query.
     * Always qualifies pivot columns with table name to avoid ambiguous column errors.
     *
     * @param QueryBuilder $query Pivot query builder
     * @return void
     */
    protected function applyPivotConstraintsToQuery(QueryBuilder $query): void
    {
        // Apply pivot where constraints
        foreach ($this->pivotWheres as $where) {
            $column = $where['column'];
            $operator = $where['operator'];
            $value = $where['value'];

            // Handle function-based columns (DATE, MONTH, YEAR, TIME, etc.)
            if (Str::contains($column, '(') && Str::contains($column, ')')) {
                $this->applyFunctionBasedPivotConstraint($query, $column, $operator, $value);
            } else {
                // Regular column - always qualify with pivot table name to avoid ambiguity
                $fullColumn = $this->ensurePivotColumnQualified($column);
                $this->applyWhereToQueryBuilder($query, $fullColumn, $operator, $value);
            }
        }

        // Apply pivot whereIn constraints
        foreach ($this->pivotWhereIns as $whereIn) {
            $column = $this->ensurePivotColumnQualified($whereIn['column']);

            if (isset($whereIn['not']) && $whereIn['not']) {
                $query->whereNotIn($column, $whereIn['values']);
            } else {
                $query->whereIn($column, $whereIn['values']);
            }
        }
    }

    /**
     * Ensure a column is qualified with the pivot table name.
     *
     * @param string $column Column name (may or may not be qualified)
     * @return string Qualified column name
     */
    protected function ensurePivotColumnQualified(string $column): string
    {
        if (!Str::contains($column, '.')) {
            // Column is not qualified, add pivot table prefix
            return $this->qualifyPivotColumn($column);
        }

        if (!Str::startsWith($column, $this->pivotTable . '.')) {
            // Column is qualified but not with pivot table, extract and requalify
            $columnName = Str::afterLast($column, '.');
            return $this->qualifyPivotColumn($columnName);
        }

        // Already qualified with pivot table
        return $column;
    }

    /**
     * Apply a function-based pivot constraint (DATE, MONTH, YEAR, TIME, etc.).
     *
     * @param QueryBuilder $query Query builder
     * @param string $column Function expression (e.g., "DATE(sort_order)")
     * @param string $operator SQL operator
     * @param mixed $value Value to compare
     * @return void
     */
    protected function applyFunctionBasedPivotConstraint(QueryBuilder $query, string $column, string $operator, mixed $value): void
    {
        // Handle specific SQL functions
        $sqlFunctions = ['DATE', 'MONTH', 'YEAR', 'TIME'];
        foreach ($sqlFunctions as $function) {
            if (Str::startsWith($column, "{$function}(")) {
                $actualColumn = str_replace(["{$function}(", ')'], '', $column);
                $qualifiedColumn = $this->ensurePivotColumnQualified($actualColumn);
                $query->whereRaw("{$function}({$qualifiedColumn}) {$operator} ?", [$value]);
                return;
            }
        }

        // Handle JSON functions (they may already have complex syntax)
        if (Str::startsWith($column, 'JSON_CONTAINS(')) {
            $query->whereRaw("{$column} = ?", [$value]);
            return;
        }

        if (Str::startsWith($column, 'JSON_LENGTH(')) {
            $query->whereRaw("{$column} {$operator} ?", [$value]);
            return;
        }

        // Generic function handling - extract function name and column
        if (preg_match('/^(\w+)\(([^)]+)\)$/', $column, $matches)) {
            $functionName = $matches[1];
            $functionColumn = $matches[2];
            $qualifiedColumn = $this->ensurePivotColumnQualified($functionColumn);
            $query->whereRaw("{$functionName}({$qualifiedColumn}) {$operator} ?", [$value]);
        } else {
            // Fallback for complex expressions
            $query->whereRaw("{$column} {$operator} ?", [$value]);
        }
    }

    /**
     * Apply a where constraint to a query builder.
     * Helper method to avoid code duplication when working with external query builders.
     *
     * @param QueryBuilder $query Query builder
     * @param string $column Column name
     * @param string $operator SQL operator
     * @param mixed $value Value to compare
     * @return void
     */
    /**
     * Get table columns with caching to avoid repeated SHOW COLUMNS queries.
     *
     * Performance: Uses static cache with 5-minute TTL to avoid repeated database queries
     * Clean Architecture: Reusable helper method for schema introspection
     *
     * @param string $tableName Table name
     * @param mixed $connection Database connection
     * @return array<string> Array of column names
     */
    protected function getCachedTableColumns(string $tableName, $connection): array
    {
        $cacheKey = $tableName;
        $now = now()->getTimestamp();

        // Check cache
        if (isset(self::$schemaCache[$cacheKey])) {
            $cached = self::$schemaCache[$cacheKey];
            // Return cached data if still valid
            if (($now - $cached['timestamp']) < self::$schemaCacheTtl) {
                return $cached['columns'];
            }
        }

        // Cache miss or expired - fetch from database
        $columns = $connection->select("SHOW COLUMNS FROM `{$tableName}`");
        $columnNames = array_column($columns, 'Field');

        // Store in cache
        self::$schemaCache[$cacheKey] = [
            'columns' => $columnNames,
            'timestamp' => $now
        ];

        return $columnNames;
    }

    protected function applyWhereToQueryBuilder(QueryBuilder $query, string $column, string $operator, mixed $value): void
    {
        match (true) {
            $operator === 'BETWEEN' && is_array($value) => $query->whereBetween($column, $value),
            $operator === 'NOT BETWEEN' && is_array($value) => $query->whereNotBetween($column, $value),
            $operator === 'IS' && $value === null => $query->whereNull($column),
            $operator === 'IS NOT' && $value === null => $query->whereNotNull($column),
            default => $query->where($column, $operator, $value),
        };
    }

    // =========================================================================
    // MAGIC METHODS
    // =========================================================================

    /**
     * Magic method to delegate calls to the query builder.
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (method_exists($this->query, $method)) {
            $result = $this->query->{$method}(...$parameters);

            return $result instanceof QueryBuilder ? $this : $result;
        }

        throw new \BadMethodCallException(
            sprintf('Method %s::%s does not exist.', static::class, $method)
        );
    }
}
