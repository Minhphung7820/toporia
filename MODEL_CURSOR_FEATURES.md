# Model Cursor Features

## Tổng quan

Framework đã được tích hợp đầy đủ tính năng `cursor()` cho Model để xử lý large datasets mà không load vào RAM. Tính năng này sử dụng PDO cursor streaming để fetch từng record một, đảm bảo memory efficiency tối đa.

## Tính năng đã thêm

### 1. Model::cursor()

Method chính để stream models từ database mà không load vào memory.

**Signature:**
```php
public static function cursor(): \Generator
```

**Ví dụ sử dụng:**
```php
// Process all users without loading into memory
foreach (UserModel::cursor() as $user) {
    echo $user->name;
    // Process user one at a time
}

// With query constraints
foreach (UserModel::where('active', true)->cursor() as $user) {
    processUser($user);
}

// With ordering
foreach (UserModel::orderBy('created_at', 'DESC')->cursor() as $user) {
    processUser($user);
}
```

**Performance:**
- Memory: O(1) - Chỉ một Model trong memory tại một thời điểm
- Time: O(N) - Xử lý tất cả records
- Database: Single query, PDO streams results
- Hydration: Models được hydrate on-demand trong quá trình iteration

### 2. ModelQueryBuilder::cursor()

Method trong ModelQueryBuilder để stream models với full query builder support.

**Signature:**
```php
public function cursor(): \Generator
```

**Ví dụ sử dụng:**
```php
// With query builder
$users = UserModel::query()
    ->where('active', true)
    ->where('created_at', '>', '2024-01-01')
    ->orderBy('name', 'ASC')
    ->cursor();

foreach ($users as $user) {
    processUser($user);
}
```

### 3. Model::lazyCollection()

Trả về LazyCollection để có thể chain collection methods.

**Signature:**
```php
public static function lazyCollection(): \Toporia\Framework\Support\Collection\LazyCollection
```

**Ví dụ sử dụng:**
```php
// Chain collection methods with lazy evaluation
$names = UserModel::lazyCollection()
    ->map(fn($user) => $user->name)
    ->filter(fn($name) => strlen($name) > 5)
    ->take(100);

foreach ($names as $name) {
    echo $name;
}
```

### 4. Model::lazyCollectionByChunk()

Trả về LazyCollection sử dụng chunked pagination thay vì cursor.

**Signature:**
```php
public static function lazyCollectionByChunk(int $chunkSize = 1000): \Toporia\Framework\Support\Collection\LazyCollection
```

**Ví dụ sử dụng:**
```php
$users = UserModel::lazyCollectionByChunk(1000)
    ->map(fn($user) => processUser($user))
    ->filter(fn($user) => $user->isActive());
```

## So sánh các phương pháp

| Method | Memory Usage | Database Queries | Use Case |
|--------|-------------|------------------|----------|
| `get()` | O(N) - Tất cả records | 1 | Dataset nhỏ (< 1000 records) |
| `cursor()` | O(1) - Một record | 1 (streaming) | Dataset lớn, xử lý tuần tự |
| `lazyCollection()` | O(1) - Một record | 1 (streaming) | Dataset lớn, cần chain methods |
| `lazyCollectionByChunk()` | O(chunkSize) | N/chunkSize | Khi cursor không available |

## Best Practices

### 1. Sử dụng cursor() khi:
- Dataset lớn (> 1000 records)
- Chỉ cần xử lý tuần tự từng record
- Memory efficiency là ưu tiên
- Không cần truy cập nhiều lần

### 2. Sử dụng lazyCollection() khi:
- Dataset lớn (> 1000 records)
- Cần chain collection methods (map, filter, etc.)
- Memory efficiency là ưu tiên
- Cần lazy evaluation

### 3. Sử dụng get() khi:
- Dataset nhỏ (< 1000 records)
- Cần truy cập nhiều lần
- Cần random access
- Performance không phải vấn đề

### 4. Lưu ý quan trọng:
- **Connection pooling**: Cursor giữ database connection mở trong quá trình iteration. Không sử dụng cho long-running processes cần connection pooling.
- **Eager loading**: Relationships KHÔNG được tự động load. Sử dụng `with()` trước khi gọi `cursor()` nếu cần relationships, nhưng lưu ý điều này có thể ảnh hưởng đến memory usage.
- **Early termination**: Có thể break loop sớm để dừng processing.

## Ví dụ thực tế

### Xử lý large dataset
```php
// Process 1 million users without loading into memory
$processed = 0;
foreach (UserModel::cursor() as $user) {
    processUser($user);
    $processed++;

    if ($processed % 1000 === 0) {
        echo "Processed {$processed} users\n";
    }
}
```

### Export data
```php
// Export users to CSV without loading into memory
$file = fopen('users.csv', 'w');
fputcsv($file, ['id', 'name', 'email']);

foreach (UserModel::cursor() as $user) {
    fputcsv($file, [
        $user->id,
        $user->name,
        $user->email
    ]);
}

fclose($file);
```

### Data migration
```php
// Migrate data without loading into memory
foreach (UserModel::where('old_format', true)->cursor() as $user) {
    $user->migrateToNewFormat();
    $user->save();
}
```

### Chain với collection methods
```php
// Process active users with lazy evaluation
$activeUserNames = UserModel::lazyCollection()
    ->filter(fn($user) => $user->isActive())
    ->map(fn($user) => $user->name)
    ->filter(fn($name) => strlen($name) > 5)
    ->take(100);

foreach ($activeUserNames as $name) {
    echo $name . "\n";
}
```

## Performance Optimization

### 1. Index optimization
Đảm bảo các columns được sử dụng trong WHERE và ORDER BY có indexes:
```php
// Good: Uses index on active column
UserModel::where('active', true)->cursor();

// Good: Uses index on created_at column
UserModel::orderBy('created_at', 'DESC')->cursor();
```

### 2. Select specific columns
Chỉ select columns cần thiết để giảm memory usage:
```php
// Good: Only select needed columns
UserModel::select('id', 'name', 'email')->cursor();
```

### 3. Early termination
Break loop sớm nếu có thể:
```php
foreach (UserModel::cursor() as $user) {
    if ($user->id > 1000) {
        break; // Stop processing
    }
    processUser($user);
}
```

## Clean Code & Best Practices

- ✅ Type safety với proper return types
- ✅ Memory-efficient với PDO cursor streaming
- ✅ Clean API tương tự Laravel
- ✅ High performance với single query streaming
- ✅ Độ chính xác tuyệt đối với proper type hints
- ✅ Comprehensive documentation
- ✅ Error handling built-in

## Kết luận

Tính năng `cursor()` đã được tích hợp đầy đủ vào framework, cho phép xử lý large datasets mà không load vào RAM. Hiệu năng cao, clean code, và độ chính xác tuyệt đối đã được đảm bảo.


