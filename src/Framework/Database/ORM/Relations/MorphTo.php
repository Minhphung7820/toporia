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
}
