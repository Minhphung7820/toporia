# Rate Limit Cache Driver

## Overview

Rate limiter trong Toporia Framework sử dụng cache driver được cấu hình qua biến môi trường `CACHE_DRIVER`.

## Flow

### 1. Cấu hình Cache Driver

**File:** `config/cache.php`

```php
'default' => env('CACHE_DRIVER', 'file'),
```

- Biến môi trường `CACHE_DRIVER` xác định driver mặc định
- Giá trị mặc định: `'file'` (nếu không set env)

**Các driver được hỗ trợ:**
- `file` - File-based cache (storage/cache)
- `redis` - Redis cache
- `memory` / `array` - In-memory cache (cho testing)

### 2. Cache Service Provider

**File:** `src/Framework/Providers/CacheServiceProvider.php`

```php
$container->singleton(CacheManager::class, function ($c) {
    $config = $c->has('config')
        ? $c->get('config')->get('cache', [])
        : $this->getDefaultConfig();

    return new CacheManager($config);
});

$container->bind(CacheInterface::class, fn($c) => $c->get(CacheManager::class));
$container->bind('cache', fn($c) => $c->get(CacheManager::class));
```

- Load config từ `config/cache.php`
- Tạo `CacheManager` với config này
- Bind `CacheManager` làm `CacheInterface` và `'cache'`

### 3. CacheManager sử dụng Default Driver

**File:** `src/Framework/Cache/CacheManager.php`

```php
public function __construct(array $config = [])
{
    $this->config = $config;
    $this->defaultDriver = $config['default'] ?? 'file'; // ← Lấy từ config
}

// Khi gọi get(), set(), increment(), etc.
public function get(string $key, mixed $default = null): mixed
{
    return $this->getDefaultDriverInstance()->get($key, $default);
    //                                    ↑
    //                          Delegate tới default driver
}
```

- `CacheManager` lưu `defaultDriver` từ config
- Tất cả operations (get, set, increment, etc.) đều delegate tới default driver instance

### 4. Rate Limiter sử dụng Cache

**File:** `src/Framework/Providers/SecurityServiceProvider.php`

```php
$container->singleton(RateLimiterInterface::class, function ($c) {
    $cache = $c->has('cache') ? $c->get('cache') : null;
    if ($cache === null) {
        throw new \RuntimeException('Cache service is required for rate limiting.');
    }
    return new CacheRateLimiter($cache); // ← Nhận CacheManager instance
});
```

- Rate limiter nhận `CacheManager` instance (bind với `'cache'`)
- `CacheRateLimiter` sử dụng `CacheInterface`, thực tế là `CacheManager`
- `CacheManager` delegate tới default driver từ `CACHE_DRIVER`

### 5. CacheRateLimiter sử dụng Cache

**File:** `src/Framework/RateLimit/CacheRateLimiter.php`

```php
public function hit(string $key, int $decaySeconds = self::DEFAULT_DECAY_SECONDS): int
{
    // ...
    $attempts = $this->cache->increment($attemptsKey, 1);
    //                    ↑
    //            CacheManager → Default Driver
    // ...
}
```

- `$this->cache` là `CacheManager` instance
- Khi gọi `increment()`, `CacheManager` delegate tới default driver

## Kết Luận

**Rate limiter sử dụng cache driver theo `CACHE_DRIVER` env variable!**

### Ví dụ:

```bash
# Development - dùng File cache
CACHE_DRIVER=file

# Production - dùng Redis (nhanh hơn, distributed)
CACHE_DRIVER=redis

# Testing - dùng Memory cache
CACHE_DRIVER=memory
```

### Lưu ý:

1. **File Cache:**
   - Chậm hơn, nhưng không cần setup
   - Phù hợp development/single server
   - Cache files trong `storage/cache/`

2. **Redis Cache:**
   - Nhanh hơn, distributed
   - Phù hợp production/multi-server
   - Cần config Redis trong `config/cache.php`

3. **Memory Cache:**
   - Nhanh nhất, nhưng không persistent
   - Chỉ phù hợp testing
   - Reset khi application restart

### Switching Cache Driver:

Chỉ cần thay đổi `.env`:

```bash
# Từ File sang Redis
CACHE_DRIVER=redis
```

Không cần thay đổi code - rate limiter tự động sử dụng driver mới!

