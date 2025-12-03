<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\{Model, ModelCollection};
use Toporia\Framework\Database\Query\QueryBuilder;

/**
 * BelongsTo Relationship
 *
 * Handles inverse one-to-one and one-to-many relationships.
 * Optimized for performance and follows Clean Architecture principles.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.1.0
 * @package     toporia/framework
 * @subpackage  Relations
 * @since       2025-01-10
 */
class BelongsTo extends Relation
{
    /** @var bool Whether constraints have been applied */
    private bool $constraintsApplied = false;

    /** @var string|null Cached relation name */
    private ?string $relationNameCache = null;

    public function __construct(
        QueryBuilder $query,
        Model $parent,
        protected string $relatedClass,
        string $foreignKey,
        string $ownerKey
    ) {
        parent::__construct($query, $parent, $foreignKey, $ownerKey);
        $this->constraintsApplied = $this->initializeConstraints();
    }

    /**
     * {@inheritdoc}
     */
    public function getRelatedClass(): string
    {
        return $this->relatedClass;
    }

    /**
     * Initialize constraints in constructor.
     */
    private function initializeConstraints(): bool
    {
        if (!$this->parent->exists()) {
            return false;
        }

        $foreignKeyValue = $this->parent->getAttribute($this->foreignKey);
        if ($foreignKeyValue === null) {
            return false;
        }

        $this->query->where($this->localKey, $foreignKeyValue);

        // Apply soft delete scope if related model uses soft deletes
        $this->applySoftDeleteScope($this->query, $this->relatedClass);

        return true;
    }

    // =========================================================================
    // CORE RELATION METHODS
    // =========================================================================

