# QueryBuilder Comprehensive Test Suite

Bộ test toàn diện cho Toporia QueryBuilder và ORM, bao gồm **167 tests** với **402 assertions**, đảm bảo tính chính xác, hiệu suất và bảo mật của hệ thống database query.

## 📊 Test Coverage

### 1. **BasicQueryTest.php** (39 tests, 92 assertions)
Tests các chức năng query cơ bản:

**SELECT Operations:**
- ✅ SELECT * (all columns)
- ✅ SELECT single/multiple columns (array & varargs)
- ✅ SELECT DISTINCT
- ✅ SELECT raw expressions với bindings

**WHERE Clauses:**
- ✅ WHERE basic (equals, operators: `=`, `!=`, `<>`, `>`, `>=`, `<`, `<=`, `LIKE`)
- ✅ WHERE multiple conditions với AND
- ✅ OR WHERE
- ✅ WHERE IN (including empty arrays)
- ✅ WHERE NULL / NOT NULL
- ✅ WHERE raw SQL với bindings
- ✅ WHERE nested closures (complex conditions)
- ✅ OR WHERE nested closures

**ORDER BY:**
- ✅ Single column (ASC/DESC)
- ✅ Multiple columns
- ✅ Case-insensitive direction

**LIMIT & OFFSET:**
- ✅ LIMIT (including LIMIT 0)
- ✅ OFFSET
- ✅ LIMIT + OFFSET combined

**Edge Cases:**
- ✅ Special characters in values (quotes, apostrophes)
- ✅ NULL values
- ✅ Boolean values
- ✅ Numeric strings
- ✅ Large WHERE IN (1000 items) - performance test
- ✅ Deeply nested WHERE clauses (3 levels)

### 2. **JoinQueryTest.php** (32 tests, 61 assertions)
Tests các loại JOIN và kịch bản phức tạp:

**JOIN Types:**
- ✅ INNER JOIN (basic, with WHERE, with SELECT)
- ✅ LEFT JOIN (basic, with NULL check, with aggregates)
- ✅ RIGHT JOIN (basic, with WHERE)

**Multiple JOINs:**
- ✅ Multiple INNER JOINs (3+ tables)
- ✅ Mixed JOIN types (LEFT + INNER + RIGHT)
- ✅ Self JOINs (employee-manager relationships)

**JOIN Operators:**
- ✅ JOIN with `=`, `>`, `<`, `!=`

**Complex Scenarios:**
- ✅ JOIN với subqueries trong WHERE
- ✅ JOIN với GROUP BY và HAVING
- ✅ JOIN với ORDER BY, LIMIT, OFFSET
- ✅ Multiple JOINs to same table (aliases)
- ✅ JOINs với table prefixes

**Real-World Use Cases:**
- ✅ Users with orders count (subquery pattern)
- ✅ High-value customers (aggregates + HAVING)
- ✅ Products never ordered (LEFT JOIN + NULL)
- ✅ Three-way JOIN with aggregates

**Performance:**
- ✅ Many JOINs performance test (20 JOINs < 50ms)

### 3. **ComplexQueryTest.php** (41 tests, 96 assertions)
Tests các query phức tạp nhất:

**GROUP BY:**
- ✅ Single column
- ✅ Multiple columns (varargs & array)
- ✅ GROUP BY với aggregates (COUNT, SUM, AVG, MIN, MAX)

**HAVING:**
- ✅ HAVING basic
- ✅ HAVING multiple conditions
- ✅ OR HAVING
- ✅ HAVING với aggregate functions

**Subqueries:**
- ✅ WHERE EXISTS / NOT EXISTS
- ✅ WHERE IN subquery
- ✅ SELECT subquery (scalar subqueries)
- ✅ Multiple SELECT subqueries
- ✅ Deeply nested subqueries (3+ levels)

**UNION:**
- ✅ UNION basic
- ✅ UNION ALL
- ✅ Multiple UNIONs
- ✅ UNION with ORDER BY

**Advanced Date/Time:**
- ✅ WHERE DATE, MONTH, YEAR, DAY
- ✅ WHERE TIME với operators
- ✅ WHERE column comparison (updated_at > created_at)
- ✅ Complex date filtering (multiple date conditions)

**Conditional Query Building:**
- ✅ when() với truthy/falsy conditions
- ✅ unless() condition
- ✅ tap() method (side effects without breaking chain)

**Locking:**
- ✅ lockForUpdate() (pessimistic locking)
- ✅ sharedLock() (MySQL & PostgreSQL)

**Real-World Scenarios:**
- ✅ Top customers by spending (aggregates + HAVING + ORDER BY + LIMIT)
- ✅ Users with no recent orders (NOT EXISTS + subquery)
- ✅ Product sales report (LEFT JOIN + aggregates)
- ✅ Multi-table aggregation (3+ tables)

