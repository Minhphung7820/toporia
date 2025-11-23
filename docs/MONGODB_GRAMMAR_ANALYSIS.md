# MongoDB Grammar: Shared vs Specific Features Analysis

**Ngày tạo**: 2025-01-23
**Mục tiêu**: Phân tích MongoDB Grammar để tối ưu code reusability

---

## 📊 Tổng Quan

MongoDB Grammar là một **Adapter Pattern** đặc biệt:
- **Input**: QueryBuilder (SQL-like API)
- **Output**: MongoDB query JSON (NoSQL format)
- **Purpose**: Cho phép dùng cùng API cho cả SQL và NoSQL

```php
// Cùng code PHP
$users = DB::table('users')->where('age', '>', 18)->limit(10)->get();

// SQL Output (MySQL)
SELECT * FROM `users` WHERE `age` > ? LIMIT 10

// MongoDB Output
{
  "operation": "find",
  "collection": "users",
  "filter": {"age": {"$gt": 18}},
  "limit": 10
}
```

---

## 🔍 Phân Loại Features

### **1. SHARED INTERFACE (10 methods)** ✅

Những methods implement từ `GrammarInterface`:

```php
✅ compileSelect(QueryBuilder $query): string
✅ compileInsert(QueryBuilder $query, array $values): string
✅ compileUpdate(QueryBuilder $query, array $values): string
✅ compileDelete(QueryBuilder $query): string
✅ wrapTable(string $table): string
✅ wrapColumn(string $column): string
✅ getParameterPlaceholder(int $index): string
✅ compileLimit(int $limit): string
✅ compileOffset(int $offset): string
✅ supportsFeature(string $feature): bool
```

**Đặc điểm**:
- ✅ **Same interface** với SQL Grammars
- ✅ **Same method signature**
- ⚠️ **Different implementation** (JSON vs SQL)

**Ví dụ**:
```php
// SQL Grammars
public function compileSelect(QueryBuilder $query): string
{
    return "SELECT * FROM `users` WHERE `id` = ?";
}

// MongoDB Grammar
public function compileSelect(QueryBuilder $query): string
{
    $mongoQuery = [
        'operation' => 'find',
        'collection' => 'users',
        'filter' => ['id' => 123]
    ];
    return json_encode($mongoQuery); // ← Returns JSON string
}
```

---

### **2. SAME CONCEPT, DIFFERENT IMPLEMENTATION (17 methods)** 🔄

Những methods có cùng **concept** nhưng **implementation khác hoàn toàn**:

#### **2.1. WHERE Clause Compilation**

**SQL Approach**:
```php
// Grammar.php (SQL base)
protected function compileBasicWhere(array $where): string
{
    $column = $this->wrapColumn($where['column']);
    $operator = $where['operator'];
    return "{$column} {$operator} ?"; // SQL string
}

// Output: "age > ?"
```

**MongoDB Approach**:
```php
// MongoDBGrammar.php
private function compileBasicWhere(array $where): array
{
    $column = $where['column'];
    $operator = $where['operator'];
    $value = $where['value'];

    $mongoOperator = match ($operator) {
        '=' => '$eq',
        '>' => '$gt',
        '>=' => '$gte',
        '<' => '$lt',
        '<=' => '$lte',
        '!=' => '$ne',
    };

    return [$column => [$mongoOperator => $value]]; // MongoDB filter
}

// Output: {"age": {"$gt": 18}}
```

**Analysis**:
- ✅ **Same concept**: Filter data by condition
- ⚠️ **Different output**: SQL string vs MongoDB array
- ⚠️ **Different operators**: SQL `>` vs MongoDB `$gt`
- ❌ **Cannot share code**: Fundamentally different data structures

#### **2.2. WHERE IN Compilation**

**SQL Approach**:
```php
// Grammar.php (SQL base)
protected function compileWhereIn(array $where): string
{
    $column = $this->wrapColumn($where['column']);
    $placeholders = implode(', ', array_fill(0, count($values), '?'));
    return "{$column} IN ({$placeholders})";
}

// Output: "id IN (?, ?, ?)"
```

**MongoDB Approach**:
```php
// MongoDBGrammar.php
private function compileWhereIn(array $where): array
{
    $column = $where['column'];
    $values = $where['values'] ?? [];

    return [$column => ['$in' => $values]];
}

// Output: {"id": {"$in": [1, 2, 3]}}
```

