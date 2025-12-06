# MongoDB Model Support

## Overview

Toporia ORM Models support MongoDB through the `$collection` property, which is automatically used when the model's connection is MongoDB. This provides a clean, intuitive API for working with MongoDB collections while maintaining consistency with SQL models.

## Architecture

### Design Pattern: Convention over Configuration
- MongoDB models use `$collection` property
- SQL models use `$table` property
- Automatic detection based on connection driver
- Fallback to `$table` if `$collection` not set

### SOLID Principles
✅ **Single Responsibility**: Model specifies its data source
✅ **Open/Closed**: Can override per model without modifying base class
✅ **Liskov Substitution**: MongoDB models work like SQL models

### Performance Optimizations
- Connection driver check is cached per model class
- Lazy evaluation of connection type
- Minimal overhead for SQL models

## Usage

### Basic MongoDB Model

```php
<?php

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

class LogModel extends Model
{
    // Specify MongoDB connection
    protected static ?string $connection = 'mongodb';

    // Use $collection instead of $table for MongoDB
    protected static string $collection = 'application_logs';

    // Optional: Hide sensitive fields
    protected static array $hidden = ['internal_data'];
}

// Usage - automatically uses MongoDBGrammar
$logs = LogModel::where('level', 'error')->get();
// Query: {"operation":"find","collection":"application_logs","filter":{"level":"error"}}
```

### Collection Name Auto-Inference

If `$collection` is not set, it falls back to `$table` or auto-infers from class name:

```php
class MessageModel extends Model
{
    protected static ?string $connection = 'mongodb';
    // No $collection set - will auto-infer as "messages"
}

// Auto-infers: MessageModel -> messages
$messages = MessageModel::all();
// Uses collection: "messages"
```

### Explicit Collection Name

```php
class ActivityLogModel extends Model
{
    protected static ?string $connection = 'mongodb';
    protected static string $collection = 'activity_logs';
    // Explicitly set collection name
}

// Uses collection: "activity_logs"
$logs = ActivityLogModel::where('user_id', 123)->get();
```

### Fallback to Table Property

If both `$collection` and `$table` are set, MongoDB uses `$collection`:

```php
class EventModel extends Model
{
    protected static ?string $connection = 'mongodb';
    protected static string $table = 'events'; // For SQL compatibility
    protected static string $collection = 'event_logs'; // For MongoDB
}

// MongoDB uses: "event_logs"
// SQL would use: "events"
```

## Comparison: SQL vs MongoDB

### SQL Model (MySQL/PostgreSQL/SQLite)

```php
class UserModel extends Model
{
    protected static ?string $connection = 'mysql';
    protected static string $table = 'users';
}

// Uses table: "users"
$users = UserModel::all();
// SQL: SELECT * FROM `users`
```

### MongoDB Model

```php
class LogModel extends Model
{
    protected static ?string $connection = 'mongodb';
    protected static string $collection = 'logs';
}

// Uses collection: "logs"
$logs = LogModel::all();
// Query: {"operation":"find","collection":"logs"}
```

## Auto-Detection Logic

The Model automatically detects MongoDB connections and uses the appropriate property:

1. **Check connection driver**: If `mongodb`, use MongoDB logic
2. **Check `$collection` property**: If set, use it
3. **Fallback to `$table`**: If `$collection` not set, use `$table`
4. **Auto-infer**: If both empty, infer from class name

```php
// Step 1: Check connection
$connection = LogModel::getConnection();
$driver = $connection->getDriverName(); // 'mongodb'

// Step 2: Check $collection
if ($driver === 'mongodb' && !empty(static::$collection)) {
    return static::$collection; // 'application_logs'
}

// Step 3: Fallback to $table
if (!empty(static::$table)) {
    return static::$table; // 'logs'
}

// Step 4: Auto-infer
return static::pluralize(static::toSnakeCase('LogModel')); // 'logs'
```

## Examples

### Complete MongoDB Model

