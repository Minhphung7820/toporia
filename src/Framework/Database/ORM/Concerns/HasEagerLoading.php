<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Concerns;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\ModelCollection;
use Toporia\Framework\Database\Contracts\RelationInterface;


/**
 * Trait HasEagerLoading
 *
 * Trait providing reusable functionality for HasEagerLoading in the
 * Concerns layer of the Toporia Framework.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Concerns
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
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
     * Nested relationships to load after first level.
     *
     * @var array<string, array<string>>
     */
    protected static array $nestedRelations = [];

    /**
     * Eager load relationships for a collection of models.
     *
     * This is the main method that prevents N+1 queries.
     * It loads all relationships in batch queries.
     *
     * Supports nested relationships like 'posts.comments.author'.
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
     *
     * // Nested relationships
     * static::eagerLoadRelations($users, ['posts.comments.author']);
     * // Loads users -> posts -> comments -> author in 4 batch queries
     * ```
     */
    public static function eagerLoadRelations(ModelCollection $models, array $relations): void
    {
        if ($models->isEmpty()) {
            return;
        }

        // Clear nested relations from previous calls
        static::$nestedRelations = [];

        // Group relationships by type for batch loading
        $grouped = static::groupRelationsByType($models, $relations);

        // Load each group in batch
        foreach ($grouped as $relationName => $relationData) {
            $relationInstances = $relationData['instances'];
            $constraint = $relationData['constraint'] ?? null;
            static::loadRelationBatch($models, $relationName, $relationInstances, $constraint);
        }

        // Clear nested relations after loading
        static::$nestedRelations = [];
    }

    /**
     * Group relationships by type for optimized batch loading.
     *
     * Supports nested relationships like 'posts.comments.author'.
     * Supports eager loading constraints via closures:
     * ['posts' => function($q) { $q->where('published', true); }]
     *
     * @param ModelCollection<Model> $models Collection of models
     * @param array<string|\Closure> $relations Relationship names or ['relation' => Closure]
     * @return array<string, array{instances: array<RelationInterface>, constraint: \Closure|null}>
     */
    protected static function groupRelationsByType(ModelCollection $models, array $relations): array
    {
        $grouped = [];
        $nested = []; // Track nested relations for later processing
        $constraints = []; // Track constraints for each relation

        foreach ($relations as $key => $value) {
            // Handle format: ['relation' => Closure] or 'relation'
            $relationName = is_string($key) ? $key : $value;
            $constraint = is_string($key) && is_callable($value) ? $value : null;

            // Skip if relationName is not a string
            if (!is_string($relationName)) {
                continue;
            }

            // Handle nested relations (e.g., 'posts.comments')
            $parts = explode('.', $relationName, 2);
            $firstLevelRelation = $parts[0];

            // Track nested relations for this first-level relation
            if (isset($parts[1])) {
                if (!isset($nested[$firstLevelRelation])) {
                    $nested[$firstLevelRelation] = [];
                }
                $nested[$firstLevelRelation][] = $parts[1];
            }

            // Skip if already grouped
            if (isset($grouped[$firstLevelRelation])) {
                continue;
            }

            // Get relationship instance from first model
            $firstModel = $models->first();
            if (!$firstModel || !method_exists($firstModel, $firstLevelRelation)) {
                continue;
            }

            $relationInstance = $firstModel->$firstLevelRelation();
            if (!$relationInstance instanceof RelationInterface) {
                continue;
            }

            // Store constraint if provided
            if ($constraint !== null) {
                $constraints[$firstLevelRelation] = $constraint;
            }

            $grouped[$firstLevelRelation] = [];
            foreach ($models as $model) {
                $grouped[$firstLevelRelation][] = $model->$firstLevelRelation();
            }
        }

        // Store nested relations for processing after first level is loaded
        if (!empty($nested)) {
            static::$nestedRelations = $nested;
        }

        // Return grouped relations with constraints
        $result = [];
        foreach ($grouped as $relationName => $instances) {
            $result[$relationName] = [
                'instances' => $instances,
                'constraint' => $constraints[$relationName] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Load a relationship in batch for all models.
     *
     * Optimized eager loading using factory method pattern to eliminate reflection overhead.
     * Supports eager loading constraints via closures.
     *
     * Performance:
     * - OLD: O(1) reflection overhead per batch load
     * - NEW: O(1) factory method call (10-50x faster than reflection)
     *
     * Clean Architecture:
     * - Open/Closed Principle: Extensible via newEagerInstance() override
     * - Single Responsibility: Each relation handles its own instantiation logic
     *
     * @param ModelCollection<Model> $models Collection of models
     * @param string $relationName Relationship name
     * @param array<RelationInterface> $relationInstances Relationship instances
     * @param \Closure|null $constraint Optional query constraint closure
     * @return void
     */
    protected static function loadRelationBatch(ModelCollection $models, string $relationName, array $relationInstances, ?\Closure $constraint = null): void
    {
        if (empty($relationInstances)) {
            return;
        }

        // Get first relation instance to determine type
        $firstRelation = $relationInstances[0];

        // Create a fresh query builder for eager loading to avoid side effects
        $originalQuery = $firstRelation->getQuery();
        $freshQuery = $originalQuery->newQuery();

        // Copy table from original query to fresh query (newQuery() doesn't preserve table)
        $table = $originalQuery->getTable();
        if ($table !== null) {
            $freshQuery->table($table);
        }

        // Use factory method to create eager loading instance
        // This eliminates reflection overhead and follows Open/Closed principle
        $eagerRelation = $firstRelation->newEagerInstance($freshQuery);

        // Apply constraint if provided (e.g., ->where('published', true))
        if ($constraint !== null) {
            $constraint($eagerRelation->getQuery());
        }

        // Add eager constraints to query (this will add WHERE IN clause for multiple models)
        $eagerRelation->addEagerConstraints($models->all());

        // Execute query to get all related models
        $results = $eagerRelation->getResults();

        // Match results to parent models (this already sets relations on models)
        $eagerRelation->match($models->all(), $results, $relationName);

        // Set eagerLoaded flag on models (match() already set the relation)
        foreach ($models as $model) {
            // match() already called setRelation(), so relation is loaded
            // Set eagerLoaded flag to indicate this was eager loaded
            if (!isset($model->eagerLoaded)) {
                $model->eagerLoaded = [];
            }
            $model->eagerLoaded[$relationName] = true;
        }

        // Load nested relationships if any (e.g., 'posts.comments')
        if (isset(static::$nestedRelations[$relationName]) && $results instanceof ModelCollection && !$results->isEmpty()) {
            static::eagerLoadRelations($results, static::$nestedRelations[$relationName]);
        }
    }

    /**
     * Set a relationship on the model.
     *
     * This method is called by the trait but should be provided by the class
     * using the trait (e.g., Model class). Model already has setRelation returning self.
     * We use a wrapper here to ignore the return value for trait compatibility.
     *
     * @param string $relation Relationship name
     * @param mixed $value Relationship value
     * @return void
     */
    protected function setRelationForEagerLoading(string $relation, mixed $value): void
    {
        // Call the parent's setRelation (from Model) and ignore return value
        if (method_exists($this, 'setRelation')) {
            $this->setRelation($relation, $value);
        }
    }

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

    /**
     * Lazy eager load relationships for this model instance.
     *
     * Loads relationships after the model has already been retrieved.
     * Useful when you need to conditionally load relationships.
     *
     * Example:
     * ```php
     * $user = User::find(1);
     * // Later, conditionally load posts
     * if ($someCondition) {
     *     $user->load('posts');
     * }
     * // Or with constraints
     * $user->load(['posts' => function($q) {
     *     $q->where('published', true);
     * }]);
     * ```
     *
     * @param string|array<string|\Closure> $relations Relationship names or ['relation' => Closure]
     * @return $this
     */
    public function load(string|array $relations): static
    {
        // Convert single relation to array
        $relations = is_array($relations) ? $relations : [$relations];

        // Create collection with this model
        $collection = new ModelCollection([$this]);

        // Use static eager loading method
        static::eagerLoadRelations($collection, $relations);

        return $this;
    }
}
