# Channel Middleware Examples

## 🎯 Sample Middleware Created

Framework đã tạo sẵn 3 middleware mẫu để bạn tham khảo:

### 1. **PremiumMiddleware** - Premium Subscription Check

**File:** `src/App/Realtime/Middleware/PremiumMiddleware.php`

**Purpose:** Chỉ cho phép premium users subscribe vào premium channels

**Logic:**
- Check `is_premium` từ connection metadata
- Check `premium` role
- Có thể extend để query database

**Usage:**
```php
ChannelRoute::channel('premium-news', fn($conn) => true)
    ->middleware(['auth', 'premium']);
```

---

### 2. **TeamMemberMiddleware** - Team Membership Check

**File:** `src/App/Realtime/Middleware/TeamMemberMiddleware.php`

**Purpose:** Verify user là member của team

**Logic:**
- Extract team ID từ channel name (e.g., `team.123` → `123`)
- Check membership trong database
- Support pattern: `team.{teamId}` hoặc `team.{teamId}.chat`

**Usage:**
```php
ChannelRoute::channel('team.{teamId}.chat', fn($conn, $teamId) => true)
    ->middleware(['auth', 'team']);
```

---

### 3. **VerifiedMiddleware** - Email Verification Check

**File:** `src/App/Realtime/Middleware/VerifiedMiddleware.php`

**Purpose:** Chỉ verified users mới được access

**Logic:**
- Check `email_verified` từ connection metadata
- Check `verified` role
- Có thể extend để query database

**Usage:**
```php
ChannelRoute::channel('verified-only', fn($conn) => true)
    ->middleware(['auth', 'verified']);
```

---

## 📝 Configuration

Middleware đã được đăng ký trong `config/realtime.php`:

```php
'channel_middleware' => [
    'premium' => App\Realtime\Middleware\PremiumMiddleware::class,
    'verified' => App\Realtime\Middleware\VerifiedMiddleware::class,
    'team' => App\Realtime\Middleware\TeamMemberMiddleware::class,
],
```

---

## 🚀 Test Examples

### Example 1: Premium Content Channel

```javascript
// Client connects
const ws = new WebSocket('ws://localhost:6001?token=YOUR_JWT');

ws.onopen = () => {
    // Subscribe to premium channel
    ws.send(JSON.stringify({
        type: 'subscribe',
        channel: 'premium-news'
    }));
};

ws.onmessage = (event) => {
    const msg = JSON.parse(event.data);

    if (msg.type === 'error' && msg.data.code === 403) {
        alert('You need premium subscription!');
    }

    if (msg.event === 'subscribed') {
        console.log('✅ Subscribed to premium content!');
    }
};
```

**Server logs:**
```
[1] Connected: user_id=123
[Auth Middleware] Allowed: User 123 authenticated
[Premium Middleware] Allowed: User 123 has premium access to 'premium-news'
[1] Subscribed to: premium-news
```

---

### Example 2: Team Chat Channel

```javascript
// Subscribe to team chat
ws.send(JSON.stringify({
    type: 'subscribe',
    channel: 'team.456.chat'
}));

// If not team member:
// [Team Middleware] Denied: User 123 is not member of team 456
// Server: {"type":"error","data":{"message":"Unauthorized"}}

// If team member:
// [Team Middleware] Allowed: User 123 is member of team 456
// Server: {"type":"event","event":"subscribed",...}
```

---

### Example 3: Verified Users Channel

```javascript
// Subscribe to verified-only channel
ws.send(JSON.stringify({
    type: 'subscribe',
    channel: 'verified-users'
}));

// If email not verified:
// [Verified Middleware] Denied: User 123 email not verified
// Server: {"type":"error","data":{"message":"Unauthorized"}}

// If verified:
// [Verified Middleware] Allowed: User 123 is verified
// Server: {"type":"event","event":"subscribed",...}
```

---

### Example 4: Multiple Middleware

```javascript
// Premium + Verified channel
ws.send(JSON.stringify({
    type: 'subscribe',
    channel: 'premium-verified'
}));

// Middleware chain:
// 1. AuthMiddleware - check authenticated
// 2. PremiumMiddleware - check premium
// 3. VerifiedMiddleware - check verified
// 4. Final callback - additional checks

// All must pass!
```

---

## 🔧 Customize Middleware

### Modify PremiumMiddleware để query database:

