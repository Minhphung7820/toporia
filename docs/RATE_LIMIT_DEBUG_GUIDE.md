# Rate Limit Debug Guide

## Vấn Đề: Bị 429 Ngay Lần Đầu Tiên

### Nguyên Nhân Có Thể

1. **Cache còn giá trị cũ từ test trước**
   - `attempts` còn giá trị cao (ví dụ: 20)
   - `resetTime` còn valid (chưa expire)
   - Request mới: `attempts = 20 >= 20` → exceeded → 429

2. **Logic check có vấn đề**
   - `tooManyAttempts()` check `attempts >= maxAttempts`
   - Nếu `attempts` từ test trước vẫn còn, sẽ bị block

### Cách Debug

#### 1. Check Cache Values

Tạo một route debug để check cache:

```php
Route::get('/debug/rate-limit/{key?}', function ($key = null) {
    $cache = app()->get('cache');
    $limiter = app()->get('rate_limiter');

    // Default key
    if (!$key) {
        $key = 'throttle:ip:' . request()->ip() . ':/api/user';
    }

    $attemptsKey = "rate_limit:{$key}:attempts";
    $resetTimeKey = "rate_limit:{$key}:reset";

    $attempts = $cache->get($attemptsKey);
    $resetTime = $cache->get($resetTimeKey);
    $currentTime = time();

    return [
        'key' => $key,
        'attempts' => $attempts,
        'resetTime' => $resetTime,
        'currentTime' => $currentTime,
        'resetTimeExpired' => $resetTime ? ($resetTime < $currentTime) : null,
        'timeUntilReset' => $resetTime ? max(0, $resetTime - $currentTime) : null,
        'tooManyAttempts' => $limiter->tooManyAttempts($key, 20, 120),
        'remaining' => $limiter->remaining($key, 20),
    ];
});
```

#### 2. Clear Cache Trước Khi Test

```php
Route::post('/debug/clear-rate-limit/{key?}', function ($key = null) {
    $limiter = app()->get('rate_limiter');

    if (!$key) {
        $key = 'throttle:ip:' . request()->ip() . ':/api/user';
    }

    $limiter->resetAttempts($key);

    return ['message' => 'Rate limit cleared for key: ' . $key];
});
```

#### 3. Check Cache Driver

Xem cache driver đang dùng và location:

```php
// Check cache config
$cacheConfig = app()->get('config')->get('cache');
$driver = $cacheConfig['default'] ?? 'file';

// If File cache, check directory
if ($driver === 'file') {
    $cacheDir = $cacheConfig['stores']['file']['path'] ?? storage_path('cache');
    echo "Cache directory: {$cacheDir}\n";

    // List rate limit files
    $files = glob($cacheDir . '/rate_limit*');
    print_r($files);
}
```

### Giải Pháp

#### Option 1: Clear Cache Trước Khi Test

```bash
# Nếu dùng File cache
rm -rf storage/cache/rate_limit*

# Hoặc clear toàn bộ cache
php console cache:clear
```

#### Option 2: Thêm Debug Logging

Thêm logging vào `tooManyAttempts()` để debug:

```php
public function tooManyAttempts(string $key, int $maxAttempts, ?int $decaySeconds = null): bool
{
    $currentTime = time();
    $resetTime = $this->getResetTime($key);
    $attempts = $this->getAttempts($key, $resetTime, $currentTime);

    // Debug logging
    error_log("Rate Limit Check: key={$key}, attempts={$attempts}, maxAttempts={$maxAttempts}, resetTime={$resetTime}, currentTime={$currentTime}");

    // ... rest of logic
}
```

#### Option 3: Auto-cleanup Stale Data

Có thể thêm logic để auto-cleanup stale attempts khi resetTime còn valid nhưng đã lâu:

```php
// In tooManyAttempts(), before checking attempts
if ($resetTime !== null && $resetTime >= $currentTime) {
    // Check if resetTime is very old (might be stale)
    $timeSinceResetSet = $currentTime - ($resetTime - ($decaySeconds ?? self::DEFAULT_DECAY_SECONDS));
    if ($timeSinceResetSet > ($decaySeconds ?? self::DEFAULT_DECAY_SECONDS) * 2) {
        // resetTime is too old, might be stale - clean up
        $this->cleanupExpired($key);
        return false;
    }
}
```

### Quick Fix

Nếu muốn test ngay, clear cache:

```bash
# Clear rate limit cache
find storage/cache -name "*rate_limit*" -delete

# Or clear all cache
rm -rf storage/cache/*
```

