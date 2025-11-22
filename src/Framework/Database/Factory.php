<?php

declare(strict_types=1);

namespace Toporia\Framework\Database;

use Toporia\Framework\Database\Contracts\FactoryInterface;
use Toporia\Framework\Database\Contracts\FakerProviderInterface;
use Toporia\Framework\Database\Factory\Concerns\{HasRelations, HasStates, HasSequences};
use Toporia\Framework\Database\ORM\Model;
use Faker\Generator;
use Faker\Factory as FakerFactory;

/**
 * Base Factory Class
 *
 * Provides factory pattern for creating model instances with fake data.
 *
 * Features:
 * - Faker integration for generating realistic fake data
 * - Lazy loading of model instances
 * - Batch processing for performance
 * - State management for different model variations
 * - Relationship support (afterCreating callbacks)
 * - Memory-efficient bulk operations
 *
 * SOLID Principles:
 * - Single Responsibility: Factory only creates model instances
 * - Open/Closed: Extend via child classes without modifying base
 * - Liskov Substitution: All factories can be used interchangeably
 * - Interface Segregation: Implements specific FactoryInterface
 * - Dependency Inversion: Depends on Model interface, not concrete classes
 *
 * Performance Optimizations:
 * - Lazy evaluation: Attributes generated only when needed
 * - Batch inserts: createMany() uses batch insert for performance
 * - Memory management: Generator-based makeMany() for large datasets
 * - Caching: Faker instance cached per factory
 *
 * Clean Architecture:
 * - Infrastructure Layer: Handles data generation and persistence
 *
 * @template T of Model
 * @implements FactoryInterface<T>
 */
abstract class Factory implements FactoryInterface
{
    use HasRelations;
    use HasStates;
    use HasSequences;
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<T>
     */
    protected string $model;

    /**
     * Faker generator instance.
     *
     * Cached per factory instance for performance.
     *
     * @var Generator|null
     */
    protected ?Generator $faker = null;

    /**
     * Locale for Faker generator.
     *
     * @var string
     */
    protected string $locale = 'en_US';

    /**
     * Active state transformations.
     *
     * @var array<int, callable|array<string, mixed>|string>
     */
    protected array $states = [];

    /**
     * Callbacks to run after creating model instances.
     *
     * @var array<int, callable(T): void>
     */
    protected array $afterCreating = [];

    /**
     * Callbacks to run after making model instances (before persistence).
     *
     * @var array<int, callable(T): void>
     */
    protected array $afterMaking = [];

    /**
     * Batch size for bulk operations.
     *
     * @var int
     */
    protected int $batchSize = 100;

    /**
     * Whether to use lazy evaluation for attributes.
     *
     * @var bool
     */
    protected bool $lazyEvaluation = true;

    /**
     * Custom Faker providers.
     *
     * @var array<int, class-string<FakerProviderInterface>>
     */
    protected array $fakerProviders = [];

    /**
     * Count value for bulk operations.
     *
     * @var int|null
     */
    protected ?int $countValue = null;

    /**
     * Create a new factory instance.
     *
     * @return static
     */
    public static function new(): static
    {
        return new static();
    }

    /**
     * Constructor.
     *
     * Automatically resolves model name if not set.
     */
    public function __construct()
    {
        if (!isset($this->model)) {
            $this->model = $this->guessModelName();
        }
    }

    /**
     * Get Faker generator instance.
     *
     * Lazy-loaded and cached for performance.
     *
     * @return Generator
     */
    protected function faker(): Generator
    {
        if ($this->faker === null) {
            $this->faker = FakerFactory::create($this->locale);

            // Register custom providers
            foreach ($this->fakerProviders as $providerClass) {
                if (is_subclass_of($providerClass, FakerProviderInterface::class)) {
                    $provider = new $providerClass();
                    $provider->register($this->faker);
                }
            }
        }

        return $this->faker;
    }

    /**
     * Create a new model instance (not persisted).
     *
     * @param array<string, mixed> $attributes
     * @return T
     */
    public function make(array $attributes = []): Model
    {
        $attributes = $this->resolveAttributes($attributes);
        $model = $this->newModel($attributes);

        // Run afterMaking callbacks
        foreach ($this->afterMaking as $callback) {
            $callback($model);
        }

        return $model;
    }

    /**
     * Create a model instance and persist it to database.
     *
     * @param array<string, mixed> $attributes
     * @return T
     */
    public function create(array $attributes = []): Model
    {
        $model = $this->make($attributes);
        $model->save();

        // Run afterCreating callbacks
        foreach ($this->afterCreating as $callback) {
            $callback($model);
        }

        return $model;
    }

    /**
     * Set count for bulk operations (fluent interface).
     *
     * @param int $count
     * @return static
     */
    public function count(int $count): static
    {
        $this->countValue = $count;
        return $this;
    }

    /**
     * Get count value.
     *
     * @return int
     */
    protected function getCount(): int
    {
        return $this->countValue ?? 1;
    }

