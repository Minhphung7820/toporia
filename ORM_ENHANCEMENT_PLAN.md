# ORM & Query Builder Enhancement Plan

## 🎯 Objective
Enhance Toporia ORM/Query Builder to match or exceed Laravel's features while maintaining:
- ✅ **Clean Architecture** principles
- ✅ **SOLID** principles
- ✅ **High Performance** optimization
- ✅ **High Reusability**
- ✅ **Comprehensive Testing**

## 📊 Current State Analysis

### ✅ Already Implemented (Strong Foundation)

#### Query Builder
- ✅ Basic CRUD operations (select, insert, update, delete)
- ✅ WHERE clauses (where, orWhere, whereIn, whereNotIn, whereBetween)
- ✅ JOIN operations (join, leftJoin, rightJoin, crossJoin)
- ✅ ORDER BY, GROUP BY, HAVING
- ✅ LIMIT, OFFSET
- ✅ DISTINCT
- ✅ Aggregate functions (count, sum, avg, min, max)
- ✅ Parameter binding (PDO prepared statements)

#### ORM (Model)
- ✅ Active Record pattern
- ✅ Relationships: HasOne, HasMany, BelongsTo, BelongsToMany
- ✅ Morph relationships: MorphOne, MorphMany, MorphTo, MorphToMany
- ✅ Through relationships: HasOneThrough, HasManyThrough
- ✅ Eager loading (with, load)
- ✅ Relationship aggregates (withCount, withSum, withAvg, withMin, withMax)
- ✅ Soft deletes
- ✅ Query scopes
- ✅ Model observers
- ✅ Batch operations (insertBatch, upsert)
- ✅ Chunking (chunk, chunkById, lazy)
- ✅ Model caching
- ✅ UUID support
- ✅ Timestamps
- ✅ Fillable/Guarded
- ✅ Type casting

#### Migrations
- ✅ Schema builder
- ✅ Table creation/modification
- ✅ Column types
- ✅ Indexes and foreign keys
- ✅ Migration rollback

#### Factory & Seeder
- ✅ Model factories
- ✅ Factory states
- ✅ Factory relationships
- ✅ Database seeding

---

## ❌ Missing Features (To Implement)

### 🔴 HIGH PRIORITY - Query Builder

#### 1. Advanced WHERE Clauses
```php
❌ whereNull($column)
❌ whereNotNull($column)
❌ whereDate($column, $date)
❌ whereMonth($column, $month)
❌ whereDay($column, $day)
❌ whereYear($column, $year)
❌ whereTime($column, $time)
❌ whereColumn($column1, $column2)
❌ whereRaw($sql, $bindings = [])
❌ orWhereNull, orWhereNotNull, etc.
```

#### 2. Subquery Support
```php
❌ whereIn($column, function($query) {})  // Subquery
❌ whereExists(Closure $callback)
❌ whereNotExists(Closure $callback)
❌ select(DB::raw('...'))
❌ selectSub($query, $as)
❌ fromSub($query, $as)
```

#### 3. Advanced Joins
```php
❌ joinSub($query, $as, $callback)
❌ joinWhere($table, $column, $operator, $value)
❌ leftJoinSub, rightJoinSub
```

#### 4. Conditional Clauses
```php
❌ when($condition, $callback, $default = null)
❌ unless($condition, $callback, $default = null)
❌ tap($callback)
```

#### 5. Union Operations
```php
❌ union($query)
❌ unionAll($query)
```

#### 6. Lock Mechanisms
```php
❌ lockForUpdate()  // FOR UPDATE
❌ sharedLock()     // LOCK IN SHARE MODE
```

#### 7. Advanced Aggregates
```php
❌ exists()
❌ doesntExist()
❌ increment($column, $amount = 1)
❌ decrement($column, $amount = 1)
❌ updateOrInsert($attributes, $values)
```

#### 8. Pagination
```php
❌ paginate($perPage = 15)
❌ simplePaginate($perPage = 15)
❌ cursorPaginate($perPage = 15)
```

---

### 🟡 MEDIUM PRIORITY - ORM Features

#### 1. Accessors & Mutators
```php
❌ getAttribute() with automatic accessor calling
❌ setAttribute() with automatic mutator calling
❌ Attribute casting (custom casters)
❌ AsArrayObject, AsCollection, AsEncryptedString casts
```

#### 2. Serialization
```php
❌ toArray() with hidden/visible attributes
❌ toJson() with options
❌ makeVisible($attributes)
❌ makeHidden($attributes)
❌ append($attributes)
```

#### 3. Model Events
```php
❌ retrieved event
❌ saving event
❌ saved event
❌ creating event
❌ created event
❌ updating event
❌ updated event
❌ deleting event
❌ deleted event
❌ restoring event
❌ restored event
❌ replicating event
```

#### 4. Global Scopes
```php
❌ Global scope registration
❌ withoutGlobalScope($scope)
❌ withoutGlobalScopes($scopes = null)
```

#### 5. Model Collections
```php
❌ find($ids)  // Find multiple
❌ fresh()     // Reload from database
❌ refresh()   // Reload and replace current
❌ replicate() // Clone model
❌ touch()     // Update timestamps
```

#### 6. Query Scopes Enhancement
```php
❌ Dynamic scope parameters
❌ Local scopes with multiple parameters
```

#### 7. Mass Assignment Protection
```php
❌ forceFill($attributes)
❌ unguard()
❌ reguard()
❌ preventAccessingMissingAttributes()
```

---

### 🟢 LOW PRIORITY - Advanced Features

