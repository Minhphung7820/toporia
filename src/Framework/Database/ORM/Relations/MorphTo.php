<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\{Model, ModelCollection};


/**
 * Class MorphTo
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
class MorphTo extends Relation
{
    protected array $morphMap = [];

    /**
     * @param \Toporia\Framework\Database\Query\QueryBuilder $query Query builder (will be replaced dynamically)
     * @param Model $parent Child model instance (Comment)
     * @param string $morphName Morph name ('commentable')
     * @param string|null $morphType Type column (commentable_type)
     * @param string|null $morphId ID column (commentable_id)
     * @param string|null $ownerKey Owner key on parent models (id)
     */
    public function __construct(
        \Toporia\Framework\Database\Query\QueryBuilder $query,
        Model $parent,
        protected string $morphName,
        ?string $morphType = null,
        ?string $morphId = null,
        ?string $ownerKey = null
    ) {
        $this->morphType = $morphType ?? "{$morphName}_type";
        $this->foreignKey = $morphId ?? "{$morphName}_id";
        $this->localKey = $ownerKey ?? 'id';

        parent::__construct($query, $parent, $this->foreignKey, $this->localKey);
    }

    /**
     * Set morph map for type resolution.
     *
     * Maps type strings to model classes:
     * ['Post' => PostModel::class, 'Video' => VideoModel::class]
     *
     * @param array $map Type to class mapping
     * @return self
     */
    public function setMorphMap(array $map): self
    {
        $this->morphMap = $map;
        return $this;
    }

    /**
     * Get the related model class from type.
     *
     * @param string $type Morph type (Post, Video, etc.)
     * @return class-string<Model>
     */
    protected function getModelClass(string $type): string
    {
        // Check morph map first
        if (isset($this->morphMap[$type])) {
            return $this->morphMap[$type];
        }

        // Fallback: Assume type is class name
        // This allows flexibility for simple use cases
        return $type;
    }

    /**
     * {@inheritdoc}
     *
     * @return Model|null
     */
    public function getResults(): mixed
    {
        // Get type and ID from parent
        // Use getAttribute() to get the value, but also check raw attributes
        $type = $this->parent->getAttribute($this->morphType);
        $id = $this->parent->getAttribute($this->foreignKey);

        // If attributes are not set, try to get from raw attributes
        if (!$type) {
            $reflection = reflection()->getClass($this->parent);
            $property = $reflection->getProperty('attributes');
            $property->setAccessible(true);
            $attributes = $property->getValue($this->parent);
            $type = $attributes[$this->morphType] ?? null;
        }

        if (!$id) {
            $reflection = reflection()->getClass($this->parent);
            $property = $reflection->getProperty('attributes');
            $property->setAccessible(true);
            $attributes = $property->getValue($this->parent);
            $id = $attributes[$this->foreignKey] ?? null;
        }

        if (!$type || !$id) {
            return null;
        }

        // Get model class - type should be full class name
        $modelClass = $this->getModelClass($type);

        // Ensure class exists
        if (!class_exists($modelClass)) {
            return null;
        }

        // Query the related model using ModelQueryBuilder
        $collection = $modelClass::query()->where($this->localKey, $id)->getModels();

        if ($collection->isEmpty()) {
            return null;
        }

        return $collection->first();
    }

    /**
     * {@inheritdoc}
     *
     * Eager loading optimization:
     * Groups by type and loads in batches.
     *
     * Example: 80 comments (50 on Posts, 30 on Videos)
     * - Query 1: SELECT * FROM posts WHERE id IN (1,2,3,...,50)
     * - Query 2: SELECT * FROM videos WHERE id IN (51,52,...,80)
     * Total: 2 queries instead of 80!
     */
    public function addEagerConstraints(array $models): void
    {
        // MorphTo doesn't use standard eager constraints
        // It groups by type and loads separately
        // Implementation is in match() method
    }

    /**
     * {@inheritdoc}
     *
     * Custom matching logic for morphTo:
     * 1. Group child models by type
     * 2. Load related models for each type (1 query per type)
     * 3. Match loaded models to children
     */
    public function match(array $models, mixed $results, string $relationName): array
    {
        // Group models by type and collect IDs
        $groups = [];
        foreach ($models as $model) {
            $type = $model->getAttribute($this->morphType);
            $id = $model->getAttribute($this->foreignKey);

            if (!$type || !$id) {
                continue;
            }

            if (!isset($groups[$type])) {
                $groups[$type] = [];
            }
            $groups[$type][] = $id;
        }

        // Load related models for each type
        $relatedModels = [];
        foreach ($groups as $type => $ids) {
            $modelClass = $this->getModelClass($type);

            // Load all related models of this type
            // SELECT * FROM posts WHERE id IN (1,2,3,...)
            $related = $modelClass::whereIn('id', array_unique($ids))->get();

            // Build dictionary: id => model
            foreach ($related as $model) {
                $key = "{$type}:{$model->getKey()}";
                $relatedModels[$key] = $model;
            }
        }

        // Match to children
        foreach ($models as $model) {
            $type = $model->getAttribute($this->morphType);
            $id = $model->getAttribute($this->foreignKey);

            if (!$type || !$id) {
                $model->setRelation($relationName, null);
                continue;
            }

            $key = "{$type}:{$id}";
            $related = $relatedModels[$key] ?? null;
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
     * Associate the parent model with the given model.
     *
     * Performance: O(1) - Direct attribute assignment
     * Clean Architecture: Expressive association method
     * SOLID: Single Responsibility - Manages association only
     *
     * @param Model|null $model Model instance to associate (null to dissociate)
     * @return Model The parent model
     *
     * @example
     * ```php
     * $comment->commentable()->associate($post);
     * $comment->save(); // Don't forget to save the parent
     * ```
     */
    public function associate(?Model $model): Model
    {
        if ($model === null) {
            return $this->dissociate();
        }

        $this->parent->setAttribute($this->foreignKey, $model->getKey());
        $this->parent->setAttribute($this->morphType, get_class($model));
        $this->parent->setRelation($this->getRelationName(), $model);

        return $this->parent;
    }

    /**
     * Dissociate the parent model from its related model.
     *
     * Performance: O(1) - Direct attribute assignment
     * Clean Architecture: Expressive dissociation method
     *
     * @return Model The parent model
     *
     * @example
     * ```php
     * $comment->commentable()->dissociate();
     * $comment->save(); // Don't forget to save the parent
     * ```
     */
    public function dissociate(): Model
    {
        $this->parent->setAttribute($this->foreignKey, null);
        $this->parent->setAttribute($this->morphType, null);
        $this->parent->setRelation($this->getRelationName(), null);

        return $this->parent;
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
     * $comment->commentable()->update(['title' => 'Updated Title']);
     * ```
     */
    public function update(array $attributes): int
    {
        $related = $this->getResults();

        if ($related === null) {
            return 0;
        }

        $related->fill($attributes);
        return $related->save() ? 1 : 0;
    }

    /**
     * Delete the related model.
     *
     * Performance: O(1) - Single DELETE operation
     * Clean Architecture: Expressive deletion method
     *
     * @return bool True if deleted successfully
     *
     * @example
     * ```php
     * $comment->commentable()->delete();
     * ```
     */
    public function delete(): bool
    {
        $related = $this->getResults();

        if ($related === null) {
            return false;
        }

        return $related->delete();
    }

    /**
     * Check if the related model exists.
     *
     * Performance: O(1) - Quick attribute check or single EXISTS query
     * Clean Architecture: Expressive existence check
     *
     * @return bool True if related model exists
     *
     * @example
     * ```php
     * if ($comment->commentable()->exists()) {
     *     // Comment has a related model
     * }
     * ```
     */
    public function exists(): bool
    {
        $type = $this->parent->getAttribute($this->morphType);
        $id = $this->parent->getAttribute($this->foreignKey);

        if (!$type || !$id) {
            return false;
        }

        $modelClass = $this->getModelClass($type);

        if (!class_exists($modelClass)) {
            return false;
        }

        return $modelClass::where($this->localKey, $id)->exists();
    }

    /**
     * Get the count of related models (always 0 or 1 for MorphTo).
     *
     * Performance: O(1) - Quick attribute check or single COUNT query
     * Clean Architecture: Consistent interface with other relations
     *
     * @return int Count of related models
     *
     * @example
     * ```php
     * $count = $comment->commentable()->count(); // 0 or 1
     * ```
     */
    public function count(): int
    {
        return $this->exists() ? 1 : 0;
    }

    /**
     * Get the morph type value from the parent model.
     *
     * Performance: O(1) - Direct attribute access
     * Clean Architecture: Expressive getter method
     *
     * @return string|null Morph type value
     *
     * @example
     * ```php
     * $type = $comment->commentable()->getMorphType(); // 'App\Models\Post'
     * ```
     */
    public function getMorphTypeValue(): ?string
    {
        return $this->parent->getAttribute($this->morphType);
    }

    /**
     * Get the morph ID value from the parent model.
     *
     * Performance: O(1) - Direct attribute access
     * Clean Architecture: Expressive getter method
     *
     * @return mixed Morph ID value
     *
     * @example
     * ```php
     * $id = $comment->commentable()->getMorphId(); // 123
     * ```
     */
    public function getMorphId(): mixed
    {
        return $this->parent->getAttribute($this->foreignKey);
    }

    /**
     * Check if the parent is associated with a specific model type.
     *
     * Performance: O(1) - Direct string comparison
     * Clean Architecture: Expressive type checking
     *
     * @param string $type Morph type to check
     * @return bool True if parent is associated with the specified type
     *
     * @example
     * ```php
     * if ($comment->commentable()->isType('App\Models\Post')) {
     *     // Comment belongs to a Post
     * }
     * ```
     */
    public function isType(string $type): bool
    {
        $currentType = $this->getMorphTypeValue();

        if (!$currentType) {
            return false;
        }

        // Check direct match or morph map match
        return $currentType === $type ||
            (isset($this->morphMap[$type]) && $this->morphMap[$type] === $currentType);
    }

    /**
     * Check if the parent is associated with a specific model instance.
     *
     * Performance: O(1) - Direct attribute comparison
     * Clean Architecture: Expressive association check
     *
     * @param Model $model Model instance to check
     * @return bool True if associated with the given model
     *
     * @example
     * ```php
     * if ($comment->commentable()->is($post)) {
     *     // Comment belongs to this specific post
     * }
     * ```
     */
    public function is(Model $model): bool
    {
        $type = $this->getMorphTypeValue();
        $id = $this->getMorphId();

        if (!$type || !$id) {
            return false;
        }

        return get_class($model) === $type && $model->getKey() === $id;
    }

    /**
     * Check if the parent is not associated with a specific model instance.
     *
     * Performance: O(1) - Direct attribute comparison
     * Clean Architecture: Expressive negative association check
     *
     * @param Model $model Model instance to check
     * @return bool True if not associated with the given model
     *
     * @example
     * ```php
     * if ($comment->commentable()->isNot($post)) {
     *     // Comment does not belong to this specific post
     * }
     * ```
     */
    public function isNot(Model $model): bool
    {
        return !$this->is($model);
    }

    /**
     * Get all possible morph types from the morph map.
     *
     * Performance: O(1) - Array key access
     * Clean Architecture: Expressive getter for available types
     *
     * @return array Array of available morph types
     *
     * @example
     * ```php
     * $types = $comment->commentable()->getAvailableTypes();
     * // ['post', 'video', 'article']
     * ```
     */
    public function getAvailableTypes(): array
    {
        return array_keys($this->morphMap);
    }

    /**
     * Get the morph map.
     *
     * Performance: O(1) - Direct property access
     * Clean Architecture: Expressive getter method
     *
     * @return array Morph map array
     *
     * @example
     * ```php
     * $map = $comment->commentable()->getMorphMap();
     * ```
     */
    public function getMorphMap(): array
    {
        return $this->morphMap;
    }

    /**
     * Create a new model of the specified type and associate it.
     *
     * Performance: O(1) - Single INSERT and attribute assignment
     * Clean Architecture: Atomic create-and-associate operation
     *
     * @param string $type Morph type or class name
     * @param array $attributes Model attributes
     * @return Model Created and associated model
     *
     * @example
     * ```php
     * $post = $comment->commentable()->createOfType('post', ['title' => 'New Post']);
     * ```
     */
    public function createOfType(string $type, array $attributes = []): Model
    {
        $modelClass = $this->getModelClass($type);

        if (!class_exists($modelClass)) {
            throw new \InvalidArgumentException("Model class {$modelClass} does not exist");
        }

        $instance = call_user_func([$modelClass, 'create'], $attributes);
        $this->associate($instance);
        $this->parent->save();

        return $instance;
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

        // Fallback: use morph name
        return $this->morphName;
    }

    /**
     * Load the related model with constraints.
     *
     * Performance: O(1) - Single SELECT with WHERE constraints
     * Clean Architecture: Flexible loading with additional constraints
     *
     * @param array $constraints Additional WHERE constraints
     * @return Model|null
     *
     * @example
     * ```php
     * $post = $comment->commentable()->loadWith(['status' => 'published']);
     * ```
     */
    public function loadWith(array $constraints = []): ?Model
    {
        $type = $this->parent->getAttribute($this->morphType);
        $id = $this->parent->getAttribute($this->foreignKey);

        if (!$type || !$id) {
            return null;
        }

        $modelClass = $this->getModelClass($type);

        if (!class_exists($modelClass)) {
            return null;
        }

        $query = $modelClass::where($this->localKey, $id);

        // Apply additional constraints
        foreach ($constraints as $column => $value) {
            $query->where($column, $value);
        }

        return $query->first();
    }

    /**
     * Magic method to delegate calls to the related model if it exists.
     *
     * Performance: O(1) - Direct method delegation after model loading
     * Clean Architecture: Proxy pattern for related model methods
     *
     * @param string $method Method name
     * @param array $parameters Method parameters
     * @return mixed
     *
     * @throws \BadMethodCallException If method doesn't exist on related model
     */
    public function __call(string $method, array $parameters): mixed
    {
        $related = $this->getResults();

        if ($related === null) {
            throw new \BadMethodCallException(
                sprintf('No related model found for MorphTo relation %s', $this->morphName)
            );
        }

        if (method_exists($related, $method)) {
            return $related->{$method}(...$parameters);
        }

        throw new \BadMethodCallException(
            sprintf('Method %s does not exist on related model %s', $method, get_class($related))
        );
    }
}
