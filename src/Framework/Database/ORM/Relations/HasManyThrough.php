<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\{Model, ModelCollection};


/**
 * Class HasManyThrough
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
class HasManyThrough extends Relation
{
    /**
     * @param \Toporia\Framework\Database\Query\QueryBuilder $query Query builder for related model
     * @param Model $parent Parent model instance
     * @param class-string<Model> $relatedClass Related model class (Post)
     * @param class-string<Model> $throughClass Through model class (User)
     * @param string $firstKey Foreign key on through table (users.country_id)
     * @param string $secondKey Foreign key on related table (posts.user_id)
     * @param string $localKey Local key on parent table (countries.id)
     * @param string $secondLocalKey Local key on through table (users.id)
     */
    public function __construct(
        \Toporia\Framework\Database\Query\QueryBuilder $query,
        Model $parent,
        protected string $relatedClass,
        protected string $throughClass,
        protected string $firstKey,  // users.country_id
        string $secondKey,           // posts.user_id
        string $localKey,            // countries.id
        protected string $secondLocalKey
    ) {
        // Store keys for eager loading
        $this->foreignKey = $secondKey; // posts.user_id
        $this->localKey = $localKey;     // countries.id

        parent::__construct($query, $parent, $firstKey, $localKey);

        // Set up the join
        $this->performJoin();
    }

    /**
     * Perform join with through table.
     *
     * @return void
     */
    protected function performJoin(): void
    {
        $throughTable = call_user_func([$this->throughClass, 'getTableName']);
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

        // Join through table to related table
        // INNER JOIN users ON users.id = posts.user_id
        $this->query->join(
            $throughTable,
            "{$throughTable}.{$this->secondLocalKey}",
            '=',
            "{$relatedTable}.{$this->foreignKey}"
        );

        // Add constraint for parent
        // WHERE users.country_id = ?
        if ($this->parent->exists()) {
            $this->query->where(
                "{$throughTable}.{$this->firstKey}",
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
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

        // Select only related table columns to avoid column ambiguity
        $this->query->select("{$relatedTable}.*");

        $rowCollection = $this->query->get();

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
     */
    public function addEagerConstraints(array $models): void
    {
        $throughTable = call_user_func([$this->throughClass, 'getTableName']);
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

        // Get parent IDs
        $keys = array_map(fn($m) => $m->getAttribute($this->localKey), $models);

        // Clear existing where (from performJoin)
        $this->query = $this->query->newQuery()->table($relatedTable);

        // Re-add join
        $this->query->join(
            $throughTable,
            "{$throughTable}.{$this->secondLocalKey}",
            '=',
            "{$relatedTable}.{$this->foreignKey}"
        );

        // WHERE users.country_id IN (1, 2, 3, ...)
        $this->query->whereIn("{$throughTable}.{$this->firstKey}", $keys);

        // Select with through key for matching
        $this->query->select("{$relatedTable}.*", "{$throughTable}.{$this->firstKey}");
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $models, mixed $results, string $relationName): array
    {
        if (!$results instanceof ModelCollection) {
            return $models;
        }

        // Build dictionary: parent_id => [related_models]
        $dictionary = [];
        foreach ($results as $result) {
            // Get the through key from result
            $key = $result->getAttribute($this->firstKey);
            if (!isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }
            $dictionary[$key][] = $result;
        }

        // Match to parents
        foreach ($models as $model) {
            $localValue = $model->getAttribute($this->localKey);
            $related = $dictionary[$localValue] ?? [];
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
}