**Analysis**:
- ✅ **Same concept**: Check if value in list
- ⚠️ **Different syntax**: SQL `IN (?)` vs MongoDB `$in`
- ❌ **Cannot share code**: Output types incompatible (string vs array)

#### **2.3. Date/Time Functions**

**SQL Approach**:
```php
// Grammar.php (SQL base)
protected function compileDateBasicWhere(array $where): string
{
    $column = $this->wrapColumn($where['column']);
    $operator = $where['operator'];
    return "DATE({$column}) {$operator} ?";
}

// Output: "DATE(created_at) = ?"
```

**MongoDB Approach**:
```php
// MongoDBGrammar.php
private function compileDateBasicWhere(array $where): array
{
    $column = $where['column'];
    $operator = $where['operator'];
    $value = $where['value'];

    // MongoDB: Use date range for equality
    $dateStart = new \DateTime($value);
    $dateStart->setTime(0, 0, 0);
    $dateEnd = clone $dateStart;
    $dateEnd->setTime(23, 59, 59);

    $mongoOperator = match ($operator) {
        '=' => ['$gte' => $dateStart, '$lte' => $dateEnd],
        '>=' => ['$gte' => $dateStart],
        // ...
    };

    return [$column => $mongoOperator];
}

// Output: {"created_at": {"$gte": ISODate(...), "$lte": ISODate(...)}}
```

**Analysis**:
- ✅ **Same concept**: Filter by date
- ⚠️ **Different approach**: SQL uses DATE() function, MongoDB uses date ranges
- ❌ **Cannot share code**: Completely different logic

---

### **3. MONGODB-SPECIFIC FEATURES (Không có trong SQL)** 🆕

#### **3.1. Aggregation Pipeline**
```php
// MongoDB only - không có tương đương trong SQL Grammars
protected array $features = [
    'aggregation_pipeline' => true, // MongoDB aggregation framework
    'text_search' => true,          // $text operator
    'geospatial' => true,           // $geoWithin, $near
];
```

**Ví dụ**:
```javascript
// MongoDB aggregation (future feature)
db.orders.aggregate([
  { $match: { status: "completed" } },
  { $group: { _id: "$customerId", total: { $sum: "$amount" } } },
  { $sort: { total: -1 } }
])
```

**SQL equivalent**: Requires complex JOINs + GROUP BY

#### **3.2. LIKE → Regex Conversion**
```php
// MongoDBGrammar.php only
private function convertLikeToRegex(string $pattern): string
{
    // Convert SQL LIKE pattern to MongoDB regex
    $pattern = preg_quote($pattern, '/');
    $pattern = str_replace(['%', '_'], ['.*', '.'], $pattern);
    return '^' . $pattern . '$';
}

// Input: "John%"
// Output: "^John.*$" (regex)
```

**Analysis**:
- ❌ **Không có trong SQL**: SQL LIKE là native syntax
- ✅ **MongoDB cần convert**: LIKE → $regex operator

#### **3.3. Projection Compilation**
```php
// MongoDBGrammar.php only
private function compileProjection(QueryBuilder $query): array
{
    $columns = $query->getColumns();

    if (empty($columns) || $columns === ['*']) {
        return []; // Return all fields
    }

    $projection = [];
    foreach ($columns as $column) {
        $projection[trim($column)] = 1; // 1 = include
    }

    return $projection;
}

// Output: {"name": 1, "email": 1, "age": 1}
```

**SQL equivalent**:
```sql
SELECT name, email, age FROM users
```

**Analysis**:
- ✅ **Same concept**: Select specific columns
- ⚠️ **Different implementation**: MongoDB uses projection object
- ❌ **Cannot share with SQL**: SQL builds string, MongoDB builds array

---

## 📈 Code Reusability Analysis

### **Reusability Score**

```
Total MongoDB Grammar methods: 27
├── Shared interface (public): 10 (37%)
├── Same concept, different impl: 17 (63%)
└── MongoDB-specific only: 3 (11%)

Actual code reusability: 0%
Conceptual reusability: 63%
```

**Why 0% actual code reusability?**

**Fundamental incompatibility**:
```php
// SQL Grammars return: string
public function compileSelect(QueryBuilder $query): string
{
    return "SELECT * FROM users"; // ← String
}

// MongoDB Grammar returns: string (but JSON)
public function compileSelect(QueryBuilder $query): string
{
    return json_encode([...]); // ← JSON string (internally array)
}
```

