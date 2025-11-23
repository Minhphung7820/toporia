# Phase 2: Grammar Integration

## Mục Tiêu

Integrate Grammar layer vào QueryBuilder để automatically compile SQL theo database type.

## Implementation Plan

### Step 1: Update Connection to Create Grammar

```php
class Connection {
    private ?GrammarInterface $grammar = null;

    public function getGrammar(): GrammarInterface {
        if ($this->grammar === null) {
            $this->grammar = $this->createGrammar();
        }
        return $this->grammar;
    }

    protected function createGrammar(): GrammarInterface {
        $driver = $this->getDriverName();

        return match($driver) {
            'mysql' => new MySQLGrammar(),
            'pgsql' => new PostgreSQLGrammar(),
            'sqlite' => new SQLiteGrammar(),
            default => throw new \InvalidArgumentException("Unsupported driver: {$driver}")
        };
    }
}
```

### Step 2: Inject Grammar into QueryBuilder

**Option A: Constructor Injection (Recommended)**
```php
class QueryBuilder {
    public function __construct(
        private ConnectionInterface $connection,
        private ?GrammarInterface $grammar = null
    ) {
        $this->grammar ??= $connection->getGrammar();
    }
}
```

**Option B: Lazy Injection**
```php
class QueryBuilder {
    private ?GrammarInterface $grammar = null;

    protected function getGrammar(): GrammarInterface {
        return $this->grammar ??= $this->connection->getGrammar();
    }
}
```

### Step 3: Update toSql() to Use Grammar

**Current (Hard-coded MySQL):**
```php
public function toSql(): string {
    $sql = sprintf(
        'SELECT %s%s FROM %s%s%s...',
        $distinct,
        implode(', ', $this->columns),
        $this->table,
        // ...
    );
    return $sql;
}
```

**New (Grammar-based):**
```php
public function toSql(): string {
    return $this->getGrammar()->compileSelect($this);
}
```

### Step 4: Update INSERT/UPDATE/DELETE

**Insert:**
```php
public function insert(array $values): bool {
    $sql = $this->getGrammar()->compileInsert($this, $values);
    // Execute...
}
```

**Update:**
```php
public function update(array $values): int {
    $sql = $this->getGrammar()->compileUpdate($this, $values);
    // Execute...
}
```

**Delete:**
```php
public function delete(): int {
    $sql = $this->getGrammar()->compileDelete($this);
    // Execute...
}
```

## Backward Compatibility

✅ **Zero Breaking Changes:**
- Existing code continues to work
- Grammar is auto-detected from Connection
- SQL output identical for MySQL
- Performance unchanged (with caching)

## Testing Strategy

1. Run existing 614 tests → All should pass
2. Add Grammar-specific tests
3. Test MySQL, PostgreSQL, SQLite separately
4. Verify identical SQL output for MySQL

## Benefits

✅ Database-agnostic code
✅ Easy to switch databases
✅ Cleaner QueryBuilder (delegates to Grammar)
✅ Testable SQL compilation
✅ Extensible for new databases

## Next Implementation

Will implement in order:
1. Add getGrammar() to Connection
2. Update QueryBuilder constructor
3. Update toSql() method
4. Test all 614 tests pass