```php
<?php

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

class MessageModel extends Model
{
    // MongoDB connection
    protected static ?string $connection = 'mongodb';

    // Collection name
    protected static string $collection = 'messages';

    // Fillable fields
    protected static array $fillable = [
        'user_id',
        'content',
        'metadata',
        'created_at',
    ];

    // Hidden fields
    protected static array $hidden = ['internal_id'];

    // Casts
    protected static array $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
}

// Usage
$messages = MessageModel::where('user_id', 123)
    ->where('created_at', '>=', '2025-01-01')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();
```

### Multi-Database Application

```php
// SQL Model (MySQL)
class UserModel extends Model
{
    protected static ?string $connection = 'mysql';
    protected static string $table = 'users';
}

// MongoDB Model
class LogModel extends Model
{
    protected static ?string $connection = 'mongodb';
    protected static string $collection = 'logs';
}

// Usage
$users = UserModel::all(); // SQL: SELECT * FROM `users`
$logs = LogModel::all(); // MongoDB: {"operation":"find","collection":"logs"}
```

### Cross-Database Operations

```php
// Get user from MySQL
$user = UserModel::find(123);

// Log activity to MongoDB
LogModel::create([
    'user_id' => $user->id,
    'action' => 'viewed',
    'timestamp' => now(),
    'metadata' => [
        'ip' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ],
]);
```

## Performance

### Connection Driver Caching

The MongoDB connection check is cached per model class:

```php
// First call: Checks connection driver
$name1 = LogModel::getTableName(); // Checks driver, caches result

// Subsequent calls: Uses cached result (O(1) lookup)
$name2 = LogModel::getTableName(); // Cache hit
$name3 = LogModel::getTableName(); // Cache hit
```

## Best Practices

### 1. Always Set Collection Name

```php
// Good: Explicit collection name
class LogModel extends Model
{
    protected static ?string $connection = 'mongodb';
    protected static string $collection = 'application_logs';
}

// Avoid: Relying on auto-inference (less clear)
class LogModel extends Model
{
    protected static ?string $connection = 'mongodb';
    // No $collection - will auto-infer
}
```

### 2. Use Descriptive Names

```php
// Good
protected static string $collection = 'user_activity_logs';
protected static string $collection = 'system_events';
protected static string $collection = 'api_requests';

// Avoid
protected static string $collection = 'data';
protected static string $collection = 'temp';
```

### 3. Document MongoDB-Specific Fields

```php
class LogModel extends Model
{
    protected static ?string $connection = 'mongodb';
    protected static string $collection = 'logs';

    /**
     * MongoDB-specific: Supports nested documents
     * @var array
     */
    protected static array $casts = [
        'metadata' => 'array', // MongoDB supports nested objects
        'tags' => 'array',
    ];
}
```

## Migration from SQL to MongoDB

### Before (SQL)

```php
class LogModel extends Model
{
    protected static ?string $connection = 'mysql';
    protected static string $table = 'logs';
}
```

### After (MongoDB)

```php
class LogModel extends Model
{
    protected static ?string $connection = 'mongodb';
    protected static string $collection = 'logs'; // Changed from $table
}
```

## Troubleshooting

### Collection Not Found

```php
// Error: Collection name is empty
class LogModel extends Model
{
    protected static ?string $connection = 'mongodb';
    // No $collection or $table set
}

// Solution: Set $collection explicitly
protected static string $collection = 'logs';
```

### Wrong Property Used

```php
// MongoDB model using $table (will work but not recommended)
class LogModel extends Model
{
    protected static ?string $connection = 'mongodb';
    protected static string $table = 'logs'; // Works but use $collection instead
}

// Recommended
protected static string $collection = 'logs';
```

## See Also

- [Model Multi-Database Support](./MODEL_MULTI_DATABASE.md)
- [MongoDB Grammar](./MONGODB_GRAMMAR.md)
- [QueryBuilder Connection API](./QUERY_BUILDER_CONNECTION.md)

---

**Version**: 1.0.0
**Last Updated**: January 23, 2025
**Author**: TMP DEV
















