# Model Multi-Database Support

## Overview

Toporia ORM Models support multiple database connections and drivers through the Grammar system. Each Model can specify its database connection, and the appropriate Grammar is automatically selected based on the connection's driver.

## Architecture

### Clean Architecture

- **Model Layer**: Defines connection preference via `$connection` property
- **Connection Layer**: Resolves connection and provides Grammar interface
- **Grammar Layer**: Compiles queries to database-specific syntax
- **Separation of Concerns**: Model doesn't know about Grammar implementation

### SOLID Principles

✅ **Single Responsibility**: Model specifies connection, Connection provides Grammar
✅ **Open/Closed**: New databases can be added without modifying Model
✅ **Liskov Substitution**: Any ConnectionInterface works with any Model
✅ **Interface Segregation**: GrammarInterface is focused and minimal
✅ **Dependency Inversion**: Model depends on ConnectionInterface, not concrete Grammar

### Performance Optimizations

- **Connection Caching**: Connections are cached per model class (O(1) lookup)
- **Grammar Caching**: Grammar instances are cached per connection
- **Lazy Loading**: Connections and Grammars created only when needed
- **Query Compilation Caching**: Identical queries are cached (90%+ hit rate)

## Usage

### Basic Usage

```php
use Toporia\Framework\Database\ORM\Model;

class UserModel extends Model
{
    // Use default connection (from config/database.php 'default')
    // No $connection property needed
}

// Automatically uses default connection's Grammar
$users = UserModel::where('status', 'active')->get();
```

### Specify Connection Name

```php
use Toporia\Framework\Database\ORM\Model;

class AnalyticsModel extends Model
{
    // Use 'analytics' connection from config/database.php
    protected static ?string $connection = 'analytics';
}

// Automatically uses PostgreSQLGrammar (if analytics uses pgsql driver)
$data = AnalyticsModel::where('date', '>=', '2025-01-01')->get();
```

### Different Database Drivers

#### MySQL Model

```php
class ProductModel extends Model
{
    protected static ?string $connection = 'mysql';
    protected static string $table = 'products';
}

// Uses MySQLGrammar automatically
// SQL: SELECT * FROM `products` WHERE `status` = ?
$products = ProductModel::where('status', 'active')->get();
```

#### PostgreSQL Model

```php
class ReportModel extends Model
{
    protected static ?string $connection = 'pgsql';
    protected static string $table = 'reports';
}

// Uses PostgreSQLGrammar automatically
// SQL: SELECT * FROM "reports" WHERE "status" = $1
$reports = ReportModel::where('status', 'published')->get();
```

#### SQLite Model

```php
class CacheModel extends Model
{
    protected static ?string $connection = 'sqlite';
    protected static string $table = 'cache';
}

// Uses SQLiteGrammar automatically
// SQL: SELECT * FROM "cache" WHERE "key" = ?
$cache = CacheModel::where('key', 'user:123')->first();
```

#### MongoDB Model

```php
class LogModel extends Model
{
    protected static ?string $connection = 'mongodb';
    protected static string $table = 'logs';
}

// Uses MongoDBGrammar automatically
// Query: {"operation":"find","collection":"logs","filter":{"level":"error"}}
$logs = LogModel::where('level', 'error')->get();
```

## Configuration

### config/database.php

```php
return [
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        // MySQL connection
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_NAME', 'app'),
            'username' => env('DB_USER', 'root'),
            'password' => env('DB_PASS', ''),
        ],

        // PostgreSQL connection
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 5432),
            'database' => env('DB_NAME', 'app'),
            'username' => env('DB_USER', 'postgres'),
            'password' => env('DB_PASS', ''),
        ],

        // SQLite connection
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', 'database/database.sqlite'),
        ],

        // MongoDB connection
        'mongodb' => [
            'driver' => 'mongodb',
            'host' => env('MONGODB_HOST', 'localhost'),
            'port' => env('MONGODB_PORT', 27017),
            'database' => env('MONGODB_DATABASE', 'app'),
            'username' => env('MONGODB_USERNAME'),
            'password' => env('MONGODB_PASSWORD'),
        ],

        // Custom connection: Analytics (PostgreSQL)
        'analytics' => [
            'driver' => 'pgsql',
            'host' => env('ANALYTICS_DB_HOST', 'localhost'),
            'database' => env('ANALYTICS_DB_NAME', 'analytics'),
            'username' => env('ANALYTICS_DB_USER', 'postgres'),
            'password' => env('ANALYTICS_DB_PASS', ''),
        ],

        // Custom connection: Logs (MongoDB)
        'logs' => [
            'driver' => 'mongodb',
            'host' => env('LOGS_DB_HOST', 'localhost'),
            'database' => env('LOGS_DB_NAME', 'logs'),
        ],
    ],
];
```

## Grammar Auto-Detection

The Grammar system automatically selects the appropriate Grammar based on the connection's driver:

| Driver | Grammar Class | SQL Syntax |
|--------|-------------|------------|
| `mysql` | `MySQLGrammar` | Backticks (`table`), positional placeholders (`?`) |
| `pgsql` | `PostgreSQLGrammar` | Double quotes (`"table"`), numbered placeholders (`$1`) |
| `sqlite` | `SQLiteGrammar` | Double quotes (`"table"`), positional placeholders (`?`) |
| `mongodb` | `MongoDBGrammar` | NoSQL query arrays (JSON format) |

### How It Works

