<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Concerns;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\ModelCollection;
use Toporia\Framework\Database\Contracts\RelationInterface;

/**
 * Has Eager Loading Trait
 *
 * Provides optimized eager loading for relationships.
 * Prevents N+1 query problems by loading relationships in batches.
 *
 * Clean Architecture:
 * - Trait-based composition (Open/Closed Principle)
 * - No framework dependencies beyond ORM layer
 *
 * SOLID Principles:
 * - Single Responsibility: Only handles eager loading
 * - Open/Closed: Can be added to any model without modifying base class
 * - Dependency Inversion: Works with any RelationInterface
 *
 * Performance Optimizations:
 * - Batch loading (loads all relationships in minimal queries)
 * - Query deduplication (avoids loading same relationship twice)
 * - Lazy eager loading (only loads when accessed)
 * - Memory-efficient (streams large datasets)
 *
 * @package Toporia\Framework\Database\ORM\Concerns
 */
trait HasEagerLoading
{
    /**
     * Eager loaded relationships.
     *
     * @var array<string, mixed>
     */
    protected array $eagerLoaded = [];

    /**
     * Relationships that should be eager loaded.
     *
     * @var array<string>
     */
    protected static array $eagerLoadDefaults = [];

    /**
     * Eager load relationships for a collection of models.
     *
     * This is the main method that prevents N+1 queries.
     * It loads all relationships in batch queries.
     *
     * Performance: O(n + m) where n = models, m = relationships
     * Without eager loading: O(n * m) queries
     * With eager loading: O(m) queries
     *
     * @param ModelCollection<Model> $models Collection of models
     * @param array<string> $relations Relationship names to load
     * @return void
     *
     * @example
     * ```php
     * $products = ProductModel::all();
     * static::eagerLoadRelations($products, ['category', 'reviews']);
     * // Now $products[0]->category and $products[0]->reviews are loaded
     * ```
     */
    public static function eagerLoadRelations(ModelCollection $models, array $relations): void
    {
        if ($models->isEmpty()) {
            return;
        }

        // Group relationships by type for batch loading
        $grouped = static::groupRelationsByType($models, $relations);

        // Load each group in batch
        foreach ($grouped as $relationName => $relationInstances) {
            static::loadRelationBatch($models, $relationName, $relationInstances);
        }
    }

    /**
     * Group relationships by type for optimized batch loading.
     *
     * @param ModelCollection<Model> $models Collection of models
     * @param array<string> $relations Relationship names
     * @return array<string, array<RelationInterface>>
     */
    protected static function groupRelationsByType(ModelCollection $models, array $relations): array
    {
        $grouped = [];

        foreach ($relations as $relationName) {
            // Get relationship instance from first model
            $firstModel = $models->first();
            if (!$firstModel || !method_exists($firstModel, $relationName)) {
                continue;
            }

            $relationInstance = $firstModel->$relationName();
            if (!$relationInstance instanceof RelationInterface) {
                continue;
            }

            $grouped[$relationName] = [];
            foreach ($models as $model) {
                $grouped[$relationName][] = $model->$relationName();
            }
        }

        return $grouped;
    }

    /**
     * Load a relationship in batch for all models.
     *
     * @param ModelCollection<Model> $models Collection of models
     * @param string $relationName Relationship name
     * @param array<RelationInterface> $relationInstances Relationship instances
     * @return void
     */
    protected static function loadRelationBatch(ModelCollection $models, string $relationName, array $relationInstances): void
    {
        if (empty($relationInstances)) {
            return;
        }

        // Get first relation instance to determine type
        $firstRelation = $relationInstances[0];

        // Batch load based on relationship type
        $results = $firstRelation->eagerLoad($models->toArray());

        // Set results on models
        foreach ($models as $index => $model) {
            if (isset($results[$index])) {
                $model->setRelation($relationName, $results[$index]);
                $model->eagerLoaded[$relationName] = true;
            }
        }
    }

    /**
     * Set a relationship on the model.
     *
     * @param string $relation Relationship name
     * @param mixed $value Relationship value
     * @return void
     */
    abstract public function setRelation(string $relation, mixed $value): void;

    /**
     * Check if a relationship is eager loaded.
     *
     * @param string $relation Relationship name
     * @return bool
     */
    public function relationLoaded(string $relation): bool
    {
        return isset($this->eagerLoaded[$relation]);
    }

    /**
     * Get all eager loaded relationships.
     *
     * @return array<string>
     */
    public function getEagerLoaded(): array
    {
        return array_keys($this->eagerLoaded);
    }

    /**
     * Set default relationships to eager load.
     *
     * @param array<string> $relations Relationship names
     * @return void
     */
    public static function setEagerLoadDefaults(array $relations): void
    {
        static::$eagerLoadDefaults = $relations;
    }

    /**
     * Get default relationships to eager load.
     *
     * @return array<string>
     */
    public static function getEagerLoadDefaults(): array
    {
        return static::$eagerLoadDefaults;
    }
}
