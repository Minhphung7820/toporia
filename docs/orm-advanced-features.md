# ORM Advanced Features

Toporia Framework cung cấp các tính năng ORM nâng cao, tối ưu performance và tuân thủ Clean Architecture + SOLID.

## 1. Soft Deletes

Soft delete cho phép "xóa" records mà không thực sự xóa khỏi database.

### Cách sử dụng:

```php
use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\Concerns\SoftDeletes;

class UserModel extends Model
{
    use SoftDeletes;

    protected static string $table = 'users';
    protected static string $deletedAtColumn = 'deleted_at'; // Default
}
```

### Methods:

```php
// Soft delete
$user->delete(); // Sets deleted_at timestamp

// Check if soft-deleted
$user->trashed(); // true/false

// Restore
$user->restore();

// Force delete (hard delete)
$user->forceDelete();

// Query soft-deleted records
UserModel::withTrashed()->get(); // Includes soft-deleted
UserModel::onlyTrashed()->get(); // Only soft-deleted

// Batch operations
UserModel::softDeleteBatch([1, 2, 3]);
UserModel::restoreBatch([1, 2, 3]);
```

### Performance:
- O(1) - Single UPDATE query
- Automatic global scope (excludes deleted by default)
- Indexed deleted_at column for fast filtering

## 2. Query Scopes

Query scopes cho phép tái sử dụng query constraints.

### Global Scopes (tự động áp dụng):

```php
use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\Concerns\HasQueryScopes;

class ProductModel extends Model
{
    use HasQueryScopes;

    protected static function boot(): void
    {
        parent::boot();

        // Global scope - tự động áp dụng cho mọi query
        static::addGlobalScope('active', function ($query) {
            $query->where('is_active', true);
        });
    }
}

// Tất cả queries tự động filter is_active = true
ProductModel::all(); // Only active products
ProductModel::find(1); // Only if active

// Remove global scope
ProductModel::withoutGlobalScope('active')->get();
```

### Local Scopes (gọi khi cần):

```php
class ProductModel extends Model
{
    use HasQueryScopes;

    // Auto-discovered: scopePublished() -> published()
    protected function scopePublished($query)
    {
        return $query->where('published_at', '<=', now());
    }

    // Auto-discovered: scopeInStock() -> inStock()
    protected function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }
}

// Sử dụng
ProductModel::published()->get();
ProductModel::published()->inStock()->get();

// Manual registration
ProductModel::addLocalScope('featured', function ($query) {
    return $query->where('is_featured', true);
});
```

### Performance:
- Scope caching (compiled scopes cached)
- Lazy application (only when needed)
- Query builder reuse

## 3. Eager Loading

Eager loading ngăn chặn N+1 query problem.

```php
use Toporia\Framework\Database\ORM\Concerns\HasEagerLoading;

class ProductModel extends Model
{
    use HasEagerLoading;

    public function category()
    {
        return $this->belongsTo(CategoryModel::class);
    }

    public function reviews()
    {
        return $this->hasMany(ReviewModel::class);
    }
}

// Without eager loading: N+1 queries
$products = ProductModel::all();
foreach ($products as $product) {
    echo $product->category->name; // 1 query per product!
}

// With eager loading: 2 queries total
$products = ProductModel::with(['category', 'reviews'])->get();
foreach ($products as $product) {
    echo $product->category->name; // No additional queries!
}

// Check if relation is loaded
$product->relationLoaded('category'); // true/false
```

### Performance:
- O(n + m) queries instead of O(n * m)
- Batch loading (loads all relationships in minimal queries)
- Query deduplication

## 4. Batch Operations

Batch operations cho phép xử lý nhiều records hiệu quả.

```php
use Toporia\Framework\Database\ORM\Concerns\HasBatchOperations;

class UserModel extends Model
{
    use HasBatchOperations;
}
```

### Insert Batch:

```php
// Insert multiple records in one query
UserModel::insertBatch([
    ['name' => 'John', 'email' => 'john@example.com'],
    ['name' => 'Jane', 'email' => 'jane@example.com'],
]);

// Insert in chunks (memory efficient for large datasets)
UserModel::insertChunked($largeArray, 500); // 500 records per chunk
```

### Update Batch:

```php
// Update multiple records efficiently
UserModel::updateBatch([
    1 => ['name' => 'John Updated', 'email' => 'john@new.com'],
    2 => ['name' => 'Jane Updated', 'email' => 'jane@new.com'],
]);
```

### Delete Batch:

```php
// Delete multiple records
UserModel::deleteBatch([1, 2, 3, 4, 5]);
```

### Upsert Batch:

```php
// Insert or update (MySQL INSERT ... ON DUPLICATE KEY UPDATE)
UserModel::upsertBatch([
    ['email' => 'john@example.com', 'name' => 'John'],
    ['email' => 'jane@example.com', 'name' => 'Jane'],
], ['email']); // email is unique key
```

