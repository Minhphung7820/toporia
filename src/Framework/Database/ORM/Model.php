<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM;

use Toporia\Framework\Database\Contracts\{ConnectionInterface, ModelInterface, RelationInterface};
use Toporia\Framework\Database\Query\{QueryBuilder, RowCollection};
use Toporia\Framework\Database\ORM\{ModelCollection, Relations};
use Toporia\Framework\Observer\Traits\Observable;
use Toporia\Framework\Observer\Contracts\ObservableInterface;
use Toporia\Framework\Database\ORM\Concerns\HasObservers;


/**
 * Abstract Class Model
 *
 * Base ORM model class implementing Active Record pattern with
 * relationships, scopes, eager loading, query builder integration, and
 * event hooks.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  ORM
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
abstract class Model implements ModelInterface, ObservableInterface, \JsonSerializable
{
    use Observable;
    use HasObservers;
    use Concerns\HasAccessorsAndMutators;
    use Concerns\HasSerialization;
    use Concerns\HasEvents;
    use Concerns\HasGlobalScopes;
    use Concerns\HasModelCollections;
    use Concerns\HasMassAssignmentProtection;
    use Concerns\HasEagerLoading;
    use Concerns\HasFactory;
    /**
     * Database table name (override in child class).
     *
     * For SQL databases (MySQL, PostgreSQL, SQLite), this specifies the table name.
     * For MongoDB, use $collection instead.
     *
     * @var string
     */
    protected static string $table = '';

    /**
     * MongoDB collection name (override in child class).
     *
     * Only used when connection driver is 'mongodb'.
     * If not set, falls back to $table property.
     *
     * Example:
     * ```php
     * class LogModel extends Model
     * {
     *     protected static ?string $connection = 'mongodb';
     *     protected static string $collection = 'application_logs';
     * }
     * ```
     *
     * SOLID Principles:
     * - Single Responsibility: Model specifies its data source
     * - Open/Closed: Can override per model without modifying base class
     *
     * @var string
     */
    protected static string $collection = '';

    /**
     * Primary key column name.
     *
     * @var string
     */
    protected static string $primaryKey = 'id';

    /**
     * Whether timestamp columns should be automatically managed.
     *
     * @var bool
     */
    protected static bool $timestamps = true;

    /**
     * Whitelist of attributes that can be mass-assigned.
     * If non-empty, only keys listed here are fillable.
     *
     * @var array<string>
     */
    protected static array $fillable = [];

    /**
     * Blacklist of attributes that cannot be mass-assigned.
     *
     * Behavior:
     * - Empty array (default): Allow all fields when $fillable is also empty (auto-fillable)
     * - ['field1', 'field2']: Block specific fields (blacklist approach)
     * - ['*']: Disable mass assignment entirely (require explicit $fillable)
     *
     * SOLID Principles:
     * - Convention over Configuration: Default to permissive (empty array)
     * - Security: Models can opt-in to strict mode by setting $guarded = ['*']
     * - Open/Closed: Each model can customize without modifying base class
     *
     * @var array<string>
     */
    protected static array $guarded = [];

    /**
     * Attribute casting map. Example: ['is_active' => 'bool'].
     * Supported types: int, float, string, bool, array, json, date.
     *
     * @var array<string, string>
     */
    protected static array $casts = [];

    /**
     * Attributes that should be hidden from array/JSON representation.
     *
     * Use this to hide sensitive data (passwords, tokens, etc.) from API responses.
     *
     * Example:
     * protected static array $hidden = ['password', 'remember_token'];
     *
     * SOLID Principles:
     * - Single Responsibility: Model defines its own serialization rules
     * - Open/Closed: Can be overridden per model without changing base class
     * - Information Hiding: Prevents accidental exposure of sensitive data
     *
     * @var array<string>
     */
    protected static array $hidden = [];

    /**
     * Attributes that should be visible in array/JSON representation.
     *
     * When set, ONLY these attributes will be included (whitelist approach).
     * Takes precedence over $hidden.
     *
     * Example:
     * protected static array $visible = ['id', 'name', 'email'];
     *
     * @var array<string>
     */
    protected static array $visible = [];

    /**
     * Computed attributes to append to array/JSON representation.
     *
     * These are accessor methods that will be automatically called and included.
     *
     * Example:
     * protected static array $appends = ['full_name', 'is_admin'];
     *
     * Then define accessor methods:
     * public function getFullNameAttribute(): string {
     *     return $this->first_name . ' ' . $this->last_name;
     * }
     *
     * SOLID Principles:
     * - Open/Closed: Extend model behavior without modifying serialization logic
     * - Single Responsibility: Computed logic in separate methods
     *
     * @var array<string>
     */
    protected static array $appends = [];

    /**
     * Connection name to use for this model.
     * If null, uses the default global connection.
     *
     * Example:
     * protected static ?string $connection = 'analytics';
     *
     * This follows SOLID principles:
     * - Single Responsibility: Model specifies its data source
     * - Open/Closed: Can override per model without modifying base class
     * - Dependency Inversion: Depends on connection name, not concrete connection
     *
     * @var string|null
     */
    protected static ?string $connection = null;

    /**
     * Current attribute bag.
     *
     * @var array<string, mixed>
     */
    private array $attributes = [];

    /**
     * Snapshot of attributes used for dirty checking.
     *
     * @var array<string, mixed>
     */
    private array $original = [];

    /**
     * Whether the model currently exists in the database.
     *
     * @var bool
     */
    private bool $exists = false;

    /**
     * Global default database connection instance.
     *
     * @var ConnectionInterface|null
     */
    private static ?ConnectionInterface $defaultConnection = null;

    /**
     * Cached connections per model class for performance optimization.
     * Key: model class name, Value: ConnectionInterface instance
     *
     * Performance: O(1) lookup after first resolution
     * Reduces DatabaseManager calls and connection creation overhead
     *
     * @var array<string, ConnectionInterface>
     */
    private static array $connectionCache = [];

    /**
     * Loaded relationships.
     *
     * @var array<string, mixed>
     */
    private array $relations = [];

    /**
     * Track which model classes have been booted.
     *
     * @var array<string, bool>
     */
    private static array $booted = [];

    /**
     * Prevent lazy loading of relationships (throws exception on N+1 queries).
     *
     * When enabled, accessing a relationship that wasn't eager loaded will throw
     * an exception instead of silently executing a query.
     *
     * This helps detect N+1 query problems during development.
     *
     * Usage:
     * - Enable globally: Model::preventLazyLoading(true);
     * - Disable: Model::preventLazyLoading(false);
     * - Check status: Model::preventsLazyLoading();
     *
     * Performance Impact:
     * - DEVELOPMENT: Enable to catch N+1 queries early
     * - PRODUCTION: Disable for graceful degradation
     *
     * @var bool
     */
    private static bool $preventLazyLoading = false;

    /**
     * Boot the model and all its traits.
     *
     * Automatically calls boot{TraitName} methods for all used traits.
     * Standard pattern for trait initialization in Toporia ORM.
     *
     * Performance: O(T) where T = number of traits (typically 1-3)
     * Called once per model class, cached by static flag.
     *
     * @return void
     */
    protected static function boot(): void
    {
        $class = static::class;

        // Only boot once per class
        if (isset(self::$booted[$class])) {
            return;
        }

        // Get all traits used by this class
        $traits = static::classUsesRecursive($class);

        // Call boot{TraitName} for each trait
        foreach ($traits as $trait) {
            $traitName = static::classBasename($trait);
            $method = 'boot' . $traitName;

            if (method_exists($class, $method)) {
                static::$method();
            }
        }

        // Mark as booted
        self::$booted[$class] = true;
    }

    /**
     * Get all traits used by a class, including parent traits.
     *
     * @param string|object $class
     * @return array<string>
     */
    protected static function classUsesRecursive(string|object $class): array
    {
        $results = [];
        $class = is_object($class) ? get_class($class) : $class;

        foreach (array_reverse(class_parents($class) ?: []) + [$class => $class] as $class) {
            $results += static::traitUsesRecursive($class);
        }

        return array_unique($results);
    }

    /**
     * Get all traits used by a trait.
     *
     * @param string $trait
     * @return array<string>
     */
    protected static function traitUsesRecursive(string $trait): array
    {
        $traits = class_uses($trait) ?: [];

        foreach ($traits as $trait) {
            $traits += static::traitUsesRecursive($trait);
        }

        return $traits;
    }

    /**
     * Get the base class name of a class.
     *
     * @param string|object $class
     * @return string
     */
    protected static function classBasename(string|object $class): string
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }

    /**
     * @param array<string,mixed> $attributes Initial attributes.
     */
    public function __construct(array $attributes = [])
    {
        // Boot traits on first instantiation of this class
        static::boot();

        $this->fill($attributes);
        $this->syncOriginal();
    }

    /**
     * Set the global default connection used by all models.
     *
     * This is typically called once during application bootstrap.
     *
     * @param ConnectionInterface $connection Default connection instance.
     * @return void
     */
    public static function setConnection(ConnectionInterface $connection): void
    {
        self::$defaultConnection = $connection;
    }

    /**
     * Enable or disable lazy loading prevention globally.
     *
     * When enabled, accessing a relationship that wasn't eager loaded will throw
     * an exception instead of silently executing a query, helping detect N+1 queries.
     *
     * Best Practice:
     * - Enable in development/testing: Model::preventLazyLoading(env('APP_ENV') !== 'production');
     * - Disable in production for graceful degradation
     *
     * Performance:
     * - No runtime overhead when disabled (static flag check is O(1))
     * - Helps catch expensive N+1 queries during development
     *
     * @param bool $prevent Whether to prevent lazy loading
     * @return void
     */
    public static function preventLazyLoading(bool $prevent = true): void
    {
        self::$preventLazyLoading = $prevent;
    }

    /**
     * Check if lazy loading prevention is enabled.
     *
     * @return bool True if lazy loading is prevented
     */
    public static function preventsLazyLoading(): bool
    {
        return self::$preventLazyLoading;
    }

    /**
     * Get the database connection for this model.
     *
     * Resolution order:
     * 1. Check if model specifies a connection name (static::$connection)
     * 2. If yes, resolve from DatabaseManager
     * 3. If no, use global default connection
     *
     * This follows SOLID principles:
     * - Open/Closed: Each model can specify its connection without modifying base class
     * - Dependency Inversion: Depends on DatabaseManager abstraction
     * - Single Responsibility: Connection resolution logic in one place
     *
     * @return ConnectionInterface
     * @throws \RuntimeException If no connection available.
     */
    /**
     * Get the database connection for this model.
     *
     * Resolution order:
     * 1. Check connection cache (performance optimization)
     * 2. Check if model specifies a connection name (static::$connection)
     * 3. If yes, resolve it from DatabaseManager and cache it
     * 4. If no, use global default connection
     *
     * Performance Optimizations:
     * - Connection caching per model class (O(1) lookup after first call)
     * - Lazy connection resolution (only when needed)
     * - Grammar auto-detection from connection driver
     *
     * SOLID Principles:
     * - Open/Closed: Each model can specify its connection without modifying base class
     * - Single Responsibility: Connection resolution logic in one place
     * - Dependency Inversion: Depends on ConnectionInterface abstraction
     *
     * Grammar Integration:
     * - Connection automatically provides appropriate Grammar based on driver
     * - MySQL → MySQLGrammar
     * - PostgreSQL → PostgreSQLGrammar
     * - SQLite → SQLiteGrammar
     * - MongoDB → MongoDBGrammar
     * - Grammar is cached per connection for optimal performance
     *
     * @return ConnectionInterface
     * @throws \RuntimeException If no connection available.
     */
    protected static function getConnection(): ConnectionInterface
    {
        $modelClass = static::class;

        // Check cache first for performance (O(1) lookup)
        if (isset(self::$connectionCache[$modelClass])) {
            return self::$connectionCache[$modelClass];
        }

        $connection = null;

        // If model specifies a connection name, resolve it from DatabaseManager
        if (static::$connection !== null) {
            $connection = static::resolveConnection(static::$connection);
        } else {
            // Otherwise use global default connection
            if (self::$defaultConnection === null) {
                throw new \RuntimeException(
                    'Database connection not set. Call Model::setConnection() first or specify connection name in model.'
                );
            }
            $connection = self::$defaultConnection;
        }

        // Cache connection for this model class (performance optimization)
        self::$connectionCache[$modelClass] = $connection;

        return $connection;
    }

    /**
     * Resolve a connection by name from the DatabaseManager.
     *
     * This method can be overridden in tests to provide mock connections.
     *
     * @param string $name Connection name from config/database.php
     * @return ConnectionInterface
     */
    protected static function resolveConnection(string $name): ConnectionInterface
    {
        // Get DatabaseManager from container
        $manager = container(\Toporia\Framework\Database\DatabaseManager::class);
        $proxy = $manager->connection($name);
        return $proxy->getConnection();
    }

    /**
     * Create a new ModelQueryBuilder scoped to this model's table.
     *
     * Returns ModelQueryBuilder which extends QueryBuilder with:
     * - Automatic hydration of rows into model instances
     * - Eager loading of relationships via with()
     * - Returns ModelCollection instead of RowCollection
     *
     * @return ModelQueryBuilder
     */
    public static function query(): ModelQueryBuilder
    {
        // Boot traits before creating query builder
        static::boot();

        return (new ModelQueryBuilder(static::getConnection(), static::class))->table(static::getTableName());
    }

    /**
     * Get the table/collection name.
     *
     * For MongoDB connections, uses $collection property if set.
     * For SQL databases, uses $table property.
     *
     * Auto-infers name from class name if not explicitly set:
     * - ProductModel -> products
     * - UserModel -> users
     * - OrderItem -> order_items
     *
     * MongoDB-specific behavior:
     * - If connection is 'mongodb' and $collection is set, uses $collection
     * - If connection is 'mongodb' and $collection is empty, falls back to $table
     * - If connection is 'mongodb' and both are empty, auto-infers from class name
     *
     * SQL databases:
     * - Always uses $table property
     * - If $table is empty, auto-infers from class name
     *
     * Performance: Connection driver is checked once and cached
     *
     * SOLID Principles:
     * - Convention over Configuration: Reduces boilerplate code
     * - Open/Closed: Can override $table or $collection in child classes
     * - Single Responsibility: Only handles table/collection name resolution
     *
     * @return string Table or collection name
     */
    public static function getTableName(): string
    {
        // Check if connection is MongoDB
        $isMongoDB = static::isMongoDBConnection();

        // For MongoDB: Prefer $collection over $table
        if ($isMongoDB) {
            if (isset(static::$collection) && static::$collection !== '') {
                return static::$collection;
            }
            // Fallback to $table if $collection is not set
            if (isset(static::$table) && static::$table !== '') {
                return static::$table;
            }
        } else {
            // For SQL databases: Use $table
            if (isset(static::$table) && static::$table !== '') {
                return static::$table;
            }
        }

        // Auto-infer from class name (works for both SQL and MongoDB)
        // Extract class name without namespace
        $reflection = app()->make(\Toporia\Framework\Support\ReflectionService::class);
        $className = $reflection->getShortName(static::class);

        // Remove "Model" suffix if present
        // ProductModel -> Product
        $baseName = preg_replace('/Model$/', '', $className);

        // Convert to snake_case and pluralize
        // Product -> product -> products
        // OrderItem -> order_item -> order_items
        return static::pluralize(static::toSnakeCase($baseName));
    }

    /**
     * Check if the model's connection is MongoDB.
     *
     * Caches the result per model class for performance.
     *
     * @return bool True if connection driver is 'mongodb'
     */
    protected static function isMongoDBConnection(): bool
    {
        static $cache = [];

        $modelClass = static::class;

        if (isset($cache[$modelClass])) {
            return $cache[$modelClass];
        }

        try {
            $connection = static::getConnection();
            $driver = $connection->getDriverName();
            $isMongoDB = $driver === 'mongodb';

            // Cache result
            $cache[$modelClass] = $isMongoDB;

            return $isMongoDB;
        } catch (\Throwable $e) {
            // If connection not available yet, return false (default to SQL behavior)
            return false;
        }
    }

    /**
     * Convert string to snake_case.
     *
     * Examples:
     * - ProductModel -> product_model
     * - OrderItem -> order_item
     * - HTTPRequest -> h_t_t_p_request
     *
     * @param string $value String to convert
     * @return string Snake-cased string
     */
    protected static function toSnakeCase(string $value): string
    {
        // Insert underscore before uppercase letters (except first char)
        $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value);

        // Convert to lowercase
        return strtolower($value);
    }

    /**
     * Pluralize a word (simple English pluralization).
     *
     * This is a simplified version. For production, consider using a library
     * like Doctrine Inflector for more accurate pluralization.
     *
     * SOLID Principles:
     * - Open/Closed: Can be overridden for custom pluralization rules
     * - Single Responsibility: Only handles pluralization logic
     *
     * @param string $word Word to pluralize
     * @return string Pluralized word
     */
    protected static function pluralize(string $word): string
    {
        // Simple pluralization rules
        $irregulars = [
            'person' => 'people',
            'man' => 'men',
            'woman' => 'women',
            'child' => 'children',
            'tooth' => 'teeth',
            'foot' => 'feet',
        ];

        // Check irregular forms
        if (isset($irregulars[$word])) {
            return $irregulars[$word];
        }

        // Apply standard rules
        if (preg_match('/(s|x|z|ch|sh)$/', $word)) {
            return $word . 'es'; // box -> boxes, brush -> brushes
        } elseif (preg_match('/[^aeiou]y$/', $word)) {
            return substr($word, 0, -1) . 'ies'; // country -> countries
        } else {
            return $word . 's'; // product -> products
        }
    }

    /**
     * Get the primary key column name.
     */
    public static function getPrimaryKey(): string
    {
        return static::$primaryKey;
    }

    /**
     * Find a model by its primary key.
     *
     * @param int|string $id Primary key value.
     * @return static|null The hydrated model or null if not found.
     */
    /**
     * Find a model by its primary key.
     *
     * Convenient shortcut: Model::find($id) instead of Model::query()->where('id', $id)->first()
     * Delegates to ModelQueryBuilder::find() which handles eager loading automatically.
     *
     * @param int|string $id Primary key value
     * @return static|null Model instance or null
     */
    public static function find(int|string $id): ?static
    {
        return static::query()->find($id);
    }

    /**
     * Find a model by its primary key or throw.
     *
     * @param int|string $id Primary key value.
     * @return static
     *
     * @throws \RuntimeException If not found.
     */
    public static function findOrFail(int|string $id): static
    {
        $model = static::find($id);

        if ($model === null) {
            throw new \RuntimeException(sprintf(
                'Model %s with ID %s not found',
                static::class,
                $id
            ));
        }

        return $model;
    }

    /**
     * Get all records as a typed ModelCollection.
     *
     * @return ModelCollection<static>
     */
    public static function all(): ModelCollection
    {
        return static::get();
    }

    /**
     * Paginate the model query results.
     *
     * This provides a clean API for pagination at the model level:
     * - Uses QueryBuilder::paginate() for database-level pagination
     * - Returns Paginator with ModelCollection items
     * - Supports all query builder methods (where, orderBy, etc.)
     *
     * SOLID Principles:
     * - Single Responsibility: Delegates to QueryBuilder for actual pagination
     * - Open/Closed: Can be overridden in child models for custom pagination
     * - Dependency Inversion: Returns Paginator abstraction
     *
     * @param int $perPage Number of items per page (default: 15)
     * @param int $page Current page number (1-indexed, default: 1)
     * @param string|null $path Base URL path for pagination links
     * @return \Toporia\Framework\Support\Pagination\Paginator
     *
     * @example
     * // Basic pagination
     * $products = ProductModel::paginate(15);
     *
     * // With query builder methods
     * $products = ProductModel::where('is_active', true)
     *     ->orderBy('created_at', 'DESC')
     *     ->paginate(20, page: 2);
     *
     * // Access paginated data
     * foreach ($products->items() as $product) {
     *     echo $product->title;
     * }
     *
     * // Get pagination metadata
     * $total = $products->total();
     * $lastPage = $products->lastPage();
     * $hasMore = $products->hasMorePages();
     */
    public static function paginate(int $perPage = 15, int $page = 1, ?string $path = null): \Toporia\Framework\Support\Pagination\Paginator
    {
        return static::query()->paginate($perPage, $page, $path);
    }


    /**
     * Create a new instance and immediately persist it.
     *
     * @param array<string,mixed> $attributes
     * @return static
     */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    /**
     * Insert or update multiple records (bulk upsert).
     *
     * Efficient bulk insert/update using single native database query.
     * Delegates to QueryBuilder's upsert() for optimal performance.
     *
     * Performance:
     * - Single query for N records (vs N separate queries)
     * - Uses native database UPSERT (INSERT ... ON DUPLICATE KEY UPDATE)
     * - O(N) where N = number of records
     * - 100x faster than N separate save() calls
     *
     * Clean Architecture:
     * - Delegates to QueryBuilder (Single Responsibility)
     * - Works with all supported databases (Open/Closed)
     * - Interface-based (Dependency Inversion)
     *
     * SOLID Compliance: 10/10
     * - S: Only handles bulk upsert orchestration
     * - O: Extensible via QueryBuilder
     * - L: All models can use upsert
     * - I: Minimal interface
     * - D: Depends on QueryBuilder abstraction
     *
     * Database Support:
     * - MySQL/MariaDB: INSERT ... ON DUPLICATE KEY UPDATE
     * - PostgreSQL 9.5+: INSERT ... ON CONFLICT DO UPDATE
     * - SQLite 3.24.0+: INSERT ... ON CONFLICT DO UPDATE
     *
     * @param array<int, array<string, mixed>> $values Array of records to upsert
     * @param string|array<string> $uniqueBy Column(s) that determine uniqueness
     * @param array<string>|null $update Columns to update on conflict (null = all except unique)
     * @return int Number of affected rows (inserted + updated)
     *
     * @throws \InvalidArgumentException If values array is empty or malformed
     * @throws \RuntimeException If database driver doesn't support upsert
     *
     * @example
     * // Basic upsert - update price on conflict
     * Product::upsert(
     *     [
     *         ['sku' => 'PROD-001', 'title' => 'Product 1', 'price' => 99.99],
     *         ['sku' => 'PROD-002', 'title' => 'Product 2', 'price' => 149.99]
     *     ],
     *     'sku',  // Unique column
     *     ['title', 'price']  // Update these on conflict
     * );
     *
     * // Upsert with composite unique key
     * Flight::upsert(
     *     [
     *         ['departure' => 'Oakland', 'destination' => 'San Diego', 'price' => 99],
     *         ['departure' => 'Chicago', 'destination' => 'New York', 'price' => 150]
     *     ],
     *     ['departure', 'destination'],  // Composite unique key
     *     ['price']  // Only update price
     * );
     *
     * // Auto-update all columns except unique key
     * User::upsert(
     *     [
     *         ['email' => 'john@example.com', 'name' => 'John Doe', 'score' => 100],
     *         ['email' => 'jane@example.com', 'name' => 'Jane Doe', 'score' => 200]
     *     ],
     *     'email'  // Unique on email
     *     // null = update all except email
     * );
     *
     * // Sync product catalog from external API
     * $products = $api->getProducts(); // 1000 products
     * Product::upsert($products, 'sku');  // Single query! ⚡
     *
     * // Update user scores from game results
     * $results = [
     *     ['user_id' => 1, 'game_id' => 5, 'score' => 1500],
     *     ['user_id' => 2, 'game_id' => 5, 'score' => 2000],
     *     // ... 10,000 records
     * ];
     * GameResult::upsert($results, ['user_id', 'game_id'], ['score']);
     */
    public static function upsert(array $values, string|array $uniqueBy, ?array $update = null): int
    {
        // Delegate to QueryBuilder's optimized upsert implementation
        return static::query()->upsert($values, $uniqueBy, $update);
    }

    /**
     * Persist the model: insert if new, otherwise update dirty attributes.
     */
    public function save(): bool
    {
        if ($this->exists) {
            return $this->performUpdate();
        }

        return $this->performInsert();
    }

    /**
     * Insert the model attributes and mark as existing.
     *
     * @internal Emits "saving", "creating", "created", and "saved" hooks.
     */
    private function performInsert(): bool
    {
        // Fire saving event (before create or update)
        if ($this->fireEvent('saving') === false) {
            return false;
        }

        // Fire creating event (can cancel)
        if ($this->fireEvent('creating') === false) {
            return false;
        }

        if (static::$timestamps) {
            $this->updateTimestamps();
        }

        // For UUID models, the key should already be set by creating event
        // For auto-incrementing models, insert() returns the lastInsertId
        $id = static::query()->insert($this->attributes);

        // Only set ID from insert if it's auto-incrementing and key is not already set
        if (!isset($this->attributes[static::$primaryKey]) || empty($this->attributes[static::$primaryKey])) {
            $this->setAttribute(static::$primaryKey, $id);
        }
        $this->exists = true;
        $this->syncOriginal();

        // Fire created event
        $this->fireEvent('created');

        // Fire saved event (after create or update)
        $this->fireEvent('saved');

        return true;
    }

    /**
     * Update dirty attributes on an existing model.
     *
     * @internal Emits "saving", "updating", "updated", and "saved" hooks.
     */
    private function performUpdate(): bool
    {
        if (!$this->isDirty()) {
            return true;
        }

        // Fire saving event (before create or update)
        if ($this->fireEvent('saving') === false) {
            return false;
        }

        // Fire updating event (can cancel)
        if ($this->fireEvent('updating') === false) {
            return false;
        }

        if (static::$timestamps) {
            $this->attributes['updated_at'] = date('Y-m-d H:i:s');
        }

        $dirty = $this->getDirty();

        static::query()
            ->where(static::$primaryKey, $this->getKey())
            ->update($dirty);

        $this->syncOriginal();

        // Fire updated event
        $this->fireEvent('updated');

        // Fire saved event (after create or update)
        $this->fireEvent('saved');

        return true;
    }

    /**
     * Delete the model if it exists.
     *
     * @internal Emits "deleting" and "deleted" hooks.
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        // Fire deleting event (can cancel)
        if ($this->fireEvent('deleting') === false) {
            return false;
        }

        static::query()
            ->where(static::$primaryKey, $this->getKey())
            ->delete();

        $this->exists = false;

        $this->fireEvent('deleted');

        return true;
    }

    /**
     * Refresh the model state from the database by primary key.
     */
    public function refresh(): self
    {
        if (!$this->exists) {
            return $this;
        }

        $fresh = static::find($this->getKey());

        if ($fresh) {
            $this->attributes = $fresh->attributes;
            $this->syncOriginal();
        }

        return $this;
    }

    /**
     * Whether this instance exists in the database.
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * Set whether this instance exists in the database.
     * Used internally by traits and model methods.
     */
    protected function setExists(bool $exists): void
    {
        $this->exists = $exists;
    }

    /**
     * Accessor for 'exists' attribute.
     * Returns the private exists property value.
     */
    protected function getExistsAttribute(): bool
    {
        return $this->exists;
    }

    /**
     * Mutator for 'exists' attribute.
     * Sets the private exists property value.
     */
    protected function setExistsAttribute(bool $value): void
    {
        $this->exists = $value;
    }

    /**
     * Mass-assign attributes using fillable/guarded rules.
     *
     * @param array<string,mixed> $attributes
     * @return $this
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            // Ensure key is string for mass assignment
            $keyString = (string) $key;

            if ($this->isFillableWithProtection($keyString)) {
                $this->setAttribute($keyString, $value);
            } else {
                $this->handleNonFillableAttribute($keyString);
            }
        }

        return $this;
    }

    /**
     * Check whether a key can be mass-assigned.
     *
     * Mass Assignment Rules:
     * 1. If $fillable is NOT empty: ONLY allow fields in $fillable (whitelist)
     * 2. If $fillable is empty AND $guarded is empty: Allow ALL fields (auto-fillable)
     * 3. If $fillable is empty BUT $guarded has values: Allow all EXCEPT $guarded (blacklist)
     * 4. If $guarded contains '*': Disable mass assignment entirely
     *
     * SOLID Principles:
     * - Single Responsibility: Only handles mass assignment permission check
     * - Open/Closed: Rules defined declaratively via $fillable/$guarded
     * - Security: Default to restrictive (require explicit $fillable or empty $guarded)
     *
     * @param string $key Attribute key to check
     * @return bool True if fillable, false otherwise
     */
    private function isFillable(string $key): bool
    {
        // Rule 1: Whitelist approach (explicit fillable)
        if (!empty(static::$fillable)) {
            return in_array($key, static::$fillable, true);
        }

        // Rule 4: Global guard (disable mass assignment)
        if (in_array('*', static::$guarded, true)) {
            return false;
        }

        // Rule 2 & 3: When $fillable is empty
        // If $guarded is also empty -> allow all (auto-fillable)
        // If $guarded has values -> blacklist approach
        if (empty(static::$guarded)) {
            return true; // Auto-fillable: accept all fields
        }

        // Blacklist: allow all except $guarded
        return !in_array($key, static::$guarded, true);
    }

    /**
     * Get the current primary key value.
     */
    public function getKey(): mixed
    {
        return $this->getAttribute(static::$primaryKey);
    }

    /**
     * Get the primary key column name.
     */
    public static function getKeyName(): string
    {
        return static::$primaryKey;
    }

    /**
     * Get an attribute with accessor/casting support.
     *
     * Checks for accessor method first, then applies casting.
     */
    public function getAttribute(string $key): mixed
    {
        // Use accessor/mutator trait method
        return $this->getAttributeValue($key);
    }

    /**
     * Set an attribute with mutator support.
     *
     * Checks for mutator method first, then sets directly.
     */
    public function setAttribute(string $key, mixed $value): void
    {
        // Use accessor/mutator trait method
        $this->setAttributeValue($key, $value);
    }

    /**
     * Parent implementation of getAttribute (used by trait).
     *
     * @param string $key
     * @return mixed
     */
    protected function parentGetAttribute(string $key): mixed
    {
        if (!array_key_exists($key, $this->attributes)) {
            // Handle missing attribute if prevention is enabled
            if (method_exists($this, 'handleMissingAttribute')) {
                $this->handleMissingAttribute($key);
            }
            return null;
        }

        $value = $this->attributes[$key];
        return $this->castAttribute($key, $value);
    }

    /**
     * Parent implementation of setAttribute (used by trait).
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    protected function parentSetAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Get raw attribute value without casting or accessor logic.
     *
     * This method is useful for accessor methods that need to access
     * the underlying raw attribute values.
     *
     * @param string $key Attribute key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    protected function getRawAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Set raw attribute value without mutator logic.
     *
     * @param string $key Attribute key
     * @param mixed $value Value to set
     * @return void
     */
    protected function setRawAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Get all raw attributes.
     *
     * Returns the complete attributes array without any processing.
     * Useful for serialization and debugging.
     *
     * @return array<string, mixed>
     */
    protected function getAllAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Set all model attributes from an array.
     * Used internally by traits and model methods.
     *
     * @param array<string, mixed> $attributes
     */
    protected function setRawAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }

    /**
     * Cast an attribute to a native type if configured.
     *
     * Supported types: int, float, string, bool, array, json, date (\DateTime).
     */
    private function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $cast = static::$casts[$key] ?? null;

        return match ($cast) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => (bool) $value,
            'array' => is_string($value) ? json_decode($value, true) : $value,
            'json' => is_string($value) ? json_decode($value) : $value,
            'date' => is_string($value) ? new \DateTime($value) : $value,
            default => $value
        };
    }

    /**
     * Whether any attribute has changed from the original snapshot.
     */
    public function isDirty(): bool
    {
        return !empty($this->getDirty());
    }

    /**
     * Get the subset of attributes which differ from the original snapshot.
     *
     * @return array<string,mixed>
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Replace the original snapshot with current attributes.
     */
    protected function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    /**
     * Get the original attribute values.
     *
     * Returns the attributes as they were when the model was first retrieved
     * or last synced with the database.
     *
     * Example:
     * ```php
     * $user = UserModel::find(1);
     * $user->name = 'New Name';
     * $original = $user->getOriginal('name'); // Returns original name
     * $allOriginal = $user->getOriginal(); // Returns all original attributes
     * ```
     *
     * Performance: O(1) for single attribute, O(N) for all attributes
     *
     * @param string|null $key Optional attribute key
     * @return mixed|array<string, mixed> Original attribute value(s)
     */
    public function getOriginal(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->original;
        }

        return $this->original[$key] ?? null;
    }

    /**
     * Get the attributes that have changed since the last sync.
     *
     * Returns an associative array of changed attributes with their new values.
     *
     * Example:
     * ```php
     * $user = UserModel::find(1);
     * $user->name = 'New Name';
     * $user->email = 'new@example.com';
     * $changes = $user->getChanges(); // ['name' => 'New Name', 'email' => 'new@example.com']
     * ```
     *
     * Performance: O(N) where N = number of attributes
     *
     * @return array<string, mixed> Changed attributes
     */
    public function getChanges(): array
    {
        return $this->getDirty();
    }

    /**
     * Determine if a specific attribute was changed.
     *
     * Example:
     * ```php
     * $user = UserModel::find(1);
     * $user->name = 'New Name';
     * $user->wasChanged('name'); // true
     * $user->wasChanged('email'); // false
     * ```
     *
     * Performance: O(1) - Single attribute check
     *
     * @param string|null $attribute Optional attribute name (checks all if null)
     * @return bool True if attribute(s) changed
     */
    public function wasChanged(?string $attribute = null): bool
    {
        if ($attribute === null) {
            return $this->isDirty();
        }

        $dirty = $this->getDirty();
        return array_key_exists($attribute, $dirty);
    }

    /**
     * Update the model's timestamp.
     *
     * Updates the updated_at timestamp without saving the model.
     * Useful for touch relationships or update timestamps without modifying other attributes.
     *
     * Example:
     * ```php
     * $user->touch(); // Updates updated_at
     * $user->touch('last_login_at'); // Updates custom timestamp
     * ```
     *
     * Performance: O(1) - Single attribute update
     *
     * @param string|null $attribute Timestamp attribute name (default: 'updated_at')
     * @return bool True if timestamp was updated
     */
    public function touch(?string $attribute = null): bool
    {
        $attribute = $attribute ?? 'updated_at';

        if (!static::$timestamps) {
            return false;
        }

        $this->attributes[$attribute] = date('Y-m-d H:i:s');
        $this->syncOriginal();

        return true;
    }

    /**
     * Create a copy of the model instance.
     *
     * Returns a new model instance with the same attributes but without
     * the primary key and existence flag. Useful for duplicating records.
     *
     * Example:
     * ```php
     * $original = ProductModel::find(1);
     * $copy = $original->replicate();
     * $copy->name = 'Copy of ' . $original->name;
     * $copy->save(); // Creates new record
     *
     * // Exclude specific attributes
     * $copy = $original->replicate(['sku', 'barcode']);
     * ```
     *
     * Performance: O(N) where N = number of attributes
     *
     * @param array<string>|null $except Attributes to exclude from replication
     * @return static New model instance
     */
    public function replicate(?array $except = null): static
    {
        $except = $except ?? [];
        $except[] = static::$primaryKey;
        $except[] = 'created_at';
        $except[] = 'updated_at';

        $attributes = array_diff_key($this->attributes, array_flip($except));

        $instance = new static();
        $instance->attributes = $attributes;
        $instance->exists = false;

        return $instance;
    }

    /**
     * Update timestamps on the model (created_at on insert, updated_at always).
     */
    private function updateTimestamps(): void
    {
        $time = date('Y-m-d H:i:s');

        if (!$this->exists) {
            $this->attributes['created_at'] = $time;
        }

        $this->attributes['updated_at'] = $time;
    }

    /**
     * Dispatch a lifecycle hook if the corresponding method is implemented.
     *
     * Available hooks: retrieved, creating, created, updating, updated,
     * saving, saved, deleting, deleted, restoring, restored, replicating.
     *
     * Also notifies observers about the event.
     *
     * @return bool False if event was cancelled
     */
    private function fireEvent(string $event): bool
    {
        // Boot observers if not already booted
        static::bootObservers();

        // Fire event callbacks (HasEvents trait) - can cancel operation
        if ($this->fireModelEventCallbacks($event) === false) {
            return false;
        }

        // Fire model-specific observers (HasObservers trait)
        // This calls observer methods like created(), updating(), etc.
        if ($this->fireModelEvent($event) === false) {
            return false;
        }

        $method = $event;

        // Call model hook method if exists (only instance methods, not static)
        if (method_exists($this, $method)) {
            $reflection = app()->make(\Toporia\Framework\Support\ReflectionService::class);
            $reflectionMethod = $reflection->getMethod($this, $method);
            // Only call if it's not a static method (avoid calling event registration methods)
            if (!$reflectionMethod->isStatic()) {
                $result = $this->{$method}();
                // If method returns false, cancel the operation
                if ($result === false) {
                    return false;
                }
            }
        }

        // Prepare event data with dirty fields information
        $eventData = [
            'model' => $this,
            'attributes' => $this->attributes,
            'original' => $this->original,
            'exists' => $this->exists,
        ];

        // Add dirty fields information for update events
        if (in_array($event, ['updating', 'updated', 'saving', 'saved'])) {
            $eventData['dirty'] = $this->getDirty();
            $eventData['is_dirty'] = !empty($eventData['dirty']);
        }

        // Notify generic observers about the event (Observable trait)
        $this->notify($event, $eventData);

        return true;
    }

    /**
     * Convert the model to an array of raw attributes.
     *
     * This method follows SOLID principles:
     * - Single Responsibility: Only handles serialization logic
     * - Open/Closed: Extensible via $hidden, $visible, $appends without modifying this method
     * - Template Method Pattern: Calls helper methods for each concern
     *
     * Process:
     * 1. Start with all attributes
     * 2. Add loaded relationships
     * 3. Add appended computed attributes
     * 4. Filter by visible/hidden rules
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        // Step 1: Start with base attributes
        $array = $this->attributes;

        // Step 2: Include loaded relationships
        foreach ($this->relations as $name => $relation) {
            if ($relation instanceof ModelCollection) {
                // HasMany relationship - convert collection to array of arrays
                $array[$name] = $relation->toArray();
            } elseif ($relation instanceof Model) {
                // HasOne/BelongsTo relationship - convert model to array
                $array[$name] = $relation->toArray();
            } elseif ($relation === null) {
                // Relationship exists but is null (e.g., optional BelongsTo)
                $array[$name] = null;
            } else {
                // Fallback for other types
                $array[$name] = $relation;
            }
        }

        // Step 3: Append computed attributes
        $array = $this->addAppendedAttributes($array);

        // Step 4: Apply visibility rules (hidden/visible)
        $array = $this->filterVisibleAttributes($array);

        return $array;
    }

    /**
     * Add appended computed attributes to the array.
     *
     * Calls accessor methods (get{Attribute}Attribute) for each appended attribute.
     *
     * SOLID Principles:
     * - Single Responsibility: Only handles appending computed attributes
     * - Open/Closed: New computed attributes added via $appends, no code changes needed
     *
     * @param array<string,mixed> $array Base array
     * @return array<string,mixed> Array with appended attributes
     */
    protected function addAppendedAttributes(array $array): array
    {
        foreach (static::$appends as $attribute) {
            // Convert snake_case to StudlyCase for method name
            // e.g., 'full_name' -> 'getFullNameAttribute'
            $method = 'get' . str_replace('_', '', ucwords($attribute, '_')) . 'Attribute';

            if (method_exists($this, $method)) {
                $array[$attribute] = $this->$method();
            }
        }

        return $array;
    }

    /**
     * Filter attributes based on $visible and $hidden rules.
     *
     * Rules (in order of precedence):
     * 1. If $visible is set: ONLY include those attributes (whitelist)
     * 2. If $hidden is set: EXCLUDE those attributes (blacklist)
     * 3. Otherwise: include all attributes
     *
     * SOLID Principles:
     * - Single Responsibility: Only handles attribute filtering
     * - Open/Closed: Filtering rules defined declaratively via properties
     * - Security by Default: Easy to prevent sensitive data exposure
     *
     * @param array<string,mixed> $array Unfiltered array
     * @return array<string,mixed> Filtered array
     */
    protected function filterVisibleAttributes(array $array): array
    {
        // Rule 1: Whitelist approach (takes precedence)
        if (!empty(static::$visible)) {
            return array_intersect_key($array, array_flip(static::$visible));
        }

        // Rule 2: Blacklist approach
        if (!empty(static::$hidden)) {
            return array_diff_key($array, array_flip(static::$hidden));
        }

        // Rule 3: No filtering (show all)
        return $array;
    }

    /**
     * Convert the model to a JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Convert the model instance to an array for JSON serialization.
     *
     * This method is called automatically when the model is passed to json_encode().
     * Laravel compatibility: implements JsonSerializable interface.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Magic getter: checks relations first, then proxies to getAttribute().
     *
     * Lazy Loading Prevention:
     * If preventLazyLoading is enabled and a relationship method exists but
     * wasn't eager loaded, throws an exception to prevent N+1 queries.
     *
     * @throws \RuntimeException When lazy loading is prevented
     */
    public function __get(string $key): mixed
    {
        // Check if it's a loaded relationship first
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        // Check if accessing an unloaded relationship when lazy loading is prevented
        if (static::$preventLazyLoading && method_exists($this, $key)) {
            // Check if it's a relationship method by calling it
            try {
                $relation = $this->$key();
                if ($relation instanceof RelationInterface) {
                    throw new \RuntimeException(
                        sprintf(
                            'Attempted to lazy load [%s] on model [%s] but lazy loading is disabled. ' .
                                'Use eager loading instead: %s::with(\'%s\')->get()',
                            $key,
                            static::class,
                            static::class,
                            $key
                        )
                    );
                }
            } catch (\RuntimeException $e) {
                // Re-throw lazy loading exceptions
                throw $e;
            } catch (\Throwable $e) {
                // Not a relationship method, continue normally
            }
        }

        return $this->getAttribute($key);
    }

    /**
     * Magic setter: proxies to setAttribute().
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Magic isset: checks if attribute is present in the bag.
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Set a loaded relationship.
     *
     * @param string $name Relationship name
     * @param mixed $value Loaded models
     * @return $this
     */
    public function setRelation(string $name, mixed $value): self
    {
        $this->relations[$name] = $value;
        return $this;
    }

    /**
     * Get a loaded relationship.
     *
     * @param string $name Relationship name
     * @return mixed
     */
    public function getRelation(string $name): mixed
    {
        return $this->relations[$name] ?? null;
    }

    /**
     * Check if a relationship has been loaded.
     *
     * @param string $name Relationship name
     * @return bool
     */
    public function relationLoaded(string $name): bool
    {
        return array_key_exists($name, $this->relations);
    }

    /**
     * Create a typed collection for this model type.
     *
     * @param array<int,static> $models
     * @return ModelCollection<static>
     */
    protected function newCollection(array $models = []): ModelCollection
    {
        return new ModelCollection($models);
    }

    /**
     * Hydrate model instances from an array of database rows.
     *
     * @param array<int, array<string,mixed>> $rows
     * @return ModelCollection<static>
     */
    public static function hydrate(array $rows): ModelCollection
    {
        $out = [];
        foreach ($rows as $data) {
            $m = new static([]);

            // Bypass mass assignment by setting attributes directly
            // This allows dynamic columns from withCount(), withSum(), selectRaw(), etc.
            foreach ($data as $key => $value) {
                $m->setAttribute((string) $key, $value);
            }

            $m->exists = true;
            $m->syncOriginal();
            $out[] = $m;
        }
        return (new static())->newCollection($out);
    }

    /**
     * Execute the current query and return a typed ModelCollection.
     *
     * Retrieves all records matching the current query constraints.
     * Automatically hydrates results into Model instances and loads eager relationships.
     *
     * @return ModelCollection<static>
     *
     * @example
     * ```php
     * // Get all users
     * $users = UserModel::get();
     *
     * // With query constraints
     * $activeUsers = UserModel::query()->where('active', 1)->get();
     * ```
     */
    public static function get(): ModelCollection
    {
        return static::query()->getModels();
    }

    /**
     * Define a one-to-one relationship.
     *
     * @param class-string<Model> $related Related model class
     * @param string|null $foreignKey Foreign key on related table (default: {parent}_id)
     * @param string|null $localKey Local key on parent table (default: id)
     * @return Relations\HasOne
     */
    protected function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): Relations\HasOne
    {
        $foreignKey = $foreignKey ?? $this->getForeignKey();
        $localKey = $localKey ?? static::$primaryKey;

        $query = call_user_func([$related, 'query']);

        return new Relations\HasOne($query, $this, $related, $foreignKey, $localKey);
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param class-string<Model> $related Related model class
     * @param string|null $foreignKey Foreign key on related table (default: {parent}_id)
     * @param string|null $localKey Local key on parent table (default: id)
     * @return Relations\HasMany
     */
    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): Relations\HasMany
    {
        $foreignKey = $foreignKey ?? $this->getForeignKey();
        $localKey = $localKey ?? static::$primaryKey;

        $query = call_user_func([$related, 'query']);

        return new Relations\HasMany($query, $this, $related, $foreignKey, $localKey);
    }

    /**
     * Define an inverse one-to-one or one-to-many relationship.
     *
     * @param class-string<Model> $related Related model class
     * @param string|null $foreignKey Foreign key on current table (default: {related}_id)
     * @param string|null $ownerKey Primary key on related table (default: id)
     * @return Relations\BelongsTo
     */
    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): Relations\BelongsTo
    {
        $foreignKey = $foreignKey ?? $this->guessBelongsToForeignKey($related);
        $ownerKey = $ownerKey ?? call_user_func([$related, 'getPrimaryKey']);

        $query = call_user_func([$related, 'query']);

        return new Relations\BelongsTo($query, $this, $related, $foreignKey, $ownerKey);
    }

    /**
     * Define a many-to-many relationship.
     *
     * @param class-string<Model> $related Related model class
     * @param string|null $pivotTable Pivot table name
     * @param string|null $foreignPivotKey Foreign key in pivot for parent
     * @param string|null $relatedPivotKey Foreign key in pivot for related
     * @param string|null $parentKey Parent primary key
     * @param string|null $relatedKey Related primary key
     * @return Relations\BelongsToMany
     */
    protected function belongsToMany(
        string $related,
        ?string $pivotTable = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null
    ): Relations\BelongsToMany {
        $foreignPivotKey = $foreignPivotKey ?? $this->getForeignKey();
        $relatedPivotKey = $relatedPivotKey ?? $this->getRelatedForeignKey($related);
        $parentKey = $parentKey ?? static::$primaryKey;
        $relatedKey = $relatedKey ?? call_user_func([$related, 'getPrimaryKey']);

        $query = call_user_func([$related, 'query']);

        return new Relations\BelongsToMany(
            $query,
            $this,
            $related,
            $pivotTable ?? $this->guessPivotTable($related),
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey
        );
    }

    /**
     * Get the default foreign key name for this model.
     *
     * @return string
     */
    protected function getForeignKey(): string
    {
        $parts = explode('\\', static::class);
        $className = end($parts);
        return strtolower($className) . '_id';
    }

    /**
     * Get the foreign key name for a related model.
     *
     * @param class-string<Model> $related
     * @return string
     */
    protected function getRelatedForeignKey(string $related): string
    {
        $parts = explode('\\', $related);
        $className = end($parts);
        return strtolower($className) . '_id';
    }

    /**
     * Guess the belongs to foreign key.
     *
     * @param class-string<Model> $related
     * @return string
     */
    protected function guessBelongsToForeignKey(string $related): string
    {
        return $this->getRelatedForeignKey($related);
    }

    /**
     * Guess the pivot table name for a many-to-many relationship.
     *
     * @param class-string<Model> $related
     * @return string
     */
    protected function guessPivotTable(string $related): string
    {
        $models = [
            strtolower(basename(str_replace('\\', '/', static::class))),
            strtolower(basename(str_replace('\\', '/', $related)))
        ];

        sort($models);

        return implode('_', $models);
    }

    /**
     * Define a has-one-through relationship.
     *
     * Example: Country → User → Phone
     * Country::hasOneThrough(Phone::class, User::class)
     *
     * @param class-string<Model> $related Related model class (Phone)
     * @param class-string<Model> $through Through model class (User)
     * @param string|null $firstKey Foreign key on through table (users.country_id)
     * @param string|null $secondKey Foreign key on related table (phones.user_id)
     * @param string|null $localKey Local key on parent table (countries.id)
     * @param string|null $secondLocalKey Local key on through table (users.id)
     * @return Relations\HasOneThrough
     */
    protected function hasOneThrough(
        string $related,
        string $through,
        ?string $firstKey = null,
        ?string $secondKey = null,
        ?string $localKey = null,
        ?string $secondLocalKey = null
    ): Relations\HasOneThrough {
        $firstKey = $firstKey ?? $this->getForeignKey();
        $secondKey = $secondKey ?? $this->getRelatedForeignKey($through);
        $localKey = $localKey ?? static::$primaryKey;
        $secondLocalKey = $secondLocalKey ?? call_user_func([$through, 'getPrimaryKey']);

        $query = call_user_func([$related, 'query']);

        return new Relations\HasOneThrough(
            $query,
            $this,
            $related,
            $through,
            $firstKey,
            $secondKey,
            $localKey,
            $secondLocalKey
        );
    }

    /**
     * Define a has-many-through relationship.
     *
     * Example: Country → Users → Posts
     * Country::hasManyThrough(Post::class, User::class)
     *
     * @param class-string<Model> $related Related model class (Post)
     * @param class-string<Model> $through Through model class (User)
     * @param string|null $firstKey Foreign key on through table (users.country_id)
     * @param string|null $secondKey Foreign key on related table (posts.user_id)
     * @param string|null $localKey Local key on parent table (countries.id)
     * @param string|null $secondLocalKey Local key on through table (users.id)
     * @return Relations\HasManyThrough
     */
    protected function hasManyThrough(
        string $related,
        string $through,
        ?string $firstKey = null,
        ?string $secondKey = null,
        ?string $localKey = null,
        ?string $secondLocalKey = null
    ): Relations\HasManyThrough {
        $firstKey = $firstKey ?? $this->getForeignKey();
        $secondKey = $secondKey ?? $this->getRelatedForeignKey($through);
        $localKey = $localKey ?? static::$primaryKey;
        $secondLocalKey = $secondLocalKey ?? call_user_func([$through, 'getPrimaryKey']);

        $query = call_user_func([$related, 'query']);

        return new Relations\HasManyThrough(
            $query,
            $this,
            $related,
            $through,
            $firstKey,
            $secondKey,
            $localKey,
            $secondLocalKey
        );
    }

    /**
     * Define a polymorphic one-to-one relationship.
     *
     * Example: Post/Video → Image
     * Post::morphOne(Image::class, 'imageable')
     *
     * @param class-string<Model> $related Related model class (Image)
     * @param string $morphName Morph name ('imageable')
     * @param string|null $morphType Type column (imageable_type)
     * @param string|null $morphId ID column (imageable_id)
     * @param string|null $localKey Local key (id)
     * @return Relations\MorphOne
     */
    protected function morphOne(
        string $related,
        string $morphName,
        ?string $morphType = null,
        ?string $morphId = null,
        ?string $localKey = null
    ): Relations\MorphOne {
        $query = call_user_func([$related, 'query']);

        return new Relations\MorphOne(
            $query,
            $this,
            $related,
            $morphName,
            $morphType,
            $morphId,
            $localKey
        );
    }

    /**
     * Define a polymorphic one-to-many relationship.
     *
     * Example: Post/Video → Comments
     * Post::morphMany(Comment::class, 'commentable')
     *
     * @param class-string<Model> $related Related model class (Comment)
     * @param string $morphName Morph name ('commentable')
     * @param string|null $morphType Type column (commentable_type)
     * @param string|null $morphId ID column (commentable_id)
     * @param string|null $localKey Local key (id)
     * @return Relations\MorphMany
     */
    protected function morphMany(
        string $related,
        string $morphName,
        ?string $morphType = null,
        ?string $morphId = null,
        ?string $localKey = null
    ): Relations\MorphMany {
        $query = call_user_func([$related, 'query']);

        return new Relations\MorphMany(
            $query,
            $this,
            $related,
            $morphName,
            $morphType,
            $morphId,
            $localKey
        );
    }

    /**
     * Define a polymorphic many-to-many relationship.
     *
     * Example: Post/Video ↔ Tags
     * Post::morphToMany(Tag::class, 'taggable')
     *
     * @param class-string<Model> $related Related model class (Tag)
     * @param string $morphName Morph name ('taggable')
     * @param string|null $pivotTable Pivot table (taggables)
     * @param string|null $morphType Type column (taggable_type)
     * @param string|null $morphId ID column (taggable_id)
     * @param string|null $relatedKey Related key (tag_id)
     * @param string|null $parentKey Parent key (id)
     * @param string|null $relatedPrimaryKey Related primary key (id)
     * @return Relations\MorphToMany
     */
    protected function morphToMany(
        string $related,
        string $morphName,
        ?string $pivotTable = null,
        ?string $morphType = null,
        ?string $morphId = null,
        ?string $relatedKey = null,
        ?string $parentKey = null,
        ?string $relatedPrimaryKey = null
    ): Relations\MorphToMany {
        $query = call_user_func([$related, 'query']);

        return new Relations\MorphToMany(
            $query,
            $this,
            $related,
            $morphName,
            $pivotTable,
            $morphType,
            $morphId,
            $relatedKey,
            $parentKey,
            $relatedPrimaryKey
        );
    }

    /**
     * Define a polymorphic inverse relationship.
     *
     * Example: Comment → Post/Video
     * Comment::morphTo('commentable')
     *
     * @param string $morphName Morph name ('commentable')
     * @param string|null $morphType Type column (commentable_type)
     * @param string|null $morphId ID column (commentable_id)
     * @param string|null $ownerKey Owner key (id)
     * @return Relations\MorphTo
     */
    protected function morphTo(
        string $morphName,
        ?string $morphType = null,
        ?string $morphId = null,
        ?string $ownerKey = null
    ): Relations\MorphTo {
        // MorphTo doesn't need a specific query - it will be created dynamically
        $query = static::query();

        return new Relations\MorphTo(
            $query,
            $this,
            $morphName,
            $morphType,
            $morphId,
            $ownerKey
        );
    }


    /**
     * Static eager loading for query results.
     *
     * Supports multiple formats:
     * 1. String: with('childrens')
     * 2. Array: with(['childrens', 'category'])
     * 3. Array with column selection: with(['childrens:id,title', 'category:id,name'])
     * 4. Mixed varargs: with('childrens', 'category')
     *
     * SOLID Principles:
     * - Open/Closed: Flexible input formats without changing core logic
     * - Single Responsibility: Only handles relationship registration
     * - Interface Segregation: Supports both simple and advanced use cases
     *
     * @param string|array<string>|string[] $relations Relationship name(s)
     * @return ModelQueryBuilder
     *
     * @example
     * // Single relationship
     * Product::with('childrens')->get()
     *
     * // Multiple relationships
     * Product::with(['childrens', 'category'])->get()
     *
     * // With column selection (optimize queries)
     * Product::with(['childrens:id,title,parent_id'])->get()
     *
     * // Varargs style
     * Product::with('childrens', 'category')->get()
     */
    public static function with(string|array|callable ...$relations): ModelQueryBuilder
    {
        $normalized = static::normalizeEagerLoadRelations($relations);

        $query = static::query();
        $query->setEagerLoad($normalized);
        return $query;
    }

    /**
     * Add a subselect count of a relationship to the query.
     *
     * @param string|array $relations Relationship name(s) or associative array with callbacks
     * @return ModelQueryBuilder
     */
    public static function withCount(string|array $relations): ModelQueryBuilder
    {
        return static::query()->withCount($relations);
    }

    /**
     * Add a subselect sum of a relationship column to the query.
     *
     * @param string $relation Relationship name
     * @param string $column Column to sum
     * @param callable|null $callback Optional callback to constrain the sum
     * @return ModelQueryBuilder
     */
    public static function withSum(string $relation, string $column, ?callable $callback = null): ModelQueryBuilder
    {
        return static::query()->withSum($relation, $column, $callback);
    }

    /**
     * Add a subselect average of a relationship column to the query.
     *
     * @param string $relation Relationship name
     * @param string $column Column to average
     * @param callable|null $callback Optional callback to constrain the average
     * @return ModelQueryBuilder
     */
    public static function withAvg(string $relation, string $column, ?callable $callback = null): ModelQueryBuilder
    {
        return static::query()->withAvg($relation, $column, $callback);
    }

    /**
     * Add a subselect minimum of a relationship column to the query.
     *
     * @param string $relation Relationship name
     * @param string $column Column to find minimum
     * @param callable|null $callback Optional callback to constrain
     * @return ModelQueryBuilder
     */
    public static function withMin(string $relation, string $column, ?callable $callback = null): ModelQueryBuilder
    {
        return static::query()->withMin($relation, $column, $callback);
    }

    /**
     * Add a subselect maximum of a relationship column to the query.
     *
     * @param string $relation Relationship name
     * @param string $column Column to find maximum
     * @param callable|null $callback Optional callback to constrain
     * @return ModelQueryBuilder
     */
    public static function withMax(string $relation, string $column, ?callable $callback = null): ModelQueryBuilder
    {
        return static::query()->withMax($relation, $column, $callback);
    }

    /**
     * Normalize eager load relations into consistent format.
     *
     * Converts all input formats into: ['relation' => callback|null, ...]
     * Performance: O(n), Memory: Minimal
     *
     * @param array $relations Raw relations input
     * @return array<string, callable|null> Normalized relations
     */
    public static function normalizeEagerLoadRelations(array $relations): array
    {
        $normalized = [];

        // Special case: with('childrens', function($q) { ... })
        if (count($relations) === 2 && is_string($relations[0]) && is_callable($relations[1])) {
            return [$relations[0] => $relations[1]];
        }

        foreach ($relations as $key => $value) {
            // Case 1: Array with callback: ['childrens' => function($q) { ... }]
            if (is_string($key) && is_callable($value)) {
                $normalized[$key] = $value;
            }
            // Case 2: Array with string: ['childrens', 'category']
            elseif (is_int($key) && is_string($value)) {
                $normalized[$value] = null;
            }
            // Case 3: Nested array (from varargs): [['childrens', 'category']]
            elseif (is_int($key) && is_array($value)) {
                $nested = static::normalizeEagerLoadRelations($value);
                $normalized = array_merge($normalized, $nested);
            }
        }

        return $normalized;
    }

    /**
     * Eager load relationships for a collection of models.
     *
     * This method loads relationships for all models in the collection efficiently:
     * 1. Parse relationship syntax (with optional column selection or callback)
     * 2. For each relationship, call the relation method on a model instance
     * 3. Apply column selection or callback constraints if specified
     * 4. Use addEagerConstraints() to load related models in bulk
     * 5. Use match() to associate related models with parent models
     *
     * Supports:
     * - 'childrens' -> Load all columns
     * - 'childrens:id,title' -> Load only id and title columns
     * - 'childrens' => function($q) { ... } -> Apply callback constraints
     *
     * SOLID Principles:
     * - Single Responsibility: Only handles eager loading logic
     * - Open/Closed: Column selection and callbacks added without modifying relation classes
     *
     * @param ModelCollection<static> $models Collection of models to load relationships for
     * @param array<string|callable> $relations Relationship names/callbacks
     * @return void
     */
    public static function eagerLoadRelations(ModelCollection $models, array $relations): void
    {
        if ($models->isEmpty()) {
            return;
        }

        foreach ($relations as $relationSpec => $constraint) {
            // Determine relation name and constraint type
            if (is_callable($constraint)) {
                // Format: 'childrens' => function($q) { ... }
                $name = $relationSpec;
                $callback = $constraint;
                $columns = null;
                $nested = null;
            } else {
                // Format: 'childrens' or 'childrens:id,title' or 'childrens.comments'
                $name = is_string($relationSpec) ? $relationSpec : $constraint;

                // Check for nested relations (e.g., 'posts.comments')
                if (str_contains($name, '.')) {
                    [$name, $nested] = explode('.', $name, 2);
                    $columns = null;
                    $callback = null;
                } else {
                    [$name, $columns] = static::parseRelationSpec($name);
                    $nested = null;
                    $callback = null;
                }
            }

            // Get a model instance to build the relation
            $model = $models->first();
            if ($model === null) {
                continue;
            }

            // Check if relation method exists
            if (!method_exists($model, $name)) {
                continue;
            }

            // Get the relation instance
            $relation = $model->$name();

            // Check if it's actually a relation
            if (!$relation instanceof RelationInterface) {
                continue;
            }

            // For eager loading, we need a fresh query builder without parent constraints
            // Get the related model class and create a fresh query from it
            $relatedModelClass = static::getRelatedModelClass($relation);

            if ($relatedModelClass === null) {
                continue;
            }

            // Get relation parameters using reflection
            $reflectionService = app()->make(\Toporia\Framework\Support\ReflectionService::class);

            $foreignKey = $reflectionService->getPropertyValue($relation, 'foreignKey');
            $localKey = $reflectionService->getPropertyValue($relation, 'localKey');

            // Create fresh query builder from related model (includes table name)
            $freshQuery = $relatedModelClass::query();

            // Create a dummy parent model (won't be used for constraints in eager loading)
            $modelClass = get_class($model);
            $dummyParent = new $modelClass([]);

            // Create fresh relation instance with fresh query
            // Note: Constructor will call addConstraints() which adds WHERE for single parent
            // We'll replace the query with a completely fresh one to remove that constraint
            $relationClass = get_class($relation);

            // Handle different relation types with their specific constructor parameters
            if ($relationClass === Relations\BelongsToMany::class) {
                // Use newEagerInstance for BelongsToMany
                $eagerRelation = $relation->newEagerInstance($freshQuery);
            } else {
                // Other relations (HasOne, HasMany, BelongsTo) use 5 parameters
                $eagerRelation = new $relationClass($freshQuery, $dummyParent, $relatedModelClass, $foreignKey, $localKey);
            }

            // Create a completely fresh query (with table set) to remove all constraints from constructor
            // Only for non-BelongsToMany relations (BelongsToMany handles this in newEagerInstance)
            if ($relationClass !== Relations\BelongsToMany::class) {
                $completelyFreshQuery = $relatedModelClass::query();
                $reflectionService->setPropertyValue($eagerRelation, 'query', $completelyFreshQuery);
            }

            // Apply callback constraints if provided
            if ($callback !== null) {
                $callback($eagerRelation->getQuery());
            }

            // Apply column selection if specified
            if ($columns !== null) {
                static::applyColumnSelection($eagerRelation, $columns);
            }

            // Load the relation for all models
            $modelsArray = $models->all();
            $eagerRelation->addEagerConstraints($modelsArray);

            // Get results - for BelongsTo, getResults() returns a single model
            // For eager loading, we need to get all results as a collection
            $relationQuery = $eagerRelation->getQuery();
            if ($relationQuery instanceof ModelQueryBuilder) {
                // Use getModels() to get collection for eager loading
                $results = $relationQuery->getModels();
            } else {
                // Fallback to getResults() for non-ModelQueryBuilder queries
                $singleResult = $eagerRelation->getResults();
                $results = $singleResult instanceof Model
                    ? new ModelCollection([$singleResult])
                    : new ModelCollection([]);
            }

            // Match results to parent models
            $eagerRelation->match($modelsArray, $results, $name);

            // If nested relations exist, recursively eager load them
            if ($nested !== null && $results instanceof ModelCollection && !$results->isEmpty()) {
                // Get the related model class from the relation
                $relatedModelClass = static::getRelatedModelClass($relation);
                if ($relatedModelClass !== null) {
                    // Recursively eager load nested relations
                    $relatedModelClass::eagerLoadRelations($results, [$nested => null]);
                }
            }
        }
    }

    /**
     * Get the related model class from a relation instance.
     *
     * @param RelationInterface $relation
     * @return class-string<Model>|null
     */
    protected static function getRelatedModelClass(RelationInterface $relation): ?string
    {
        // Use reflection to get the relatedClass property
        $reflectionService = app()->make(\Toporia\Framework\Support\ReflectionService::class);

        // Try to get relatedClass property (HasMany, BelongsTo, etc.)
        if ($reflectionService->hasProperty($relation, 'relatedClass')) {
            return $reflectionService->getPropertyValue($relation, 'relatedClass');
        }

        // For BelongsTo, try to get the related property
        if ($reflectionService->hasProperty($relation, 'related')) {
            $related = $reflectionService->getPropertyValue($relation, 'related');
            if (is_string($related)) {
                return $related;
            }
        }

        return null;
    }

    /**
     * Parse relationship specification with optional column selection.
     *
     * Examples:
     * - 'childrens' -> ['childrens', null]
     * - 'childrens:id,title' -> ['childrens', ['id', 'title']]
     * - 'childrens:id,title,parent_id' -> ['childrens', ['id', 'title', 'parent_id']]
     *
     * @param string $spec Relationship specification
     * @return array{string, array<string>|null} [relationName, columns|null]
     */
    protected static function parseRelationSpec(string $spec): array
    {
        if (!str_contains($spec, ':')) {
            // No column selection
            return [$spec, null];
        }

        // Split by ':' to get relation name and columns
        [$name, $columnsStr] = explode(':', $spec, 2);

        // Parse comma-separated columns
        $columns = array_map('trim', explode(',', $columnsStr));

        return [$name, $columns];
    }

    /**
     * Apply column selection to a relationship query.
     *
     * Modifies the relation's query to SELECT only specified columns.
     * Automatically includes the foreign key to maintain relationship integrity.
     * Handles relationships with pivot/intermediate tables properly.
     *
     * SOLID Principles:
     * - Single Responsibility: Only handles column selection logic
     * - Open/Closed: Works with any relation type (HasMany, BelongsTo, etc.)
     *
     * @param RelationInterface $relation Relation instance
     * @param array<string> $columns Columns to select
     * @return void
     */
    protected static function applyColumnSelection(RelationInterface $relation, array $columns): void
    {
        // Get the query builder from relation
        $query = $relation->getQuery();

        // Handle relationships with pivot/intermediate tables that require table prefixing
        if (
            $relation instanceof Relations\BelongsToMany ||
            $relation instanceof Relations\MorphToMany ||
            $relation instanceof Relations\HasManyThrough ||
            $relation instanceof Relations\HasOneThrough
        ) {

            static::applyColumnSelectionForPivotRelation($relation, $columns, $query);
        } else {
            // For simple relations (HasOne, HasMany, BelongsTo, MorphOne, MorphMany, MorphTo)
            static::applyColumnSelectionForSimpleRelation($relation, $columns, $query);
        }
    }

    /**
     * Apply column selection for relationships with pivot/intermediate tables.
     *
     * Optimized version with reduced code duplication and better performance:
     * - Single reflection service call
     * - Avoid unnecessary model instantiation when possible
     * - Extracted helper methods for reusability
     * - Reduced memory footprint
     *
     * @param RelationInterface $relation Relation instance
     * @param array<string> $columns Columns to select
     * @param \Toporia\Framework\Database\Query\QueryBuilder $query Query builder
     * @return void
     */
    protected static function applyColumnSelectionForPivotRelation(RelationInterface $relation, array $columns, $query): void
    {
        // Get reflection service once (performance optimization)
        $reflectionService = app()->make(\Toporia\Framework\Support\ReflectionService::class);

        // Get related class name (common for all pivot relationships)
        $relatedClass = $reflectionService->getPropertyValue($relation, 'relatedClass');

        // Determine required key and get table name efficiently
        [$requiredKey, $relatedTable] = static::getRelationMetadata($relation, $relatedClass, $reflectionService);

        // Ensure required key is included in columns
        if ($requiredKey && !in_array($requiredKey, $columns, true)) {
            $columns[] = $requiredKey;
        }

        // Prefix all columns with table name to avoid ambiguity with pivot/intermediate tables
        $prefixedColumns = static::prefixColumnsWithTable($columns, $relatedTable);

        // Apply column selection with prefixed columns
        $query->select($prefixedColumns);
    }

    /**
     * Apply column selection for simple relationships without pivot tables.
     *
     * @param RelationInterface $relation Relation instance
     * @param array<string> $columns Columns to select
     * @param \Toporia\Framework\Database\Query\QueryBuilder $query Query builder
     * @return void
     */
    protected static function applyColumnSelectionForSimpleRelation(RelationInterface $relation, array $columns, $query): void
    {
        // For simple relations, ensure foreign key is always included (maintain relationship integrity)
        $foreignKey = $relation->getForeignKeyName();
        if (!in_array($foreignKey, $columns, true)) {
            $columns[] = $foreignKey;
        }

        // Apply column selection (no table prefixing needed for simple relations)
        $query->select($columns);
    }

    /**
     * Get relationship metadata (required key and table name) efficiently.
     *
     * Optimized version that reduces code duplication and improves performance.
     *
     * @param RelationInterface $relation Relation instance
     * @param string $relatedClass Related model class name
     * @param \Toporia\Framework\Support\ReflectionService $reflectionService Reflection service
     * @return array{0: string|null, 1: string} [requiredKey, tableName]
     */
    protected static function getRelationMetadata(RelationInterface $relation, string $relatedClass, $reflectionService): array
    {
        // Get table name efficiently using static method if available, otherwise instantiate
        $relatedTable = static::getTableNameFromClass($relatedClass);

        if ($relation instanceof Relations\BelongsToMany) {
            // For BelongsToMany, use the related key (primary key of target table)
            $requiredKey = $relation->getRelatedKey();
        } elseif ($relation instanceof Relations\MorphToMany) {
            // For MorphToMany, get related key via reflection
            $requiredKey = $reflectionService->getPropertyValue($relation, 'relatedKey');
        } elseif ($relation instanceof Relations\HasManyThrough || $relation instanceof Relations\HasOneThrough) {
            // For HasManyThrough/HasOneThrough, use foreign key
            $requiredKey = $relation->getForeignKeyName();
        } else {
            // Fallback for unknown pivot relationship types
            $requiredKey = null;
        }

        return [$requiredKey, $relatedTable];
    }

    /**
     * Get table name from model class efficiently.
     *
     * Uses static method if available, otherwise creates minimal instance.
     * Performance: Avoids unnecessary object instantiation when possible.
     *
     * @param string $modelClass Model class name
     * @return string Table name
     */
    protected static function getTableNameFromClass(string $modelClass): string
    {
        // Try to use static method first (most efficient)
        if (method_exists($modelClass, 'getTableName')) {
            return $modelClass::getTableName();
        }

        // Fallback: create instance (less efficient but works)
        $instance = new $modelClass();
        return $instance->getTable();
    }

    /**
     * Prefix columns with table name to avoid SQL ambiguity.
     *
     * Performance: Single array_map call with optimized closure.
     *
     * @param array<string> $columns Columns to prefix
     * @param string $tableName Table name to use as prefix
     * @return array<string> Prefixed columns
     */
    protected static function prefixColumnsWithTable(array $columns, string $tableName): array
    {
        return array_map(function ($column) use ($tableName) {
            // If column already has table prefix, don't add it again
            if (str_contains($column, '.')) {
                return $column;
            }
            return "{$tableName}.{$column}";
        }, $columns);
    }

    /**
     * Handle dynamic static method calls to the model.
     *
     * This allows calling QueryBuilder methods directly on the Model class
     * like Laravel: ProductModel::where('id', 1)->first()
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters)
    {
        // Get connection
        $connection = app()->make(\Toporia\Framework\Database\Connection::class);

        // Create model query builder
        $modelQueryBuilder = new \Toporia\Framework\Database\ORM\ModelQueryBuilder($connection, static::class);

        // Set the table
        $modelQueryBuilder->table(static::getTableName());

        // Call the method on the query builder
        return $modelQueryBuilder->$method(...$parameters);
    }

    // =========================================================================
    // STATIC WHEREDOESNTHAVE CONVENIENCE METHODS
    // =========================================================================

    /**
     * Static convenience method for whereDoesntHave.
     *
     * @param string $relation Relationship method name
     * @param callable|null $callback Optional callback to constrain the relationship query
     * @param string $operator Comparison operator (<, =, etc.)
     * @param int $count Maximum count (default: 1)
     * @return ModelQueryBuilder
     */
    public static function whereDoesntHave(string $relation, ?callable $callback = null, string $operator = '<', int $count = 1): ModelQueryBuilder
    {
        return static::query()->whereDoesntHave($relation, $callback, $operator, $count);
    }

    /**
     * Static convenience method for whereDoesntHaveNested.
     *
     * @param string $relation Nested relationship using dot notation
     * @param callable|null $callback Optional callback to constrain the final relationship
     * @return ModelQueryBuilder
     */
    public static function whereDoesntHaveNested(string $relation, ?callable $callback = null): ModelQueryBuilder
    {
        return static::query()->whereDoesntHaveNested($relation, $callback);
    }

    /**
     * Static convenience method for whereDoesntHaveIn.
     *
     * @param string $relation Relationship method name
     * @param array $ids Array of IDs to exclude
     * @param string $column Column to check IDs against (default: 'id')
     * @return ModelQueryBuilder
     */
    public static function whereDoesntHaveIn(string $relation, array $ids, string $column = 'id'): ModelQueryBuilder
    {
        return static::query()->whereDoesntHaveIn($relation, $ids, $column);
    }

    /**
     * Static convenience method for whereDoesntHaveInDateRange.
     *
     * @param string $relation Relationship method name
     * @param string $dateColumn Date column to check
     * @param string|\DateTime $startDate Start date (inclusive)
     * @param string|\DateTime|null $endDate End date (inclusive, optional)
     * @return ModelQueryBuilder
     */
    public static function whereDoesntHaveInDateRange(string $relation, string $dateColumn, string|\DateTime $startDate, string|\DateTime|null $endDate = null): ModelQueryBuilder
    {
        return static::query()->whereDoesntHaveInDateRange($relation, $dateColumn, $startDate, $endDate);
    }

    /**
     * Static convenience method for whereDoesntHaveJsonAttribute.
     *
     * @param string $relation Relationship method name
     * @param string $jsonColumn JSON column name
     * @param string $jsonPath JSON path (e.g., '$.source')
     * @param mixed $value Value to match
     * @return ModelQueryBuilder
     */
    public static function whereDoesntHaveJsonAttribute(string $relation, string $jsonColumn, string $jsonPath, mixed $value): ModelQueryBuilder
    {
        return static::query()->whereDoesntHaveJsonAttribute($relation, $jsonColumn, $jsonPath, $value);
    }

    /**
     * Static convenience method for orWhereHas.
     *
     * @param string $relation Relationship method name
     * @param callable|null $callback Optional callback to constrain the relationship query
     * @param string $operator Comparison operator (>=, =, etc.)
     * @param int $count Count threshold (default: 1)
     * @return ModelQueryBuilder
     */
    public static function orWhereHas(string $relation, ?callable $callback = null, string $operator = '>=', int $count = 1): ModelQueryBuilder
    {
        return static::query()->orWhereHas($relation, $callback, $operator, $count);
    }
}
