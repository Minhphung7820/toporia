# Rate Limit Debug Guide

## Vấn Đề Hiện Tại

Rate limit vẫn bị chặn liên tục sau khi chờ 60s - cứ lặp đi lặp lại.

## Các Fix Đã Áp Dụng

1. ✅ **`tooManyAttempts()`**: Check `resetTime` trước, nếu hết hạn → reset và return false
2. ✅ **`hit()`**: Check `resetTime` trước khi increment, nếu hết hạn → reset attempts về 1
3. ✅ **`attempts()`**: Check `resetTime` trước khi return, nếu hết hạn → return 0

## Logic Hiện Tại

### Flow khi `resetTime` hết hạn:

1. **Request đến** → `tooManyAttempts()` check `resetTime`
2. **Nếu `resetTime === null` hoặc `resetTime < currentTime`**:
   - Reset attempts (delete cả attempts và resetTime keys)
   - Return `false` (cho phép request)
3. **Request tiếp theo** → `hit()` được gọi
   - Check `resetTime` → nếu hết hạn, reset attempts
   - Set `resetTime` mới = `now + decaySeconds`
   - Set `attempts = 1`

## Có Thể Còn Vấn Đề

### 1. Cache TTL Không Đồng Bộ

- `attempts` có TTL = `decaySeconds`
- `resetTime` có TTL = `decaySeconds`
- Nhưng cache driver có thể không expire keys đúng thời điểm

### 2. Cache Delete Không Ngay Lập Tức

- `delete()` có thể không xóa ngay
- Hoặc cache driver có delay trong việc expire keys

### 3. Race Condition

- Có thể có multiple requests đồng thời
- Cache operations có thể không atomic

## Cách Debug

### Test Thủ Công

1. **Check cache keys:**
```php
$cache = app()->get('cache');
$key = 'throttle:user:123:/api/user';
$attemptsKey = "rate_limit:{$key}:attempts";
$resetTimeKey = "rate_limit:{$key}:reset";

// Check values
var_dump([
    'attempts' => $cache->get($attemptsKey),
    'resetTime' => $cache->get($resetTimeKey),
    'currentTime' => time(),
    'resetTimeExpired' => $cache->get($resetTimeKey) < time(),
]);
```

2. **Test reset logic:**
```php
$limiter = app()->get(RateLimiterInterface::class);
$key = 'test:user:123:/api/user';

// Simulate expired resetTime
$cache->set("rate_limit:{$key}:reset", time() - 10, 60); // Expired 10s ago
$cache->set("rate_limit:{$key}:attempts", 60, 60);

// Check
var_dump($limiter->tooManyAttempts($key, 60)); // Should return false
```

### Log Debug

Thêm logging vào code để track:

```php
// In tooManyAttempts()
error_log("tooManyAttempts: key={$key}, resetTime={$resetTime}, currentTime={$currentTime}, expired=" . ($resetTime < $currentTime ? 'yes' : 'no'));
```

## Giải Pháp Khả Thi

### Option 1: Dùng Redis với TTL chính xác hơn

### Option 2: Không dùng cache TTL, chỉ dùng timestamp

- Store `resetTime` như timestamp (Unix time)
- Check `resetTime < time()` thay vì dựa vào cache TTL
- Xóa `attempts` khi `resetTime` hết hạn

### Option 3: Reset attempts khi `resetTime` hết hạn trong tất cả methods

- Check `resetTime` trong MỌI method
- Đảm bảo consistency

