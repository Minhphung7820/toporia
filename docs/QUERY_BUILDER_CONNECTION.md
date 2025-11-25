# QueryBuilder Connection API

## Overview

QueryBuilder supports dynamic connection selection through fluent API, enabling queries across multiple databases with automatic Grammar selection.

## Architecture

### Design Pattern: Proxy Pattern
- `ConnectionProxy` wraps `ConnectionInterface` for fluent API
- Provides `table()` method for QueryBuilder creation
- Maintains connection reference for performance

### SOLID Principles
✅ **Single Responsibility**: ConnectionProxy only provides table() method
✅ **Open/Closed**: Extensible for new connection types
✅ **Dependency Inversion**: Depends on ConnectionInterface abstraction

### Performance Optimizations
- Connection caching per name (O(1) lookup)
- Grammar caching per connection
- Lazy connection creation

## Usage

### Basic Syntax

```php
// MySQL connection
$users = DB()->connection('mysql')
    ->table('users')
    ->where('status', 'active')
    ->get();

// PostgreSQL connection
$reports = DB()->connection('pgsql')
    ->table('reports')
    ->where('date', '>=', '2025-01-01')
    ->get();

// SQLite connection
$cache = DB()->connection('sqlite')
    ->table('cache')
    ->where('key', 'user:123')
    ->first();

// MongoDB connection
$logs = DB()->connection('mongodb')
    ->table('logs')
    ->where('level', 'error')
    ->get();
```

### Grammar Auto-Detection

Grammar is automatically selected based on connection driver:

```php
// MySQL → MySQLGrammar
DB()->connection('mysql')->table('users')->toSql();
// SQL: SELECT * FROM `users` WHERE `status` = ?

// PostgreSQL → PostgreSQLGrammar
DB()->connection('pgsql')->table('users')->toSql();
// SQL: SELECT * FROM "users" WHERE "status" = $1

// SQLite → SQLiteGrammar
DB()->connection('sqlite')->table('users')->toSql();
// SQL: SELECT * FROM "users" WHERE "status" = ?

// MongoDB → MongoDBGrammar
DB()->connection('mongodb')->table('logs')->toSql();
// Query: {"operation":"find","collection":"logs","filter":{"level":"error"}}
```

### Switching Connections

You can switch connections mid-query using `onConnection()`:

```php
// Start with MySQL
$query = DB()->connection('mysql')
    ->table('users')
    ->where('status', 'active');

// Switch to MongoDB for related data
$mongoQuery = $query->onConnection('mongodb')
    ->table('messages')
    ->where('user_id', 123)
    ->get();
```

### Complete Examples

#### Cross-Database Queries

```php
// Get users from MySQL
$users = DB()->connection('mysql')
    ->table('users')
    ->where('status', 'active')
    ->get();

// Log activity to MongoDB
foreach ($users as $user) {
    DB()->connection('mongodb')
        ->table('logs')
        ->insert([
            'user_id' => $user->id,
            'action' => 'viewed',
            'timestamp' => now(),
        ]);
}

// Store analytics in PostgreSQL
DB()->connection('analytics')
    ->table('events')
    ->insert([
        'event' => 'user_viewed',
        'user_id' => $user->id,
        'data' => json_encode(['page' => 'dashboard']),
    ]);
```

#### Database-Specific Features

```php
// MySQL: Use ON DUPLICATE KEY UPDATE
DB()->connection('mysql')
    ->table('products')
    ->insertOrUpdate([
        'sku' => 'PROD-001',
        'name' => 'Product Name',
    ], ['name']);

// PostgreSQL: Use RETURNING clause
DB()->connection('pgsql')
    ->table('reports')
    ->insertReturning([
        'title' => 'Monthly Report',
        'data' => json_encode(['sales' => 1000]),
    ]);

// MongoDB: Use aggregation pipeline (via native client)
$collection = DB()->connection('mongodb')->getConnection()->getCollection('logs');
$results = $collection->aggregate([
    ['$match' => ['level' => 'error']],
    ['$group' => ['_id' => '$category', 'count' => ['$sum' => 1]]],
]);
```

