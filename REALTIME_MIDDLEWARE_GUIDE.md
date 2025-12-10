# Realtime Channel Middleware System

## Overview

Hệ thống **Channel Middleware** cho phép bạn định nghĩa authorization và middleware cho từng realtime channel, giống như Laravel's `routes/channels.php`.

## Key Features

✅ **Middleware Support** - Auth, Role-based, Rate Limiting, Custom middleware
✅ **Works Across ALL Transports** - WebSocket, SSE, Long Polling, Socket.IO
✅ **Pattern Matching** - Wildcards (`private-*`) và parameters (`user.{userId}`)
✅ **Backward Compatible** - Legacy config authorizers still work
✅ **Centralized Security** - All channel authorization in one place (`routes/channels.php`)

---

## Architecture

```
Client Request to Subscribe to Channel
            ↓
    Transport Layer (WebSocket/SSE/etc.)
            ↓
    RealtimeManager.channel(name)
            ↓
    ChannelRoute.match(name) → Find channel definition
            ↓
    Middleware Pipeline Execution
            ↓
    [AuthMiddleware] → Check if authenticated
            ↓
    [RoleMiddleware] → Check if has required roles
            ↓
    [RateLimitMiddleware] → Check rate limit
            ↓
    [Custom Middleware] → Your custom checks
            ↓
    Final Authorization Callback
            ↓
    Subscribe if authorized, deny if not
```

---

## Usage

### 1. Define Channels in `routes/channels.php`

```php
use Toporia\Framework\Realtime\ChannelRoute;
use Toporia\Framework\Realtime\Contracts\ConnectionInterface;

// Public channel - no auth required
ChannelRoute::channel('public-news', function (ConnectionInterface $connection) {
    return true; // Allow all
});

// Private user channel - requires authentication
ChannelRoute::channel('user.{userId}', function (ConnectionInterface $connection, string $userId) {
    return $connection->getUserId() === (int) $userId;
})->middleware(['auth']);

// Admin channel - requires auth + admin role
ChannelRoute::channel('admin-dashboard', function (ConnectionInterface $connection) {
    $roles = $connection->get('roles', []);
    return in_array('admin', $roles, true);
})->middleware(['auth', 'role:admin']);

// Wildcard pattern
ChannelRoute::channel('private-*', function (ConnectionInterface $connection) {
    return $connection->getUserId() !== null;
})->middleware(['auth']);
```

### 2. Built-in Middleware

#### `auth` - Authentication Required
```php
ChannelRoute::channel('private-chat', fn($conn) => true)
    ->middleware(['auth']);
```

#### `role:admin,moderator` - Role-based Authorization
```php
ChannelRoute::channel('admin-panel', fn($conn) => true)
    ->middleware(['auth', 'role:admin']);

// Multiple roles (OR logic)
ChannelRoute::channel('moderator-panel', fn($conn) => true)
    ->middleware(['auth', 'role:admin,moderator']);
```

#### `ratelimit` - Rate Limiting
```php
ChannelRoute::channel('high-frequency', fn($conn) => true)
    ->middleware(['auth', 'ratelimit']);
```

### 3. Custom Middleware

Create your own middleware:

```php
// src/App/Realtime/Middleware/PremiumMiddleware.php
namespace App\Realtime\Middleware;

use Toporia\Framework\Realtime\Middleware\ChannelMiddlewareInterface;
use Toporia\Framework\Realtime\Contracts\ConnectionInterface;

final class PremiumMiddleware implements ChannelMiddlewareInterface
{
    public function handle(ConnectionInterface $connection, string $channelName, callable $next): bool
    {
        $isPremium = $connection->get('is_premium', false);

        if (!$isPremium) {
            return false; // Deny
        }

        return $next($connection, $channelName); // Pass to next middleware
    }
}
```

Register and use:

```php
// routes/channels.php
use Toporia\Framework\Realtime\Middleware\ChannelMiddlewarePipeline;
use App\Realtime\Middleware\PremiumMiddleware;

// Register custom middleware alias
ChannelMiddlewarePipeline::register('premium', PremiumMiddleware::class);

// Use it
ChannelRoute::channel('premium-content', fn($conn) => true)
    ->middleware(['auth', 'premium']);
```

### 4. Pattern Matching

#### Wildcards
```php
// Matches: private-chat, private-room, private-anything
ChannelRoute::channel('private-*', fn($conn) => true)
    ->middleware(['auth']);
```

#### Parameters
```php
// Matches: user.123, user.456
ChannelRoute::channel('user.{userId}', function ($conn, $userId) {
    return $conn->getUserId() === (int) $userId;
})->middleware(['auth']);

// Multiple parameters
ChannelRoute::channel('team.{teamId}.project.{projectId}', function ($conn, $teamId, $projectId) {
    // Check if user has access to this team + project
    return TeamService::hasAccess($conn->getUserId(), $teamId, $projectId);
})->middleware(['auth']);
```

---

## Client-Side Usage

### WebSocket (JavaScript)

```javascript
const ws = new WebSocket('ws://localhost:6001?token=YOUR_JWT_TOKEN');

ws.onopen = () => {
    // Subscribe to public channel (no auth needed)
    ws.send(JSON.stringify({
        type: 'subscribe',
        channel: 'public-news'
    }));

    // Subscribe to private user channel (requires auth)
    ws.send(JSON.stringify({
        type: 'subscribe',
        channel: 'user.123'
    }));

    // Subscribe to admin channel (requires auth + admin role)
    ws.send(JSON.stringify({
        type: 'subscribe',
        channel: 'admin-dashboard'
    }));
};

ws.onmessage = (event) => {
    const message = JSON.parse(event.data);

    if (message.type === 'subscription_success') {
        console.log('Subscribed to:', message.channel);
    }

    if (message.type === 'error') {
        console.error('Error:', message.data.message);
        // Example errors:
        // - "Unauthorized for channel: user.456" (not your user ID)
        // - "Authentication required" (not logged in)
        // - "Missing required role: admin" (not admin)
    }

    if (message.type === 'event') {
        console.log('Event received:', message);
    }
};
```

---

## Security Best Practices

### ✅ DO

1. **Always use middleware for private channels**
   ```php
   ChannelRoute::channel('user.{userId}', fn($conn, $userId) => ...)
       ->middleware(['auth']); // ✅
   ```

2. **Validate parameters in callback**
   ```php
   ChannelRoute::channel('user.{userId}', function ($conn, $userId) {
       return $conn->getUserId() === (int) $userId; // ✅ Check ownership
   })->middleware(['auth']);
   ```

3. **Use multiple middleware layers**
   ```php
   ChannelRoute::channel('sensitive-data', fn($conn) => true)
       ->middleware(['auth', 'verified', 'role:admin', 'ratelimit']); // ✅
   ```

### ❌ DON'T

1. **Don't trust client input**
   ```php
   ChannelRoute::channel('user.{userId}', fn($conn, $userId) => true); // ❌ No validation!
   ```

2. **Don't skip middleware for private data**
   ```php
   ChannelRoute::channel('admin-dashboard', fn($conn) => true); // ❌ No auth middleware!
   ```

3. **Don't hardcode secrets in callbacks**
   ```php
   ChannelRoute::channel('secret', fn($conn) => $conn->get('secret') === 'hardcoded'); // ❌
   ```

---

## Transport Compatibility

| Transport       | Middleware Support | Authentication | Rate Limiting | Notes                          |
|-----------------|-------------------|----------------|---------------|--------------------------------|
| WebSocket       | ✅                | ✅             | ✅            | Full support                   |
| SSE             | ✅                | ✅             | ✅            | Full support                   |
| Long Polling    | ✅                | ✅             | ✅            | Full support                   |
| Socket.IO       | ✅                | ✅             | ✅            | Full support                   |
| Memory          | ✅                | ✅             | ✅            | For testing                    |