    /**
     * Add constraints for belongs to relationship.
     */
    public function addConstraints(): static
    {
        if (!$this->constraintsApplied) {
            $this->constraintsApplied = $this->initializeConstraints();
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getResults(): mixed
    {
        if (!$this->constraintsApplied) {
            $this->addConstraints();
        }

        // Check if this is eager loading (has whereIn constraint)
        $wheres = $this->query->getWheres();
        $hasWhereIn = false;
        foreach ($wheres as $where) {
            if (($where['type'] ?? '') === 'in' || ($where['type'] ?? '') === 'In') {
                $hasWhereIn = true;
                break;
            }
        }

        // If eager loading (whereIn), return ModelCollection
        if ($hasWhereIn) {
            // Ensure table is set before executing query
            $table = $this->query->getTable();
            if ($table === null) {
                // Get table from related model if not set
                $table = $this->relatedClass::getTableName();
                if ($table !== null) {
                    $this->query->table($table);
                }
            }
            return $this->relatedClass::hydrate($this->query->get()->toArray());
        }

        // Single model query - return single Model or null
        if (!$this->hasValidForeignKey()) {
            return null;
        }

        return $this->query->first();
    }

    /**
     * {@inheritdoc}
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = $this->extractForeignKeys($models);

        if ($keys !== []) {
            $this->query->whereIn($this->localKey, $keys);
        }

        // Apply soft delete scope if related model uses soft deletes
        $this->applySoftDeleteScope($this->query, $this->relatedClass);
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $models, mixed $results, string $relationName): array
    {
        if (!$results instanceof ModelCollection) {
            return $models;
        }

        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $foreignValue = $model->getAttribute($this->foreignKey);
            $model->setRelation($relationName, $dictionary[$foreignValue] ?? null);
        }

        return $models;
    }

    /**
     * For BelongsTo, the owner key is used for matching.
     */
    public function getForeignKeyName(): string
    {
        return $this->localKey;
    }

    /**
     * {@inheritdoc}
     */
    public function newEagerInstance(QueryBuilder $freshQuery): static
    {
        // Create a completely clean query without any constraints
        // This prevents WHERE id = ? AND id IN (...) issue during eager loading
        $table = $freshQuery->getTable();
        $connection = $freshQuery->getConnection();
        $cleanQuery = new \Toporia\Framework\Database\Query\QueryBuilder($connection);

        if ($table !== null) {
            $cleanQuery->table($table);
        }

        // Copy selects from freshQuery if any
        $selects = $freshQuery->getColumns();
        if (!empty($selects)) {
            $cleanQuery->select($selects);
        }

        // CRITICAL: Copy all where constraints from original query (relationship method)
        // This ensures constraints like where('is_active', true) are preserved during eager loading
        // Exclude local key constraint as it will be added by addEagerConstraints()
        // This allows relationship methods with constraints to work correctly with eager loading
        $this->copyWhereConstraints($cleanQuery, [$this->localKey]);

        // Create a dummy parent model that doesn't exist to prevent initializeConstraints()
        // from adding WHERE localKey = ? constraint. This ensures only WHERE localKey IN (...)
        // from addEagerConstraints() is used during eager loading.
        // Creating a new instance ensures exists() returns false and foreign key is null
        $parentClass = get_class($this->parent);
        $dummyParent = new $parentClass();

        // Create instance with clean query and dummy parent
        // initializeConstraints() will return early because dummy parent has no foreign key
        $instance = new static(
            $cleanQuery,
            $dummyParent,
            $this->relatedClass,
            $this->foreignKey,
            $this->localKey
        );

        // Ensure constraintsApplied is false so addEagerConstraints() will add whereIn
        $instance->constraintsApplied = false;

        return $instance;
    }

    // =========================================================================
    // ASSOCIATION METHODS
    // =========================================================================

    /**
     * Associate the parent model with the given model.
     *
     * @param Model|int|string $model Model instance or ID to associate
     * @return Model The parent model
     */
    public function associate(Model|int|string $model): Model
    {
        $ownerKey = $model instanceof Model
            ? $model->getAttribute($this->localKey)
            : $model;

        $this->parent->setAttribute($this->foreignKey, $ownerKey);

        if ($model instanceof Model) {
            $this->parent->setRelation($this->getRelationName(), $model);
        }

        return $this->parent;
    }

    /**
     * Dissociate the parent model from its related model.
     */
    public function dissociate(): Model
    {
        $this->parent->setAttribute($this->foreignKey, null);
        $this->parent->setRelation($this->getRelationName(), null);

        return $this->parent;
    }

    // =========================================================================
    // CRUD OPERATIONS
    // =========================================================================

    /**
     * Create a new related model and associate it.
     */
    public function create(array $attributes = []): Model
    {
        $instance = $this->relatedClass::create($attributes);
        $this->associate($instance);
        $this->parent->save();

        return $instance;
    }

    /**
     * Save a related model and associate it.
     */
    public function save(Model $model): Model
    {
        $model->save();
        $this->associate($model);
        $this->parent->save();

        return $model;
    }

    /**
     * Update the related model.
     */
    public function update(array $attributes): int
    {
        return $this->parent->exists() ? $this->query->update($attributes) : 0;
    }

    /**
     * Delete the related model.
     */
    public function delete(): int
    {
        return $this->parent->exists() ? $this->query->delete() : 0;
    }

    /**
     * Get the first related model or create a new one.
     */
    public function firstOrCreate(array $attributes = []): Model
    {
        return $this->getResults() ?? $this->create($attributes);
    }

    /**
     * Get the first related model or instantiate a new one (without saving).
     */
    public function firstOrNew(array $attributes = []): Model
    {
        return $this->getResults() ?? new $this->relatedClass($attributes);
    }

    /**
     * Update or create a related model and associate it.
     */
    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        $instance = $this->getResults();

        if ($instance !== null) {
            $instance->fill([...$attributes, ...$values]);
            $instance->save();
            return $instance;
        }

        return $this->create([...$attributes, ...$values]);
    }

    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    /**
     * Check if the related model exists.
     */
    public function exists(): bool
    {
        return $this->hasValidForeignKey() && $this->query->exists();
    }

    /**
     * Get the count of related models (always 0 or 1 for BelongsTo).
     */
    public function count(): int
    {
        return $this->hasValidForeignKey() ? $this->query->count() : 0;
    }

    /**
     * Get the foreign key value from the parent model.
     */
    public function getForeignKeyValue(): mixed
    {
        return $this->parent->getAttribute($this->foreignKey);
    }

    // =========================================================================
    // COMPARISON METHODS
    // =========================================================================

    /**
     * Check if the parent is associated with a specific model.
     */
    public function is(Model|int|string $model): bool
    {
        $foreignKeyValue = $this->getForeignKeyValue();

        if ($foreignKeyValue === null) {
            return false;
        }

        $compareValue = $model instanceof Model
            ? $model->getAttribute($this->localKey)
            : $model;

        return $foreignKeyValue === $compareValue;
    }

    /**
     * Check if the parent is not associated with a specific model.
     */
    public function isNot(Model|int|string $model): bool
    {
        return !$this->is($model);
    }

    // =========================================================================
    // INTERNAL HELPERS
    // =========================================================================

    /**
     * Check if parent exists and has a valid foreign key.
     */
    protected function hasValidForeignKey(): bool
    {
        return $this->parent->exists()
            && $this->parent->getAttribute($this->foreignKey) !== null;
    }

    /**
     * Extract foreign keys from models array.
     *
     * @param array<Model> $models
     * @return array<int|string>
     */
    protected function extractForeignKeys(array $models): array
    {
        $keys = [];
        foreach ($models as $model) {
            $key = $model->getAttribute($this->foreignKey);
            if ($key !== null) {
                $keys[] = $key;
            }
        }
        return array_values(array_unique($keys));
    }

    /**
     * Build dictionary mapping owner key to model.
     *
     * @return array<int|string, Model>
     */
    protected function buildDictionary(ModelCollection $results): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            $key = $result->getAttribute($this->localKey);
            if ($key !== null) {
                $dictionary[$key] = $result;
            }
        }
        return $dictionary;
    }

    /**
     * Get the relation name with caching.
     * Uses class name as fallback instead of expensive debug_backtrace.
     */
    protected function getRelationName(): string
    {
        if ($this->relationNameCache !== null) {
            return $this->relationNameCache;
        }

        // Use simple class name derivation instead of debug_backtrace
        $parts = explode('\\', $this->relatedClass);
        $className = end($parts);

        // Remove common suffixes like "Model"
        $className = preg_replace('/Model$/', '', $className);

        return $this->relationNameCache = lcfirst($className);
    }

    /**
     * Set the cached relation name explicitly.
     */
    public function setRelationName(string $name): static
    {
        $this->relationNameCache = $name;
        return $this;
    }

    /**
     * Magic method to delegate calls to the underlying query builder.
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