    /**
     * Create multiple model instances (not persisted).
     *
     * Uses generator for memory efficiency when creating many instances.
     *
     * @param int $count
     * @param array<string, mixed> $attributes
     * @return array<int, T>
     */
    public function makeMany(int $count, array $attributes = []): array
    {
        if ($count <= 0) {
            return [];
        }

        // For small batches, create all at once
        if ($count <= $this->batchSize) {
            $models = [];
            for ($i = 0; $i < $count; $i++) {
                $models[] = $this->make($attributes);
            }
            return $models;
        }

        // For large batches, use generator to save memory
        return iterator_to_array($this->makeManyLazy($count, $attributes));
    }

    /**
     * Create multiple model instances lazily (generator).
     *
     * Memory-efficient for large datasets.
     *
     * @param int $count
     * @param array<string, mixed> $attributes
     * @return \Generator<int, T>
     */
    protected function makeManyLazy(int $count, array $attributes = []): \Generator
    {
        for ($i = 0; $i < $count; $i++) {
            yield $this->make($attributes);
        }
    }

    /**
     * Create multiple model instances and persist them to database.
     *
     * Uses batch insert for performance optimization.
     *
     * @param int|null $count If null, uses count() value
     * @param array<string, mixed> $attributes
     * @return array<int, T>|T If count is 1, returns single model; otherwise array
     */
    public function createMany(?int $count = null, array $attributes = []): array|Model
    {
        $count = $count ?? $this->getCount();

        if ($count <= 0) {
            return [];
        }

        if ($count === 1) {
            return $this->create($attributes);
        }

        // Get model class for batch operations
        $modelClass = $this->model;

        // For small batches, create individually (allows callbacks)
        if ($count <= $this->batchSize) {
            $models = [];
            for ($i = 0; $i < $count; $i++) {
                $models[] = $this->create($attributes);
            }
            return $models;
        }

        // For large batches, use batch insert for performance
        return $this->createManyBatch($count, $attributes);
    }

    /**
     * Create many models using batch insert for performance.
     *
     * @param int $count
     * @param array<string, mixed> $attributes
     * @return array<int, T>
     */
    protected function createManyBatch(int $count, array $attributes = []): array
    {
        $modelClass = $this->model;
        $batch = [];

        // Prepare batch data
        for ($i = 0; $i < $count; $i++) {
            $modelAttributes = $this->resolveAttributes($attributes);

            // Apply timestamps if model uses them
            if (property_exists($modelClass, 'timestamps') && $modelClass::$timestamps) {
                $now = date('Y-m-d H:i:s');
                $modelAttributes['created_at'] = $modelAttributes['created_at'] ?? $now;
                $modelAttributes['updated_at'] = $modelAttributes['updated_at'] ?? $now;
            }

            $batch[] = $modelAttributes;

            // Insert in batches
            if (count($batch) >= $this->batchSize) {
                $this->insertBatch($modelClass, $batch);
                $batch = [];
            }
        }

        // Insert remaining
        if (!empty($batch)) {
            $this->insertBatch($modelClass, $batch);
        }

        // Note: After batch insert, we can't run callbacks for individual models
        // If callbacks are needed, use smaller batches or create() individually

        // Return empty array as we can't retrieve IDs after batch insert
        // For better performance, we trade off model instance access
        return [];
    }

    /**
     * Insert batch of models into database.
     *
     * @param class-string<T> $modelClass
     * @param array<int, array<string, mixed>> $batch
     * @return void
     */
    protected function insertBatch(string $modelClass, array $batch): void
    {
        if (empty($batch)) {
            return;
        }

        // Use model's insertBatch method if available (HasBatchOperations trait)
        if (method_exists($modelClass, 'insertBatch')) {
            $modelClass::insertBatch($batch);
            return;
        }

        // Fallback: use query builder directly
        // Use getTableName() method instead of accessing protected property
        $table = method_exists($modelClass, 'getTableName')
            ? $modelClass::getTableName()
            : static::guessTableName($modelClass);
        $connection = static::resolveConnection($modelClass);

        $columns = array_keys($batch[0]);
        $placeholders = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $values = array_fill(0, count($batch), $placeholders);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES %s',
            $table,
            implode(',', array_map(fn($col) => "`{$col}`", $columns)),
            implode(',', $values)
        );

        $bindings = [];
        foreach ($batch as $row) {
            foreach ($columns as $column) {
                $bindings[] = $row[$column] ?? null;
            }
        }