#### 1. Database Transactions
```php
❌ DB::transaction($callback)
❌ DB::beginTransaction()
❌ DB::commit()
❌ DB::rollBack()
❌ DB::transactionLevel()
```

#### 2. Query Logging
```php
❌ DB::enableQueryLog()
❌ DB::disableQueryLog()
❌ DB::getQueryLog()
❌ DB::flushQueryLog()
```

#### 3. Database Events
```php
❌ DB::listen(function($query) {})
❌ StatementPrepared event
❌ QueryExecuted event
❌ TransactionBeginning event
❌ TransactionCommitted event
❌ TransactionRolledBack event
```

#### 4. Pruning
```php
❌ Model::prunable()
❌ Model::pruning()
❌ Model::pruned()
```

#### 5. Database Notifications
```php
❌ Model::observe() enhancement
❌ Broadcasting model events
```

---

## 🏗️ Implementation Strategy

### Phase 1: Query Builder Enhancements (Week 1-2)
**Priority:** HIGH
**Estimated Effort:** 40-50 hours

Tasks:
1. ✅ Implement advanced WHERE clauses (whereNull, whereDate, etc.)
2. ✅ Add subquery support (whereIn with Closure, whereExists, etc.)
3. ✅ Implement conditional clauses (when, unless, tap)
4. ✅ Add union operations
5. ✅ Implement lock mechanisms
6. ✅ Add advanced aggregates
7. ✅ Implement pagination (paginate, simplePaginate)
8. ✅ Write comprehensive tests for all features
9. ✅ Benchmark performance vs Laravel

**Success Criteria:**
- All tests passing (100% coverage)
- Performance equal or better than Laravel
- Clean Architecture maintained
- SOLID principles followed

---

### Phase 2: ORM Feature Enhancements (Week 3-4)
**Priority:** MEDIUM
**Estimated Effort:** 30-40 hours

Tasks:
1. ✅ Implement accessors & mutators with magic methods
2. ✅ Enhance serialization (toArray, toJson with options)
3. ✅ Add model events (saving, saved, etc.)
4. ✅ Implement global scopes
5. ✅ Enhance model collections
6. ✅ Add mass assignment protection features
7. ✅ Write comprehensive tests
8. ✅ Update documentation

**Success Criteria:**
- Feature parity with Laravel Eloquent
- All tests passing
- Documentation complete

---

### Phase 3: Advanced Features (Week 5)
**Priority:** LOW
**Estimated Effort:** 20-30 hours

Tasks:
1. ✅ Implement database transactions
2. ✅ Add query logging
3. ✅ Implement database events
4. ✅ Add model pruning
5. ✅ Write tests
6. ✅ Performance optimization

---

## 📋 Testing Requirements

### Test Coverage Targets
- **Query Builder:** 95%+ code coverage
- **ORM (Model):** 90%+ code coverage
- **Relationships:** 90%+ code coverage
- **Migrations:** 85%+ code coverage

### Test Types
1. **Unit Tests:** Individual method testing
2. **Integration Tests:** Database integration testing
3. **Performance Tests:** Benchmark against Laravel
4. **Feature Tests:** End-to-end scenarios

### Test Databases
- SQLite (in-memory for speed)
- MySQL (real-world testing)
- PostgreSQL (compatibility testing)

---

## 🚀 Performance Optimization Strategies

### 1. Query Optimization
- ✅ Use prepared statements (already implemented)
- ✅ Batch operations for bulk inserts/updates
- ✅ Lazy loading to prevent N+1 queries
- ⏳ Query result caching
- ⏳ Index usage analysis

### 2. ORM Optimization
- ✅ Model instance caching (already implemented)
- ✅ Attribute casting optimization
- ⏳ Eager loading optimization
- ⏳ Relationship query optimization

### 3. Memory Optimization
- ✅ Chunking for large datasets (already implemented)
- ✅ Lazy collections (already implemented)
- ⏳ Generator-based result streaming

---

## 📚 Documentation Plan

### Updates Required
1. **docs/DATABASE.md** - Complete Query Builder reference
2. **docs/ORM_ADVANCED.md** - Advanced ORM features
3. **docs/RELATIONSHIPS.md** - Relationship guide
4. **docs/QUERY_OPTIMIZATION.md** - Performance guide
5. **docs/TESTING_DATABASE.md** - Database testing guide

---

## 🎯 Success Metrics

### Feature Completeness
- [ ] 100% of High Priority features implemented
- [ ] 80% of Medium Priority features implemented
- [ ] 50% of Low Priority features implemented

### Quality Metrics
- [ ] 90%+ test coverage
- [ ] 0 critical bugs
- [ ] Performance within 10% of Laravel

### Code Quality
- [ ] All SOLID principles followed
- [ ] Clean Architecture maintained
- [ ] High reusability (traits, interfaces)
- [ ] Comprehensive PHPDoc

---

## 📅 Timeline

**Total Estimated Time:** 5-6 weeks

- **Week 1-2:** Query Builder Enhancements
- **Week 3-4:** ORM Feature Enhancements
- **Week 5:** Advanced Features
- **Week 6:** Testing, Optimization, Documentation

---

## 🤝 Next Steps

1. **Review & Approval:** Get stakeholder approval on plan
2. **Start Phase 1:** Begin Query Builder enhancements
3. **Daily Progress:** Update TODO list daily
4. **Weekly Review:** Review progress weekly
5. **Testing:** Write tests as we build (TDD approach)

---

**Status:** ⏳ Awaiting approval to begin implementation

**Last Updated:** 2025-01-22
