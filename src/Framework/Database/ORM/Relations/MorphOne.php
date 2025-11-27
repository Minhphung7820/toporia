<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\{Model, ModelCollection};
use Toporia\Framework\Database\Query\QueryBuilder;

/**
 * MorphOne Relationship
 *
 * Handles polymorphic one-to-one relationships.
 * Example: Post/Video morphOne Image
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.1.0
 * @package     toporia/framework
 * @subpackage  Relations
 * @since       2025-01-10
 */
class MorphOne extends Relation
{
    /** @var string Morph type column name */
    protected string $morphType;

    /**
     * @param QueryBuilder $query Query builder for related model
     * @param Model $parent Parent model instance (Post or Video)
     * @param class-string<Model> $relatedClass Related model class (Image)
     * @param string $morphName Morph name ('imageable')
     * @param string|null $morphType Type column (imageable_type)
     * @param string|null $morphId ID column (imageable_id)
     * @param string|null $localKey Local key on parent (id)
     */
    public function __construct(
        QueryBuilder $query,
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

    // =========================================================================
    // INTERNAL HELPERS
    // =========================================================================

    /**
     * Get parent key or throw exception.
     *
     * @throws \RuntimeException If parent key is null
     */
    protected function getParentKeyOrFail(): mixed
    {
        $parentKey = $this->parent->getAttribute($this->localKey);

        if ($parentKey === null) {
            throw new \RuntimeException('Cannot perform operation: parent model does not have a key');
        }

        return $parentKey;
    }

    /**
     * Build dictionary for eager loading matching.
     *
     * @return array<string, Model>
     */
    protected function buildDictionary(ModelCollection $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $type = $result->getAttribute($this->morphType);
            $id = $result->getAttribute($this->foreignKey);
            $key = "{$type}:{$id}";
            $dictionary[$key] = $result;
        }

        return $dictionary;
    }

    // =========================================================================
    // CORE RELATION METHODS
    // =========================================================================

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
     */
    public function getResults(): ?Model
    {
        if (!$this->parent->exists()) {
            return null;
        }

        $this->query->where($this->morphType, $this->getMorphClass());
        $this->query->where($this->foreignKey, $this->parent->getAttribute($this->localKey));

        return $this->query->first();
    }

    /**
     * {@inheritdoc}
     */
    public function addEagerConstraints(array $models): void
    {
        $types = [];
        foreach ($models as $model) {
            $type = get_class($model);
            $types[$type][] = $model->getAttribute($this->localKey);
        }

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

        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $type = get_class($model);
            $id = $model->getAttribute($this->localKey);
            $key = "{$type}:{$id}";

            $model->setRelation($relationName, $dictionary[$key] ?? null);
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
     * {@inheritdoc}
     */
    public function newEagerInstance(QueryBuilder $freshQuery): static
    {
        $instance = new static(
            $freshQuery,
            $this->parent,
            $this->relatedClass,
            $this->morphName,
            $this->morphType,
            $this->foreignKey,
            $this->localKey
        );

        $instance->setQuery($freshQuery->newQuery());

        return $instance;
    }

    // =========================================================================
    // CRUD OPERATIONS
    // =========================================================================

    /**
     * Create a new related model.
     */
    public function create(array $attributes = []): Model
    {
        $attributes[$this->foreignKey] = $this->getParentKeyOrFail();
        $attributes[$this->morphType] = $this->getMorphClass();

        return $this->relatedClass::create($attributes);
    }

    /**
     * Save a related model.
     */
    public function save(Model $model): Model
    {
        $model->setAttribute($this->foreignKey, $this->getParentKeyOrFail());
        $model->setAttribute($this->morphType, $this->getMorphClass());
        $model->save();

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
     * Get the first related model or instantiate a new one.
     */
    public function firstOrNew(array $attributes = []): Model
    {
        $instance = $this->getResults();

        if ($instance !== null) {
            return $instance;
        }

        $attributes[$this->foreignKey] = $this->parent->getAttribute($this->localKey);
        $attributes[$this->morphType] = $this->getMorphClass();

        return new $this->relatedClass($attributes);
    }

    /**
     * Update or create a related model.
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
        return $this->parent->exists() && $this->query->exists();
    }

    /**
     * Get the count of related models (always 0 or 1 for MorphOne).
     */
    public function count(): int
    {
        return $this->parent->exists() ? $this->query->count() : 0;
    }

    // =========================================================================
    // GETTER METHODS
    // =========================================================================

    /**
     * Get the morph type column name.
     */
    public function getMorphType(): string
    {
        return $this->morphType;
    }

    /**
     * Get the morph class value.
     */
    public function getMorphClassValue(): string
    {
        return $this->getMorphClass();
    }

    /**
     * Check if the parent is of a specific morph type.
     */
    public function isType(string $type): bool
    {
        return $this->getMorphClass() === $type;
    }

    /**
     * Get the related model class name.
     *
     * @return class-string<Model>
     */
    public function getRelatedClass(): string
    {
        return $this->relatedClass;
    }

    // =========================================================================
    // MAGIC METHODS
    // =========================================================================

    /**
     * Magic method to delegate calls to the underlying query builder.
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (method_exists($this->query, $method)) {
            $result = $this->query->{$method}(...$parameters);

            if ($result instanceof QueryBuilder) {
                return $this;
            }

            return $result;
        }

        throw new \BadMethodCallException(
            sprintf('Method %s::%s does not exist.', static::class, $method)
        );
    }
}