**Root cause**:
- ✅ **Same interface signature**: Both return `string`
- ❌ **Different data structure**: SQL = SQL string, MongoDB = JSON string
- ❌ **Different internal logic**: SQL concatenates strings, MongoDB builds arrays

---

## 🎯 Optimization Opportunities

### **Strategy 1: Share WHERE Type Mapping** ⭐⭐

**Current**: Each WHERE type handled separately in both SQL and MongoDB

**Potential sharing**:
```php
// Base Grammar class
protected const WHERE_TYPE_MAP = [
    'basic' => 'compileBasicWhere',
    'in' => 'compileWhereIn',
    'Null' => 'compileWhereNull',
    'NotNull' => 'compileWhereNotNull',
    // ... etc
];

protected function compileWhere(array $where): mixed
{
    $type = $where['type'];
    $method = self::WHERE_TYPE_MAP[$type] ?? null;

    if (!$method) {
        throw new \InvalidArgumentException("Unknown WHERE type: {$type}");
    }

    return $this->$method($where);
}
```

**Benefit**:
- ✅ Centralize WHERE type handling
- ✅ Easier to add new WHERE types
- ⚠️ But implementations still different

**Savings**: ~10 lines per Grammar (minor)

---

### **Strategy 2: Extract Operator Mapping** ⭐⭐⭐

**Current**: Operator mapping duplicated

**SQL grammars**:
```php
// All SQL grammars support same operators
=, !=, >, >=, <, <=, LIKE, NOT LIKE
```

**MongoDB**:
```php
// MongoDB maps to different operators
'=' => '$eq',
'!=' => '$ne',
'>' => '$gt',
'>=' => '$gte',
'<' => '$lt',
'<=' => '$lte',
'LIKE' => '$regex',
```

**Potential sharing**:
```php
// Base Grammar
protected const OPERATOR_MAP = [
    '=' => '=',
    '!=' => '!=',
    '>' => '>',
    '>=' => '>=',
    '<' => '<',
    '<=' => '<=',
];

// MongoDBGrammar override
protected const OPERATOR_MAP = [
    '=' => '$eq',
    '!=' => '$ne',
    '>' => '$gt',
    '>=' => '$gte',
    '<' => '$lt',
    '<=' => '$lte',
    'LIKE' => '$regex',
];

protected function mapOperator(string $sqlOperator): string
{
    return static::OPERATOR_MAP[$sqlOperator]
        ?? throw new \InvalidArgumentException("Unsupported operator: {$sqlOperator}");
}
```

**Benefit**:
- ✅ Centralize operator mapping
- ✅ Easier to add new operators
- ✅ Type-safe with constants

**Savings**: ~15 lines, better maintainability

---

### **Strategy 3: Share Feature Detection** ⭐⭐⭐⭐

**Current**: Already shared via `supportsFeature()` ✅

```php
// GrammarInterface
public function supportsFeature(string $feature): bool;

// All Grammars implement
protected array $features = [
    'window_functions' => true/false,
    'returning_clause' => true/false,
    'upsert' => true/false,
    // ...
];
```

**Already optimal** - no changes needed ✅

---

### **Strategy 4: Create Abstract NoSQLGrammar Base** ⭐⭐⭐⭐⭐

**Current**: MongoDBGrammar implements GrammarInterface directly

**Proposed**: Create intermediate base class for NoSQL databases

```php
// New abstract class
abstract class NoSQLGrammar implements GrammarInterface
{
    // Shared NoSQL logic

    /**
     * Compile query to array/object (not SQL string)
     *
     * @return array MongoDB query object
     */
    abstract protected function compileToArray(QueryBuilder $query): array;

    /**
     * Final compilation converts to JSON string
     */
    public function compileSelect(QueryBuilder $query): string
    {
        $queryArray = $this->compileToArray($query);
        return json_encode($queryArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    // Shared operator mapping
    protected const SQL_TO_NOSQL_OPERATORS = [
        '=' => '$eq',
        '!=' => '$ne',
        '>' => '$gt',
        '>=' => '$gte',
        '<' => '$lt',
        '<=' => '$lte',
    ];

    protected function mapOperator(string $sqlOperator): string
    {
        return static::SQL_TO_NOSQL_OPERATORS[$sqlOperator] ?? $sqlOperator;
    }
}

// MongoDB implementation
class MongoDBGrammar extends NoSQLGrammar
{
    protected function compileToArray(QueryBuilder $query): array
    {
        return [
            'operation' => 'find',
            'collection' => $query->getTable(),
            'filter' => $this->compileWheres($query),
            // ...
        ];
    }
}

// Future: CassandraGrammar, CouchDBGrammar
class CassandraGrammar extends NoSQLGrammar
{
    protected function compileToArray(QueryBuilder $query): array
    {
        // Cassandra-specific implementation
    }
}
```

