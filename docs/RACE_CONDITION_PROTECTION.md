# Race Condition Protection System

Hệ thống bảo vệ chống race condition toàn diện cho ORM và QueryBuilder của Toporia Framework.

## Tổng quan

Hệ thống cung cấp 3 cơ chế chính:
1. **Pessimistic Locking** - Khóa ở cấp database (FOR UPDATE, LOCK IN SHARE MODE)
2. **Optimistic Locking** - Kiểm tra version để phát hiện conflict
3. **Atomic Operations** - Các thao tác nguyên tử không cần lock

## 1. Pessimistic Locking

### QueryBuilder Level

```php
use Toporia\Framework\Support\Accessors\DB;

// FOR UPDATE - Khóa độc quyền
DB::transaction(function() {
    $user = DB::table('users')
        ->where('id', 1)
        ->lockForUpdate()
        ->first();

    // Cập nhật an toàn
    DB::table('users')
        ->where('id', 1)
        ->update(['balance' => $user['balance'] + 100]);
});

// FOR UPDATE với timeout
DB::table('products')
    ->where('id', 1)
    ->lockForUpdate(5) // Timeout 5 giây
    ->first();

// FOR UPDATE NOWAIT - Fail ngay nếu không lock được
try {
    $product = DB::table('products')
        ->where('id', 1)
        ->lockForUpdateNowait()
        ->first();
} catch (\Exception $e) {
    // Lock không thể acquire
}

// FOR UPDATE SKIP LOCKED - Bỏ qua row đã bị lock
$job = DB::table('jobs')
    ->where('status', 'pending')
    ->orderBy('created_at')
    ->lockForUpdateSkipLocked()
    ->first();

// Shared Lock - Cho phép đọc, chặn ghi
DB::transaction(function() {
    $product = DB::table('products')
        ->where('id', 1)
        ->sharedLock()
        ->first();

    // Có thể đọc an toàn, nhưng không thể update
});
```

### Model Level

```php
use Toporia\Framework\Database\ORM\Concerns\HasRaceConditionProtection;

class ProductModel extends Model
{
    use HasRaceConditionProtection;
}

// Lock model instance
DB::transaction(function() use ($product) {
    $product->lockForUpdate();
    $product->stock -= 1;
    $product->save();
});
```

## 2. Optimistic Locking

### Setup

```php
use Toporia\Framework\Database\ORM\Concerns\OptimisticLocking;

class ProductModel extends Model
{
    use OptimisticLocking;

    // Tùy chọn: đổi tên version column
    protected static string $versionColumn = 'version';

    // Tùy chọn: số lần retry tối đa
    protected static int $maxOptimisticRetries = 3;
}
```

### Migration

```php
Schema::table('products', function($table) {
    $table->integer('version')->default(1);
});
```

### Usage

```php
// Save với optimistic locking tự động
$product = ProductModel::find(1);
$product->price = 99.99;
$product->save(); // Tự động kiểm tra version

// Save với retry tự động
$product->saveWithRetry();

// Save với retry và callback
$product->saveWithRetry(5, function($model, $attempt, $exception) {
    // Refresh model với version mới nhất
    $model->refresh();
    // Merge changes nếu cần
});
```

### Xử lý StaleObjectException

```php
try {
    $product->save();
} catch (\Toporia\Framework\Database\ORM\Exceptions\StaleObjectException $e) {
    // Object đã bị thay đổi bởi transaction khác
    $product->refresh(); // Lấy version mới nhất
    // Merge changes và thử lại
    $product->save();
}
```

## 3. Atomic Operations

### QueryBuilder Level

```php
// Atomic increment - Thread-safe
DB::table('products')
    ->where('id', 1)
    ->atomicIncrement('views'); // Tăng views lên 1

DB::table('products')
    ->where('id', 1)
    ->atomicIncrement('stock', 5); // Tăng stock lên 5

// Atomic decrement
DB::table('products')
    ->where('id', 1)
    ->atomicDecrement('stock'); // Giảm stock xuống 1

// Compare-and-swap (CAS)
$success = DB::table('accounts')
    ->where('id', 1)
    ->compareAndSwap('balance', 100, 150);
    // Chỉ update nếu balance hiện tại = 100

// Atomic update với điều kiện
DB::table('products')
    ->where('id', 1)
    ->atomicUpdateIf('stock', 'stock - 1', 'stock > 0');
    // Chỉ giảm stock nếu stock > 0
```

### Model Level

```php
use Toporia\Framework\Database\ORM\Concerns\HasRaceConditionProtection;

class ProductModel extends Model
{
    use HasRaceConditionProtection;
}

$product = ProductModel::find(1);

// Atomic increment trên model instance
$product->incrementAtomic('views');
$product->incrementAtomic('stock', 5);

// Atomic decrement
$product->decrementAtomic('stock');

// Compare-and-swap
$product->compareAndSwap('balance', 100, 150);
```

## 4. Deadlock Handling

Tự động retry khi gặp deadlock:

```php
// QueryBuilder tự động retry deadlock
DB::table('users')
    ->where('id', 1)
    ->maxDeadlockRetries(5) // Tối đa 5 lần retry
    ->deadlockRetryDelay(200000) // Delay 200ms
    ->lockForUpdate()
    ->first();

// Hoặc sử dụng executeWithDeadlockRetry
$result = DB::table('products')
    ->executeWithDeadlockRetry(function() {
        return DB::table('products')
            ->where('id', 1)
            ->lockForUpdate()
            ->first();
    }, 3); // Max 3 retries
```

