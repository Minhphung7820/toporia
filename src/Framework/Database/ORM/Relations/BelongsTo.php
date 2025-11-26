<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\{Model, ModelCollection};


/**
 * Class BelongsTo
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
class BelongsTo extends Relation
{
    /**
     * @var bool Whether constraints have been applied
     */
    private bool $constraintsApplied = false;

    public function __construct(
        \Toporia\Framework\Database\Query\QueryBuilder $query,
        Model $parent,
        protected string $relatedClass,
        string $foreignKey,
        string $ownerKey
    ) {
        parent::__construct($query, $parent, $foreignKey, $ownerKey);
        if ($this->addConstraints()->constraintsApplied) {
            $this->constraintsApplied = true;
        }
    }

    /**
     * Add constraints for belongs to relationship.
     *
     * @return $this
     */
    public function addConstraints(): static
    {
        if ($this->parent->exists()) {
            $foreignKeyValue = $this->parent->getAttribute($this->foreignKey);

            if ($foreignKeyValue !== null) {
                // For BelongsTo, we query owner table WHERE owner_key = parent's foreign_key
                $this->query->where($this->localKey, $foreignKeyValue);
                $this->constraintsApplied = true;
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return Model|null
     */
    public function getResults(): ?Model
    {
        // Check if parent exists and has foreign key value
        if (!$this->parent->exists()) {
            return null;
        }

        $foreignKeyValue = $this->parent->getAttribute($this->foreignKey);
        if ($foreignKeyValue === null) {
            return null;
        }

        // Re-apply constraints if they weren't applied in constructor
        // or if parent didn't exist at construction time
        if (!$this->constraintsApplied) {
            $this->addConstraints();
        }

        // Since $this->query is a ModelQueryBuilder, first() returns Model|null
        return $this->query->first();
    }

    /**
     * {@inheritdoc}
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = [];
        foreach ($models as $model) {
            $key = $model->getAttribute($this->foreignKey);
            if ($key !== null) {
                $keys[] = $key;
            }
        }

        if (!empty($keys)) {
            // Query owner table WHERE owner_key IN (foreign_key_values)
            $this->query->whereIn($this->localKey, array_unique($keys));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $models, mixed $results, string $relationName): array
    {
        if (!$results instanceof ModelCollection) {
            return $models;
        }

        // Build dictionary: owner_key => model
        $dictionary = [];
        foreach ($results as $result) {
            $key = $result->getAttribute($this->localKey);
            $dictionary[$key] = $result;
        }

        // Match to children
        foreach ($models as $model) {
            $foreignValue = $model->getAttribute($this->foreignKey);
            $model->setRelation($relationName, $dictionary[$foreignValue] ?? null);
        }

        return $models;
    }

    /**
     * {@inheritdoc}
     *
     * For BelongsTo, we need to ensure the owner key (localKey) is selected
     * on the related model, not the foreign key (which is on the parent).
     */
    public function getForeignKeyName(): string
    {
        return $this->localKey;
    }

    /**
     * {@inheritdoc}
     *
     * Override to handle BelongsTo's constructor which has relatedClass parameter.
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

        // BelongsTo constructor calls addConstraints() which adds parent WHERE clause
        // We need to reset the query to remove parent-specific constraints
        // Only eager constraints (WHERE IN) should be added later via addEagerConstraints()
        $cleanQuery = $freshQuery->newQuery();

        // Use setter method instead of reflection (cleaner & faster)
        $instance->setQuery($cleanQuery);

        return $instance;
    }

    /**
     * Associate the parent model with the given model.
     *
     * Performance: O(1) - Single attribute assignment and save
     * Clean Architecture: Expressive association method
     * SOLID: Single Responsibility - Manages association only
     *
     * @param Model|int|string $model Model instance or ID to associate
     * @return Model The parent model
     *
     * @example
     * ```php
     * $post->author()->associate($user);
     * $post->save(); // Don't forget to save the parent
     * ```
     */
    public function associate(Model|int|string $model): Model
    {
        if ($model instanceof Model) {
            $ownerKey = $model->getAttribute($this->localKey);
        } else {
            $ownerKey = $model;
        }

        $this->parent->setAttribute($this->foreignKey, $ownerKey);

        if ($model instanceof Model) {
            $this->parent->setRelation($this->getRelationName(), $model);
        }

        return $this->parent;
    }

    /**
     * Dissociate the parent model from its related model.
     *
     * Performance: O(1) - Single attribute assignment
     * Clean Architecture: Expressive dissociation method
     *
     * @return Model The parent model
     *
     * @example
     * ```php
     * $post->author()->dissociate();
     * $post->save(); // Don't forget to save the parent
     * ```
     */
    public function dissociate(): Model
    {
        $this->parent->setAttribute($this->foreignKey, null);
        $this->parent->setRelation($this->getRelationName(), null);

        return $this->parent;
    }

    /**
     * Create a new related model and associate it.
     *
     * Performance: O(1) - Single INSERT and attribute assignment
     * Clean Architecture: Atomic create-and-associate operation
     *
     * @param array $attributes Model attributes
     * @return Model Created and associated model
     *
     * @example
     * ```php
     * $author = $post->author()->create(['name' => 'John Doe']);
     * // Post is automatically associated with the new author
     * ```
     */
    public function create(array $attributes = []): Model
    {
        $instance = call_user_func([$this->relatedClass, 'create'], $attributes);
        $this->associate($instance);
        $this->parent->save();

        return $instance;
    }

    /**
     * Save a related model and associate it.
     *
     * Performance: O(1) - Single UPDATE and attribute assignment
     * Clean Architecture: Atomic save-and-associate operation
     *
     * @param Model $model Model to save and associate
     * @return Model Saved and associated model
     *
     * @example
     * ```php
     * $author = new User(['name' => 'Jane Doe']);
     * $post->author()->save($author);
     * ```
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
     *
     * Performance: O(1) - Single UPDATE operation
     * Clean Architecture: Expressive update method
     *
     * @param array $attributes Attributes to update
     * @return int Number of affected rows
     *
     * @example
     * ```php
     * $post->author()->update(['name' => 'Updated Name']);
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
     * $post->author()->delete();
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
     * $author = $post->author()->firstOrCreate(['name' => 'Default Author']);
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
     * $author = $post->author()->firstOrNew(['name' => 'Default Author']);
     * ```
     */
    public function firstOrNew(array $attributes = []): Model
    {
        $instance = $this->getResults();

        if ($instance === null) {
            $instance = new $this->relatedClass($attributes);
        }

        return $instance;
    }

    /**
     * Update or create a related model and associate it.
     *
     * Performance: O(1) - Single SELECT, potential INSERT/UPDATE
     * Clean Architecture: Atomic upsert-and-associate operation
     *
     * @param array $attributes Attributes to search by
     * @param array $values Additional values for creation
     * @return Model Updated or created model
     *
     * @example
     * ```php
     * $author = $post->author()->updateOrCreate(
     *     ['email' => 'john@example.com'],
     *     ['name' => 'John Doe']
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
     * Performance: O(1) - Single EXISTS query or foreign key check
     * Clean Architecture: Expressive existence check
     *
     * @return bool True if related model exists
     *
     * @example
     * ```php
     * if ($post->author()->exists()) {
     *     // Post has an author
     * }
     * ```
     */
    public function exists(): bool
    {
        // Quick check: if foreign key is null, no relation exists
        $foreignKeyValue = $this->parent->getAttribute($this->foreignKey);
        if ($foreignKeyValue === null) {
            return false;
        }

        if (!$this->parent->exists()) {
            return false;
        }

        return $this->query->exists();
    }

    /**
     * Get the count of related models (always 0 or 1 for BelongsTo).
     *
     * Performance: O(1) - Single COUNT query or foreign key check
     * Clean Architecture: Consistent interface with other relations
     *
     * @return int Count of related models
     *
     * @example
     * ```php
     * $count = $post->author()->count(); // 0 or 1
     * ```
     */
    public function count(): int
    {
        // Quick check: if foreign key is null, no relation exists
        $foreignKeyValue = $this->parent->getAttribute($this->foreignKey);
        if ($foreignKeyValue === null) {
            return 0;
        }

        if (!$this->parent->exists()) {
            return 0;
        }

        return $this->query->count();
    }

    /**
     * Get the foreign key value from the parent model.
     *
     * Performance: O(1) - Direct attribute access
     * Clean Architecture: Expressive getter method
     *
     * @return mixed Foreign key value
     *
     * @example
     * ```php
     * $authorId = $post->author()->getForeignKeyValue();
     * ```
     */
    public function getForeignKeyValue(): mixed
    {
        return $this->parent->getAttribute($this->foreignKey);
    }

    /**
     * Check if the parent is associated with a specific model.
     *
     * Performance: O(1) - Direct attribute comparison
     * Clean Architecture: Expressive association check
     *
     * @param Model|int|string $model Model instance or ID to check
     * @return bool True if associated
     *
     * @example
     * ```php
     * if ($post->author()->is($user)) {
     *     // Post belongs to this user
     * }
     * ```
     */
    public function is(Model|int|string $model): bool
    {
        $foreignKeyValue = $this->getForeignKeyValue();

        if ($foreignKeyValue === null) {
            return false;
        }

        if ($model instanceof Model) {
            $compareValue = $model->getAttribute($this->localKey);
        } else {
            $compareValue = $model;
        }

        return $foreignKeyValue === $compareValue;
    }

    /**
     * Check if the parent is not associated with a specific model.
     *
     * Performance: O(1) - Direct attribute comparison
     * Clean Architecture: Expressive negative association check
     *
     * @param Model|int|string $model Model instance or ID to check
     * @return bool True if not associated
     *
     * @example
     * ```php
     * if ($post->author()->isNot($user)) {
     *     // Post does not belong to this user
     * }
     * ```
     */
    public function isNot(Model|int|string $model): bool
    {
        return !$this->is($model);
    }

    /**
     * Get the relation name (used for setting relations).
     *
     * Performance: O(1) - String manipulation
     * Clean Architecture: Helper method for relation management
     *
     * @return string Relation name
     */
    protected function getRelationName(): string
    {
        // Extract relation name from backtrace
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        foreach ($trace as $frame) {
            if (
                isset($frame['function']) && $frame['function'] !== '__call' &&
                isset($frame['class']) && is_subclass_of($frame['class'], Model::class)
            ) {
                return $frame['function'];
            }
        }

        // Fallback: use class name
        $parts = explode('\\', $this->relatedClass);
        $className = end($parts);
        return strtolower($className);
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
