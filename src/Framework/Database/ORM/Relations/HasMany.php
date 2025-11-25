<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\{Model, ModelCollection};


/**
 * Class HasMany
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
class HasMany extends Relation
{
    /**
     * @param class-string<Model> $relatedClass Related model class name
     */
    public function __construct(
        \Toporia\Framework\Database\Query\QueryBuilder $query,
        Model $parent,
        protected string $relatedClass,
        string $foreignKey,
        string $localKey
    ) {
        parent::__construct($query, $parent, $foreignKey, $localKey);
        $this->addConstraints();
    }

    /**
     * {@inheritdoc}
     *
     * @return ModelCollection
     */
    public function getResults(): ModelCollection
    {
        $rowCollection = $this->query->get();

        // Convert RowCollection to array for hydration
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
    public function match(array $models, mixed $results, string $relationName): array
    {
        if (!$results instanceof ModelCollection) {
            return $models;
        }

        // Build dictionary: foreign_key => [model1, model2, ...]
        $dictionary = [];
        foreach ($results as $result) {
            $key = $result->getAttribute($this->foreignKey);
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

    /**
     * Save multiple related models.
     *
     * Creates and saves multiple related models in a single operation.
     * Optimized for performance using bulk insert when possible.
     *
     * Architecture:
     * - SOLID: Single Responsibility (bulk save only)
     * - Performance: Uses single bulk INSERT query (O(1) queries instead of O(N))
     * - Clean Architecture: Uses QueryBuilder for database operations
     *
     * @param array<int, array<string, mixed>> $models Array of model attributes
     * @return ModelCollection Collection of saved models
     *
     * @example
     * $user->posts()->saveMany([
     *     ['title' => 'Post 1', 'content' => 'Content 1'],
     *     ['title' => 'Post 2', 'content' => 'Content 2'],
     * ]);
     */
    public function saveMany(array $models): ModelCollection
    {
        if (empty($models)) {
            return new ModelCollection([]);
        }

        $parentKey = $this->parent->getAttribute($this->localKey);

        if ($parentKey === null) {
            throw new \RuntimeException('Cannot save related models: parent model does not have a key');
        }

        // Create temporary model instance to get table name and handle timestamps
        $tempModel = new $this->relatedClass([]);
        $table = $tempModel->getTableName();

        // Check if model uses timestamps (via reflection to access static property)
        $reflection = app()->make(\Toporia\Framework\Support\ReflectionService::class)->getClass($this->relatedClass);
        $timestampsProperty = $reflection->getStaticPropertyValue('timestamps', true);
        $hasTimestamps = $timestampsProperty ?? true;

        // Prepare data with foreign key and timestamps
        $preparedModels = [];
        $now = $hasTimestamps ? date('Y-m-d H:i:s') : null;

        foreach ($models as $attributes) {
            // Set foreign key
            $attributes[$this->foreignKey] = $parentKey;

            // Add timestamps if enabled
            if ($hasTimestamps) {
                $attributes['created_at'] = $attributes['created_at'] ?? $now;
                $attributes['updated_at'] = $attributes['updated_at'] ?? $now;
            }

            $preparedModels[] = $attributes;
        }

        // Get all unique columns from all models
        $allColumns = [];
        foreach ($preparedModels as $attributes) {
            $allColumns = array_merge($allColumns, array_keys($attributes));
        }
        $columns = array_unique($allColumns);
        sort($columns); // Consistent column order

        // Prepare bulk insert
        $values = [];
        $placeholders = [];

        foreach ($preparedModels as $attributes) {
            $rowValues = [];
            foreach ($columns as $column) {
                $rowValues[] = $attributes[$column] ?? null;
            }
            $placeholders[] = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
            $values = array_merge($values, $rowValues);
        }

        // Bulk insert
        $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES " . implode(', ', $placeholders);
        $connection = $this->query->getConnection();
        $connection->execute($sql, $values);

        // Get inserted IDs (MySQL returns first inserted ID, subsequent IDs are sequential)
        $lastInsertId = $connection->lastInsertId();
        $firstId = (int) $lastInsertId;

        // Validate that we got a valid ID
        if ($firstId <= 0) {
            // If lastInsertId failed, try to query the database for the inserted records
            // This can happen in some edge cases
            throw new \RuntimeException(
                "Failed to get inserted ID from database. LastInsertId returned: {$lastInsertId}. " .
                    "This might indicate the bulk insert failed or the table doesn't have an auto-increment column."
            );
        }

        $insertedIds = range($firstId, $firstId + count($preparedModels) - 1);

        // Hydrate models with IDs
        $savedModels = [];
        $modelReflection = app()->make(\Toporia\Framework\Support\ReflectionService::class)->getClass($this->relatedClass);

        // Check if exists property exists (it's private in Model base class)
        $hasExistsProperty = $modelReflection->hasProperty('exists');
        $existsProperty = null;
        if ($hasExistsProperty) {
            $existsProperty = $modelReflection->getProperty('exists');
            $existsProperty->setAccessible(true);
        }

        // Get syncOriginal method
        $syncMethod = $modelReflection->getMethod('syncOriginal');
        $syncMethod->setAccessible(true);

        foreach ($insertedIds as $index => $id) {
            $attributes = $preparedModels[$index];
            $model = new $this->relatedClass($attributes);

            // Set ID directly using setAttribute (bypasses mass assignment protection)
            $model->setAttribute('id', $id);

            // Set exists flag using reflection if property exists
            if ($hasExistsProperty && $existsProperty !== null) {
                $existsProperty->setValue($model, true);
            }

            // Sync original attributes (includes the ID we just set)
            $syncMethod->invoke($model);

            $savedModels[] = $model;
        }

        return new ModelCollection($savedModels);
    }

    /**
     * Create multiple related models.
     *
     * Alias for saveMany() for convenience.
     *
     * @param array<int, array<string, mixed>> $models Array of model attributes
     * @return ModelCollection Collection of created models
     */
    public function createMany(array $models): ModelCollection
    {
        return $this->saveMany($models);
    }

    /**
     * {@inheritdoc}
     *
     * Override to handle HasMany's constructor which has relatedClass parameter.
     * Creates a fresh instance without parent constraints for eager loading.
     *
     * Performance: O(1) - Direct instantiation, zero reflection overhead
     * Clean Architecture: Factory Method + Setter pattern for extensibility
     */
    public function newEagerInstance(\Toporia\Framework\Database\Query\QueryBuilder $freshQuery): static
    {
        $instance = new static(
            $freshQuery,
            $this->parent,
            $this->relatedClass,
            $this->foreignKey,
            $this->localKey
        );

        // HasMany constructor calls addConstraints() which adds parent WHERE clause
        // We need to reset the query to remove parent-specific constraints
        // Only eager constraints (WHERE IN) should be added later via addEagerConstraints()
        $cleanQuery = $freshQuery->newQuery();

        // Use setter method instead of reflection (cleaner & faster)
        $instance->setQuery($cleanQuery);

        return $instance;
    }
}