**All transports use the same middleware system!**

---

## Migration from Old Approach

### Before (Wrong Approach ❌)
```php
// Created separate WebSocketTransportSecure.php
// Hardcoded auth in transport layer
// Only worked for WebSocket
```

### After (Correct Approach ✅)
```php
// routes/channels.php
ChannelRoute::channel('user.{userId}', fn($conn, $userId) => ...)
    ->middleware(['auth']);

// Works for ALL transports!
```

---

## Examples

### Example 1: Public Channel
```php
ChannelRoute::channel('public-news', fn() => true);
```

### Example 2: User-specific Channel
```php
ChannelRoute::channel('user.{userId}.notifications', function ($conn, $userId) {
    return $conn->getUserId() === (int) $userId;
})->middleware(['auth']);
```

### Example 3: Admin Only
```php
ChannelRoute::channel('admin-dashboard', function ($conn) {
    return in_array('admin', $conn->get('roles', []), true);
})->middleware(['auth', 'role:admin']);
```

### Example 4: Presence Channel
```php
ChannelRoute::channel('presence-chat.{roomId}', function ($conn, $roomId) {
    // Check if user has access to this chat room
    return ChatRoom::find($roomId)->hasUser($conn->getUserId());
})->middleware(['auth']);
```

### Example 5: Order Channel (Business Logic)
```php
ChannelRoute::channel('order.{orderId}', function ($conn, $orderId) {
    $order = Order::find($orderId);
    return $order && $order->user_id === $conn->getUserId();
})->middleware(['auth']);
```

---

## Debugging

### Enable Logging

Middleware automatically logs denied subscriptions:

```
[Auth Middleware] Denied: Connection 123 not authenticated for channel 'private-chat'
[Role Middleware] Denied: Connection 456 missing required roles [admin] for channel 'admin-dashboard'
[Rate Limit Middleware] Denied: Connection 789 exceeded subscription rate limit
```

### Testing Channel Routes

```php
use Toporia\Framework\Realtime\ChannelRoute;

// Check if channel is registered
$exists = ChannelRoute::has('user.{userId}'); // true

// Get channel definition
$definition = ChannelRoute::match('user.123');
// Returns: ['pattern' => 'user.{userId}', 'callback' => ..., 'middleware' => ['auth'], 'params' => ['userId' => '123']]

// Clear all channels (for testing)
ChannelRoute::clear();
```

---

## Performance

- **Middleware Pipeline**: O(n) where n = number of middleware
- **Pattern Matching**: O(m) where m = number of registered channels
- **Authorization Callback**: O(1) for simple checks
- **Overall**: Very fast, minimal overhead

---

## Comparison with Laravel Broadcasting

| Feature                  | Laravel Broadcasting | Toporia Realtime |
|--------------------------|---------------------|------------------|
| Channel Authorization    | ✅                  | ✅               |
| Middleware Support       | ✅                  | ✅               |
| Pattern Matching         | ✅                  | ✅               |
| Multiple Transports      | Pusher/Redis        | WebSocket/SSE/etc|
| Custom Middleware        | ✅                  | ✅               |
| Role-based Authorization | Manual              | Built-in         |
| Rate Limiting            | Manual              | Built-in         |

---

## Summary

✅ **Centralized Authorization** - All channel rules in `routes/channels.php`
✅ **Middleware Pipeline** - Auth, roles, rate limiting, custom
✅ **Transport Agnostic** - Works with WebSocket, SSE, Long Polling, Socket.IO
✅ **Pattern Matching** - Wildcards and parameters
✅ **Backward Compatible** - Legacy config still works
✅ **Production Ready** - Secure, performant, extensible

---

## Questions?

For more details, see:
- `routes/channels.php` - Channel definitions
- `src/Framework/Realtime/ChannelRoute.php` - Route builder
- `src/Framework/Realtime/Middleware/` - Built-in middleware
- `config/realtime.php` - Configuration

---

**Happy Broadcasting! 🚀**

