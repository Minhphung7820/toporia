# Channel Middleware Guide

## 🎯 Overview

Channel Middleware là hệ thống middleware **RIÊNG BIỆT** cho Realtime Channels.

**QUAN TRỌNG:**
- ❌ **KHÔNG dùng chung** với HTTP Middleware
- ✅ **Chỉ dùng** cho WebSocket/SSE/Long-polling channels
- ✅ **Khác interface** với HTTP middleware

---

## 🔍 So sánh HTTP vs Channel Middleware

| Aspect | HTTP Middleware | Channel Middleware |
|--------|----------------|-------------------|
| **Config** | `config/middleware.php` | `config/realtime.php` → `channel_middleware` |
| **Interface** | `MiddlewareInterface` | `ChannelMiddlewareInterface` |
| **Input** | `Request $request` | `ConnectionInterface $connection` |
| **Output** | `Response` | `bool` (allow/deny) |
| **Used in** | HTTP routes (`routes/web.php`) | Channel routes (`routes/channels.php`) |
| **Purpose** | HTTP request filtering | Channel subscription authorization |

---

## 📋 Built-in Middleware

Framework cung cấp 3 middleware có sẵn:

### 1. **`auth`** - Authentication Required

Kiểm tra xem connection đã authenticated chưa.

```php
// routes/channels.php
ChannelRoute::channel('private-chat', fn($conn) => true)
    ->middleware(['auth']);
```

**Implementation:**
```php
// AuthMiddleware.php
public function handle(ConnectionInterface $connection, string $channelName, callable $next): bool
{
    if ($connection->getUserId() === null) {
        return false; // Not authenticated
    }
    return $next($connection, $channelName);
}
```

---

### 2. **`role:admin,moderator`** - Role-based Authorization

Kiểm tra xem user có role yêu cầu không.

```php
// routes/channels.php
ChannelRoute::channel('admin-dashboard', fn($conn) => true)
    ->middleware(['auth', 'role:admin']);

// Multiple roles (OR logic)
ChannelRoute::channel('moderator-panel', fn($conn) => true)
    ->middleware(['auth', 'role:admin,moderator']);
```

**Implementation:**
```php
// RoleMiddleware.php
public function handle(ConnectionInterface $connection, string $channelName, callable $next): bool
{
    $userRoles = $connection->get('roles', []);
    $hasRole = !empty(array_intersect($this->requiredRoles, $userRoles));

    if (!$hasRole) {
        return false;
    }
    return $next($connection, $channelName);
}
```

---

### 3. **`ratelimit`** - Rate Limiting

Giới hạn số lần subscribe trong 1 khoảng thời gian.

```php
// routes/channels.php
ChannelRoute::channel('high-frequency', fn($conn) => true)
    ->middleware(['auth', 'ratelimit']);
```

**Implementation:**
```php
// RateLimitMiddleware.php
public function handle(ConnectionInterface $connection, string $channelName, callable $next): bool
{
    $identifier = "subscription:{$connection->getId()}";

    try {
        $this->rateLimiter->check($identifier);
    } catch (\Throwable $e) {
        return false; // Rate limit exceeded
    }

    return $next($connection, $channelName);
}
```

---

## 🔧 Register Custom Middleware

### Method 1: Config-based (Recommended)

**File:** `config/realtime.php`

```php
return [
    // ...

    'channel_middleware' => [
        // Business logic middleware
        'premium' => App\Realtime\Middleware\PremiumMiddleware::class,
        'verified' => App\Realtime\Middleware\VerifiedMiddleware::class,
        'team' => App\Realtime\Middleware\TeamMemberMiddleware::class,
        'subscription' => App\Realtime\Middleware\ActiveSubscriptionMiddleware::class,
    ],
];
```

**Usage in routes/channels.php:**
```php
ChannelRoute::channel('premium-content', fn($conn) => true)
    ->middleware(['auth', 'premium']);

ChannelRoute::channel('team.{teamId}', fn($conn, $teamId) => true)
    ->middleware(['auth', 'team']);
```

---

### Method 2: Programmatic Registration

**File:** `app/Providers/AppServiceProvider.php`

```php
use Toporia\Framework\Realtime\Middleware\ChannelMiddlewarePipeline;
use App\Realtime\Middleware\PremiumMiddleware;

public function boot()
{
    // Register middleware programmatically
    ChannelMiddlewarePipeline::register('premium', PremiumMiddleware::class);
}
```

---

## 🎨 Create Custom Middleware

### Step 1: Create Middleware Class

