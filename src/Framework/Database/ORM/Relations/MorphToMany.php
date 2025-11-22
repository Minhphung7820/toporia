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
     * Attach models to the relationship.
     *
     * @param mixed $ids Model IDs or models to attach
     * @return bool
     */
    public function attach(mixed $ids): bool
    {
        $ids = is_array($ids) ? $ids : [$ids];

        foreach ($ids as $id) {
            $connection = $this->parent::getConnection();
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
        $connection = $this->parent::getConnection();
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
     * Sync the relationship with the given IDs.
     *
     * @param mixed $ids Model IDs to sync
     * @return void
     */
    public function sync(mixed $ids): void
    {
        $ids = is_array($ids) ? $ids : [$ids];

        // Detach all existing
        $this->detach();

        // Attach new ones
        $this->attach($ids);
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
}
