# Model Multi-Database Examples

## Quick Start

### Example 1: MySQL Model (Default)

```php
<?php

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

class UserModel extends Model
{
    // Uses default connection (mysql from config)
    // No $connection property needed

    protected static string $table = 'users';
    protected static array $fillable = ['name', 'email', 'password'];
    protected static array $hidden = ['password'];
}

// Usage - automatically uses MySQLGrammar
$users = UserModel::where('status', 'active')->get();
// SQL: SELECT * FROM `users` WHERE `status` = ?
```

### Example 2: PostgreSQL Model

```php
<?php

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

class AnalyticsModel extends Model
{
    // Specify PostgreSQL connection
    protected static ?string $connection = 'pgsql';
    protected static string $table = 'analytics';
}

// Usage - automatically uses PostgreSQLGrammar
$reports = AnalyticsModel::where('date', '>=', '2025-01-01')->get();
// SQL: SELECT * FROM "analytics" WHERE "date" >= $1
```

### Example 3: SQLite Model

```php
<?php

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

class CacheModel extends Model
{
    // Specify SQLite connection
    protected static ?string $connection = 'sqlite';
    protected static string $table = 'cache';
}

// Usage - automatically uses SQLiteGrammar
$cache = CacheModel::where('key', 'user:123')->first();
// SQL: SELECT * FROM "cache" WHERE "key" = ? LIMIT 1
```

### Example 4: MongoDB Model

```php
<?php

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

class LogModel extends Model
{
    // Specify MongoDB connection
    protected static ?string $connection = 'mongodb';
    protected static string $table = 'logs';
}

// Usage - automatically uses MongoDBGrammar
$logs = LogModel::where('level', 'error')
    ->where('created_at', '>=', '2025-01-01')
    ->get();
// Query: {"operation":"find","collection":"logs","filter":{"level":"error","created_at":{"$gte":"2025-01-01"}}}
```

## Advanced Examples

### Multi-Database Application

```php
<?php

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

// Main application data (MySQL)
class UserModel extends Model
{
    protected static string $table = 'users';
}

// Analytics data (PostgreSQL)
class AnalyticsModel extends Model
{
    protected static ?string $connection = 'analytics';
    protected static string $table = 'analytics';
}

// Application logs (MongoDB)
class LogModel extends Model
{
    protected static ?string $connection = 'logs';
    protected static string $table = 'logs';
}

// Usage
$users = UserModel::all(); // MySQL
$reports = AnalyticsModel::all(); // PostgreSQL
$logs = LogModel::all(); // MongoDB
```

### Cross-Database Operations

```php
<?php

// Get users from MySQL
$users = UserModel::where('status', 'active')->get();

// Log activity to MongoDB
foreach ($users as $user) {
    LogModel::create([
        'user_id' => $user->id,
        'action' => 'viewed',
        'timestamp' => now(),
        'metadata' => [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ],
    ]);
}

// Store analytics in PostgreSQL
AnalyticsModel::create([
    'event' => 'user_viewed',
    'user_id' => $user->id,
    'timestamp' => now(),
    'data' => json_encode(['page' => 'dashboard']),
]);
```

### Database-Specific Features

#### PostgreSQL: RETURNING Clause

```php
class ReportModel extends Model
{
    protected static ?string $connection = 'pgsql';
    protected static string $table = 'reports';
}

// PostgreSQLGrammar supports RETURNING clause
$report = ReportModel::create([
    'title' => 'Monthly Report',
    'data' => json_encode(['sales' => 1000]),
]);
// Automatically uses RETURNING * to get inserted data
```

#### MySQL: ON DUPLICATE KEY UPDATE

```php
class ProductModel extends Model
{
    protected static ?string $connection = 'mysql';
    protected static string $table = 'products';
}

// MySQLGrammar supports upsert
ProductModel::query()
    ->insertOrUpdate([
        'sku' => 'PROD-001',
        'name' => 'Product Name',
        'price' => 99.99,
    ], ['price']); // Update price on duplicate
```

#### MongoDB: Aggregation Pipeline

```php
class LogModel extends Model
{
    protected static ?string $connection = 'mongodb';
    protected static string $table = 'logs';
}

// For complex queries, use MongoDB aggregation directly
$pipeline = [
    ['$match' => ['level' => 'error']],
    ['$group' => [
        '_id' => '$category',
        'count' => ['$sum' => 1],
    ]],
];

// Execute with MongoDB client
$collection = $connection->getCollection('logs');
$results = $collection->aggregate($pipeline);
```

## Performance Best Practices

### 1. Connection Caching

Connections are automatically cached per model class:

```php
// First call: Resolves and caches connection
$users1 = UserModel::all(); // Connection resolved

// Subsequent calls: Uses cached connection (O(1) lookup)
$users2 = UserModel::all(); // Cache hit
$users3 = UserModel::all(); // Cache hit
```

### 2. Grammar Caching

Grammar instances are cached per connection:

```php
$connection = UserModel::getConnection();

// First call: Creates and caches Grammar
$grammar1 = $connection->getGrammar(); // Creates MySQLGrammar

// Subsequent calls: Uses cached Grammar
$grammar2 = $connection->getGrammar(); // Cache hit
```

### 3. Query Compilation Caching

Identical queries are cached:

```php
// First query: Compiles SQL
$sql1 = UserModel::where('status', 'active')->toSql();

// Identical query: Uses cached SQL (90%+ hit rate)
$sql2 = UserModel::where('status', 'active')->toSql();
```

## Configuration Examples

### config/database.php

```php
return [
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        // MySQL - Main application
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_NAME', 'app'),
            'username' => env('DB_USER', 'root'),
            'password' => env('DB_PASS', ''),
        ],

        // PostgreSQL - Analytics
        'analytics' => [
            'driver' => 'pgsql',
            'host' => env('ANALYTICS_DB_HOST', 'localhost'),
            'database' => env('ANALYTICS_DB_NAME', 'analytics'),
            'username' => env('ANALYTICS_DB_USER', 'postgres'),
            'password' => env('ANALYTICS_DB_PASS', ''),
        ],

        // MongoDB - Logs
        'logs' => [
            'driver' => 'mongodb',
            'host' => env('LOGS_DB_HOST', 'localhost'),
            'port' => env('LOGS_DB_PORT', 27017),
            'database' => env('LOGS_DB_NAME', 'logs'),
        ],

        // SQLite - Testing
        'testing' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ],
    ],
];
```

## Testing Examples

### Unit Tests with Different Databases

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Infrastructure\Persistence\Models\UserModel;
use App\Infrastructure\Persistence\Models\LogModel;

class MultiDatabaseTest extends TestCase
{
    public function test_mysql_model(): void
    {
        // Uses MySQL connection
        $user = UserModel::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->assertNotNull($user->id);
    }

    public function test_mongodb_model(): void
    {
        // Uses MongoDB connection
        $log = LogModel::create([
            'level' => 'info',
            'message' => 'Test log',
        ]);

        $this->assertNotNull($log->_id ?? $log->id);
    }
}
```

## Migration Examples

### MySQL Migration

```php
Schema::connection('mysql')->create('users', function ($table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamps();
});
```

### PostgreSQL Migration

```php
Schema::connection('analytics')->create('reports', function ($table) {
    $table->id();
    $table->string('title');
    $table->jsonb('data');
    $table->timestamps();
});
```

### MongoDB Collection (No migrations needed)

```php
// MongoDB collections are created automatically
// Just use the model
LogModel::create(['level' => 'info', 'message' => 'Test']);
```

---

**Version**: 1.0.0
**Last Updated**: January 23, 2025
**Author**: TMP DEV