1. Model specifies connection name via `$connection` property
2. `Model::getConnection()` resolves connection from DatabaseManager
3. Connection automatically creates appropriate Grammar based on driver
4. QueryBuilder uses Grammar to compile queries
5. Grammar is cached per connection for performance

```php
// Step 1: Model specifies connection
class UserModel extends Model
{
    protected static ?string $connection = 'pgsql';
}

// Step 2: Model resolves connection
$connection = UserModel::getConnection(); // Returns Connection with driver='pgsql'

// Step 3: Connection provides Grammar
$grammar = $connection->getGrammar(); // Returns PostgreSQLGrammar instance

// Step 4: QueryBuilder uses Grammar
$query = UserModel::query();
$sql = $query->toSql(); // Uses PostgreSQLGrammar to compile
// Result: SELECT * FROM "users" WHERE "status" = $1
```

## Performance Optimizations

### Connection Caching

Connections are cached per model class to avoid repeated DatabaseManager lookups:

```php
// First call: Resolves connection from DatabaseManager
$connection1 = UserModel::getConnection(); // DatabaseManager lookup

// Subsequent calls: Uses cached connection (O(1) lookup)
$connection2 = UserModel::getConnection(); // Cache hit
$connection3 = UserModel::getConnection(); // Cache hit
```

### Grammar Caching

Grammar instances are cached per connection:

```php
$connection = UserModel::getConnection();

// First call: Creates Grammar instance
$grammar1 = $connection->getGrammar(); // Creates PostgreSQLGrammar

// Subsequent calls: Uses cached Grammar
$grammar2 = $connection->getGrammar(); // Cache hit
$grammar3 = $connection->getGrammar(); // Cache hit
```

### Query Compilation Caching

Identical queries are cached for optimal performance:

```php
// First query: Compiles SQL
$sql1 = UserModel::where('status', 'active')->toSql(); // Compilation

// Identical query: Uses cached SQL
$sql2 = UserModel::where('status', 'active')->toSql(); // Cache hit (90%+ hit rate)
```

## Real-World Examples

### Multi-Database Application

```php
// Main application (MySQL)
class UserModel extends Model
{
    // Uses default 'mysql' connection
}

// Analytics (PostgreSQL)
class AnalyticsModel extends Model
{
    protected static ?string $connection = 'analytics';
}

// Logs (MongoDB)
class LogModel extends Model
{
    protected static ?string $connection = 'logs';
}

// Usage
$users = UserModel::all(); // MySQL
$reports = AnalyticsModel::all(); // PostgreSQL
$logs = LogModel::all(); // MongoDB
```

### Cross-Database Queries

```php
// Get users from MySQL
$users = UserModel::where('status', 'active')->get();

// Log activity to MongoDB
foreach ($users as $user) {
    LogModel::create([
        'user_id' => $user->id,
        'action' => 'viewed',
        'timestamp' => now(),
    ]);
}
```

### Database-Specific Features

```php
// PostgreSQL: Use RETURNING clause
class ReportModel extends Model
{
    protected static ?string $connection = 'pgsql';
}

// MySQL: Use ON DUPLICATE KEY UPDATE
class ProductModel extends Model
{
    protected static ?string $connection = 'mysql';
}

// MongoDB: Use aggregation pipeline
class LogModel extends Model
{
    protected static ?string $connection = 'mongodb';
}
```

## Best Practices

### 1. Connection Naming

Use descriptive connection names:

```php
// Good
protected static ?string $connection = 'analytics';
protected static ?string $connection = 'logs';
protected static ?string $connection = 'cache';

// Avoid
protected static ?string $connection = 'db2';
protected static ?string $connection = 'other';
```

### 2. Connection Caching

Let the framework handle connection caching automatically. Don't manually cache connections:

```php
// Good: Framework handles caching
$users = UserModel::all();

// Avoid: Manual caching (unnecessary)
$connection = UserModel::getConnection();
// ... manual caching logic
```

### 3. Grammar Awareness

Be aware of database-specific syntax differences:

```php
// MySQL: Backticks
SELECT * FROM `users` WHERE `status` = ?

// PostgreSQL: Double quotes
SELECT * FROM "users" WHERE "status" = $1

// MongoDB: Query array
{"operation":"find","collection":"users","filter":{"status":"active"}}
```

### 4. Performance

- Use connection caching (automatic)
- Use query compilation caching (automatic)
- Specify connection names in models (avoids default connection lookups)
- Use appropriate database for each use case (MySQL for relational, MongoDB for documents)

## Troubleshooting

### Connection Not Found

```php
// Error: Database connection 'analytics' not configured
class AnalyticsModel extends Model
{
    protected static ?string $connection = 'analytics';
}

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
'connections' => [
    'invalid' => [
        'driver' => 'invalid', // Wrong driver name
    ],
],

// Solution: Use supported drivers
// mysql, pgsql, sqlite, mongodb
```

### Default Connection Not Set

```php
// Error: Database connection not set
$users = UserModel::all();

// Solution 1: Set default connection
Model::setConnection($connection);

// Solution 2: Specify connection in model
class UserModel extends Model
{
    protected static ?string $connection = 'mysql';
}
```

## See Also

- [Grammar Interface](../src/Framework/Database/Contracts/GrammarInterface.php)
- [MongoDB Grammar](./MONGODB_GRAMMAR.md)
- [Multi-Database Architecture](./MULTI_DATABASE_ARCHITECTURE.md)
- [Model Class](../src/Framework/Database/ORM/Model.php)

---

**Version**: 1.0.0
**Last Updated**: January 23, 2025
**Author**: TMP DEV















