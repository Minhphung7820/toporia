<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\{Model, ModelCollection};


/**
 * Class HasOne
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
class HasOne extends Relation
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
     * @return Model|null
     */
    public function getResults(): ?Model
    {
        // Ensure constraints are applied (query might have been modified)
        // Only apply if parent exists and has local key value
        if ($this->parent->exists()) {
            $localValue = $this->parent->getAttribute($this->localKey);
            if ($localValue !== null) {
                // Re-apply the base constraint (will add as additional WHERE if not already present)
                // QueryBuilder handles multiple WHERE clauses with AND
                $this->query->where($this->foreignKey, $localValue);
            } else {
                return null;
            }
        } else {
            return null;
        }

        // Since $this->query is a ModelQueryBuilder, first() returns Model|null
        return $this->query->first();
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $models, mixed $results, string $relationName): array
    {
        if (!$results instanceof ModelCollection) {
            return $models;
        }

        // Build dictionary: foreign_key => model
        $dictionary = [];
        foreach ($results as $result) {
            $key = $result->getAttribute($this->foreignKey);
            $dictionary[$key] = $result;
        }

        // Match to parents
        foreach ($models as $model) {
            $localValue = $model->getAttribute($this->localKey);
            $model->setRelation($relationName, $dictionary[$localValue] ?? null);
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
     * Create a new related model.
     *
     * Performance: O(1) - Single INSERT operation
     * Clean Architecture: Factory method with automatic foreign key assignment
     * SOLID: Single Responsibility - Creates and associates model
     *
     * @param array $attributes Model attributes
     * @return Model Created model instance
     *
     * @example
     * ```php
     * $profile = $user->profile()->create(['bio' => 'Software Developer']);
     * ```
     */
    public function create(array $attributes = []): Model
    {
        $parentKey = $this->parent->getAttribute($this->localKey);

        if ($parentKey === null) {
            throw new \RuntimeException('Cannot create related model: parent model does not have a key');
        }

        // Set foreign key to parent's local key
        $attributes[$this->foreignKey] = $parentKey;

        return call_user_func([$this->relatedClass, 'create'], $attributes);
    }

    /**
     * Save a related model.
     *
     * Performance: O(1) - Single UPDATE operation
     * Clean Architecture: Automatic foreign key management
     *
     * @param Model $model Model to save
     * @return Model Saved model instance
     *
     * @example
     * ```php
     * $profile = new Profile(['bio' => 'Developer']);
     * $user->profile()->save($profile);
     * ```
     */
    public function save(Model $model): Model
    {
        $parentKey = $this->parent->getAttribute($this->localKey);

        if ($parentKey === null) {
            throw new \RuntimeException('Cannot save related model: parent model does not have a key');
        }

        // Set foreign key to parent's local key
        $model->setAttribute($this->foreignKey, $parentKey);
        $model->save();

        return $model;
    }

    /**
     * Update the related model.
     *
     * Performance: O(1) - Single UPDATE operation with WHERE constraint
     * Clean Architecture: Expressive update method
     *
     * @param array $attributes Attributes to update
     * @return int Number of affected rows
     *
     * @example
     * ```php
     * $user->profile()->update(['bio' => 'Senior Developer']);
     * ```
     */
    public function update(array $attributes): int
    {
        if ($this->parent->exists()) {
            return $this->query->update($attributes);
        }

        return 0;
    }

    /**
     * Delete the related model.
     *
     * Performance: O(1) - Single DELETE operation
     * Clean Architecture: Expressive deletion method
     *
     * @return int Number of deleted rows
     *
     * @example
     * ```php
     * $user->profile()->delete();
     * ```
     */
    public function delete(): int
    {
        if ($this->parent->exists()) {
            return $this->query->delete();
        }

        return 0;
    }

    /**
     * Get the first related model or create a new one.
     *
     * Performance: O(1) - Single SELECT, potential INSERT
     * Clean Architecture: Atomic find-or-create operation
     *
     * @param array $attributes Attributes for new model if not found
     * @return Model Found or created model
     *
     * @example
     * ```php
     * $profile = $user->profile()->firstOrCreate(['bio' => 'Default bio']);
     * ```
     */
    public function firstOrCreate(array $attributes = []): Model
    {
        $instance = $this->getResults();

        if ($instance === null) {
            $instance = $this->create($attributes);
        }

        return $instance;
    }

    /**
     * Get the first related model or instantiate a new one.
     *
     * Performance: O(1) - Single SELECT operation
     * Clean Architecture: Non-persistent find-or-new operation
     *
     * @param array $attributes Attributes for new model if not found
     * @return Model Found or new model instance
     *
     * @example
     * ```php
     * $profile = $user->profile()->firstOrNew(['bio' => 'Default bio']);
     * ```
     */
    public function firstOrNew(array $attributes = []): Model
    {
        $instance = $this->getResults();

        if ($instance === null) {
            $parentKey = $this->parent->getAttribute($this->localKey);
            $attributes[$this->foreignKey] = $parentKey;
            $instance = new $this->relatedClass($attributes);
        }

        return $instance;
    }

    /**
     * Update or create a related model.
     *
     * Performance: O(1) - Single SELECT, potential INSERT/UPDATE
     * Clean Architecture: Atomic upsert operation
     *
     * @param array $attributes Attributes to search by
     * @param array $values Additional values for creation
     * @return Model Updated or created model
     *
     * @example
     * ```php
     * $profile = $user->profile()->updateOrCreate(
     *     ['user_id' => $user->id],
     *     ['bio' => 'Updated bio']
     * );
     * ```
     */
    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        $instance = $this->getResults();

        if ($instance !== null) {
            $instance->fill(array_merge($attributes, $values));
            $instance->save();
        } else {
            $instance = $this->create(array_merge($attributes, $values));
        }

        return $instance;
    }

    /**
     * Check if the related model exists.
     *
     * Performance: O(1) - Single EXISTS query
     * Clean Architecture: Expressive existence check
     *
     * @return bool True if related model exists
     *
     * @example
     * ```php
     * if ($user->profile()->exists()) {
     *     // User has a profile
     * }
     * ```
     */
    public function exists(): bool
    {
        if (!$this->parent->exists()) {
            return false;
        }

        return $this->query->exists();
    }

    /**
     * Get the count of related models (always 0 or 1 for HasOne).
     *
     * Performance: O(1) - Single COUNT query
     * Clean Architecture: Consistent interface with other relations
     *
     * @return int Count of related models
     *
     * @example
     * ```php
     * $count = $user->profile()->count(); // 0 or 1
     * ```
     */
    public function count(): int
    {
        if (!$this->parent->exists()) {
            return 0;
        }

        return $this->query->count();
    }

    /**
     * Magic method to delegate calls to the underlying query builder.
     *
     * Performance: O(1) - Direct method delegation
     * Clean Architecture: Proxy pattern for query builder methods
     * SOLID: Interface Segregation - Expose only relevant query methods
     *
     * @param string $method Method name
     * @param array $parameters Method parameters
     * @return mixed
     *
     * @throws \BadMethodCallException If method doesn't exist on query builder
     */
    public function __call(string $method, array $parameters): mixed
    {
        // Delegate to query builder for standard query methods
        if (method_exists($this->query, $method)) {
            $result = $this->query->{$method}(...$parameters);

            // Return $this for fluent interface on builder methods that return QueryBuilder
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
