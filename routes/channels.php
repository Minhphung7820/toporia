<?php

declare(strict_types=1);

/**
 * Realtime Channel Authorization Routes
 *
 * Define channel authorization and middleware here.
 * Similar to Laravel's routes/channels.php
 *
 * Usage:
 *   Channel::auth('channel-name', middleware: ['auth'], callback: function($connection, $channelName) {
 *       return true; // or false to deny
 *   });
 *
 * Examples:
 *   - Public channel (no auth): Channel::auth('public-news', callback: fn() => true);
 *   - Private user channel: Channel::auth('user.{userId}', middleware: ['auth'], callback: fn($conn, $channel) => $conn->getUserId() === $channel->param('userId'));
 *   - Presence channel with role check: Channel::auth('presence-chat.{roomId}', middleware: ['auth', 'role:admin'], ...);
 */

use Toporia\Framework\Realtime\ChannelRoute;
use Toporia\Framework\Realtime\Contracts\ConnectionInterface;

// ============================================================================
// PUBLIC CHANNELS - No authentication required
// ============================================================================

// Public news channel - anyone can subscribe
ChannelRoute::channel('public-news', function (ConnectionInterface $connection) {
    return true; // Allow all
});

// Public announcements
ChannelRoute::channel('public-announcements', function (ConnectionInterface $connection) {
    return true;
});

// ============================================================================
// PRIVATE USER CHANNELS - Require authentication
// ============================================================================

// Private user channel - only authenticated user can subscribe to their own channel
ChannelRoute::channel('user.{userId}', function (ConnectionInterface $connection, string $userId) {
    // Only the user themselves can subscribe
    return $connection->getUserId() === (int) $userId;
})->middleware(['auth']);

// User notifications
ChannelRoute::channel('user.{userId}.notifications', function (ConnectionInterface $connection, string $userId) {
    return $connection->getUserId() === (int) $userId;
})->middleware(['auth']);

// ============================================================================
// PRIVATE CHANNELS - Pattern-based authorization
// ============================================================================

// Private channels - require authentication
ChannelRoute::channel('private-*', function (ConnectionInterface $connection) {
    // Check if user is authenticated
    return $connection->getUserId() !== null;
})->middleware(['auth']);

// ============================================================================
// PRESENCE CHANNELS - Require authentication + presence tracking
// ============================================================================

// Presence chat room
ChannelRoute::channel('presence-chat.{roomId}', function (ConnectionInterface $connection, string $roomId) {
    // Check if user has access to this chat room
    // Example: check database, permissions, etc.
    return $connection->getUserId() !== null;
})->middleware(['auth']);

// Presence online users
ChannelRoute::channel('presence-online', function (ConnectionInterface $connection) {
    return $connection->getUserId() !== null;
})->middleware(['auth']);

// ============================================================================
// ADMIN CHANNELS - Require admin role
// ============================================================================

// Admin dashboard channel - only admins
ChannelRoute::channel('admin-dashboard', function (ConnectionInterface $connection) {
    $roles = $connection->get('roles', []);
    return in_array('admin', $roles, true);
})->middleware(['auth', 'role:admin']);

// ============================================================================
// CUSTOM CHANNELS - Complex authorization logic
// ============================================================================

// Order channel - user can subscribe to their own orders
ChannelRoute::channel('order.{orderId}', function (ConnectionInterface $connection, string $orderId) {
    // Example: Check if user owns this order
    // In production, you would query the database
    $userId = $connection->getUserId();

    if (!$userId) {
        return false;
    }

    // TODO: Add your business logic here
    // Example: $order = Order::find($orderId);
    // return $order && $order->user_id === $userId;

    return true; // Placeholder
})->middleware(['auth']);

// Product updates channel
ChannelRoute::channel('product.{productId}.updates', function (ConnectionInterface $connection, string $productId) {
    // Anyone authenticated can subscribe to product updates
    return $connection->getUserId() !== null;
})->middleware(['auth']);

// ============================================================================
// EXAMPLES - Different authorization patterns
// ============================================================================

/*
// 1. Simple public channel
ChannelRoute::channel('public-channel', fn() => true);

// 2. Authenticated only
ChannelRoute::channel('private-channel', fn($conn) => $conn->getUserId() !== null)
    ->middleware(['auth']);

// 3. Role-based authorization
ChannelRoute::channel('admin-only', function ($conn) {
    return in_array('admin', $conn->get('roles', []), true);
})->middleware(['auth', 'role:admin']);

// 4. Dynamic channel with parameters
ChannelRoute::channel('team.{teamId}', function ($conn, $teamId) {
    // Check if user is member of team
    return TeamService::isMember($conn->getUserId(), $teamId);
})->middleware(['auth']);

// 5. Wildcard patterns
ChannelRoute::channel('user.*', fn($conn) => $conn->getUserId() !== null)
    ->middleware(['auth']);

// 6. Multiple middleware
ChannelRoute::channel('sensitive-data', fn($conn) => true)
    ->middleware(['auth', 'verified', 'premium']);

// 7. Custom middleware with parameters
ChannelRoute::channel('premium-content', fn($conn) => true)
    ->middleware(['auth', 'subscription:premium']);
*/
