# Toporia ORM - Public API Guide

## Overview

Toporia ORM provides a **clean, fluent API** for database operations. This guide documents the **public-facing API** that users should use.

## ✅ Public API (Use These)

### Query Execution Methods

#### `get()` - Get Collection of Models

Returns a `ModelCollection` of all matching records.

```php
// Get all users
$users = UserModel::get();

// Get with constraints
$activeUsers = UserModel::query()
    ->where('status', 'active')
    ->orderBy('created_at', 'DESC')
    ->get();

// With eager loading
$users = UserModel::query()
    ->with('posts', 'comments')
    ->get();
```

#### `first()` - Get First Model

Returns a single `Model` instance or `null`.

```php
// Get first user
$user = UserModel::first();

// Get first matching
$admin = UserModel::query()
    ->where('role', 'admin')
    ->first();

// With eager loading
$user = UserModel::query()
    ->with('profile')
    ->where('email', 'john@example.com')
    ->first();
```

#### `find()` - Find by Primary Key

Returns a single `Model` instance or `null`.

```php
// Find by ID
$user = UserModel::find(1);

// Find via query builder
$user = UserModel::query()->find(1);

// With eager loading
$user = UserModel::query()
    ->with('posts')
    ->find(1);

// Find by custom column
$user = UserModel::query()->find('john@example.com', 'email');
```

#### `paginate()` - Paginated Results

Returns a `Paginator` with `ModelCollection` items.

```php
// Simple pagination
$users = UserModel::query()->paginate(15);

// With constraints
$users = UserModel::query()
    ->where('active', true)
    ->orderBy('name')
    ->paginate(20, page: 2);

// Access paginator data
echo $users->total();        // Total records
echo $users->perPage();      // Items per page
echo $users->currentPage();  // Current page
foreach ($users->items() as $user) {
    // Process each user
}
```

#### `chunk()` - Process in Batches

Process large datasets in memory-efficient chunks.

```php
// Process 100 records at a time
UserModel::query()
    ->where('active', true)
    ->chunk(100, function($users) {
        foreach ($users as $user) {
            // Process user
        }
    });

// Or use generator (lazy loading)
foreach (UserModel::query()->chunk(100) as $chunk) {
    // Process chunk
}
```

#### `chunkById()` - Cursor-based Chunking

More efficient for very large datasets.

```php
// Uses WHERE id > lastId instead of OFFSET
UserModel::query()
    ->where('status', 'pending')
    ->chunkById(1000, function($users) {
        foreach ($users as $user) {
            $user->status = 'processed';
            $user->save();
        }
    });
```

### Query Building Methods

All standard query builder methods are available:

```php
$users = UserModel::query()
    ->select('id', 'name', 'email')
    ->where('age', '>=', 18)
    ->whereIn('role', ['admin', 'editor'])
    ->whereNotNull('email_verified_at')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->offset(20)
    ->get();
```

### Relationship Methods

```php
// Eager loading
$users = UserModel::query()
    ->with('posts', 'comments')
    ->get();

// Nested eager loading
$users = UserModel::query()
    ->with('posts.comments.author')
    ->get();

// Conditional eager loading
$users = UserModel::query()
    ->with(['posts' => function($q) {
        $q->where('published', true);
    }])
    ->get();

// Relationship existence
$users = UserModel::query()
    ->whereHas('posts', function($q) {
        $q->where('views', '>', 1000);
    })
    ->get();
```

### Aggregate Methods

```php
// Count relationships
$users = UserModel::query()
    ->withCount('posts')
    ->get();
// Access: $user->posts_count

// Sum relationship column
$users = UserModel::query()
    ->withSum('orders', 'total')
    ->get();
// Access: $user->orders_sum_total

// Average, Min, Max
$users = UserModel::query()
    ->withAvg('reviews', 'rating')
    ->withMin('orders', 'created_at')
    ->withMax('orders', 'total')
    ->get();
```

## ❌ Internal Methods (Avoid Using)

### `getModels()` - Internal Implementation

**Status:** `@internal` - This is an internal implementation method.

```php
// ❌ DON'T use this directly
$users = UserModel::query()->where('active', 1)->getModels();

// ✅ USE this instead
$users = UserModel::query()->where('active', 1)->get();
```

**Why it exists:**
- PHP doesn't support return type covariance for Collection types
- `QueryBuilder::get()` returns `RowCollection`
- `ModelQueryBuilder` needs to return `ModelCollection`
- `getModels()` is the internal implementation
- Magic `__call()` intercepts `get()` calls and delegates to `getModels()`

**When you might see it:**
- In framework internals (relationship loading, etc.)
- In older code before fluent API was added
- It's `public` for framework internal access, but marked `@internal`

## Migration Guide

If your code uses `getModels()`, update it:

```php
// ❌ Old way
$users = UserModel::query()->where('age', '>', 18)->getModels();
$user = UserModel::query()->where('email', $email)->getModels()->first();

// ✅ New way
$users = UserModel::query()->where('age', '>', 18)->get();
$user = UserModel::query()->where('email', $email)->first();
```

## Complete Examples

### Simple CRUD

```php
// Create
$user = UserModel::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// Read
$user = UserModel::find(1);
$users = UserModel::query()->where('active', true)->get();

// Update
$user = UserModel::find(1);
$user->name = 'Jane Doe';
$user->save();

// Delete
$user = UserModel::find(1);
$user->delete();
```

### Complex Queries

```php
// Multiple constraints with relationships
$users = UserModel::query()
    ->where('status', 'active')
    ->where('age', '>=', 18)
    ->whereHas('posts', function($q) {
        $q->where('published', true)
          ->where('views', '>', 1000);
    })
    ->with(['posts' => function($q) {
        $q->where('published', true)
          ->orderBy('created_at', 'DESC')
          ->limit(5);
    }])
    ->withCount('comments')
    ->orderBy('created_at', 'DESC')
    ->paginate(20);

foreach ($users->items() as $user) {
    echo "{$user->name} has {$user->comments_count} comments\n";
    foreach ($user->posts as $post) {
        echo "  - {$post->title}\n";
    }
}
```

### Batch Processing

```php
// Process millions of records efficiently
UserModel::query()
    ->where('needs_migration', true)
    ->chunkById(1000, function($users) {
        foreach ($users as $user) {
            // Migrate user data
            $user->new_field = migrateData($user->old_field);
            $user->save();
        }
    });
```

## Summary

| Method | Returns | Use Case |
|--------|---------|----------|
| `get()` | `ModelCollection` | Get all matching records |
| `first()` | `Model\|null` | Get first matching record |
| `find($id)` | `Model\|null` | Find by primary key |
| `paginate()` | `Paginator` | Paginated results |
| `chunk()` | `Generator` or `void` | Batch processing |
| `chunkById()` | `Generator` or `void` | Cursor-based batching |

**Golden Rule:** Use `get()`, `first()`, `find()` for public API. Avoid `getModels()` - it's internal only.
