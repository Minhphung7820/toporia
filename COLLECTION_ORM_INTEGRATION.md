# Collection, ORM và Query Builder Integration

## Tổng quan

Framework này đã được tích hợp hoàn chỉnh giữa Collection, ORM và Query Builder, tương tự như Laravel. Kết quả truy vấn có thể được sử dụng trực tiếp như Collection với đầy đủ các method như `map()`, `filter()`, `pluck()`, v.v.

## Kiến trúc

### 1. Collection Hierarchy

```
Collection (base class)
    ↓
DatabaseCollection (abstract base)
    ├── RowCollection (cho QueryBuilder)
    └── ModelCollection (cho ModelQueryBuilder)
```

### 2. Query Builder Integration

#### QueryBuilder
- `get()` → Trả về `RowCollection` (extends `DatabaseCollection` → extends `Collection`)
- Tất cả Collection methods có sẵn: `map()`, `filter()`, `pluck()`, `each()`, `reduce()`, `sum()`, `min()`, `max()`, `sort()`, `groupBy()`, `chunk()`, `take()`, `skip()`, `first()`, `last()`, `count()`, `isEmpty()`, `isNotEmpty()`, `contains()`, `unique()`, `flatten()`, `values()`, `keys()`, `only()`, `except()`, `merge()`, `concat()`, `zip()`, `partition()`, `reverse()`, `shuffle()`, `random()`, `pad()`, `diff()`, `intersect()`, `union()`, và nhiều hơn nữa.

#### ModelQueryBuilder
- `get()` → Trả về `ModelCollection` (extends `DatabaseCollection` → extends `Collection`)
- Tất cả Collection methods có sẵn, tương tự QueryBuilder
- Thêm các methods đặc biệt cho Models: `modelKeys()`, `find()`, `save()`

## Sử dụng

### 1. Query Builder với Collection Methods

```php
// Ví dụ cơ bản: get()->map()
$users = DB::table('users')
    ->where('active', true)
    ->get()
    ->map(fn($user) => [
        'id' => $user['id'],
        'name' => strtoupper($user['name']),
        'email' => $user['email']
    ]);

// Ví dụ: get()->filter()->map()
$activeUsers = DB::table('users')
    ->get()
    ->filter(fn($user) => $user['status'] === 'active')
    ->map(fn($user) => $user['name']);

// Ví dụ: get()->pluck()
$userIds = DB::table('users')
    ->where('active', true)
    ->get()
    ->pluck('id');

// Ví dụ: get()->groupBy()
$usersByRole = DB::table('users')
    ->get()
    ->groupBy('role');

// Ví dụ: get()->chunk()
$chunks = DB::table('users')
    ->get()
    ->chunk(100);

foreach ($chunks as $chunk) {
    // Process 100 users at a time
    foreach ($chunk as $user) {
        // Process user
    }
}

// Ví dụ: get()->sortBy()
$sortedUsers = DB::table('users')
    ->get()
    ->sortBy('name');

// Ví dụ: get()->sum()
$totalRevenue = DB::table('orders')
    ->where('status', 'completed')
    ->get()
    ->sum('amount');

// Ví dụ: get()->count()
$activeUserCount = DB::table('users')
    ->where('active', true)
    ->get()
    ->count();
```

### 2. Model Query Builder với Collection Methods

```php
// Ví dụ: Model::query()->get()->map()
$users = UserModel::query()
    ->where('active', true)
    ->get()
    ->map(fn($user) => [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email
    ]);

// Ví dụ: Model::query()->get()->filter()
$activeUsers = UserModel::query()
    ->get()
    ->filter(fn($user) => $user->isActive());

// Ví dụ: Model::query()->get()->pluck()
$userNames = UserModel::query()
    ->where('active', true)
    ->get()
    ->pluck('name');

// Ví dụ: Model::query()->get()->groupBy()
$usersByRole = UserModel::query()
    ->get()
    ->groupBy('role');

// Ví dụ: Model::query()->get()->modelKeys()
$userIds = UserModel::query()
    ->where('active', true)
    ->get()
    ->modelKeys();
```