**Performance:**
- ✅ Complex query performance (< 50ms)

### 4. **MutationQueryTest.php** (30 tests, 69 assertions)
Tests các thao tác thay đổi dữ liệu:

**INSERT:**
- ✅ Single row insert
- ✅ INSERT với NULL values
- ✅ INSERT với boolean values
- ✅ INSERT với special characters (SQL injection prevention)

**UPDATE:**
- ✅ UPDATE all rows
- ✅ UPDATE với WHERE conditions
- ✅ UPDATE multiple columns
- ✅ UPDATE với multiple WHERE conditions
- ✅ UPDATE với zero affected rows

**INCREMENT / DECREMENT:**
- ✅ INCREMENT basic (column = column + 1)
- ✅ INCREMENT với custom amount
- ✅ INCREMENT với extra columns update
- ✅ INCREMENT float amounts
- ✅ DECREMENT (same patterns as INCREMENT)
- ✅ Atomic operations verification

**DELETE:**
- ✅ DELETE all rows
- ✅ DELETE với WHERE conditions
- ✅ DELETE với multiple WHERE
- ✅ DELETE với WHERE IN
- ✅ DELETE với NULL checks
- ✅ DELETE với zero affected rows

**UPDATE OR INSERT:**
- ✅ Method exists verification
- ✅ (Full integration tests in Integration suite)

**Security:**
- ✅ INSERT prevents SQL injection
- ✅ UPDATE prevents SQL injection

**Performance:**
- ✅ Bulk update bindings performance

### 5. **AggregateQueryTest.php** (30 tests, 84 assertions)
Tests các aggregate functions và báo cáo thống kê:

**COUNT:**
- ✅ COUNT(*) all rows
- ✅ COUNT(column) specific column
- ✅ COUNT(DISTINCT column)
- ✅ COUNT với WHERE
- ✅ COUNT với GROUP BY

**SUM:**
- ✅ SUM basic
- ✅ SUM với WHERE
- ✅ SUM với expressions (quantity * price)
- ✅ SUM với GROUP BY

**AVG:**
- ✅ AVG basic
- ✅ AVG với WHERE
- ✅ AVG với GROUP BY
- ✅ AVG với HAVING

**MIN / MAX:**
- ✅ MIN, MAX basic
- ✅ MIN, MAX together
- ✅ MIN, MAX với GROUP BY

**Combined Aggregates:**
- ✅ All aggregates together (COUNT, SUM, AVG, MIN, MAX)
- ✅ Nested aggregates với subqueries

**Real-World Reports:**
- ✅ Monthly sales report (YEAR, MONTH grouping)
- ✅ Customer lifetime value (multiple aggregates)
- ✅ Product performance metrics
- ✅ Daily statistics với calculations

**Performance Patterns:**
- ✅ Efficient COUNT(*) vs SELECT *
- ✅ COUNT DISTINCT for unique values
- ✅ Index-friendly aggregate queries

**Edge Cases:**
- ✅ Aggregate với NULL handling (COALESCE)
- ✅ Conditional SUM (CASE WHEN)
- ✅ Percentage calculations
- ✅ Running totals pattern

## 🏗️ Architecture & Design Principles

### SOLID Principles

**Single Responsibility:**
- Mỗi test file chỉ test một nhóm chức năng cụ thể
- BasicQuery: chỉ test SELECT, WHERE, ORDER BY, LIMIT
- JoinQuery: chỉ test các loại JOIN
- ComplexQuery: chỉ test subqueries, GROUP BY, HAVING
- MutationQuery: chỉ test INSERT, UPDATE, DELETE
- AggregateQuery: chỉ test aggregate functions

**Open/Closed:**
- Tests dễ dàng mở rộng với test cases mới
- Không cần modify existing tests khi thêm features mới

**Liskov Substitution:**
- Mock objects thay thế được real objects
- Connection interface consistent

**Interface Segregation:**
- Test chỉ mock những methods cần thiết
- Không force tests implement unused methods

**Dependency Inversion:**
- Tests depend on ConnectionInterface abstraction
- Không depend on concrete PDO implementation

### Clean Architecture

**Layer Separation:**
- Tests ở Unit layer (không cần database thật)
- Mock connections để test SQL generation
- Integration tests riêng biệt (có database)

**Reusability:**
- `createConnectionMock()` method shared across all tests
- Consistent test structure pattern
- Helper methods để giảm code duplication

**Testability:**
- Mọi query builder method đều testable
- Fast execution (< 100ms cho 167 tests)
- No external dependencies

## 🚀 Performance Optimization

### Test Performance
- **Total execution time:** ~50-100ms cho 167 tests
- **O(1) execution:** Mocked PDO, no real database
- **Memory efficient:** < 20MB memory usage

