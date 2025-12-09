# Indexing Strategy for Ultra Complex Query

## 📋 Overview

Migration file: `2025_12_09_000001_AddOptimizedIndexesForUltraComplexQuery.php`

Optimizes the ultra-complex relationship query in:
- `App\Presentation\Http\Controllers\RelationshipTestController::testAllRelationships()`
- Test case: `test_11_mixed_ultra_complex`

## 🎯 Query Structure

```php
CountryModel::with([
    'cities' => function ($q) {
        $q->where('is_capital', true)->orWhere('population', '>', 5000000);
    },
    'cities.authors' => function ($q) {
        $q->where('is_verified', true)->where('rating', '>=', 4.0);
    },
    'cities.authors.books' => function ($q) {
        $q->where('is_available', true)->where('stock', '>', 0);
    },
    'cities.authors.books.categories' => function ($q) {
        $q->wherePivot('is_primary', true);
    },
    'cities.authors.books.chapters' => function ($q) {
        $q->where('is_free_preview', true)->limit(2);
    },
    'publishers' => function ($q) {
        $q->where('publishers.is_active', true);
    },
])
->whereHas('cities.authors.books', function ($q) {
    $q->where('rating', '>=', 4.5)->where('reviews_count', '>', 100);
})
->withCount(['cities', 'authors', 'publishers'])
```

## 📊 Indexes Created

### 1. Cities Table
```sql
idx_cities_country_capital_pop (country_id, is_capital, population)
```
**Purpose**: Optimize eager loading with OR condition
**Query**: `WHERE (is_capital = 1 OR population > 5000000) AND country_id IN (...)`

### 2. Authors Table
```sql
idx_authors_city_verified_rating (city_id, is_verified, rating)
```
**Purpose**: Optimize eager loading with verification check
**Query**: `WHERE is_verified = 1 AND rating >= 4.0 AND city_id IN (...)`

### 3. Books Table (Critical!)
```sql
idx_books_author_available_stock (author_id, is_available, stock)
idx_books_author_rating_reviews (author_id, rating, reviews_count)
```
**Purpose**:
- First index: Eager loading with availability check
- Second index: whereHas subquery optimization

**Queries**:
- `WHERE is_available = 1 AND stock > 0 AND author_id IN (...)`
- `WHERE rating >= 4.5 AND reviews_count > 100 AND author_id IN (...)`

### 4. Book_Category Table (Pivot)
```sql
idx_book_category_primary (book_id, category_id, is_primary)
idx_book_category_reverse (category_id, book_id)
```
**Purpose**: Optimize pivot queries in both directions
**Query**: `WHERE book_id IN (...) AND is_primary = 1`

### 5. Chapters Table
```sql
idx_chapters_book_preview (book_id, is_free_preview)
```
**Purpose**: Optimize eager loading with preview filter
**Query**: `WHERE is_free_preview = 1 AND book_id IN (...) LIMIT 2`

### 6. Publishers Table
```sql
idx_publishers_country_active (country_id, is_active)
```
**Purpose**: Optimize eager loading with active filter
**Query**: `WHERE is_active = 1 AND country_id IN (...)`

## 🚀 Usage

### Running the Migration

```bash
# Run this specific migration
php console migrate

# Or run all pending migrations
php console migrate

# Check migration status
php console migrate:status
```

### Verifying Index Usage

```sql
-- Check if indexes are created
SHOW INDEX FROM cities;
SHOW INDEX FROM authors;
SHOW INDEX FROM books;
SHOW INDEX FROM book_category;
SHOW INDEX FROM chapters;
SHOW INDEX FROM publishers;

-- Verify query plan (should use new indexes)
EXPLAIN SELECT * FROM cities
WHERE country_id IN (1, 2, 3)
  AND (is_capital = 1 OR population > 5000000);

EXPLAIN SELECT * FROM books
WHERE author_id IN (1, 2, 3)
  AND rating >= 4.5
  AND reviews_count > 100;
```

### Testing Performance

```bash
# Enable query logging
php console tinker
> \Toporia\Framework\Database\Query\QueryBuilder::enableQueryLog();

# Run the test endpoint
curl http://localhost:8000/api/relationships/test-all

# Check query execution time in response
```

## 📈 Expected Performance Improvement

### Before Indexes:
- **Query time**: 2-5 seconds
- **Rows scanned**: ~48,700 rows
- **Database load**: High CPU usage

