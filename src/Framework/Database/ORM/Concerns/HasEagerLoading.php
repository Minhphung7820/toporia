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
     * Thread-safe implementation using context-based approach instead of static properties.
     * This ensures no conflicts in concurrent/async scenarios or recursive calls.
     *
     * @param ModelCollection<Model> $models Collection of models
     * @param array<string> $relations Relationship names to load
     * @param array|null $context Internal context for recursive calls (do not pass manually)
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
     *
     * // Constrained nested relationships
     * static::eagerLoadRelations($users, [
     *     'posts.comments' => fn($q) => $q->where('approved', true)
     * ]);
     * ```
     */
    public static function eagerLoadRelations(ModelCollection $models, array $relations, ?array $context = null): void
    {
        if ($models->isEmpty()) {
            return;
        }

        // Initialize context for this call (thread-safe, no static state)
        $context ??= [
            'nestedRelations' => [],
            'nestedConstraints' => [],
        ];

        // Group relationships by type for batch loading
        $grouped = static::groupRelationsByType($models, $relations, $context);

        // Load each group in batch
        foreach ($grouped as $relationName => $relationData) {
            $relationInstances = $relationData['instances'];
            $constraint = $relationData['constraint'] ?? null;
            static::loadRelationBatch($models, $relationName, $relationInstances, $constraint, $context);
        }
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
     * @param array &$context Context array for storing nested relations (passed by reference)
     * @return array<string, array{instances: array<RelationInterface>, constraint: \Closure|null}>
     */
    protected static function groupRelationsByType(ModelCollection $models, array $relations, array &$context): array
    {
        $grouped = [];
        $nested = []; // Track nested relations for later processing
        $constraints = []; // Track constraints for first-level relations
        $nestedConstraints = []; // Track constraints for nested relations

        foreach ($relations as $key => $value) {
            // Handle format: ['relation' => Closure] or 'relation'
            $relationName = is_string($key) ? $key : $value;
            $constraint = is_string($key) && is_callable($value) ? $value : null;

            // Skip if relationName is not a string
            if (!is_string($relationName)) {
                continue;
            }

            // Handle nested relations (e.g., 'posts.comments', 'reviews.user.profile')
            // Split only on first dot to support multi-level nesting (handled recursively)
            $parts = explode('.', $relationName, 2);
            $firstLevelRelation = $parts[0];

            // Track nested relations for this first-level relation
            if (isset($parts[1])) {
                $nestedRelation = $parts[1]; // Can be 'user' or 'user.profile' (handled recursively)

                if (!isset($nested[$firstLevelRelation])) {
                    $nested[$firstLevelRelation] = [];
                }
                // Avoid duplicate nested relations (important for performance)
                // Using in_array with strict comparison for accuracy
                if (!in_array($nestedRelation, $nested[$firstLevelRelation], true)) {
                    $nested[$firstLevelRelation][] = $nestedRelation;
                }

                // Store constraint for nested relation (not first-level)
                // IMPORTANT: Constraint applies to the FINAL relationship in the path
                // Example: 'reviews.user' => fn($q) => $q->where(...) applies to 'user', not 'reviews'
                // To constrain 'reviews', use: 'reviews' => fn($q) => $q->where(...), 'reviews.user'
                if ($constraint !== null) {
                    if (!isset($nestedConstraints[$firstLevelRelation])) {
                        $nestedConstraints[$firstLevelRelation] = [];
                    }
                    $nestedConstraints[$firstLevelRelation][$nestedRelation] = $constraint;
                }
            } else {
                // This is a first-level relation, store constraint for it
                // Example: 'reviews' => fn($q) => $q->where('helpful_count', '<', 20)
                if ($constraint !== null) {
                    $constraints[$firstLevelRelation] = $constraint;
                }
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

            $grouped[$firstLevelRelation] = [];
            foreach ($models as $model) {
                $grouped[$firstLevelRelation][] = $model->$firstLevelRelation();
            }
        }

        // Store nested relations in context for processing after first level is loaded
        if (!empty($nested)) {
            $existingNested = $context['nestedRelations'] ?? [];
            foreach ($nested as $firstLevel => $nestedRels) {
                if (!isset($existingNested[$firstLevel])) {
                    $existingNested[$firstLevel] = [];
                }
                // Merge and deduplicate nested relations
                // array_unique with SORT_REGULAR ensures proper deduplication
                $existingNested[$firstLevel] = array_values(array_unique(
                    array_merge($existingNested[$firstLevel], $nestedRels),
                    SORT_REGULAR
                ));
            }
            $context['nestedRelations'] = $existingNested;
        }

        // Store nested constraints in context
        // Use proper merge to avoid array_merge_recursive issues with duplicate keys
        if (!empty($nestedConstraints)) {
            $existingConstraints = $context['nestedConstraints'] ?? [];
            foreach ($nestedConstraints as $firstLevel => $constraints) {
                if (!isset($existingConstraints[$firstLevel])) {
                    $existingConstraints[$firstLevel] = [];
                }
                // Merge constraints, later ones override earlier ones (Laravel behavior)
                $existingConstraints[$firstLevel] = array_merge(
                    $existingConstraints[$firstLevel],
                    $constraints
                );
            }
            $context['nestedConstraints'] = $existingConstraints;
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
     * @param array $context Context for nested relations (thread-safe)
     * @return void
     */
    protected static function loadRelationBatch(
        ModelCollection $models,
        string $relationName,
        array $relationInstances,
        ?\Closure $constraint = null,
        array $context = []
    ): void {
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
        // Use context instead of static properties for thread-safety
        $nestedRelations = $context['nestedRelations'] ?? [];
        $nestedConstraints = $context['nestedConstraints'] ?? [];

        if (isset($nestedRelations[$relationName]) && $results instanceof ModelCollection && !$results->isEmpty()) {
            $nestedRelationsToLoad = $nestedRelations[$relationName];

            // Apply constraints to nested relations if they exist
            $nestedRelationsWithConstraints = [];
            $nestedConstraintsForThisRelation = $nestedConstraints[$relationName] ?? [];

            foreach ($nestedRelationsToLoad as $nestedRelation) {
                // If there's a constraint for this nested relation, use it
                if (isset($nestedConstraintsForThisRelation[$nestedRelation])) {
                    $nestedRelationsWithConstraints[$nestedRelation] = $nestedConstraintsForThisRelation[$nestedRelation];
                } else {
                    // No constraint - use string key consistently to avoid hybrid array issues
                    // This ensures groupRelationsByType() can properly identify relations without constraints
                    $nestedRelationsWithConstraints[$nestedRelation] = null;
                }
            }

            // Recursive call with fresh context for nested relations
            static::eagerLoadRelations($results, $nestedRelationsWithConstraints);
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

    /**
     * Lazy eager load relationships only if they haven't been loaded yet.
     *
     * This is more efficient than load() when you're unsure if relationships
     * are already loaded - it avoids duplicate queries.
     *
     * Example:
     * ```php
     * $user = User::with('posts')->find(1); // posts already loaded
     * $user->loadMissing('posts'); // Does nothing - posts already loaded
     * $user->loadMissing('comments'); // Loads comments
     *
     * // Also supports constraints
     * $user->loadMissing(['posts' => fn($q) => $q->where('published', true)]);
     *
     * // Multiple relations
     * $user->loadMissing(['posts', 'comments', 'profile']);
     * ```
     *
     * @param string|array<string|\Closure> $relations Relationship names or ['relation' => Closure]
     * @return $this
     */
    public function loadMissing(string|array $relations): static
    {
        // Convert single relation to array
        $relations = is_array($relations) ? $relations : [$relations];

        // Filter out already loaded relations
        $missingRelations = [];

        foreach ($relations as $key => $value) {
            // Handle format: ['relation' => Closure] or 'relation'
            $relationName = is_string($key) ? $key : $value;

            // Skip if not a string
            if (!is_string($relationName)) {
                continue;
            }

            // Get first level relation name (handle nested like 'posts.comments')
            $firstLevel = explode('.', $relationName, 2)[0];

            // Check if already loaded
            if (!$this->relationLoaded($firstLevel)) {
                // Preserve the original key/value pair
                if (is_string($key)) {
                    $missingRelations[$key] = $value;
                } else {
                    $missingRelations[] = $value;
                }
            }
        }

        // Only load if there are missing relations
        if (!empty($missingRelations)) {
            $this->load($missingRelations);
        }

        return $this;
    }

    /**
     * Lazy eager load a count of related models only if not already loaded.
     *
     * Example:
     * ```php
     * $user->loadMissingCount('posts');
     * // $user->posts_count is now available
     * ```
     *
     * @param string|array<string|\Closure> $relations Relationship names
     * @return $this
     */
    public function loadMissingCount(string|array $relations): static
    {
        // Convert single relation to array
        $relations = is_array($relations) ? $relations : [$relations];

        // Filter out already loaded counts
        $missingRelations = [];

        foreach ($relations as $key => $value) {
            $relationName = is_string($key) ? $key : $value;

            if (!is_string($relationName)) {
                continue;
            }

            $countAttribute = $relationName . '_count';

            // Check if count already loaded (exists as attribute)
            if (!isset($this->attributes[$countAttribute])) {
                if (is_string($key)) {
                    $missingRelations[$key] = $value;
                } else {
                    $missingRelations[] = $value;
                }
            }
        }

        // Only load if there are missing counts
        if (!empty($missingRelations)) {
            $this->loadCount($missingRelations);
        }

        return $this;
    }

    /**
     * Lazy eager load count of related models.
     *
     * Example:
     * ```php
     * $user = User::find(1);
     * $user->loadCount('posts');
     * // $user->posts_count is now available
     *
     * // With constraints
     * $user->loadCount(['posts' => fn($q) => $q->where('published', true)]);
     * ```
     *
     * @param string|array<string|\Closure> $relations Relationship names
     * @return $this
     */
    public function loadCount(string|array $relations): static
    {
        // Convert single relation to array
        $relations = is_array($relations) ? $relations : [$relations];

        foreach ($relations as $key => $value) {
            $relationName = is_string($key) ? $key : $value;
            $constraint = is_string($key) && is_callable($value) ? $value : null;

            if (!is_string($relationName) || !method_exists($this, $relationName)) {
                continue;
            }

            // Get relationship instance
            $relationInstance = $this->$relationName();

            if (!$relationInstance instanceof RelationInterface) {
                continue;
            }

            // Get query and apply constraints
            $query = $relationInstance->getQuery();

            if ($constraint !== null) {
                $constraint($query);
            }

            // Get count
            $count = $query->count();

            // Set count as attribute
            $countAttribute = $relationName . '_count';
            $this->setAttribute($countAttribute, $count);
        }

        return $this;
    }

    /**
     * Load aggregate values for relationships.
     *
     * Example:
     * ```php
     * $user->loadSum('posts', 'views');
     * // $user->posts_sum_views is now available
     *
     * $user->loadAvg('reviews', 'rating');
     * // $user->reviews_avg_rating is now available
     * ```
     *
     * @param string $relation Relationship name
     * @param string $column Column to aggregate
     * @param string $function Aggregate function (sum, avg, min, max)
     * @return $this
     */
    public function loadAggregate(string $relation, string $column, string $function = 'sum'): static
    {
        if (!method_exists($this, $relation)) {
            return $this;
        }

        $relationInstance = $this->$relation();

        if (!$relationInstance instanceof RelationInterface) {
            return $this;
        }

        $query = $relationInstance->getQuery();

        $value = match (strtolower($function)) {
            'sum' => $query->sum($column),
            'avg' => $query->avg($column),
            'min' => $query->min($column),
            'max' => $query->max($column),
            'count' => $query->count($column),
            default => null,
        };

        // Set aggregate as attribute
        $attributeName = "{$relation}_{$function}_{$column}";
        $this->setAttribute($attributeName, $value);

        return $this;
    }

    /**
     * Load sum aggregate for a relationship.
     *
     * @param string $relation Relationship name
     * @param string $column Column to sum
     * @return $this
     */
    public function loadSum(string $relation, string $column): static
    {
        return $this->loadAggregate($relation, $column, 'sum');
    }

    /**
     * Load average aggregate for a relationship.
     *
     * @param string $relation Relationship name
     * @param string $column Column to average
     * @return $this
     */
    public function loadAvg(string $relation, string $column): static
    {
        return $this->loadAggregate($relation, $column, 'avg');
    }

    /**
     * Load minimum aggregate for a relationship.
     *
     * @param string $relation Relationship name
     * @param string $column Column to get minimum
     * @return $this
     */
    public function loadMin(string $relation, string $column): static
    {
        return $this->loadAggregate($relation, $column, 'min');
    }

    /**
     * Load maximum aggregate for a relationship.
     *
     * @param string $relation Relationship name
     * @param string $column Column to get maximum
     * @return $this
     */
    public function loadMax(string $relation, string $column): static
    {
        return $this->loadAggregate($relation, $column, 'max');
    }
}
