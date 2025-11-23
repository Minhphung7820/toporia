# Multi-Database Architecture Design

## Overview

Thiết kế hệ thống ORM/QueryBuilder hỗ trợ đa database (MySQL, PostgreSQL, SQLite, MongoDB) theo **Clean Architecture** và **SOLID Principles**.

## Current Architecture Issues

```
QueryBuilder (current)
├── Hard-coded SQL syntax (MySQL-only)
├── No abstraction for different SQL dialects
├── toSql() generates MySQL-specific queries
└── No support for NoSQL databases
```

## Proposed Architecture

### 1. **Grammar Pattern** (Strategy Pattern for SQL Generation)

```
Grammar (Abstract)
├── MySQLGrammar
├── PostgreSQLGrammar
├── SQLiteGrammar
└── MongoGrammar (NoSQL adapter)
```

**Responsibilities:**
- Compile SELECT, INSERT, UPDATE, DELETE queries
- Handle database-specific syntax (LIMIT vs FETCH, JSON operators, etc.)
- Quote identifiers (backticks vs double quotes)
- Type casting and column definitions

### 2. **Driver Pattern** (Adapter Pattern for Database Operations)

```
Driver (Abstract)
├── MySQLDriver (PDO)
├── PostgreSQLDriver (PDO)
├── SQLiteDriver (PDO)
└── MongoDriver (MongoDB extension)
```

**Responsibilities:**
- Execute queries
- Handle transactions
- Manage connections
- Return unified result format

### 3. **Connection Abstraction**

```php
interface ConnectionInterface
{
    public function getGrammar(): GrammarInterface;
    public function getDriver(): DriverInterface;
    public function select(string $query, array $bindings): array;
    // ...
}
```

## SOLID Principles Application

### 1. **Single Responsibility Principle (SRP)**

- **QueryBuilder**: Build query structure (WHERE, SELECT, JOIN)
- **Grammar**: Compile to SQL dialect
- **Driver**: Execute and fetch results
- **Connection**: Manage database connection

### 2. **Open/Closed Principle (OCP)**

```php
// Open for extension (new databases)
abstract class Grammar {
    abstract public function compileSelect(QueryBuilder $query): string;
}

// Closed for modification
class PostgreSQLGrammar extends Grammar {
    public function compileSelect(QueryBuilder $query): string {
        // PostgreSQL-specific compilation
    }
}
```

### 3. **Liskov Substitution Principle (LSP)**

```php
// Any Grammar can replace another
function executeQuery(QueryBuilder $builder, Grammar $grammar) {
    $sql = $grammar->compileSelect($builder);
    // All grammars produce valid SQL
}
```

### 4. **Interface Segregation Principle (ISP)**

```php
interface GrammarInterface {
    public function compileSelect(QueryBuilder $query): string;
}

interface NoSQLGrammarInterface {
    public function compileAggregation(QueryBuilder $query): array;
}

// MongoDB implements both
class MongoGrammar implements GrammarInterface, NoSQLGrammarInterface
```

### 5. **Dependency Inversion Principle (DIP)**

```php
// High-level QueryBuilder depends on abstraction
class QueryBuilder {
    public function __construct(
        private ConnectionInterface $connection,
        private GrammarInterface $grammar  // Abstraction, not concrete
    ) {}
}
```

## Performance Optimizations

### 1. **Query Compilation Caching**

```php
class Grammar {
    private array $compiledCache = [];

    public function compileSelect(QueryBuilder $query): string {
        $hash = $query->getHash();
        return $this->compiledCache[$hash] ??= $this->doCompile($query);
    }
}
```

### 2. **Lazy Grammar Loading**

```php
class Connection {
    private ?GrammarInterface $grammar = null;

    public function getGrammar(): GrammarInterface {
        return $this->grammar ??= $this->resolveGrammar();
    }
}
```

### 3. **Database-Specific Optimizations**

**PostgreSQL:**
- Use `FETCH FIRST n ROWS` instead of `LIMIT`
- Leverage `RETURNING *` for inserts
- Use native JSON operators (`->`, `->>`)
- Window functions for pagination

**MySQL:**
- Use `LIMIT` with offset
- `ON DUPLICATE KEY UPDATE` for upserts
- JSON path expressions
- Index hints for optimization

**MongoDB:**
- Aggregation pipeline for complex queries
- Projection for column selection
- Native cursor for chunking

## File Structure

