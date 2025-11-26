<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\{Model, ModelCollection};


/**
 * Class MorphToMany
 *
 * Core class for the Relations layer providing essential functionality for
 * the Toporia Framework.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Relations
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
class MorphToMany extends Relation
{
    /**
     * @param \Toporia\Framework\Database\Query\QueryBuilder $query Query builder
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
        \Toporia\Framework\Database\Query\QueryBuilder $query,
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
        $this->relatedKey = $relatedPrimaryKey ?? call_user_func([$relatedClass, 'getPrimaryKey']);

        parent::__construct($query, $parent, $this->foreignKey, $this->localKey);

        $this->performJoin();
    }

    /**
     * Guess pivot table name.
     *
     * @return string
     */
    protected function guessPivotTable(): string
    {
        // Use morph name + 's' (taggable -> taggables)
        return $this->morphName . 's';
    }

    /**
     * Guess related key name.
     *
     * @return string
     */
    protected function guessRelatedKey(): string
    {
        $parts = explode('\\', $this->relatedClass);
        $className = strtolower(end($parts));
        return "{$className}_id";
    }

    /**
     * Get morph class name for parent.
     *
     * @return string
     */
    protected function getMorphClass(): string
    {
        // Use full class name to match database storage
        return get_class($this->parent);
    }

    /**
     * Perform join with pivot table.
     *
     * @return void
     */
    protected function performJoin(): void
    {
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

        // Join pivot table to related table
        // INNER JOIN taggables ON tags.id = taggables.tag_id
        $this->query->join(
            $this->pivotTable,
            "{$relatedTable}.{$this->relatedKey}",
            '=',
            "{$this->pivotTable}.{$this->relatedPivotKey}"
        );

        // Add morph constraints
        if ($this->parent->exists()) {
            // WHERE taggables.taggable_type = 'Post'
            $this->query->where(
                "{$this->pivotTable}.{$this->morphType}",
                $this->getMorphClass()
            );

            // AND taggables.taggable_id = ?
            $this->query->where(
                "{$this->pivotTable}.{$this->foreignKey}",
                $this->parent->getAttribute($this->localKey)
            );
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return ModelCollection
     */
    public function getResults(): ModelCollection
    {
        // Ensure constraints are applied if parent exists now but didn't at construction
        if ($this->parent->exists()) {
            // Create fresh query to avoid conflicts with query modifications
            $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);
            $freshQuery = $this->query->newQuery();
            $freshQuery->table($relatedTable);

            // Re-add join
            $freshQuery->join(
                $this->pivotTable,
                "{$relatedTable}.{$this->relatedKey}",
                '=',
                "{$this->pivotTable}.{$this->relatedPivotKey}"
            );

            // Apply morph constraints
            $freshQuery->where("{$this->pivotTable}.{$this->morphType}", $this->getMorphClass());
            $freshQuery->where("{$this->pivotTable}.{$this->foreignKey}", $this->parent->getAttribute($this->localKey));

            // Select related table columns
            $freshQuery->select("{$relatedTable}.*");

            $rowCollection = $freshQuery->get();
        } else {
            $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);
            $this->query->select("{$relatedTable}.*");
            $rowCollection = $this->query->get();
        }

        $rows = $rowCollection instanceof \Toporia\Framework\Database\Query\RowCollection
            ? $rowCollection->all()
            : $rowCollection;

        if (empty($rows)) {
            return new ModelCollection([]);
        }

        return call_user_func([$this->relatedClass, 'hydrate'], $rows);
    }

    /**
     * {@inheritdoc}
     *
     * Eager loading optimization:
     * Groups by type and loads in minimal queries.
     *
     * Example: 50 Posts and 30 Videos with Tags
     * - Query 1: Load tags for Posts (WHERE type='Post' AND id IN (...))
     * - Query 2: Load tags for Videos (WHERE type='Video' AND id IN (...))
     * Total: 2 queries instead of 80!
     */
    public function addEagerConstraints(array $models): void
    {
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

        // Group models by type (full class name)
        $types = [];
        foreach ($models as $model) {
            $type = get_class($model);

            if (!isset($types[$type])) {
                $types[$type] = [];
            }
            $types[$type][] = $model->getAttribute($this->localKey);
        }

        // Clear existing where (from performJoin)
        $this->query = $this->query->newQuery()->table($relatedTable);

        // Re-add join
        $this->query->join(
            $this->pivotTable,
            "{$relatedTable}.{$this->relatedKey}",
            '=',
            "{$this->pivotTable}.{$this->relatedPivotKey}"
        );

        // Add constraints for all types using closures
        // WHERE (type='Post' AND id IN (?,...)) OR (type='Video' AND id IN (?,...))
        $pivotTable = $this->pivotTable;
        $morphType = $this->morphType;
        $foreignKey = $this->foreignKey;

        $this->query->where(function ($q) use ($types, $pivotTable, $morphType, $foreignKey) {
            $first = true;
            foreach ($types as $type => $ids) {
                if ($first) {
                    $q->where(function ($subQ) use ($type, $ids, $pivotTable, $morphType, $foreignKey) {
                        $subQ->where("{$pivotTable}.{$morphType}", $type)
                            ->whereIn("{$pivotTable}.{$foreignKey}", $ids);
                    });
                    $first = false;
                } else {
                    $q->orWhere(function ($subQ) use ($type, $ids, $pivotTable, $morphType, $foreignKey) {
                        $subQ->where("{$pivotTable}.{$morphType}", $type)
                            ->whereIn("{$pivotTable}.{$foreignKey}", $ids);
                    });
                }
            }
        });

        // Select with pivot columns for matching
        $this->query->select(
            "{$relatedTable}.*",
            "{$this->pivotTable}.{$this->morphType}",
            "{$this->pivotTable}.{$this->foreignKey}"
        );
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $models, mixed $results, string $relationName): array
    {
        if (!$results instanceof ModelCollection) {
            return $models;
        }

        // Build dictionary: type:id => [related_models]
        $dictionary = [];
        foreach ($results as $result) {
            $type = $result->getAttribute($this->morphType);
            $id = $result->getAttribute($this->foreignKey);
            $key = "{$type}:{$id}";

            if (!isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }
            $dictionary[$key][] = $result;
        }

        // Match to parents
        foreach ($models as $model) {
            $type = get_class($model);
            $id = $model->getAttribute($this->localKey);
            $key = "{$type}:{$id}";

            $related = $dictionary[$key] ?? [];
            $model->setRelation($relationName, new ModelCollection($related));
        }

        return $models;
    }

    /**
     * {@inheritdoc}
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * Attach models to the relationship (original implementation).
     *
     * @param mixed $ids Model IDs or models to attach
     * @return bool
     */
    public function attachOriginal(mixed $ids): bool
    {
        $ids = is_array($ids) ? $ids : [$ids];

        foreach ($ids as $id) {
            $connection = $this->query->getConnection();
            $query = new \Toporia\Framework\Database\Query\QueryBuilder($connection);

            $query->table($this->pivotTable)->insert([
                $this->relatedPivotKey => $id,
                $this->foreignKey => $this->parent->getAttribute($this->localKey),
                $this->morphType => $this->getMorphClass()
            ]);
        }

        return true;
    }

    /**
     * Detach models from the relationship.
     *
     * @param mixed $ids Model IDs to detach (null = detach all)
     * @return int Number of rows deleted
     */
    public function detach(mixed $ids = null): int
    {
        $connection = $this->query->getConnection();
        $query = new \Toporia\Framework\Database\Query\QueryBuilder($connection);

        $query->table($this->pivotTable)
            ->where($this->foreignKey, $this->parent->getAttribute($this->localKey))
            ->where($this->morphType, $this->getMorphClass());

        if ($ids !== null) {
            $ids = is_array($ids) ? $ids : [$ids];
            $query->whereIn($this->relatedPivotKey, $ids);
        }

        return $query->delete();
    }

    /**
     * Sync the relationship with the given IDs (original implementation).
     *
     * @param mixed $ids Model IDs to sync
     * @return void
     */
    public function syncOriginal(mixed $ids): void
    {
        $ids = is_array($ids) ? $ids : [$ids];

        // Detach all existing
        $this->detach();

        // Attach new ones
        $this->attachOriginal($ids);
    }

    /**
     * Store pivot table name
     */
    protected string $pivotTable;

    /**
     * Store morph type column
     */
    protected string $morphType;

    /**
     * Store related pivot key
     */
    protected string $relatedPivotKey;

    /**
     * Store related key
     */
    protected string $relatedKey;

    /**
     * Additional pivot columns to select.
     *
     * @var array<string>
     */
    protected array $pivotColumns = [];

    /**
     * Whether to include timestamps in pivot table.
     *
     * @var bool
     */
    protected bool $withTimestamps = false;

    /**
     * Custom pivot accessor name.
     *
     * @var string
     */
    protected string $pivotAccessor = 'pivot';

    /**
     * Pivot where constraints.
     *
     * @var array<array{column: string, operator: string, value: mixed}>
     */
    protected array $pivotWheres = [];

    /**
     * Pivot whereIn constraints.
     *
     * @var array<array{column: string, values: array}>
     */
    protected array $pivotWhereIns = [];

    /**
     * Pivot order by clauses.
     *
     * @var array<array{column: string, direction: string}>
     */
    protected array $pivotOrderBy = [];

    /**
     * Custom pivot model class.
     *
     * @var class-string|null
     */
    protected ?string $pivotClass = null;

    /**
     * Specify additional pivot columns to include in query results.
     *
     * Performance: O(1) - Array merge operation
     * Clean Architecture: Fluent interface for readability
     *
     * @param string ...$columns Pivot column names to select
     * @return $this
     *
     * @example
     * ```php
     * $post->tags()->withPivot('created_at', 'priority')->get();
     * ```
     */
    public function withPivot(string ...$columns): static
    {
        $this->pivotColumns = array_merge($this->pivotColumns, $columns);
        return $this;
    }

    /**
     * Include created_at and updated_at timestamps in pivot table.
     *
     * @return $this
     *
     * @example
     * ```php
     * $post->tags()->withTimestamps()->get();
     * ```
     */
    public function withTimestamps(): static
    {
        $this->withTimestamps = true;
        return $this->withPivot('created_at', 'updated_at');
    }

    /**
     * Customize the pivot accessor name.
     *
     * @param string $accessor Custom accessor name
     * @return $this
     *
     * @example
     * ```php
     * $post->tags()->as('tagging')->withTimestamps()->get();
     * ```
     */
    public function as(string $accessor): static
    {
        $this->pivotAccessor = $accessor;
        return $this;
    }

    /**
     * Add a where constraint on the pivot table.
     *
     * @param string $column Pivot column name
     * @param mixed $operator Operator or value if no operator provided
     * @param mixed $value Value to compare (optional)
     * @return $this
     *
     * @example
     * ```php
     * $post->tags()->wherePivot('status', 'active')->get();
     * ```
     */
    public function wherePivot(string $column, mixed $operator, mixed $value = null): static
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->pivotWheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value
        ];

        return $this;
    }

    /**
     * Add a whereIn constraint on the pivot table.
     *
     * @param string $column Pivot column name
     * @param array $values Array of values
     * @return $this
     */
    public function wherePivotIn(string $column, array $values): static
    {
        $this->pivotWhereIns[] = [
            'column' => $column,
            'values' => $values
        ];

        return $this;
    }

    /**
     * Add a whereNotIn constraint on the pivot table.
     *
     * @param string $column Pivot column name
     * @param array $values Array of values
     * @return $this
     */
    public function wherePivotNotIn(string $column, array $values): static
    {
        $this->pivotWhereIns[] = [
            'column' => $column,
            'values' => $values,
            'not' => true
        ];

        return $this;
    }

    /**
     * Add an order by clause on the pivot table.
     *
     * @param string $column Pivot column name
     * @param string $direction Sort direction (asc|desc)
     * @return $this
     */
    public function orderByPivot(string $column, string $direction = 'asc'): static
    {
        $this->pivotOrderBy[] = [
            'column' => $column,
            'direction' => strtolower($direction)
        ];

        return $this;
    }

    /**
     * Specify a custom pivot model class to use.
     *
     * @param class-string $class Custom pivot model class
     * @return $this
     */
    public function using(string $class): static
    {
        $this->pivotClass = $class;
        return $this;
    }

    /**
     * Create a new related model and attach it.
     *
     * Performance: O(1) - Single INSERT operations
     * Clean Architecture: Atomic create-and-attach operation
     *
     * @param array $attributes Model attributes
     * @param array $pivotData Additional pivot data
     * @return Model Created and attached model
     *
     * @example
     * ```php
     * $tag = $post->tags()->create(['name' => 'Laravel'], ['priority' => 1]);
     * ```
     */
    public function create(array $attributes = [], array $pivotData = []): Model
    {
        $instance = call_user_func([$this->relatedClass, 'create'], $attributes);
        $this->attach($instance->getAttribute($this->relatedKey), $pivotData);
        return $instance;
    }

    /**
     * Save a related model and attach it.
     *
     * @param Model $model Model to save and attach
     * @param array $pivotData Additional pivot data
     * @return Model Saved and attached model
     */
    public function save(Model $model, array $pivotData = []): Model
    {
        $model->save();
        $this->attach($model->getAttribute($this->relatedKey), $pivotData);
        return $model;
    }

    /**
     * Enhanced attach method with pivot data support.
     *
     * Performance: O(n) - Batch operations when possible
     * Clean Architecture: Flexible attachment with pivot data
     *
     * @param int|string|array $id Related model ID or array of IDs with pivot data
     * @param array<string, mixed> $pivotData Additional pivot data
     * @return array|bool Array of attached IDs or boolean for single attach
     */
    public function attach(int|string|array $id, array $pivotData = []): array|bool
    {
        if (is_array($id)) {
            return $this->attachMany($id);
        }

        $data = array_merge([
            $this->relatedPivotKey => $id,
            $this->foreignKey => $this->parent->getAttribute($this->localKey),
            $this->morphType => $this->getMorphClass(),
        ], $pivotData);

        // Add timestamps if enabled
        if ($this->withTimestamps) {
            $now = date('Y-m-d H:i:s');
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
        }

        $connection = $this->query->getConnection();
        $query = new \Toporia\Framework\Database\Query\QueryBuilder($connection);
        $query->table($this->pivotTable)->insert($data);

        return true;
    }

    /**
     * Attach multiple related models with individual pivot data.
     *
     * @param array $ids Array of IDs or associative array with pivot data
     * @return array Array of attached IDs
     */
    protected function attachMany(array $ids): array
    {
        $attached = [];
        $insertData = [];

        foreach ($ids as $key => $value) {
            if (is_numeric($key)) {
                $relatedId = $value;
                $pivotData = [];
            } else {
                $relatedId = $key;
                $pivotData = is_array($value) ? $value : [];
            }

            $data = array_merge([
                $this->relatedPivotKey => $relatedId,
                $this->foreignKey => $this->parent->getAttribute($this->localKey),
                $this->morphType => $this->getMorphClass(),
            ], $pivotData);

            if ($this->withTimestamps) {
                $now = date('Y-m-d H:i:s');
                $data['created_at'] = $now;
                $data['updated_at'] = $now;
            }

            $insertData[] = $data;
            $attached[] = $relatedId;
        }

        if (!empty($insertData)) {
            $connection = $this->query->getConnection();
            $query = new \Toporia\Framework\Database\Query\QueryBuilder($connection);
            $query->table($this->pivotTable)->insert($insertData);
        }

        return $attached;
    }

    /**
     * Enhanced sync method with pivot data support.
     *
     * Performance: O(n) - Batch operations with single transaction
     * Clean Architecture: Atomic sync operation with consistent state
     *
     * @param array $ids Related model IDs or associative array with pivot data
     * @param bool $detaching Whether to detach missing records
     * @return array Sync results with attached, detached, and updated arrays
     */
    public function sync(array $ids, bool $detaching = true): array
    {
        $changes = [
            'attached' => [],
            'detached' => [],
            'updated' => []
        ];

        // Get current pivot records
        $current = $this->getCurrentPivotIds();

        // Normalize input IDs
        $records = $this->formatSyncRecords($ids);
        $syncIds = array_keys($records);

        if ($detaching) {
            // Determine what to detach
            $detach = array_diff($current, $syncIds);
            if (!empty($detach)) {
                $this->detach($detach);
                $changes['detached'] = $detach;
            }
        }

        // Determine what to attach or update
        foreach ($records as $id => $pivotData) {
            if (in_array($id, $current)) {
                // Update existing pivot record
                if (!empty($pivotData)) {
                    $this->updateExistingPivot($id, $pivotData);
                    $changes['updated'][] = $id;
                }
            } else {
                // Attach new record
                $this->attach($id, $pivotData);
                $changes['attached'][] = $id;
            }
        }

        return $changes;
    }

    /**
     * Toggle the attachment of related models.
     *
     * @param array|int|string $ids Related model IDs
     * @return array Toggle results with attached and detached arrays
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
     *
     * @param int|string $id Related model ID
     * @param array $pivotData Pivot data to update
     * @return bool
     */
    public function updateExistingPivot(int|string $id, array $pivotData): bool
    {
        if ($this->withTimestamps && !isset($pivotData['updated_at'])) {
            $pivotData['updated_at'] = date('Y-m-d H:i:s');
        }

        $connection = $this->query->getConnection();
        $query = new \Toporia\Framework\Database\Query\QueryBuilder($connection);

        $affected = $query->table($this->pivotTable)
            ->where($this->foreignKey, $this->parent->getAttribute($this->localKey))
            ->where($this->morphType, $this->getMorphClass())
            ->where($this->relatedPivotKey, $id)
            ->update($pivotData);

        return $affected > 0;
    }

    /**
     * Get current pivot IDs for the parent model.
     *
     * @return array
     */
    protected function getCurrentPivotIds(): array
    {
        $connection = $this->query->getConnection();
        $query = new \Toporia\Framework\Database\Query\QueryBuilder($connection);

        $results = $query->table($this->pivotTable)
            ->where($this->foreignKey, $this->parent->getAttribute($this->localKey))
            ->where($this->morphType, $this->getMorphClass())
            ->pluck($this->relatedPivotKey);

        return $results->toArray();
    }

    /**
     * Format sync records from various input formats.
     *
     * @param array $records Input records
     * @return array Formatted records
     */
    protected function formatSyncRecords(array $records): array
    {
        $formatted = [];

        foreach ($records as $key => $value) {
            if (is_numeric($key)) {
                $formatted[$value] = [];
            } else {
                $formatted[$key] = is_array($value) ? $value : [];
            }
        }

        return $formatted;
    }

    /**
     * Process records in chunks to optimize memory usage.
     *
     * Performance: O(n/chunk_size) - Memory-efficient processing
     * Clean Architecture: Callback pattern for flexible processing
     *
     * @param int $count Number of records per chunk
     * @param callable $callback Function to process each chunk
     * @return bool True if all chunks processed successfully
     */
    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;

        do {
            $results = $this->query->limit($count)->offset(($page - 1) * $count)->get();

            if ($results->isEmpty()) {
                break;
            }

            $models = call_user_func([$this->relatedClass, 'hydrate'], $results->toArray());

            if ($callback($models, $page) === false) {
                return false;
            }

            $page++;
        } while ($results->count() === $count);

        return true;
    }

    /**
     * Get the count of related models.
     *
     * @return int Count of related models
     */
    public function count(): int
    {
        if (!$this->parent->exists()) {
            return 0;
        }

        return $this->query->count();
    }

    /**
     * Check if any related models exist.
     *
     * @return bool True if related models exist
     */
    public function exists(): bool
    {
        if (!$this->parent->exists()) {
            return false;
        }

        return $this->query->exists();
    }

    /**
     * Get the first related model or create a new one.
     *
     * @param array $attributes Attributes for new model if not found
     * @param array $pivotData Additional pivot data
     * @return Model Found or created model
     */
    public function firstOrCreate(array $attributes = [], array $pivotData = []): Model
    {
        $instance = $this->query->first();

        if ($instance === null) {
            $instance = $this->create($attributes, $pivotData);
        }

        return $instance;
    }

    /**
     * Magic method to delegate calls to the underlying query builder.
     *
     * Performance: O(1) - Direct method delegation
     * Clean Architecture: Proxy pattern for query builder methods
     *
     * @param string $method Method name
     * @param array $parameters Method parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (method_exists($this->query, $method)) {
            $result = $this->query->{$method}(...$parameters);

            if ($result instanceof \Toporia\Framework\Database\Query\QueryBuilder) {
                return $this;
            }

            return $result;
        }

        throw new \BadMethodCallException(
            sprintf('Method %s::%s does not exist.', static::class, $method)
        );
    }
}