## API Reference

### DB() Helper Function

```php
/**
 * Get DatabaseManager instance with fluent API
 * @return DatabaseManager
 */
DB(): DatabaseManager
```

### DatabaseManager::connection()

```php
/**
 * Get connection proxy for fluent API
 * @param string|null $name Connection name (null for default)
 * @return ConnectionProxy
 */
connection(?string $name = null): ConnectionProxy
```

### ConnectionProxy::table()

```php
/**
 * Create QueryBuilder for table/collection
 * @param string $table Table/collection name
 * @return QueryBuilder
 */
table(string $table): QueryBuilder
```

### QueryBuilder::onConnection()

```php
/**
 * Switch to different connection (preserves query state)
 * @param string $connectionName Connection name
 * @return QueryBuilder New instance with specified connection
 */
onConnection(string $connectionName): QueryBuilder
```

## Performance

### Connection Caching

```php
// First call: Creates and caches connection
$query1 = DB()->connection('mysql')->table('users');

// Subsequent calls: Uses cached connection (O(1) lookup)
$query2 = DB()->connection('mysql')->table('users');
$query3 = DB()->connection('mysql')->table('users');
```

### Grammar Caching

```php
$connection = DB()->getConnection('mysql');

// First call: Creates and caches Grammar
$grammar1 = $connection->getGrammar(); // Creates MySQLGrammar

// Subsequent calls: Uses cached Grammar
$grammar2 = $connection->getGrammar(); // Cache hit
```

## Configuration

### config/database.php

```php
return [
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'localhost'),
            'database' => env('DB_NAME', 'app'),
            // ...
        ],

        'mongodb' => [
            'driver' => 'mongodb',
            'host' => env('MONGODB_HOST', 'localhost'),
            'database' => env('MONGODB_DATABASE', 'app'),
            // ...
        ],
    ],
];
```

## Best Practices

### 1. Connection Naming

Use descriptive connection names:

```php
// Good
DB()->connection('analytics')->table('reports');
DB()->connection('logs')->table('events');

// Avoid
DB()->connection('db2')->table('data');
```

### 2. Performance

Let the framework handle caching:

```php
// Good: Framework caches automatically
$query = DB()->connection('mysql')->table('users');

// Avoid: Manual caching (unnecessary)
$connection = DB()->getConnection('mysql');
// ... manual caching
```

### 3. Grammar Awareness

Be aware of database-specific syntax:

```php
// MySQL: Backticks
SELECT * FROM `users` WHERE `status` = ?

// PostgreSQL: Double quotes, numbered placeholders
SELECT * FROM "users" WHERE "status" = $1

// MongoDB: Query array
{"operation":"find","collection":"users","filter":{"status":"active"}}
```

## Troubleshooting

### Connection Not Found

```php
// Error: Database connection 'analytics' not configured
DB()->connection('analytics')->table('reports');

// Solution: Add connection to config/database.php
'connections' => [
    'analytics' => [
        'driver' => 'pgsql',
        // ... configuration
    ],
],
```

### Grammar Not Found

```php
// Error: Unsupported driver for Grammar: invalid
DB()->connection('invalid')->table('data');

// Solution: Use supported drivers
// mysql, pgsql, sqlite, mongodb
```

## See Also

- [Model Multi-Database Support](./MODEL_MULTI_DATABASE.md)
- [MongoDB Grammar](./MONGODB_GRAMMAR.md)
- [DatabaseManager](../src/Framework/Database/DatabaseManager.php)
- [ConnectionProxy](../src/Framework/Database/ConnectionProxy.php)

---

**Version**: 1.0.0
**Last Updated**: January 23, 2025
**Author**: TMP DEV