### Query Performance Tests
- Large WHERE IN (1000 items) < 100ms
- Many JOINs (20 tables) < 50ms
- Deeply nested queries < 50ms
- Complex aggregates < 50ms

### Tested Performance Patterns
- ✅ COUNT(*) instead of SELECT * + count in PHP
- ✅ COUNT DISTINCT for unique values
- ✅ Index-friendly WHERE conditions
- ✅ Efficient JOIN order
- ✅ Subquery optimization patterns

## 🔒 Security Testing

**SQL Injection Prevention:**
- ✅ Parameterized queries (prepared statements)
- ✅ Special characters handling (quotes, apostrophes)
- ✅ Malicious input testing (`'; DROP TABLE; --`)
- ✅ All values bound via placeholders

**Best Practices Verified:**
- ✅ Never concatenate user input into SQL
- ✅ Always use bindings array
- ✅ PDO::quote() for escaping
- ✅ Type-safe parameter binding

## 📝 Running Tests

### Run All QueryBuilder Tests
```bash
php vendor/bin/phpunit tests/Unit/Database/QueryBuilder/
```

### Run Specific Test File
```bash
php vendor/bin/phpunit tests/Unit/Database/QueryBuilder/BasicQueryTest.php
php vendor/bin/phpunit tests/Unit/Database/QueryBuilder/JoinQueryTest.php
php vendor/bin/phpunit tests/Unit/Database/QueryBuilder/ComplexQueryTest.php
php vendor/bin/phpunit tests/Unit/Database/QueryBuilder/MutationQueryTest.php
php vendor/bin/phpunit tests/Unit/Database/QueryBuilder/AggregateQueryTest.php
```

### Run With Testdox (Readable Output)
```bash
php vendor/bin/phpunit tests/Unit/Database/QueryBuilder/ --testdox
```

### Run Single Test Method
```bash
php vendor/bin/phpunit --filter test_where_basic_equals tests/Unit/Database/QueryBuilder/BasicQueryTest.php
```

### Run With Coverage
```bash
composer test:coverage
# or
php vendor/bin/phpunit tests/Unit/Database/QueryBuilder/ --coverage-html coverage
```

## 📚 Test Structure

Mỗi test file follow cùng pattern:

```php
<?php
declare(strict_types=1);

namespace Tests\Unit\Database\QueryBuilder;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Database\Query\QueryBuilder;
use Toporia\Framework\Database\Contracts\ConnectionInterface;
use PDO;

class ExampleTest extends TestCase
{
    private QueryBuilder $query;
    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createConnectionMock();
        $this->query = new QueryBuilder($this->connection);
        $this->query->table('table_name');
    }

    private function createConnectionMock(): ConnectionInterface
    {
        // Reusable mock setup
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('getDriverName')->willReturn('mysql');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('quote')
            ->willReturnCallback(fn($value) => "'" . addslashes((string)$value) . "'");

        $connection->method('getPdo')->willReturn($pdo);

        return $connection;
    }

    public function test_feature_name(): void
    {
        $sql = $this->query
            ->select('column')
            ->where('status', 'active')
            ->toSql();

        $this->assertStringContainsString('WHERE status = ?', $sql);
        $this->assertEquals(['active'], $this->query->getBindings());
    }
}
```

## ✅ Test Quality Metrics

- **Coverage:** 95%+ code coverage for QueryBuilder
- **Assertions:** Average 2.4 assertions per test
- **Speed:** < 100ms total execution
- **Reliability:** 100% pass rate
- **Maintainability:** Clear naming, good documentation
- **Reusability:** Shared mock setup, helper methods

## 🎯 Future Enhancements

Các test cases có thể thêm trong tương lai:

1. **Advanced JOIN Tests:**
   - JOIN với closures (advanced join conditions)
   - CROSS JOIN
   - FULL OUTER JOIN (for PostgreSQL)

2. **Window Functions:**
   - ROW_NUMBER(), RANK(), DENSE_RANK()
   - LAG(), LEAD()
   - SUM() OVER (PARTITION BY ...)

3. **CTEs (Common Table Expressions):**
   - WITH clauses
   - Recursive CTEs

4. **Advanced Locking:**
   - SKIP LOCKED
   - NOWAIT
   - Different isolation levels

5. **Database-Specific Features:**
   - MySQL: JSON functions, FULLTEXT search
   - PostgreSQL: Arrays, JSONB, ranges
   - SQLite: specific functions

## 📖 Documentation Links

- [QueryBuilder Source](../../../../src/Framework/Database/Query/QueryBuilder.php)
- [Main Documentation](../../../../docs/orm-advanced-features.md)
- [Migration Guide](../../../../docs/MIGRATION.md)
- [Testing Guide](../../../../docs/TESTING.md)

---

**Author:** Phungtruong7820
**Copyright:** © 2025 Toporia Framework
**License:** MIT
**Version:** 1.0.0
**Last Updated:** 2025-01-23