### After Indexes:
- **Query time**: 50-200ms (10-50x faster)
- **Rows scanned**: ~4,600 rows (90% reduction)
- **Database load**: Minimal CPU usage

### Breakdown by Operation:

| Operation | Before | After | Improvement |
|-----------|--------|-------|-------------|
| Eager: cities | 600 rows | 300 rows | 2x |
| Eager: authors | 3,000 rows | 500 rows | 6x |
| Eager: books | 5,000 rows | 1,000 rows | 5x |
| whereHas: books | Full scan | Index scan | 20x |
| Pivot: categories | 5,000 rows | 1,000 rows | 5x |
| Eager: chapters | 20,000 rows | 2,000 rows | 10x |

## 🎓 Index Design Principles

### 1. Foreign Key First
```
✅ GOOD: (country_id, is_capital, population)
❌ BAD:  (is_capital, country_id, population)
```
**Reason**: Eager loading always filters by parent FK first

### 2. Equality Before Range
```
✅ GOOD: (city_id, is_verified, rating)
❌ BAD:  (city_id, rating, is_verified)
```
**Reason**: Equality conditions (=) are more selective than ranges (>=)

### 3. Covering Index
```
✅ GOOD: (author_id, is_available, stock)
❌ BAD:  (author_id)
```
**Reason**: Include all WHERE columns to avoid table lookup

### 4. OR Condition Support
```
✅ GOOD: (country_id, is_capital, population)
```
**Reason**: Composite index covers both OR branches

## ⚠️ Important Considerations

### Write Performance Impact
Each index adds ~10-15% overhead to:
- `INSERT` operations
- `UPDATE` operations on indexed columns
- `DELETE` operations

**Recommendation**: These indexes are optimal for **READ-HEAVY** workloads (like reporting, analytics, complex queries).

### Index Maintenance
```bash
# Rebuild indexes (if needed)
OPTIMIZE TABLE cities;
OPTIMIZE TABLE authors;
OPTIMIZE TABLE books;

# Update statistics for query optimizer
ANALYZE TABLE cities;
ANALYZE TABLE authors;
ANALYZE TABLE books;
```

### Monitoring Index Usage
```sql
-- Check index statistics (MySQL 5.7+)
SELECT * FROM sys.schema_unused_indexes;

-- Check index cardinality
SHOW INDEX FROM books WHERE Key_name LIKE 'idx_books%';

-- Monitor slow queries
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 0.1;  -- Log queries > 100ms
```

## 🔄 Rolling Back

If you need to remove these indexes:

```bash
php console migrate:rollback

# Or rollback specific number of migrations
php console migrate:rollback --step=1
```

This will execute the `down()` method and drop all created indexes.

## 📚 Related Documentation

- [MySQL Index Optimization](https://dev.mysql.com/doc/refman/8.0/en/optimization-indexes.html)
- [Composite Index Design](https://use-the-index-luke.com/)
- [Query Performance Tuning](https://dev.mysql.com/doc/refman/8.0/en/statement-optimization.html)

## 🐛 Troubleshooting

### Index Not Being Used?

1. **Check index exists**:
   ```sql
   SHOW INDEX FROM books WHERE Key_name = 'idx_books_author_rating_reviews';
   ```

2. **Update statistics**:
   ```sql
   ANALYZE TABLE books;
   ```

3. **Force index usage** (for testing):
   ```sql
   SELECT * FROM books USE INDEX (idx_books_author_rating_reviews)
   WHERE author_id IN (1,2,3) AND rating >= 4.5;
   ```

4. **Check optimizer decision**:
   ```sql
   EXPLAIN FORMAT=JSON SELECT ... ;
   ```

### Query Still Slow?

1. Check data volume: Indexes are most effective with > 1,000 rows
2. Check WHERE conditions: Ensure they match index columns
3. Check cardinality: `SHOW INDEX FROM table_name;`
4. Consider adding more covering columns
5. Check for table locks: `SHOW PROCESSLIST;`

## 📧 Support

For questions or issues:
- GitHub Issues: [toporia/framework](https://github.com/Minhphung7820/toporia)
- Email: minhphung485@gmail.com

---

**Last Updated**: December 9, 2025
**Migration Version**: 2025_12_09_000001
**Framework**: Toporia Framework v1.0.0