## 5. Best Practices

### Khi nào dùng Pessimistic Locking?

- Khi cần đảm bảo tính nhất quán tuyệt đối
- Khi có nhiều thao tác phức tạp trên cùng một record
- Khi transaction ngắn và ít contention

```php
DB::transaction(function() {
    $account = AccountModel::find(1)->lockForUpdate();
    $account->balance -= 100;
    $account->save();

    $transaction = new TransactionModel([
        'account_id' => $account->id,
        'amount' => -100
    ]);
    $transaction->save();
});
```

### Khi nào dùng Optimistic Locking?

- Khi có nhiều read, ít write
- Khi muốn tránh blocking
- Khi có thể handle conflict gracefully

```php
$product = ProductModel::find(1);
$product->price = 99.99;

try {
    $product->saveWithRetry();
} catch (StaleObjectException $e) {
    // Handle conflict - có thể merge changes hoặc báo lỗi
}
```

### Khi nào dùng Atomic Operations?

- Khi chỉ cần increment/decrement đơn giản
- Khi muốn hiệu năng tối đa
- Khi không cần lock

```php
// Tốt nhất cho counter, views, likes, etc.
ProductModel::find(1)->incrementAtomic('views');
ProductModel::find(1)->incrementAtomic('likes');
```

## 6. Performance Considerations

### Pessimistic Locking
- ✅ Đảm bảo consistency
- ❌ Có thể gây blocking
- ❌ Có thể gây deadlock
- 💡 Giữ transaction ngắn

### Optimistic Locking
- ✅ Không blocking
- ✅ Hiệu năng tốt khi ít conflict
- ❌ Cần retry khi conflict
- 💡 Phù hợp read-heavy workloads

### Atomic Operations
- ✅ Hiệu năng cao nhất
- ✅ Không cần lock
- ✅ Thread-safe
- 💡 Phù hợp cho simple operations

## 7. Database Support

| Feature | MySQL | PostgreSQL | SQLite |
|---------|-------|------------|--------|
| FOR UPDATE | ✅ | ✅ | ❌ |
| LOCK IN SHARE MODE | ✅ | ✅ (FOR SHARE) | ❌ |
| NOWAIT | ✅ (8.0+) | ✅ | ❌ |
| SKIP LOCKED | ✅ (8.0+) | ✅ (9.5+) | ❌ |
| Atomic Operations | ✅ | ✅ | ✅ |
| Optimistic Locking | ✅ | ✅ | ✅ |

## 8. Examples

### E-commerce: Giảm stock

```php
// Cách 1: Pessimistic Locking
DB::transaction(function() use ($productId, $quantity) {
    $product = ProductModel::find($productId)->lockForUpdate();

    if ($product->stock < $quantity) {
        throw new \Exception('Insufficient stock');
    }

    $product->stock -= $quantity;
    $product->save();
});

// Cách 2: Atomic Operation (Tốt hơn)
$updated = ProductModel::query()
    ->where('id', $productId)
    ->atomicUpdateIf('stock', 'stock - ' . $quantity, 'stock >= ' . $quantity);

if (!$updated) {
    throw new \Exception('Insufficient stock');
}

// Cách 3: Optimistic Locking
$product = ProductModel::find($productId);
$product->stock -= $quantity;

try {
    $product->saveWithRetry();
} catch (StaleObjectException $e) {
    // Retry logic đã được handle tự động
}
```

### Queue Processing

```php
// Lấy job với SKIP LOCKED
$job = DB::table('jobs')
    ->where('status', 'pending')
    ->where('available_at', '<=', time())
    ->orderBy('priority', 'DESC')
    ->orderBy('created_at', 'ASC')
    ->lockForUpdateSkipLocked()
    ->first();

if ($job) {
    // Process job
    DB::table('jobs')
        ->where('id', $job['id'])
        ->update(['status' => 'processing']);
}
```

### Account Balance Update

```php
// Sử dụng Compare-and-Swap
$account = AccountModel::find(1);
$currentBalance = $account->balance;

if ($account->compareAndSwap('balance', $currentBalance, $currentBalance - 100)) {
    // Update thành công
    Log::info('Balance updated');
} else {
    // Balance đã thay đổi, cần retry
    $account->refresh();
    // Retry logic...
}
```

## 9. Testing

```php
// Test race condition
$product = ProductModel::find(1);

// Simulate concurrent updates
$threads = [];
for ($i = 0; $i < 10; $i++) {
    $threads[] = new Thread(function() use ($product) {
        $p = ProductModel::find($product->id);
        $p->incrementAtomic('views');
    });
}

foreach ($threads as $thread) {
    $thread->start();
}

foreach ($threads as $thread) {
    $thread->join();
}

// Views should be exactly 10
$product->refresh();
assert($product->views === 10);
```

## 10. Troubleshooting

### Deadlock thường xuyên
- Giảm thời gian transaction
- Lock theo thứ tự nhất quán
- Sử dụng optimistic locking thay vì pessimistic

### StaleObjectException quá nhiều
- Tăng số lần retry
- Sử dụng pessimistic locking
- Tối ưu business logic để giảm contention

### Performance chậm
- Sử dụng atomic operations thay vì lock
- Giảm thời gian transaction
- Sử dụng SKIP LOCKED cho queue processing