        $connection->execute($sql, $bindings);
    }

    /**
     * Resolve database connection from model class.
     *
     * @param class-string<T> $modelClass
     * @return \Toporia\Framework\Database\Contracts\ConnectionInterface
     */
    protected static function resolveConnection(string $modelClass): \Toporia\Framework\Database\Contracts\ConnectionInterface
    {
        // Try to get connection from model's query builder
        if (method_exists($modelClass, 'query')) {
            try {
                $query = $modelClass::query();
                if (method_exists($query, 'getConnection')) {
                    return $query->getConnection();
                }
            } catch (\Throwable $e) {
                // Fall through to other methods
            }
        }

        // Fallback: get from DatabaseManager
        if (function_exists('app') && app()->has('db')) {
            $db = app()->get('db');
            if ($db instanceof \Toporia\Framework\Database\DatabaseManager) {
                return $db->connection();
            }
        }

        // Last resort: throw exception
        throw new \RuntimeException(
            "Unable to resolve database connection for model [{$modelClass}]. " .
                "Please ensure DatabaseManager is configured."
        );
    }

    /**
     * Guess table name from model class name.
     *
     * @param class-string<T> $modelClass
     * @return string
     */
    protected static function guessTableName(string $modelClass): string
    {
        // Get class name without namespace using Reflection
        $reflection = new \ReflectionClass($modelClass);
        $className = $reflection->getShortName();

        // Remove 'Model' suffix if present
        $tableName = preg_replace('/Model$/', '', $className);

        // Convert PascalCase to snake_case
        $tableName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $tableName));

        // Pluralize (simple version)
        if (!str_ends_with($tableName, 's')) {
            $tableName .= 's';
        }

        return $tableName;
    }

    /**
     * Apply state transformations.
     *
     * States can be:
     * - String: method name like 'admin' -> calls stateAdmin()
     * - Callable: directly applied as callback
     * - Array: merged as attributes
     *
     * Supports method chaining.
     *
     * @param string|callable|array<string, mixed> $state
     * @return static
     */
    public function state(string|callable|array $state): static
    {
        $this->states[] = $state;
        return $this;
    }

    /**
     * Register a callback to run after creating a model.
     *
     * Useful for creating related models.
     *
     * @param callable(T): void $callback
     * @return static
     */
    public function afterCreating(callable $callback): static
    {
        $this->afterCreating[] = $callback;
        return $this;
    }

    /**
     * Register a callback to run after making a model (before persistence).
     *
     * @param callable(T): void $callback
     * @return static
     */
    public function afterMaking(callable $callback): static
    {
        $this->afterMaking[] = $callback;
        return $this;
    }

    /**
     * Set batch size for bulk operations.
     *
     * @param int $size
     * @return static
     */
    public function batchSize(int $size): static
    {
        $this->batchSize = max(1, $size);
        return $this;
    }

    /**
     * Set locale for Faker generator.
     *
     * @param string $locale
     * @return static
     */
    public function locale(string $locale): static
    {
        $this->locale = $locale;
        $this->faker = null; // Reset to reload with new locale
        return $this;
    }

    /**
     * Resolve attributes for model creation.
     *
     * Applies definition, states, and provided attributes in order.
     *
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    protected function resolveAttributes(array $attributes): array
    {
        // Start with definition
        $resolved = $this->definition();

        // Apply sequence if defined (HasSequences trait)
        if (trait_exists('Toporia\Framework\Database\Factory\Concerns\HasSequences') && !empty($this->sequences)) {
            $resolved = array_merge($resolved, $this->getNextSequence());
        }

        // Apply states (in order)
        foreach ($this->states as $state) {
            if (is_string($state)) {
                // Call state method (e.g., 'admin' -> stateAdmin())
                $method = 'state' . ucfirst($state);
                if (method_exists($this, $method)) {
                    $resolved = array_merge($resolved, $this->$method($resolved));
                }
            } elseif (is_callable($state)) {
                // Apply callback state
                $resolved = array_merge($resolved, $state($resolved));
            } elseif (is_array($state)) {
                // Merge array state
                $resolved = array_merge($resolved, $state);
            }
        }

        // Finally, merge provided attributes (highest priority)
        return array_merge($resolved, $attributes);
    }

    /**
     * Create a new model instance with given attributes.
     *
     * @param array<string, mixed> $attributes
     * @return T
     */
    protected function newModel(array $attributes): Model
    {
        $modelClass = $this->model;
        return new $modelClass($attributes);
    }

    /**
     * Guess model name from factory class name.
     *
     * Example: UserFactory -> App\Domain\User\UserModel
     *
     * @return class-string<T>
     */
    protected function guessModelName(): string
    {
        $factoryName = static::class;

        // Remove 'Factory' suffix
        $modelName = preg_replace('/Factory$/', '', $factoryName);

        // Try common patterns
        $patterns = [
            str_replace('\\Database\\Factories', '\\Domain\\', $modelName) . '\\' . basename(str_replace('\\', '/', $modelName)) . 'Model',
            str_replace('\\Database\\Factories', '\\Domain\\', $modelName) . 'Model',
            str_replace('\\Database\\Factories', '\\Domain\\', $modelName),
        ];

        foreach ($patterns as $pattern) {
            if (class_exists($pattern) && is_subclass_of($pattern, Model::class)) {
                return $pattern;
            }
        }

        throw new \RuntimeException(
            "Unable to guess model name for factory [{$factoryName}]. " .
                "Please define \$model property in factory class."
        );
    }

    /**
     * Define model's default attributes.
     *
     * Must be implemented by child classes.
     *
     * @return array<string, mixed>
     */
    abstract public function definition(): array;
}
