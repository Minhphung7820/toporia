<?php

declare(strict_types=1);

/**
 * Realtime Channel Authorization Routes
 *
 * Define channel authorization and middleware here.
 *
 * CALLBACK SIGNATURE:
 *   function($user, ...$channelParams, $guard = null): bool|array
 *
 *   - $user: Authenticated user (Authenticatable instance or array)
 *   - $channelParams: Extracted parameters from channel pattern (e.g., {orderId})
 *   - $guard: (optional) Authentication guard name if callback requests it
 *   - Return: true = allow, false = deny, array = allow + presence user_info
 *
 * GUARDS OPTION:
 *   Restrict which authentication guards can access the channel.
 *   Empty guards array = all guards allowed.
 *
 * Examples:
 *   // 1. Simple authorization - all guards allowed
 *   ChannelRoute::channel('orders.{orderId}', function ($user, $orderId) {
 *       return (int) $user['id'] === (int) Order::find($orderId)->user_id;
 *   });
 *
 *   // 2. Restrict to specific guards (options array style)
 *   ChannelRoute::channel('orders.{orderId}', function ($user, $orderId) {
 *       return (int) $user['id'] === (int) Order::find($orderId)->user_id;
 *   }, ['guards' => ['web', 'api']]);
 *
 *   // 3. Restrict guards (fluent API)
 *   ChannelRoute::channel('admin.dashboard', function ($user) {
 *       return in_array('admin', $user['roles'] ?? [], true);
 *   })->guards(['admin']);
 *
 *   // 4. Access guard name in callback
 *   ChannelRoute::channel('multi-guard.{id}', function ($user, $id, $guard = null) {
 *       if ($guard === 'admin') {
 *           return true; // Admins can access any
 *       }
 *       return (int) $user['id'] === (int) $id;
 *   }, ['guards' => ['web', 'admin']]);
 *
 *   // 5. Presence channel with user info
 *   ChannelRoute::channel('chat.{roomId}', function ($user, $roomId) {
 *       return [
 *           'id' => $user['id'],
 *           'name' => $user['name'],
 *           'avatar' => $user['avatar'] ?? null,
 *       ];
 *   });
 */

use Toporia\Framework\Realtime\ChannelRoute;
use Toporia\Framework\Realtime\Broadcast;

// ============================================================================
// PUBLIC CHANNELS - No authentication required
// ============================================================================

// Public news channel - anyone can subscribe
ChannelRoute::channel('public-news', function ($user) {
    return true; // Allow all
});

// Public announcements
ChannelRoute::channel('public-announcements', function ($user) {
    return true;
});

// ============================================================================
// PRIVATE USER CHANNELS - Require authentication
// ============================================================================

// Private user channel - only authenticated user can subscribe to their own channel
// All guards allowed by default
ChannelRoute::channel('user.{userId}', function ($user, $userId) {
    // $user is array with: id, user_id, name, email, roles, guard
    return (int) ($user['id'] ?? $user['user_id'] ?? 0) === (int) $userId;
});

// User notifications - restrict to 'api' and 'web' guards only
ChannelRoute::channel('user.{userId}.notifications', function ($user, $userId) {
    return (int) ($user['id'] ?? 0) === (int) $userId;
}, ['guards' => ['api', 'web']]);

// ============================================================================
// PRIVATE CHANNELS - Pattern-based authorization
// ============================================================================

// Notifications channel - public for demo (no auth required)
ChannelRoute::channel('notifications', function ($user) {
    return true; // Allow all
});

// Private channels - require authenticated user
ChannelRoute::channel('private-*', function ($user) {
    // Check if user exists (authenticated)
    return isset($user['id']) || isset($user['user_id']);
});

// ============================================================================
// PRESENCE CHANNELS - Return user info array
// ============================================================================

// Presence chat room - return user info for presence tracking
ChannelRoute::channel('chat.{roomId}', function ($user, $roomId) {
    // Return array = authorized + user info for presence
    return [
        'id' => $user['id'] ?? $user['user_id'] ?? null,
        'name' => $user['name'] ?? $user['username'] ?? 'Anonymous',
        'email' => $user['email'] ?? null,
    ];
});

// Presence online users
ChannelRoute::channel('presence-online', function ($user) {
    return [
        'id' => $user['id'] ?? $user['user_id'] ?? null,
        'name' => $user['name'] ?? 'User',
    ];
});

// ============================================================================
// GUARD-RESTRICTED CHANNELS (toporia-style)
// ============================================================================

// Admin dashboard - ONLY admin guard allowed
ChannelRoute::channel('admin.dashboard', function ($user, $guard = null) {
    // Guard is passed as last parameter if callback accepts it
    $roles = $user['roles'] ?? [];
    return in_array('admin', $roles, true);
}, ['guards' => ['admin']]);

// Admin dashboard (alternative - fluent API)
ChannelRoute::channel('admin.stats', function ($user) {
    return in_array('admin', $user['roles'] ?? [], true);
})->guards(['admin']);

// Multi-guard channel - allow both 'api' and 'admin'
ChannelRoute::channel('orders.{orderId}', function ($user, $orderId, $guard = null) {
    // Admin can view any order
    if ($guard === 'admin') {
        return true;
    }

    // Regular users can only view their own orders
    // TODO: Replace with actual order lookup
    // $order = Order::find($orderId);
    // return $order && $order->user_id === (int) ($user['id'] ?? 0);
    return true; // Placeholder
}, ['guards' => ['api', 'admin']]);

// ============================================================================
// COMPLEX EXAMPLES
// ============================================================================

// Product updates - all guards allowed
ChannelRoute::channel('product.{productId}.updates', function ($user, $productId) {
    // Anyone authenticated can subscribe to product updates
    return isset($user['id']);
});

// Team channel - restrict to 'api' guard
ChannelRoute::channel('team.{teamId}.chat', function ($user, $teamId) {
    // TODO: Check team membership
    // return TeamMember::where('team_id', $teamId)->where('user_id', $user['id'])->exists();
    return true; // Placeholder
}, ['guards' => ['api']]);

// Premium content - any guard, but check subscription
ChannelRoute::channel('premium.{contentId}', function ($user, $contentId) {
    // TODO: Check user's subscription status
    // return Subscription::active($user['id'])->exists();
    return true; // Placeholder
});

// ============================================================================
// EXAMPLE PATTERNS
// ============================================================================

/*
// 1. Simple - all guards allowed
ChannelRoute::channel('news', fn($user) => true);

// 2. With guards option (array style)
ChannelRoute::channel('api-only', fn($user) => true, ['guards' => ['api']]);

// 3. With guards (fluent style)
ChannelRoute::channel('web-only', fn($user) => true)->guards(['web']);

// 4. Multiple guards
ChannelRoute::channel('mixed', fn($user) => true, ['guards' => ['api', 'web', 'admin']]);

// 5. Guard-aware callback
ChannelRoute::channel('smart.{id}', function ($user, $id, $guard = null) {
    if ($guard === 'admin') {
        return true; // Admin can do anything
    }
    return (int) $user['id'] === (int) $id;
}, ['guards' => ['api', 'admin']]);

// 6. Presence with custom data
ChannelRoute::channel('room.{roomId}', function ($user, $roomId) {
    return [
        'id' => $user['id'],
        'name' => $user['name'],
        'avatar' => $user['avatar'] ?? '/default-avatar.png',
        'status' => 'online',
    ];
});

// 7. Wildcard pattern
ChannelRoute::channel('user.{userId}.*', fn($user, $userId) => (int) $user['id'] === (int) $userId);
*/
