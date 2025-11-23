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

        // Use getModels() if query is ModelQueryBuilder
        // For eager loading, we need to return the collection, not just first()
        if ($this->query instanceof \Toporia\Framework\Database\ORM\ModelQueryBuilder) {
            $collection = $this->query->getModels();
            if ($collection->isEmpty()) {
                return null;
            }
            // For single parent (non-eager), return first model
            // For eager loading, the match() method will handle the collection
            return $collection->first();
        }

        $data = $this->query->first();

        if ($data === null) {
            return null;
        }

        /** @var Model $model */
        $model = new $this->relatedClass($data);

        // Set exists and sync original attributes using reflection
        // (setExists() and syncOriginal() are protected methods)
        $reflection = new \ReflectionClass($model);

        // Set exists flag
        $setExistsMethod = $reflection->getMethod('setExists');
        $setExistsMethod->setAccessible(true);
        $setExistsMethod->invoke($model, true);

        // Sync original attributes
        $syncMethod = $reflection->getMethod('syncOriginal');
        $syncMethod->setAccessible(true);
        $syncMethod->invoke($model);

        return $model;
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
}
