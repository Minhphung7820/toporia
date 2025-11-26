<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\{Model, ModelCollection};


/**
 * Class MorphOne
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
class MorphOne extends Relation
{
    /**
     * @param \Toporia\Framework\Database\Query\QueryBuilder $query Query builder
     * @param Model $parent Parent model instance (Post or Video)
     * @param class-string<Model> $relatedClass Related model class (Image)
     * @param string $morphName Morph name ('imageable')
     * @param string|null $morphType Type column (imageable_type)
     * @param string|null $morphId ID column (imageable_id)
     * @param string|null $localKey Local key on parent (id)
     */
    public function __construct(
        \Toporia\Framework\Database\Query\QueryBuilder $query,
        Model $parent,
        protected string $relatedClass,
        protected string $morphName,
        ?string $morphType = null,
        ?string $morphId = null,
        ?string $localKey = null
    ) {
        $this->morphType = $morphType ?? "{$morphName}_type";
        $this->foreignKey = $morphId ?? "{$morphName}_id";
        $this->localKey = $localKey ?? $parent::getPrimaryKey();

        parent::__construct($query, $parent, $this->foreignKey, $this->localKey);

        $this->addConstraints();
    }

    /**
     * Add constraints for morph relationship.
     *
     * @return static
     */
    public function addConstraints(): static
    {
        if ($this->parent->exists()) {
            // WHERE imageable_type = 'Post'
            $this->query->where($this->morphType, $this->getMorphClass());

            // AND imageable_id = ?
            $this->query->where(
                $this->foreignKey,
                $this->parent->getAttribute($this->localKey)
            );
        }
        return $this;
    }

    /**
     * Get morph class name for parent.
     *
     * @return string
     */
    protected function getMorphClass(): string
    {
        // Use full class name to match database storage
        // Can be customized via getMorphClass() method on model
        return get_class($this->parent);
    }

    /**
     * {@inheritdoc}
     *
     * @return Model|null
     */
    public function getResults(): mixed
    {
        // Ensure constraints are applied if parent exists now but didn't at construction
        if ($this->parent->exists()) {
            // Use the query builder to get model directly
            // Since $this->query is a ModelQueryBuilder, first() returns Model|null
            $this->query->where($this->morphType, $this->getMorphClass());
            $this->query->where($this->foreignKey, $this->parent->getAttribute($this->localKey));

            return $this->query->first();
        }

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * Eager loading with closure-based WHERE for clean, efficient SQL:
     * WHERE (type = 'Post' AND id IN (?,...)) OR (type = 'Video' AND id IN (?,...))
     *
     * Performance: O(N) where N = number of distinct types (typically 2-3)
     */
    public function addEagerConstraints(array $models): void
    {
        // Group models by type (full class name)
        $types = [];
        foreach ($models as $model) {
            $type = get_class($model);

            if (!isset($types[$type])) {
                $types[$type] = [];
            }
            $types[$type][] = $model->getAttribute($this->localKey);
        }

        // Build nested WHERE with closures
        // WHERE (type='Post' AND id IN (...)) OR (type='Video' AND id IN (...))
        $this->query->where(function ($q) use ($types) {
            $first = true;
            foreach ($types as $type => $ids) {
                if ($first) {
                    $q->where(function ($subQ) use ($type, $ids) {
                        $subQ->where($this->morphType, $type)
                            ->whereIn($this->foreignKey, $ids);
                    });
                    $first = false;
                } else {
                    $q->orWhere(function ($subQ) use ($type, $ids) {
                        $subQ->where($this->morphType, $type)
                            ->whereIn($this->foreignKey, $ids);
                    });
                }
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $models, mixed $results, string $relationName): array
    {
        if (!$results instanceof ModelCollection) {
            return $models;
        }

        // Build dictionary: type:id => related_model
        $dictionary = [];
        foreach ($results as $result) {
            $type = $result->getAttribute($this->morphType);
            $id = $result->getAttribute($this->foreignKey);
            $key = "{$type}:{$id}";
            $dictionary[$key] = $result;
        }

        // Match to parents
        foreach ($models as $model) {
            $type = get_class($model);
            $id = $model->getAttribute($this->localKey);
            $key = "{$type}:{$id}";

            $related = $dictionary[$key] ?? null;
            $model->setRelation($relationName, $related);
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
     * Store morphType for access
     */
    protected string $morphType;

    /**
     * Create a new related model.
     *
     * Performance: O(1) - Single INSERT operation
     * Clean Architecture: Factory method with automatic morph key assignment
     * SOLID: Single Responsibility - Creates and associates model
     *
     * @param array $attributes Model attributes
     * @return Model Created model instance
     *
     * @example
     * ```php
     * $image = $post->image()->create(['url' => 'image.jpg', 'alt' => 'Post image']);
     * ```
     */
    public function create(array $attributes = []): Model
    {
        $parentKey = $this->parent->getAttribute($this->localKey);

        if ($parentKey === null) {
            throw new \RuntimeException('Cannot create related model: parent model does not have a key');
        }

        // Set morph keys
        $attributes[$this->foreignKey] = $parentKey;
        $attributes[$this->morphType] = $this->getMorphClass();

        return call_user_func([$this->relatedClass, 'create'], $attributes);
    }

    /**
     * Save a related model.
     *
     * Performance: O(1) - Single UPDATE operation
     * Clean Architecture: Automatic morph key management
     *
     * @param Model $model Model to save
     * @return Model Saved model instance
     *
     * @example
     * ```php
     * $image = new Image(['url' => 'image.jpg']);
     * $post->image()->save($image);
     * ```
     */
    public function save(Model $model): Model
    {
        $parentKey = $this->parent->getAttribute($this->localKey);

        if ($parentKey === null) {
            throw new \RuntimeException('Cannot save related model: parent model does not have a key');
        }

        // Set morph keys
        $model->setAttribute($this->foreignKey, $parentKey);
        $model->setAttribute($this->morphType, $this->getMorphClass());
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
     * $post->image()->update(['alt' => 'Updated alt text']);
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
     * $post->image()->delete();
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
     * $image = $post->image()->firstOrCreate(['url' => 'default.jpg']);
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
     * $image = $post->image()->firstOrNew(['url' => 'default.jpg']);
     * ```
     */
    public function firstOrNew(array $attributes = []): Model
    {
        $instance = $this->getResults();

        if ($instance === null) {
            $parentKey = $this->parent->getAttribute($this->localKey);
            $attributes[$this->foreignKey] = $parentKey;
            $attributes[$this->morphType] = $this->getMorphClass();
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
     * $image = $post->image()->updateOrCreate(
     *     ['url' => 'image.jpg'],
     *     ['alt' => 'Updated alt text']
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
     * if ($post->image()->exists()) {
     *     // Post has an image
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
     * Get the count of related models (always 0 or 1 for MorphOne).
     *
     * Performance: O(1) - Single COUNT query
     * Clean Architecture: Consistent interface with other relations
     *
     * @return int Count of related models
     *
     * @example
     * ```php
     * $count = $post->image()->count(); // 0 or 1
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
     * Get the morph type value.
     *
     * Performance: O(1) - Direct method call
     * Clean Architecture: Expressive getter method
     *
     * @return string Morph type value
     *
     * @example
     * ```php
     * $type = $post->image()->getMorphType(); // 'App\Models\Post'
     * ```
     */
    public function getMorphType(): string
    {
        return $this->morphType;
    }

    /**
     * Get the morph class value.
     *
     * Performance: O(1) - Direct method call
     * Clean Architecture: Expressive getter method
     *
     * @return string Morph class value
     *
     * @example
     * ```php
     * $class = $post->image()->getMorphClass(); // 'App\Models\Post'
     * ```
     */
    public function getMorphClassValue(): string
    {
        return $this->getMorphClass();
    }

    /**
     * Check if the parent is of a specific morph type.
     *
     * Performance: O(1) - Direct string comparison
     * Clean Architecture: Expressive type checking
     *
     * @param string $type Morph type to check
     * @return bool True if parent is of the specified type
     *
     * @example
     * ```php
     * if ($image->imageable()->isType('App\Models\Post')) {
     *     // Image belongs to a Post
     * }
     * ```
     */
    public function isType(string $type): bool
    {
        return $this->getMorphClass() === $type;
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
