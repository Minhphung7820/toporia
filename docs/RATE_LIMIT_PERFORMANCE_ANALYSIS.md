# Rate Limiter Performance Analysis

## 🔍 Phân Tích Hiệu Năng

### ✅ Điểm Tốt

1. **Cache-based**: Sử dụng cache để tránh database queries
2. **Atomic Operations**: Sử dụng `increment()` cho attempts
3. **TTL Auto-expiry**: Cache tự động expire, không cần cleanup manual

### ⚠️ Vấn Đề Hiệu Năng

#### 1. **Redundant Cache Calls** (Nghiêm Trọng)

**Vấn đề:**
- `tooManyAttempts()` gọi `attempts()` → `attempts()` lại gọi `cache->get(resetTimeKey)`
- Nhưng `tooManyAttempts()` đã lấy `resetTime` rồi → **duplicate cache call**

**Impact:**
- Mỗi request thực hiện **3 cache calls** thay vì **2 cache calls**
- Tăng latency ~30-50% (tùy cache driver)

**Example:**
```php
// tooManyAttempts()
$resetTime = $this->cache->get($resetTimeKey); // Call 1
$attempts = $this->attempts($key); // →
    // attempts()
    $resetTime = $this->cache->get($resetTimeKey); // Call 2 (DUPLICATE!)
    return $this->cache->get($attemptsKey); // Call 3
```

#### 2. **Multiple Cache Keys Per Rate Limit**

**Vấn đề:**
- Mỗi rate limit key có **3 cache keys**: attempts, resetTime, resetFlag
- Với 10,000 active users → **30,000 cache keys**
- Tăng memory usage và cache overhead

**Current:**
```
rate_limit:user:123:attempts
rate_limit:user:123:reset
rate_limit:user:123:reset_flag
```

**Optimization:**
- Có thể combine thành 1 cache key với array value
- Giảm 66% số lượng cache keys

#### 3. **Repeated Cache Reads**

**Vấn đề:**
- `tooManyAttempts()` và `availableIn()` đều đọc `resetTime`
- Middleware có thể gọi cả 2 methods → **duplicate reads**

**Flow hiện tại:**
```php
// Middleware
if ($limiter->tooManyAttempts($key, $max)) { // Read resetTime
    $retryAfter = $limiter->availableIn($key); // Read resetTime AGAIN
}
```

#### 4. **Complex Logic in Hot Path**

**Vấn đề:**
- `tooManyAttempts()` có nhiều nested if/checks
- `attempts()` có logic check resetTime phức tạp
- Tính toán `originalResetTime`, `timeSinceWindowStart` mỗi lần

**Impact:**
- Tăng CPU overhead cho mỗi request
- Code khó maintain và debug

#### 5. **Missing resetFlag Cleanup**

**Vấn đề:**
- `resetAttempts()` xóa resetFlag, nhưng không xóa khi resetTime tự expire
- Có thể tạo orphan cache keys

---

## 🚀 Đề Xuất Cải Thiện

### Priority 1: Giảm Redundant Cache Calls

**Solution:**
- Pass `resetTime` vào `attempts()` thay vì đọc lại từ cache
- Hoặc cache `resetTime` trong memory cho duration của request

**Before:**
```php
public function tooManyAttempts(...) {
    $resetTime = $this->cache->get($resetTimeKey); // Call 1
    $attempts = $this->attempts($key); // →
        $resetTime = $this->cache->get($resetTimeKey); // Call 2 (DUPLICATE)
}
```

**After:**
```php
public function tooManyAttempts(...) {
    $resetTime = $this->cache->get($resetTimeKey); // Call 1
    $attempts = $this->attempts($key, $resetTime); // Pass as parameter
}
```

**Impact:** Giảm 33% cache calls

### Priority 2: Combine Cache Keys

**Solution:**
- Store attempts + resetTime + resetFlag trong 1 cache key

**Before:**
```php
// 3 separate keys
attempts: 123
reset: 1234567890
reset_flag: true
```

**After:**
```php
// 1 combined key
rate_limit:user:123: {attempts: 123, resetTime: 1234567890, resetFlag: true}
```

**Impact:** Giảm 66% số lượng cache keys, giảm memory

### Priority 3: Cache Result in Request

**Solution:**
- Cache `resetTime` và `attempts` trong memory cho duration của request
- Tránh duplicate reads trong cùng 1 request

**Implementation:**
```php
private array $requestCache = [];

private function getResetTime(string $key): ?int {
    if (!isset($this->requestCache['reset'][$key])) {
        $this->requestCache['reset'][$key] = $this->cache->get($this->resetTimeKey($key));
    }
    return $this->requestCache['reset'][$key];
}
```

### Priority 4: Simplify Logic

**Solution:**
- Extract complex calculations thành helper methods
- Reduce nested if statements
- Use early returns

**Before:**
```php
if ($resetTime === null) {
    $attempts = $this->attempts($key);
    if ($attempts > 0) {
        $this->resetAttempts($key);
    }
    return false;
}
if ($resetTime < $currentTime) {
    $this->resetAttempts($key);
    return false;
}
// ... more nested logic
```

**After:**
```php
if ($this->shouldReset($resetTime, $currentTime)) {
    $this->resetAttempts($key);
    return false;
}
```

---

## 📊 Performance Metrics (Estimate)

### Current Performance:
- **Cache calls per request**: 3-4 calls
- **Memory per user**: ~150 bytes (3 keys)
- **Latency per request**: ~2-5ms (cache-dependent)

### After Optimization:
- **Cache calls per request**: 1-2 calls (-50%)
- **Memory per user**: ~50 bytes (-66%)
- **Latency per request**: ~1-3ms (-40%)

### Scale Impact:
- **10,000 active users**: 30,000 cache keys → 10,000 cache keys
- **1M requests/day**: 3M cache calls → 1.5M cache calls

---

## 🎯 Khuyến Nghị

1. **Immediate** (Priority 1-2): Giảm redundant cache calls
2. **Short-term** (Priority 3): Combine cache keys
3. **Long-term** (Priority 4): Refactor code structure

**Current code hoạt động đúng, nhưng có thể tối ưu hơn 40-50%.**