```
src/Framework/Database/
├── Contracts/
│   ├── ConnectionInterface.php
│   ├── GrammarInterface.php
│   └── DriverInterface.php
├── Grammar/
│   ├── Grammar.php (abstract base)
│   ├── MySQLGrammar.php
│   ├── PostgreSQLGrammar.php
│   ├── SQLiteGrammar.php
│   └── MongoGrammar.php
├── Drivers/
│   ├── Driver.php (abstract base)
│   ├── PDODriver.php (base for SQL databases)
│   ├── MySQLDriver.php
│   ├── PostgreSQLDriver.php
│   ├── SQLiteDriver.php
│   └── MongoDriver.php
├── Query/
│   └── QueryBuilder.php (uses Grammar abstraction)
└── Connection.php (factory for Grammar + Driver)
```

## Example Usage

```php
// MySQL
$connection = new Connection('mysql', $config);
$users = $connection->table('users')
    ->where('active', true)
    ->get();

// PostgreSQL - same code, different SQL
$connection = new Connection('pgsql', $config);
$users = $connection->table('users')
    ->where('active', true)
    ->get();

// MongoDB - same API, NoSQL backend
$connection = new Connection('mongodb', $config);
$users = $connection->table('users')
    ->where('active', true)
    ->get();
```

**Generated SQL:**

```sql
-- MySQL
SELECT * FROM `users` WHERE `active` = ? LIMIT 100

-- PostgreSQL
SELECT * FROM "users" WHERE "active" = ? FETCH FIRST 100 ROWS ONLY

-- MongoDB (aggregation pipeline)
db.users.aggregate([
    { $match: { active: true } },
    { $limit: 100 }
])
```

## High Reusability

### 1. **Grammar Traits for Common Functionality**

```php
trait CompilesWheres {
    protected function compileWheres(QueryBuilder $query): string {
        // Shared WHERE compilation logic
    }
}

class MySQLGrammar extends Grammar {
    use CompilesWheres, CompilesJoins, CompilesOrders;
}
```

### 2. **Driver Traits for PDO Operations**

```php
trait ExecutesPDOQueries {
    protected function execute(string $sql, array $bindings): PDOStatement {
        // Shared PDO execution
    }
}
```

### 3. **Configurable Query Features**

```php
// Enable/disable features per database
class PostgreSQLGrammar extends Grammar {
    protected array $features = [
        'window_functions' => true,
        'returning_clause' => true,
        'upsert' => true,
    ];
}
```

## Migration Path

### Phase 1: Grammar Abstraction (No Breaking Changes)
1. Create Grammar interface and base class
2. Extract SQL compilation to MySQLGrammar
3. QueryBuilder delegates to Grammar
4. All existing tests pass

### Phase 2: Add PostgreSQL Support
1. Implement PostgreSQLGrammar
2. Add PostgreSQL-specific tests
3. Document PostgreSQL features

### Phase 3: Add SQLite Support
1. Implement SQLiteGrammar
2. Add SQLite-specific tests

### Phase 4: MongoDB Adapter
1. Implement MongoGrammar + MongoDriver
2. Query-to-Aggregation pipeline translation
3. Full NoSQL support

## Testing Strategy

```php
// Grammar tests (unit)
class MySQLGrammarTest {
    public function test_compiles_select_with_where() {
        $grammar = new MySQLGrammar();
        $sql = $grammar->compileSelect($builder);
        $this->assertEquals('SELECT * FROM `users` WHERE `id` = ?', $sql);
    }
}

// Integration tests (all databases)
class MultiDatabaseTest {
    /**
     * @dataProvider databaseProvider
     */
    public function test_where_query($connection) {
        $users = $connection->table('users')->where('active', 1)->get();
        $this->assertCount(5, $users);
    }

    public function databaseProvider() {
        return [
            'mysql' => [new Connection('mysql', $mysqlConfig)],
            'pgsql' => [new Connection('pgsql', $pgsqlConfig)],
            'sqlite' => [new Connection('sqlite', $sqliteConfig)],
        ];
    }
}
```

## Performance Benchmarks Target

- **Query Compilation**: < 0.1ms (with caching: < 0.01ms)
- **Database Switch**: Zero overhead (resolved once per connection)
- **Memory**: < 1KB per Grammar instance
- **Reusability**: 90% code shared between SQL grammars

## Summary

Thiết kế này đảm bảo:
- ✅ **Clean Architecture**: Tách biệt business logic và database implementation
- ✅ **SOLID Principles**: Mỗi class có một trách nhiệm duy nhất
- ✅ **High Performance**: Caching, lazy loading, optimized compilation
- ✅ **High Reusability**: Traits, abstract classes, shared logic
- ✅ **Extensibility**: Dễ dàng thêm database mới
- ✅ **Maintainability**: Code rõ ràng, dễ test, dễ debug