**File:** `src/App/Realtime/Middleware/PremiumMiddleware.php`

```php
<?php

namespace App\Realtime\Middleware;

use Toporia\Framework\Realtime\Middleware\ChannelMiddlewareInterface;
use Toporia\Framework\Realtime\Contracts\ConnectionInterface;

final class PremiumMiddleware implements ChannelMiddlewareInterface
{
    public function __construct(
        // Inject dependencies via DI container
        private readonly UserRepository $users
    ) {}

    public function handle(ConnectionInterface $connection, string $channelName, callable $next): bool
    {
        $userId = $connection->getUserId();

        if ($userId === null) {
            error_log("[Premium Middleware] Denied: Not authenticated");
            return false;
        }

        // Check if user has premium subscription
        $user = $this->users->find($userId);

        if (!$user || !$user->isPremium()) {
            error_log("[Premium Middleware] Denied: User {$userId} not premium for channel '{$channelName}'");
            return false;
        }

        // Pass to next middleware
        return $next($connection, $channelName);
    }
}
```

---

### Step 2: Register in Config

**File:** `config/realtime.php`

```php
'channel_middleware' => [
    'premium' => App\Realtime\Middleware\PremiumMiddleware::class,
],
```

---

### Step 3: Use in Channel Routes

**File:** `routes/channels.php`

```php
ChannelRoute::channel('premium-news', fn($conn) => true)
    ->middleware(['auth', 'premium']);

ChannelRoute::channel('vip-chat', fn($conn) => true)
    ->middleware(['auth', 'premium', 'verified']);
```

---

## 📚 Complete Examples

### Example 1: Team Member Middleware

Check if user is member of a team.

```php
// src/App/Realtime/Middleware/TeamMemberMiddleware.php
namespace App\Realtime\Middleware;

use Toporia\Framework\Realtime\Middleware\ChannelMiddlewareInterface;
use Toporia\Framework\Realtime\Contracts\ConnectionInterface;
use App\Domain\Repositories\TeamRepository;

final class TeamMemberMiddleware implements ChannelMiddlewareInterface
{
    public function __construct(
        private readonly TeamRepository $teams
    ) {}

    public function handle(ConnectionInterface $connection, string $channelName, callable $next): bool
    {
        // Extract team ID from channel name
        // Example: 'team.123' -> teamId = 123
        if (!preg_match('/^team\.(\d+)/', $channelName, $matches)) {
            return false; // Invalid channel pattern
        }

        $teamId = (int) $matches[1];
        $userId = $connection->getUserId();

        if ($userId === null) {
            return false;
        }

        // Check if user is team member
        if (!$this->teams->isMember($teamId, $userId)) {
            error_log("[Team Middleware] Denied: User {$userId} not member of team {$teamId}");
            return false;
        }

        return $next($connection, $channelName);
    }
}
```

**Register:**
```php
// config/realtime.php
'channel_middleware' => [
    'team' => App\Realtime\Middleware\TeamMemberMiddleware::class,
],
```

**Use:**
```php
// routes/channels.php
ChannelRoute::channel('team.{teamId}', function ($conn, $teamId) {
    // Additional checks if needed
    return true;
})->middleware(['auth', 'team']);
```

---

### Example 2: Active Subscription Middleware

Check if user has active subscription.

```php
// src/App/Realtime/Middleware/ActiveSubscriptionMiddleware.php
namespace App\Realtime\Middleware;

use Toporia\Framework\Realtime\Middleware\ChannelMiddlewareInterface;
use Toporia\Framework\Realtime\Contracts\ConnectionInterface;
use App\Domain\Services\SubscriptionService;

final class ActiveSubscriptionMiddleware implements ChannelMiddlewareInterface
{
    public function __construct(
        private readonly SubscriptionService $subscriptions
    ) {}

    public function handle(ConnectionInterface $connection, string $channelName, callable $next): bool
    {
        $userId = $connection->getUserId();

        if ($userId === null) {
            return false;
        }

        // Check active subscription
        if (!$this->subscriptions->hasActiveSubscription($userId)) {
            error_log("[Subscription Middleware] Denied: User {$userId} no active subscription");
            return false;
        }

        return $next($connection, $channelName);
    }
}
```

---

### Example 3: IP Whitelist Middleware

Allow only specific IPs.