### 3. Lazy Collection (Memory-Efficient)

#### QueryBuilder với LazyCollection

```php
// Sử dụng cursor (memory-efficient nhất)
$users = DB::table('users')
    ->where('active', true)
    ->toLazyCollection()
    ->map(fn($user) => $user['name'])
    ->filter(fn($name) => strlen($name) > 5)
    ->take(100);

foreach ($users as $name) {
    echo $name;
}

// Sử dụng chunked pagination
$users = DB::table('users')
    ->toLazyCollectionByChunk(1000)
    ->map(fn($user) => processUser($user))
    ->filter(fn($user) => $user['active']);
```

#### ModelQueryBuilder với LazyCollection

```php
// Sử dụng cursor (memory-efficient nhất)
$users = UserModel::query()
    ->where('active', true)
    ->toLazyCollection()
    ->map(fn($user) => $user->name)
    ->filter(fn($name) => strlen($name) > 5)
    ->take(100);

foreach ($users as $name) {
    echo $name;
}

// Sử dụng chunked pagination với relationships
$users = UserModel::query()
    ->with('posts')
    ->toLazyCollectionByChunk(1000)
    ->map(fn($user) => $user->posts->count())
    ->filter(fn($count) => $count > 10);
```

## Performance

### Eager Collection (get())
- **Memory**: O(N) - Tất cả records được load vào memory
- **Time**: O(1) - Single query
- **Use case**: Khi dataset nhỏ hoặc cần truy cập nhiều lần

### Lazy Collection (toLazyCollection())
- **Memory**: O(1) - Chỉ một record trong memory tại một thời điểm
- **Time**: O(N) - Single query với cursor streaming
- **Use case**: Khi dataset lớn hoặc cần xử lý tuần tự

### Lazy Collection by Chunk (toLazyCollectionByChunk())
- **Memory**: O(chunkSize) - Chỉ chunkSize records trong memory
- **Time**: O(N) - Multiple queries với LIMIT/OFFSET
- **Use case**: Khi cursor không available hoặc cần xử lý theo chunks

## Tất cả Collection Methods Available

### Transformation Methods
- `map(callable $callback)` - Transform mỗi item
- `mapWithKeys(callable $callback)` - Transform với keys
- `flatMap(callable $callback)` - Map rồi flatten
- `pluck(string|array $path)` - Extract values by key/path
- `keyBy(callable|string $key)` - Reindex by key

### Filtering Methods
- `filter(callable $callback = null)` - Filter items
- `reject(callable $callback)` - Reject items
- `take(int $limit)` - Take first N items
- `skip(int $offset)` - Skip first N items
- `slice(int $offset, ?int $length)` - Slice collection
- `takeWhile(callable $callback)` - Take while condition
- `takeUntil(callable $callback)` - Take until condition
- `skipWhile(callable $callback)` - Skip while condition
- `skipUntil(callable $callback)` - Skip until condition

### Aggregation Methods
- `sum(string|callable|null $callback = null)` - Sum values
- `min(string|callable|null $callback = null)` - Min value
- `max(string|callable|null $callback = null)` - Max value
- `avg(string|callable|null $callback = null)` - Average value
- `median(string|callable|null $callback = null)` - Median value
- `mode(string|callable|null $callback = null)` - Mode value
- `count()` - Count items
- `reduce(callable $callback, mixed $initial = null)` - Reduce to single value

### Search Methods
- `first(callable $callback = null, mixed $default = null)` - First item
- `last(callable $callback = null, mixed $default = null)` - Last item
- `find(int|string $key)` - Find by key (ModelCollection only)
- `contains(mixed $key, mixed $operator = null, mixed $value = null)` - Check if contains
- `some(callable $callback)` - Any item passes test
- `every(callable $callback)` - All items pass test
- `search(mixed $value, bool $strict = false)` - Search for value

