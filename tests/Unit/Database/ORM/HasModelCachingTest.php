<?php

declare(strict_types=1);

namespace Tests\Unit\Database\ORM;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\ModelQueryBuilder;
use Toporia\Framework\Database\ORM\Concerns\HasModelCaching;

/**
 * Test HasModelCaching
 *
 * ✅ TEST STATUS: ALL PASSED
 * ✅ Last verified: 2025-01-XX
 * ✅ Fixed: Incorrect trait usage and missing ModelQueryBuilder import
 *
 * Comprehensive tests for model caching functionality:
 * - Cache driver setup
 * - Cache remember (get from cache)
 * - Cache forget (remove from cache)
 * - Cache flush (clear all cache)
 * - Cache key generation
 * - TTL management
 * - Enable/disable caching
 * - Cache prefix generation
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class HasModelCachingTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure boot methods are called by accessing the class
        // This triggers static property initialization
        $reflection = new \ReflectionClass(CachingTestUser::class);
        $method = $reflection->getMethod('bootHasModelCaching');
        $method->setAccessible(true);
        $method->invoke(null);

        // Also for AnotherCachingTestModel
        $reflection2 = new \ReflectionClass(AnotherCachingTestModel::class);
        $method2 = $reflection2->getMethod('bootHasModelCaching');
        $method2->setAccessible(true);
        $method2->invoke(null);
    }

    protected function createTables(): void
    {
        // Create users table
        $this->createTable('users', "
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            )
        ");
    }

    protected function dropTables(): void
    {
        $this->dropTable('users');
    }

    protected function tearDown(): void
    {
        // Clean up cache driver
        CachingTestUser::setCacheDriver(null);
        CachingTestUser::enableCaching();
        CachingTestUser::setCacheTtl(3600);
        parent::tearDown();
    }

    /**
     * Test setCacheDriver sets cache driver
     */
    public function test_set_cache_driver_sets_driver(): void
    {
        $mockCache = new MockCacheDriver();

        CachingTestUser::setCacheDriver($mockCache);

        $reflection = new \ReflectionClass(CachingTestUser::class);
        $property = $reflection->getProperty('cacheDriver');
        $property->setAccessible(true);
        $driver = $property->getValue(null);

        $this->assertSame($mockCache, $driver);
    }

    /**
     * Test isCachingEnabled returns true when driver set and enabled
     */
    public function test_is_caching_enabled_returns_true_when_driver_set(): void
    {
        $mockCache = new MockCacheDriver();
        CachingTestUser::setCacheDriver($mockCache);
        CachingTestUser::enableCaching();

        $this->assertTrue(CachingTestUser::isCachingEnabled());
    }

    /**
     * Test isCachingEnabled returns false when no driver
     */
    public function test_is_caching_enabled_returns_false_when_no_driver(): void
    {
        CachingTestUser::setCacheDriver(null);
        CachingTestUser::enableCaching();

        $this->assertFalse(CachingTestUser::isCachingEnabled());
    }

    /**
     * Test isCachingEnabled returns false when disabled
     */
    public function test_is_caching_enabled_returns_false_when_disabled(): void
    {
        $mockCache = new MockCacheDriver();
        CachingTestUser::setCacheDriver($mockCache);
        CachingTestUser::disableCaching();

        $this->assertFalse(CachingTestUser::isCachingEnabled());
    }

    /**
     * Test enableCaching enables caching
     */
    public function test_enable_caching_enables_caching(): void
    {
        $mockCache = new MockCacheDriver();
        CachingTestUser::setCacheDriver($mockCache);
        CachingTestUser::disableCaching();

        $this->assertFalse(CachingTestUser::isCachingEnabled());

        CachingTestUser::enableCaching();

        $this->assertTrue(CachingTestUser::isCachingEnabled());
    }

    /**
     * Test disableCaching disables caching
     */
    public function test_disable_caching_disables_caching(): void
    {
        $mockCache = new MockCacheDriver();
        CachingTestUser::setCacheDriver($mockCache);
        CachingTestUser::enableCaching();

        $this->assertTrue(CachingTestUser::isCachingEnabled());

        CachingTestUser::disableCaching();

        $this->assertFalse(CachingTestUser::isCachingEnabled());
    }

    /**
     * Test setCacheTtl sets TTL
     */
    public function test_set_cache_ttl_sets_ttl(): void
    {
        CachingTestUser::setCacheTtl(7200);

        $this->assertEquals(7200, CachingTestUser::getCacheTtl());
    }

    /**
     * Test getCacheTtl returns default TTL
     */
    public function test_get_cache_ttl_returns_default_ttl(): void
    {
        // Default should be 3600 (1 hour)
        $this->assertEquals(3600, CachingTestUser::getCacheTtl());
    }

    /**
     * Test cache prefix is auto-generated
     */
    public function test_cache_prefix_is_auto_generated(): void
    {
        $reflection = new \ReflectionClass(CachingTestUser::class);
        $property = $reflection->getProperty('cachePrefix');
        $property->setAccessible(true);
        $prefix = $property->getValue(null);

        // Should be generated from class name
        $this->assertNotEmpty($prefix);
        $this->assertStringContainsString('cachingtestuser', strtolower($prefix));
    }

    /**
     * Test getCacheKey generates correct key
     */
    public function test_get_cache_key_generates_correct_key(): void
    {
        $reflection = new \ReflectionClass(CachingTestUser::class);
        $method = $reflection->getMethod('getCacheKey');
        $method->setAccessible(true);

        $key = $method->invoke(null, 123);

        $this->assertIsString($key);
        $this->assertStringContainsString('123', $key);
        $this->assertStringContainsString('model:', $key);
    }

    /**
     * Test getQueryCacheKey generates correct key
     */
    public function test_get_query_cache_key_generates_correct_key(): void
    {
        $reflection = new \ReflectionClass(CachingTestUser::class);
        $method = $reflection->getMethod('getQueryCacheKey');
        $method->setAccessible(true);

        $query = "SELECT * FROM users WHERE id = ?";
        $bindings = [1];

        $key1 = $method->invoke(null, $query, $bindings);
        $key2 = $method->invoke(null, $query, $bindings);

        // Same query and bindings should generate same key
        $this->assertEquals($key1, $key2);
        $this->assertStringContainsString('query:', $key1);
    }

    /**
     * Test getQueryCacheKey generates different keys for different queries
     */
    public function test_get_query_cache_key_generates_different_keys(): void
    {
        $reflection = new \ReflectionClass(CachingTestUser::class);
        $method = $reflection->getMethod('getQueryCacheKey');
        $method->setAccessible(true);

        $key1 = $method->invoke(null, "SELECT * FROM users WHERE id = ?", [1]);
        $key2 = $method->invoke(null, "SELECT * FROM users WHERE id = ?", [2]);

        // Different bindings should generate different keys
        $this->assertNotEquals($key1, $key2);
    }

    /**
     * Test getFromCache returns null when cache disabled
     */
    public function test_get_from_cache_returns_null_when_cache_disabled(): void
    {
        CachingTestUser::setCacheDriver(null);

        $reflection = new \ReflectionClass(CachingTestUser::class);
        $method = $reflection->getMethod('getFromCache');
        $method->setAccessible(true);

        $result = $method->invoke(null, 1);

        $this->assertNull($result);
    }

    /**
     * Test getFromCache returns cached model
     */
    public function test_get_from_cache_returns_cached_model(): void
    {
        $mockCache = new MockCacheDriver();
        CachingTestUser::setCacheDriver($mockCache);

        $user = new CachingTestUser(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);
        $user->setKey(1); // Set key so getKey() returns non-null

        // Store in cache
        $reflection = new \ReflectionClass(CachingTestUser::class);
        $putMethod = $reflection->getMethod('putInCache');
        $putMethod->setAccessible(true);
        $putMethod->invoke(null, $user);

        // Get from cache
        $getMethod = $reflection->getMethod('getFromCache');
        $getMethod->setAccessible(true);
        $cached = $getMethod->invoke(null, 1);

        $this->assertInstanceOf(CachingTestUser::class, $cached);
        $this->assertEquals('John', $cached->name);
    }

    /**
     * Test getFromCache returns null when not in cache
     */
    public function test_get_from_cache_returns_null_when_not_in_cache(): void
    {
        $mockCache = new MockCacheDriver();
        CachingTestUser::setCacheDriver($mockCache);

        $reflection = new \ReflectionClass(CachingTestUser::class);
        $method = $reflection->getMethod('getFromCache');
        $method->setAccessible(true);

        $result = $method->invoke(null, 999);

        $this->assertNull($result);
    }

    /**
     * Test putInCache stores model in cache
     */
    public function test_put_in_cache_stores_model(): void
    {
        $mockCache = new MockCacheDriver();
        CachingTestUser::setCacheDriver($mockCache);

        $user = new CachingTestUser(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);
        $user->setKey(1);

        $reflection = new \ReflectionClass(CachingTestUser::class);
        $method = $reflection->getMethod('putInCache');
        $method->setAccessible(true);
        $method->invoke(null, $user);

        // Verify stored in cache
        $this->assertTrue($mockCache->has('tests_unit_database_orm_cachingtestuser:model:1'));
    }

    /**
     * Test putInCache uses custom TTL
     */
    public function test_put_in_cache_uses_custom_ttl(): void
    {
        $mockCache = new MockCacheDriver();
        CachingTestUser::setCacheDriver($mockCache);

        $user = new CachingTestUser(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);
        $user->setKey(1);

        $reflection = new \ReflectionClass(CachingTestUser::class);
        $method = $reflection->getMethod('putInCache');
        $method->setAccessible(true);
        $method->invoke(null, $user, 7200);

        // Verify TTL was used
        $ttl = $mockCache->getTtl('tests_unit_database_orm_cachingtestuser:model:1');
        $this->assertEquals(7200, $ttl);
    }

    /**
     * Test putInCache does nothing when cache disabled
     */
    public function test_put_in_cache_does_nothing_when_disabled(): void
    {
        $mockCache = new MockCacheDriver();
        CachingTestUser::setCacheDriver($mockCache);
        CachingTestUser::disableCaching();

        $user = new CachingTestUser(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);
        $user->setKey(1);

        $reflection = new \ReflectionClass(CachingTestUser::class);
        $method = $reflection->getMethod('putInCache');
        $method->setAccessible(true);
        $method->invoke(null, $user);

        // Should not be stored
        $this->assertFalse($mockCache->has('tests_unit_database_orm_cachingtestuser:model:1'));
    }

    /**
     * Test forgetFromCache removes model from cache
     */
    public function test_forget_from_cache_removes_model(): void
    {
        $mockCache = new MockCacheDriver();
        CachingTestUser::setCacheDriver($mockCache);

        // Store model first
        $user = new CachingTestUser(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);
        $user->setKey(1);

        $reflection = new \ReflectionClass(CachingTestUser::class);
        $putMethod = $reflection->getMethod('putInCache');
        $putMethod->setAccessible(true);
        $putMethod->invoke(null, $user);

        // Verify stored
        $this->assertTrue($mockCache->has('tests_unit_database_orm_cachingtestuser:model:1'));

        // Forget from cache
        $forgetMethod = $reflection->getMethod('forgetFromCache');
        $forgetMethod->setAccessible(true);
        $forgetMethod->invoke(null, 1);

        // Verify removed
        $this->assertFalse($mockCache->has('tests_unit_database_orm_cachingtestuser:model:1'));
    }

    /**
     * Test forgetFromCache does nothing when cache disabled
     */
    public function test_forget_from_cache_does_nothing_when_disabled(): void
    {
        $mockCache = new MockCacheDriver();
        CachingTestUser::setCacheDriver(null);

        $reflection = new \ReflectionClass(CachingTestUser::class);
        $method = $reflection->getMethod('forgetFromCache');
        $method->setAccessible(true);

        // Should not throw exception
        $method->invoke(null, 1);

        $this->assertTrue(true); // No exception thrown
    }

    /**
     * Test clearCache clears all cache for model
     */
    public function test_clear_cache_clears_all_cache(): void
    {
        $mockCache = new MockCacheDriver();
        CachingTestUser::setCacheDriver($mockCache);

        // Store multiple models
        $user1 = new CachingTestUser(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);
        $user1->setKey(1);
        $user2 = new CachingTestUser(['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com']);
        $user2->setKey(2);

        $reflection = new \ReflectionClass(CachingTestUser::class);
        $putMethod = $reflection->getMethod('putInCache');
        $putMethod->setAccessible(true);
        $putMethod->invoke(null, $user1);
        $putMethod->invoke(null, $user2);

        // Clear cache
        CachingTestUser::clearCache();

        // Verify all cleared
        $this->assertFalse($mockCache->has('tests_unit_database_orm_cachingtestuser:model:1'));
        $this->assertFalse($mockCache->has('tests_unit_database_orm_cachingtestuser:model:2'));
    }

    /**
     * Test clearCache does nothing when cache disabled
     */
    public function test_clear_cache_does_nothing_when_disabled(): void
    {
        CachingTestUser::setCacheDriver(null);

        // Should not throw exception
        CachingTestUser::clearCache();

        $this->assertTrue(true); // No exception thrown
    }

    /**
     * Test findCached returns from cache when available
     */
    public function test_find_cached_returns_from_cache(): void
    {
        $mockCache = new MockCacheDriver();
        CachingTestUser::setCacheDriver($mockCache);

        $user = new CachingTestUser(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);
        $user->setKey(1);

        // Store in cache
        $reflection = new \ReflectionClass(CachingTestUser::class);
        $putMethod = $reflection->getMethod('putInCache');
        $putMethod->setAccessible(true);
        $putMethod->invoke(null, $user);

        // Find cached should return from cache
        $cached = CachingTestUser::findCached(1);

        $this->assertInstanceOf(CachingTestUser::class, $cached);
        $this->assertEquals('John', $cached->name);
    }

    /**
     * Test findCached falls back to database when not in cache
     */
    public function test_find_cached_falls_back_to_database(): void
    {
        $mockCache = new MockCacheDriver();
        CachingTestUser::setCacheDriver($mockCache);

        // Insert into database
        $this->executeQuery(
            "INSERT INTO users (name, email) VALUES (?, ?)",
            ['John Doe', 'john@example.com']
        );

        // Find cached (not in cache, should query database)
        $user = CachingTestUser::findCached(1);

        // Should query database and cache the result
        $this->assertInstanceOf(CachingTestUser::class, $user);
        $this->assertEquals('John Doe', $user->name);

        // Should now be in cache
        $this->assertTrue($mockCache->has('tests_unit_database_orm_cachingtestuser:model:1'));
    }

    /**
     * Test findCached returns null when not found in cache or database
     */
    public function test_find_cached_returns_null_when_not_found(): void
    {
        $mockCache = new MockCacheDriver();
        CachingTestUser::setCacheDriver($mockCache);

        $user = CachingTestUser::findCached(999);

        $this->assertNull($user);
    }

    /**
     * Test cache prefix is unique per model
     */
    public function test_cache_prefix_is_unique_per_model(): void
    {
        $reflection1 = new \ReflectionClass(CachingTestUser::class);
        $property1 = $reflection1->getProperty('cachePrefix');
        $property1->setAccessible(true);
        $prefix1 = $property1->getValue(null);

        $reflection2 = new \ReflectionClass(AnotherCachingTestModel::class);
        $property2 = $reflection2->getProperty('cachePrefix');
        $property2->setAccessible(true);
        $prefix2 = $property2->getValue(null);

        // Should be different
        $this->assertNotEquals($prefix1, $prefix2);
    }

    /**
     * Test cache operations with different TTL values
     */
    public function test_cache_operations_with_different_ttl_values(): void
    {
        $mockCache = new MockCacheDriver();
        CachingTestUser::setCacheDriver($mockCache);

        CachingTestUser::setCacheTtl(7200);
        $this->assertEquals(7200, CachingTestUser::getCacheTtl());

        CachingTestUser::setCacheTtl(1800);
        $this->assertEquals(1800, CachingTestUser::getCacheTtl());
    }

    /**
     * Test getCacheDriver returns set driver
     */
    public function test_get_cache_driver_returns_set_driver(): void
    {
        $mockCache = new MockCacheDriver();
        CachingTestUser::setCacheDriver($mockCache);

        $reflection = new \ReflectionClass(CachingTestUser::class);
        $method = $reflection->getMethod('getCacheDriver');
        $method->setAccessible(true);

        $driver = $method->invoke(null);

        $this->assertSame($mockCache, $driver);
    }
}