```php
// src/App/Realtime/Middleware/IpWhitelistMiddleware.php
namespace App\Realtime\Middleware;

use Toporia\Framework\Realtime\Middleware\ChannelMiddlewareInterface;
use Toporia\Framework\Realtime\Contracts\ConnectionInterface;

final class IpWhitelistMiddleware implements ChannelMiddlewareInterface
{
    private array $allowedIps = [
        '127.0.0.1',
        '192.168.1.0/24',
    ];

    public function handle(ConnectionInterface $connection, string $channelName, callable $next): bool
    {
        $ip = $connection->get('ip');

        if (!$ip || !$this->isAllowed($ip)) {
            error_log("[IP Whitelist] Denied: IP {$ip} not allowed for channel '{$channelName}'");
            return false;
        }

        return $next($connection, $channelName);
    }

    private function isAllowed(string $ip): bool
    {
        // Check if IP is in whitelist
        foreach ($this->allowedIps as $allowed) {
            if (str_contains($allowed, '/')) {
                // CIDR notation
                if ($this->ipInCidr($ip, $allowed)) {
                    return true;
                }
            } else {
                // Exact match
                if ($ip === $allowed) {
                    return true;
                }
            }
        }
        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = -1 << (32 - (int) $mask);
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
```

---

## 🔄 Middleware Priority

Priority khi resolve middleware alias:

1. **Config-based** (`config/realtime.php`) - Highest priority
2. **Programmatic** (`ChannelMiddlewarePipeline::register()`)
3. **Built-in** (`auth`, `role`, `ratelimit`)
4. **Full class name** (fallback)

```php
// 1. Config (highest priority)
'channel_middleware' => [
    'custom' => App\Middleware\CustomMiddleware::class,
],

// 2. Programmatic
ChannelMiddlewarePipeline::register('custom', App\Middleware\AnotherMiddleware::class);

// 3. Built-in
'auth' => AuthMiddleware::class

// 4. Full class name
->middleware([App\Middleware\DirectMiddleware::class])
```

---

## ⚠️ Important Notes

### 1. **NOT HTTP Middleware!**

```php
// ❌ WRONG - Using HTTP middleware in channels
use App\Http\Middleware\Authenticate; // HTTP middleware

ChannelRoute::channel('test', fn($conn) => true)
    ->middleware([Authenticate::class]); // ❌ Won't work!
```

```php
// ✅ CORRECT - Using Channel middleware
use Toporia\Framework\Realtime\Middleware\AuthMiddleware; // Channel middleware

ChannelRoute::channel('test', fn($conn) => true)
    ->middleware(['auth']); // ✅ Works!
```

### 2. **Interface Difference**

```php
// HTTP Middleware
interface MiddlewareInterface {
    public function handle(Request $request, Closure $next): Response;
}

// Channel Middleware
interface ChannelMiddlewareInterface {
    public function handle(ConnectionInterface $connection, string $channelName, callable $next): bool;
}
```

### 3. **Dependency Injection**

Middleware được resolve qua DI container, có thể inject dependencies:

```php
final class PremiumMiddleware implements ChannelMiddlewareInterface
{
    public function __construct(
        private readonly UserRepository $users,  // ✅ Auto-injected
        private readonly SubscriptionService $subscriptions  // ✅ Auto-injected
    ) {}
}
```

---

## 🧪 Testing

```php
use PHPUnit\Framework\TestCase;
use App\Realtime\Middleware\PremiumMiddleware;
use Toporia\Framework\Realtime\Connection;

class PremiumMiddlewareTest extends TestCase
{
    public function testDeniesNonPremiumUsers()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('find')->willReturn(new User(['is_premium' => false]));

        $middleware = new PremiumMiddleware($userRepo);

        $connection = new Connection('test-1', ['user_id' => 123]);

        $result = $middleware->handle(
            $connection,
            'premium-channel',
            fn() => true
        );

        $this->assertFalse($result);
    }
}
```

---

## 📖 Summary

**Channel Middleware:**
- ✅ Config: `config/realtime.php` → `channel_middleware`
- ✅ Interface: `ChannelMiddlewareInterface`
- ✅ Usage: `routes/channels.php`
- ✅ Built-in: `auth`, `role:admin`, `ratelimit`
- ✅ Custom: Register in config or programmatically
- ✅ DI Support: Auto-inject dependencies
- ❌ NOT for HTTP: Completely separate from HTTP middleware

**Priority:**
1. Config-based
2. Programmatic
3. Built-in
4. Full class name

**Best Practice:**
- Register business middleware in `config/realtime.php`
- Keep middleware focused (single responsibility)
- Use DI for dependencies
- Log denials for debugging
- Test middleware thoroughly