### Sorting Methods
- `sort(callable $callback = null)` - Sort collection
- `sortBy(string|callable $callback, bool $descending = false)` - Sort by key
- `sortDesc()` - Sort descending
- `sortKeys(int $flags = SORT_REGULAR)` - Sort by keys
- `reverse()` - Reverse order

### Grouping Methods
- `groupBy(string|callable $key)` - Group items
- `chunk(int $size)` - Chunk into smaller collections
- `split(int $numberOfGroups)` - Split into N groups
- `partition(callable $callback)` - Partition into two collections

### Set Operations
- `unique(string|callable|null $key = null)` - Unique items
- `diff(mixed $items)` - Set difference
- `diffKeys(mixed $items)` - Diff by keys
- `diffBy(mixed $items, callable $keySelector)` - Diff by key selector
- `intersect(mixed $items)` - Set intersection
- `intersectKeys(mixed $items)` - Intersect by keys
- `intersectBy(mixed $items, callable $keySelector)` - Intersect by key selector
- `union(mixed $items)` - Union with other collection

### Combination Methods
- `merge(mixed ...$arrays)` - Merge with other collections
- `concat(mixed ...$iters)` - Concatenate iterables
- `combine(mixed $values)` - Combine with values
- `zip(mixed ...$arrays)` - Zip with other collections
- `crossJoin(mixed ...$arrays)` - Cartesian product

### Utility Methods
- `all()` - Get all items as array
- `toArray()` - Convert to array
- `toJson(int $options = 0)` - Convert to JSON
- `values()` - Get values only (reindex)
- `keys()` - Get keys only
- `only(array $keys)` - Get only specified keys
- `except(array $keys)` - Get all except specified keys
- `isEmpty()` - Check if empty
- `isNotEmpty()` - Check if not empty
- `each(callable $callback)` - Execute callback on each item
- `tap(callable $callback)` - Tap into collection
- `when(bool $condition, callable $callback, ?callable $default = null)` - Conditional callback
- `unless(bool $condition, callable $callback, ?callable $default = null)` - Unless condition
- `pipe(callable $callback)` - Pipe through callback
- `flatten(int|float $depth = INF)` - Flatten nested collection
- `implode(string|callable $value, string $glue = '')` - Implode to string
- `join(string $glue = ', ', string $finalGlue = ' and ')` - Join with final glue
- `random(int $number = 1)` - Get random item(s)
- `shuffle()` - Shuffle items
- `pad(int $size, mixed $value)` - Pad collection

### ModelCollection Specific Methods
- `modelKeys()` - Get array of primary keys
- `find(int|string $key)` - Find model by primary key
- `save()` - Save all models in collection

## Best Practices

1. **Sử dụng `get()` khi dataset nhỏ (< 1000 records)**
2. **Sử dụng `toLazyCollection()` khi dataset lớn (> 1000 records)**
3. **Sử dụng `toLazyCollectionByChunk()` khi cần xử lý relationships**
4. **Chain methods một cách hợp lý để tối ưu performance**
5. **Sử dụng `pluck()` thay vì `map()` khi chỉ cần extract một field**
6. **Sử dụng `filter()` trước `map()` để giảm số lượng items cần xử lý**

## Clean Code & Performance

- ✅ Tất cả methods trả về Collection instances mới (immutable)
- ✅ Type safety với proper return types
- ✅ Memory-efficient với lazy collections
- ✅ Clean API tương tự Laravel
- ✅ High performance với cursor streaming
- ✅ Độ chính xác tuyệt đối với proper type hints

## Kết luận

Framework đã được tích hợp hoàn chỉnh giữa Collection, ORM và Query Builder. Tất cả các methods của Collection đều có sẵn và hoạt động chính xác với kết quả truy vấn, tương tự như Laravel. Hiệu năng cao, clean code, và độ chính xác tuyệt đối đã được đảm bảo.