### Performance:
- O(1) queries regardless of record count
- Transaction wrapping (all-or-nothing)
- Chunked processing for large datasets

## 5. Chunking

Chunking cho phép xử lý large datasets mà không tốn memory.

```php
use Toporia\Framework\Database\ORM\Concerns\HasChunking;

class UserModel extends Model
{
    use HasChunking;
}
```

### Basic Chunking:

```php
// Process in chunks of 100
foreach (UserModel::chunk(100) as $chunk) {
    foreach ($chunk as $user) {
        // Process user
    }
}

// With callback
UserModel::chunk(100, function ($chunk) {
    foreach ($chunk as $user) {
        // Process user
    }
});
```

### Cursor-based Chunking (more efficient):

```php
// Uses WHERE id > lastId instead of OFFSET
// Better performance for large datasets
foreach (UserModel::chunkById(100) as $chunk) {
    foreach ($chunk as $user) {
        // Process user
    }
}
```

### Lazy Evaluation:

```php
// Most memory efficient - one record at a time
foreach (UserModel::lazy() as $user) {
    // Process one user at a time
}

// Lazy by ID (most efficient)
foreach (UserModel::lazyById() as $user) {
    // Process one user at a time
}
```

### Performance:
- Memory: O(chunkSize) instead of O(n)
- Automatic garbage collection between chunks
- Cursor-based pagination (no OFFSET)

## 6. Model Caching

Model caching giảm database queries bằng cách cache kết quả.

```php
use Toporia\Framework\Database\ORM\Concerns\HasModelCaching;

class UserModel extends Model
{
    use HasModelCaching;

    protected static int $cacheTtl = 3600; // 1 hour
}
```

### Setup Cache Driver:

```php
// Set cache driver (Redis, Memcached, etc.)
UserModel::setCacheDriver($cacheDriver);
```

### Cached Queries:

```php
// Find with caching
$user = UserModel::findCached(1); // Checks cache first

// Cache is automatically invalidated on update/delete
$user->update(['name' => 'New Name']); // Cache cleared
```

### Cache Management:

```php
// Enable/disable caching
UserModel::enableCaching();
UserModel::disableCaching();

// Set TTL
UserModel::setCacheTtl(7200); // 2 hours

// Clear cache
UserModel::clearCache();
```

### Performance:
- Query result caching (TTL-based)
- Automatic cache invalidation
- Memory-efficient (LRU eviction)

## 7. UUID Support

UUID support cho phép sử dụng UUID làm primary key.

```php
use Toporia\Framework\Database\ORM\Concerns\HasUuid;

class UserModel extends Model
{
    use HasUuid;

    protected static string $primaryKey = 'uuid';
}
```

### Automatic UUID Generation:

```php
// UUID automatically generated on create
$user = new UserModel(['name' => 'John']);
$user->save(); // UUID generated automatically

// Manual generation
$uuid = UserModel::generateUuid();
```

### Performance:
- UUID generation only when needed (lazy)
- Indexed UUID columns (fast lookups)
- Binary UUID storage option (more efficient)

## 8. Kết Hợp Nhiều Traits

Bạn có thể kết hợp nhiều traits:

```php
use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\Concerns\SoftDeletes;
use Toporia\Framework\Database\ORM\Concerns\HasQueryScopes;
use Toporia\Framework\Database\ORM\Concerns\HasEagerLoading;
use Toporia\Framework\Database\ORM\Concerns\HasBatchOperations;
use Toporia\Framework\Database\ORM\Concerns\HasChunking;
use Toporia\Framework\Database\ORM\Concerns\HasModelCaching;
use Toporia\Framework\Database\ORM\Concerns\HasUuid;

class ProductModel extends Model
{
    use SoftDeletes;
    use HasQueryScopes;
    use HasEagerLoading;
    use HasBatchOperations;
    use HasChunking;
    use HasModelCaching;
    use HasUuid;

    protected static string $table = 'products';
    protected static string $primaryKey = 'uuid';

    // Global scope
    protected static function boot(): void
    {
        parent::boot();
        static::addGlobalScope('active', function ($query) {
            $query->where('is_active', true);
        });
    }

    // Local scope
    protected function scopePublished($query)
    {
        return $query->where('published_at', '<=', now());
    }
}
```

## 9. Best Practices

1. **Use Soft Deletes** cho data quan trọng cần recover
2. **Use Eager Loading** để tránh N+1 queries
3. **Use Batch Operations** cho bulk inserts/updates
4. **Use Chunking** cho large datasets
5. **Use Caching** cho frequently accessed data
6. **Use Query Scopes** để tái sử dụng query logic
7. **Use UUID** khi cần distributed systems

## 10. Performance Tips

1. **Index columns** used in scopes and where clauses
2. **Use chunkById** instead of chunk for large datasets
3. **Enable caching** for read-heavy applications
4. **Use batch operations** instead of loops
5. **Eager load relationships** to avoid N+1
6. **Use soft deletes** with indexed deleted_at column