**Benefits**:
- ✅ Share code between NoSQL databases
- ✅ Easier to add new NoSQL databases (Redis, Cassandra, CouchDB)
- ✅ Clear separation: SQLGrammar vs NoSQLGrammar
- ✅ Reuse operator mapping, feature detection

**Savings**:
- MongoDB: -20 lines
- Future NoSQL databases: -50 lines each

---

## 📊 Architecture Comparison

### **Current Architecture**

```
GrammarInterface (interface)
    ├── Grammar (abstract, SQL base)
    │   ├── MySQLGrammar
    │   ├── PostgreSQLGrammar
    │   └── SQLiteGrammar
    └── MongoDBGrammar (direct implementation)
```

**Issues**:
- ⚠️ MongoDBGrammar has no shared base with other NoSQL
- ⚠️ Cannot reuse NoSQL logic for future databases

### **Proposed Architecture**

```
GrammarInterface (interface)
    ├── Grammar (abstract, SQL base)
    │   ├── MySQLGrammar
    │   ├── PostgreSQLGrammar
    │   └── SQLiteGrammar
    └── NoSQLGrammar (abstract, NoSQL base) ← NEW
        ├── MongoDBGrammar
        ├── CassandraGrammar (future)
        ├── RedisGrammar (future)
        └── CouchDBGrammar (future)
```

**Benefits**:
- ✅ Clear SQL vs NoSQL separation
- ✅ Shared NoSQL logic (operator mapping, JSON compilation)
- ✅ Easier to add NoSQL databases
- ✅ Better organization

---

## 🎯 Recommendations

### **Immediate Actions (P1 - High Priority)**

1. ✅ **Keep current MongoDB implementation** - Works well, no breaking changes needed
2. ✅ **Document NoSQL differences** - Help developers understand SQL vs MongoDB

### **Short-term (P2 - Medium Priority)**

1. **Create NoSQLGrammar base class** (if planning to add more NoSQL DBs)
   - Time: 2-3 hours
   - Benefit: Future-proof for Cassandra, CouchDB, Redis

2. **Extract operator mapping to constants**
   - Time: 1 hour
   - Benefit: Better maintainability, type safety

### **Long-term (P3 - Low Priority)**

1. **Add more NoSQL databases** (Cassandra, CouchDB, Redis)
   - Will benefit from NoSQLGrammar base
   - Reuse operator mapping, feature detection

2. **Implement aggregation pipeline support**
   - MongoDB-specific advanced feature
   - Not shared with SQL

---

## ✅ Conclusion

### **MongoDB Grammar Sharing Analysis**

```
✅ Interface compatibility: 100% (implements GrammarInterface)
⚠️ Code reusability: 0% (fundamentally different output)
✅ Conceptual reusability: 63% (same WHERE types, operators)
✅ Architecture: Clean Adapter Pattern
```

### **Key Findings**

1. **Cannot share code with SQL Grammars** ❌
   - Different output types (SQL string vs JSON)
   - Different data structures (string concatenation vs array building)

2. **Can share with other NoSQL Grammars** ✅
   - Operator mapping
   - Feature detection
   - JSON compilation logic

3. **Current implementation is optimal** ✅
   - Clean Adapter Pattern
   - No premature optimization
   - Works well as-is

### **Recommendation**

**DO NOT try to share code between SQL and MongoDB Grammars**
- They are fundamentally different
- Forcing code sharing would create abstraction hell
- Current separation is clean and maintainable

**DO create NoSQLGrammar base if adding more NoSQL databases**
- Redis, Cassandra, CouchDB will benefit
- Share operator mapping, JSON compilation
- ~50 lines savings per new NoSQL database

**Overall verdict**: MongoDB Grammar implementation is **EXCELLENT AS-IS** ✅

---

**Created by**: AI Code Analyst
**Date**: 2025-01-23
**Version**: 1.0.0
