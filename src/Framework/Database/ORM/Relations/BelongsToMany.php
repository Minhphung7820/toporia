<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\{Model, ModelCollection};
use Toporia\Framework\Database\Query\QueryBuilder;


/**
 * Class BelongsToMany
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
class BelongsToMany extends Relation
{
    /**
     * @param QueryBuilder $query Query builder for related model
     * @param Model $parent Parent model instance
     * @param class-string<Model> $relatedClass Related model class name
     * @param string $pivotTable Pivot table name
     * @param string $foreignPivotKey Foreign key in pivot table for parent
     * @param string $relatedPivotKey Foreign key in pivot table for related
     * @param string $parentKey Parent's primary key
     * @param string $relatedKey Related model's primary key
     */
    public function __construct(
        QueryBuilder $query,
        Model $parent,
        protected string $relatedClass,
        protected string $pivotTable,
        protected string $foreignPivotKey,
        protected string $relatedPivotKey,
        protected string $parentKey,
        protected string $relatedKey
    ) {
        parent::__construct($query, $parent, $foreignPivotKey, $parentKey);
        $this->addPivotConstraints();
    }

    /**
     * Add JOIN and WHERE constraints for the pivot table.
     *
     * @return $this
     */
    protected function addPivotConstraints(): static
    {
        if ($this->parent->exists()) {
            $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

            $this->query
                ->join(
                    $this->pivotTable,
                    "{$relatedTable}.{$this->relatedKey}",
                    '=',
                    "{$this->pivotTable}.{$this->relatedPivotKey}"
                )
                ->where(
                    "{$this->pivotTable}.{$this->foreignPivotKey}",
                    $this->parent->getAttribute($this->parentKey)
                )
                ->select(["{$relatedTable}.*"]);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return ModelCollection
     */
    public function getResults(): ModelCollection
    {
        $rows = $this->query->get();

        if (empty($rows)) {
            return new ModelCollection([]);
        }

        return call_user_func([$this->relatedClass, 'hydrate'], $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = [];
        foreach ($models as $model) {
            $key = $model->getAttribute($this->parentKey);
            if ($key !== null) {
                $keys[] = $key;
            }
        }

        if (!empty($keys)) {
            $this->query->whereIn(
                "{$this->pivotTable}.{$this->foreignPivotKey}",
                array_unique($keys)
            );
        }
    }

    /**
     * {@inheritdoc}
     *
     * Match eagerly loaded results to their parent models.
     *
     * For BelongsToMany, we need to query the pivot table to determine
     * which related models belong to which parent models.
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

        // Step 1: Collect parent and related IDs
        $parentIds = [];
        foreach ($models as $model) {
            $parentId = $model->getAttribute($this->parentKey);
            if ($parentId !== null) {
                $parentIds[] = $parentId;
            }
        }

        $relatedIds = [];
        foreach ($results as $result) {
            $relatedId = $result->getAttribute($this->relatedKey);
            if ($relatedId !== null) {
                $relatedIds[] = $relatedId;
            }
        }

        if (empty($parentIds) || empty($relatedIds)) {
            // No valid IDs - set empty collections
            foreach ($models as $model) {
                $model->setRelation($relationName, new ModelCollection([]));
            }
            return $models;
        }

        // Step 2: Query pivot table to get parent-to-related mappings
        // SELECT foreign_key, related_key FROM pivot_table
        // WHERE foreign_key IN (...) AND related_key IN (...)
        $qb = new QueryBuilder($this->query->getConnection());
        $pivotRows = $qb->table($this->pivotTable)
            ->select($this->foreignPivotKey, $this->relatedPivotKey)
            ->whereIn($this->foreignPivotKey, array_unique($parentIds))
            ->whereIn($this->relatedPivotKey, array_unique($relatedIds))
            ->get();

        // Step 3: Build dictionary mapping parent IDs to arrays of related IDs
        // Example: [1 => [10, 20, 30], 2 => [20, 40], ...]
        $dictionary = [];
        foreach ($pivotRows as $pivot) {
            $parentId = $pivot[$this->foreignPivotKey] ?? null;
            $relatedId = $pivot[$this->relatedPivotKey] ?? null;

            if ($parentId !== null && $relatedId !== null) {
                if (!isset($dictionary[$parentId])) {
                    $dictionary[$parentId] = [];
                }
                $dictionary[$parentId][] = $relatedId;
            }
        }

        // Step 4: Build index of related models by ID for O(1) lookup
        // Example: [10 => Model, 20 => Model, ...]
        $relatedIndex = [];
        foreach ($results as $result) {
            $relatedId = $result->getAttribute($this->relatedKey);
            if ($relatedId !== null) {
                $relatedIndex[$relatedId] = $result;
            }
        }

        // Step 5: Match related models to parents using dictionary
        foreach ($models as $model) {
            $parentId = $model->getAttribute($this->parentKey);
            $matched = [];

            if ($parentId !== null && isset($dictionary[$parentId])) {
                // Get related IDs for this parent from dictionary
                $relatedIdsForParent = $dictionary[$parentId];

                // Look up actual models by ID
                foreach ($relatedIdsForParent as $relatedId) {
                    if (isset($relatedIndex[$relatedId])) {
                        $matched[] = $relatedIndex[$relatedId];
                    }
                }
            }

            // Set relation with matched models (or empty collection)
            $model->setRelation($relationName, new ModelCollection($matched));
        }

        return $models;
    }

    /**
     * Attach a related model to the parent via pivot table.
     *
     * @param int|string $id Related model ID
     * @param array<string, mixed> $pivotData Additional pivot data
     * @return bool
     */
    public function attach(int|string $id, array $pivotData = []): bool
    {
        $data = array_merge([
            $this->foreignPivotKey => $this->parent->getAttribute($this->parentKey),
            $this->relatedPivotKey => $id,
        ], $pivotData);

        $qb = new QueryBuilder($this->query->getConnection());
        $qb->table($this->pivotTable)->insert($data);

        return true;
    }

    /**
     * Detach a related model from the parent via pivot table.
     *
     * @param int|string|null $id Related model ID (null = detach all)
     * @return int Number of rows deleted
     */
    public function detach(int|string|null $id = null): int
    {
        $qb = new QueryBuilder($this->query->getConnection());
        $qb->table($this->pivotTable)
            ->where($this->foreignPivotKey, $this->parent->getAttribute($this->parentKey));

        if ($id !== null) {
            $qb->where($this->relatedPivotKey, $id);
        }

        return $qb->delete();
    }

    /**
     * Sync the pivot table with the given IDs.
     *
     * @param array<int|string> $ids Related model IDs
     * @return void
     */
    public function sync(array $ids): void
    {
        // Detach all current relations
        $this->detach();

        // Attach new relations
        foreach ($ids as $id) {
            $this->attach($id);
        }
    }

    /**
     * {@inheritdoc}
     *
     * For BelongsToMany, we need to ensure the related key is selected
     * on the related model (not the pivot table keys).
     */
    public function getForeignKeyName(): string
    {
        return $this->relatedPivotKey;
    }

    /**
     * {@inheritdoc}
     *
     * Override to handle BelongsToMany's complex constructor with pivot table.
     * Creates a fresh instance without parent constraints for eager loading.
     *
     * Performance: O(1) - Direct instantiation, no reflection overhead
     * Clean Architecture: Factory Method pattern for extensibility
     */
    public function newEagerInstance(\Toporia\Framework\Database\Query\QueryBuilder $freshQuery): static
    {
        $instance = new static(
            $freshQuery,
            $this->parent,
            $this->relatedClass,
            $this->pivotTable,
            $this->foreignPivotKey,
            $this->relatedPivotKey,
            $this->parentKey,
            $this->relatedKey
        );

        // BelongsToMany constructor calls addPivotConstraints() which adds parent WHERE clause
        // We need to reset the query to remove parent-specific constraints
        // Only eager constraints (WHERE IN) should be added later via addEagerConstraints()
        $cleanQuery = $freshQuery->newQuery();

        // Set clean query using property access (query is protected in parent)
        $reflection = new \ReflectionClass($instance);
        $queryProp = $reflection->getProperty('query');
        $queryProp->setAccessible(true);
        $queryProp->setValue($instance, $cleanQuery);

        return $instance;
    }
}