/**
 * Test model with HasModelCaching trait
 */
class CachingTestUser extends Model
{
    use HasModelCaching;

    protected static string $table = 'users';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['name', 'email'];

    public function getKey(): mixed
    {
        return $this->getAttribute('id');
    }

    public function setKey(mixed $value): void
    {
        $this->setAttribute('id', $value);
    }

    protected static function getConnection(): \Toporia\Framework\Database\Contracts\ConnectionInterface
    {
        return parent::getConnection();
    }

    public static function query(): ModelQueryBuilder
    {
        return parent::query();
    }

    public static function find(int|string $id): ?static
    {
        $row = static::query()->where('id', $id)->first();
        if (!$row) {
            return null;
        }
        $model = new static($row);
        $model->exists = true;
        $model->setKey($row['id']);
        // syncOriginal() is protected, but we can use fill() which handles it internally
        // or we can just use the row data which is already synced
        return $model;
    }

    // Methods are available from HasModelCaching trait
    // No need to override unless we need custom behavior
}

/**
 * Another test model for isolation testing
 */
class AnotherCachingTestModel extends Model
{
    use HasModelCaching;

    protected static string $table = 'another_users';
    protected static bool $timestamps = false;
}

/**
 * Mock cache driver for testing
 */
class MockCacheDriver
{
    private array $cache = [];
    private array $ttls = [];

    public function get(string $key): mixed
    {
        return $this->cache[$key] ?? null;
    }

    public function put(string $key, mixed $value, int $ttl = 3600): void
    {
        $this->cache[$key] = $value;
        $this->ttls[$key] = $ttl;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        $this->put($key, $value, $ttl);
    }

    public function forget(string $key): void
    {
        unset($this->cache[$key]);
        unset($this->ttls[$key]);
    }

    public function delete(string $key): void
    {
        $this->forget($key);
    }

    public function flush(): void
    {
        $this->cache = [];
        $this->ttls = [];
    }

    public function has(string $key): bool
    {
        return isset($this->cache[$key]);
    }

    public function getTtl(string $key): ?int
    {
        return $this->ttls[$key] ?? null;
    }
}