```php
// src/App/Realtime/Middleware/PremiumMiddleware.php

use App\Domain\Repositories\UserRepository;

final class PremiumMiddleware implements ChannelMiddlewareInterface
{
    public function __construct(
        private readonly UserRepository $users  // DI injection
    ) {}

    private function checkPremiumStatus(ConnectionInterface $connection): bool
    {
        $userId = $connection->getUserId();

        // Query database
        $user = $this->users->find($userId);

        if (!$user) {
            return false;
        }

        // Check subscription status
        return $user->hasActiveSubscription('premium');
    }
}
```

---

### Modify TeamMemberMiddleware để query database:

```php
// src/App/Realtime/Middleware/TeamMemberMiddleware.php

use App\Domain\Repositories\TeamRepository;

final class TeamMemberMiddleware implements ChannelMiddlewareInterface
{
    public function __construct(
        private readonly TeamRepository $teams  // DI injection
    ) {}

    private function isTeamMember(int $userId, int $teamId): bool
    {
        // Query database
        return $this->teams->isMember($teamId, $userId);

        // Or using DB directly:
        // return DB::table('team_members')
        //     ->where('team_id', $teamId)
        //     ->where('user_id', $userId)
        //     ->where('status', 'active')
        //     ->exists();
    }
}
```

---

## 🧪 Testing

### Test PremiumMiddleware:

```php
// tests/Realtime/Middleware/PremiumMiddlewareTest.php

use PHPUnit\Framework\TestCase;
use App\Realtime\Middleware\PremiumMiddleware;
use Toporia\Framework\Realtime\Connection;

class PremiumMiddlewareTest extends TestCase
{
    public function testAllowsPremiumUsers()
    {
        $middleware = new PremiumMiddleware();

        $connection = new Connection('test-1', [
            'user_id' => 123,
            'is_premium' => true,
        ]);

        $result = $middleware->handle(
            $connection,
            'premium-news',
            fn() => true
        );

        $this->assertTrue($result);
    }

    public function testDeniesNonPremiumUsers()
    {
        $middleware = new PremiumMiddleware();

        $connection = new Connection('test-1', [
            'user_id' => 123,
            'is_premium' => false,
        ]);

        $result = $middleware->handle(
            $connection,
            'premium-news',
            fn() => true
        );

        $this->assertFalse($result);
    }

    public function testDeniesUnauthenticated()
    {
        $middleware = new PremiumMiddleware();

        $connection = new Connection('test-1', []); // No user_id

        $result = $middleware->handle(
            $connection,
            'premium-news',
            fn() => true
        );

        $this->assertFalse($result);
    }
}
```

---

## 📊 Logs Output

### Successful subscription:

```
[1] Connected: user_id=123
Client → Server: {"type":"subscribe","channel":"premium-news"}
[Auth Middleware] Allowed: User 123 authenticated
[Premium Middleware] Allowed: User 123 has premium access to 'premium-news'
[1] Subscribed to: premium-news
Server → Client: {"type":"event","event":"subscribed","channel":"premium-news"}
```

### Failed (not premium):

```
[1] Connected: user_id=123
Client → Server: {"type":"subscribe","channel":"premium-news"}
[Auth Middleware] Allowed: User 123 authenticated
[Premium Middleware] Denied: User 123 is not premium for channel 'premium-news'
Server → Client: {"type":"error","data":{"message":"Unauthorized","code":403}}
```

### Failed (not authenticated):

```
[1] Connected: anonymous
Client → Server: {"type":"subscribe","channel":"premium-news"}
[Auth Middleware] Denied: Connection 1 not authenticated for channel 'premium-news'
Server → Client: {"type":"error","data":{"message":"Unauthorized","code":403}}
```

---

## 🎓 Best Practices

1. **Always check authentication first**
   ```php
   if ($connection->getUserId() === null) {
       return false;
   }
   ```

2. **Log denials for debugging**
   ```php
   error_log("[Middleware] Denied: User {$userId} ...");
   ```

3. **Use DI for dependencies**
   ```php
   public function __construct(
       private readonly UserRepository $users
   ) {}
   ```

4. **Keep middleware focused**
   - One responsibility per middleware
   - Don't mix business logic

5. **Test thoroughly**
   - Unit tests for each middleware
   - Integration tests for middleware chains

---

## 🚀 Next Steps

1. **Customize middleware** với database queries
2. **Add more middleware** theo business logic của bạn
3. **Test với WebSocket client** để verify
4. **Monitor logs** để debug issues

---

**Happy coding! 🎉**

