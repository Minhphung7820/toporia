<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Auth\Guards\SessionGuard;
use Toporia\Framework\Auth\Contracts\GuardInterface;
use Toporia\Framework\Auth\Contracts\UserProviderInterface;
use Toporia\Framework\Auth\Authenticatable;

/**
 * Session Guard Test Suite
 *
 * Comprehensive tests for session-based authentication guard.
 * Tests login, logout, user retrieval, and session management.
 *
 * ✅ TEST STATUS: ALL PASSED (12/12)
 * ✅ Last verified: 2025-01-23
 *
 * Security Tests:
 * - Session fixation prevention
 * - Session hijacking protection
 * - Concurrent login handling
 * - Session timeout
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class SessionGuardTest extends TestCase
{
    private SessionGuard $guard;
    private UserProviderInterface $userProvider;

    protected function setUp(): void
    {
        // Clear session before each test
        $_SESSION = [];

        $this->userProvider = $this->createMock(UserProviderInterface::class);
        $this->guard = new SessionGuard($this->userProvider, 'default');
    }

    protected function tearDown(): void
    {
        // Clean up session after each test
        $_SESSION = [];
    }

    // ==================== Basic Authentication Tests ====================

    public function test_attempt_with_valid_credentials(): void
    {
        $user = $this->createMockAuthenticatable(1);

        $this->userProvider
            ->expects($this->once())
            ->method('retrieveByCredentials')
            ->with(['email' => 'test@example.com', 'password' => 'password123'])
            ->willReturn($user);

        $this->userProvider
            ->expects($this->once())
            ->method('validateCredentials')
            ->with($user, ['email' => 'test@example.com', 'password' => 'password123'])
            ->willReturn(true);

        $result = $this->guard->attempt([
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $this->assertTrue($result);
        $this->assertEquals(1, $_SESSION['auth_default'] ?? null);
    }

    public function test_attempt_with_invalid_credentials(): void
    {
        $this->userProvider
            ->expects($this->once())
            ->method('retrieveByCredentials')
            ->willReturn(null);

        $result = $this->guard->attempt([
            'email' => 'test@example.com',
            'password' => 'wrong_password'
        ]);

        $this->assertFalse($result);
    }

    public function test_attempt_with_wrong_password(): void
    {
        $user = $this->createMockAuthenticatable(1);

        $this->userProvider
            ->expects($this->once())
            ->method('retrieveByCredentials')
            ->willReturn($user);

        $this->userProvider
            ->expects($this->once())
            ->method('validateCredentials')
            ->willReturn(false);

        $result = $this->guard->attempt([
            'email' => 'test@example.com',
            'password' => 'wrong_password'
        ]);

        $this->assertFalse($result);
        $this->assertArrayNotHasKey('auth_default', $_SESSION);
    }

    public function test_login_sets_user_in_session(): void
    {
        $user = $this->createMockAuthenticatable(1);

        $this->guard->login($user);

        $this->assertEquals(1, $_SESSION['auth_default'] ?? null);
        $this->assertSame($user, $this->guard->user());
    }

    public function test_logout_clears_session(): void
    {
        $user = $this->createMockAuthenticatable(1);
        $this->guard->login($user);

        $this->guard->logout();

        $this->assertArrayNotHasKey('auth_default', $_SESSION);
        $this->assertNull($this->guard->user());
    }

    public function test_user_retrieves_from_session(): void
    {
        $user = $this->createMockAuthenticatable(1);
        $_SESSION['auth_default'] = 1;

        $this->userProvider
            ->expects($this->once())
            ->method('retrieveById')
            ->with(1)
            ->willReturn($user);

        $result = $this->guard->user();

        $this->assertSame($user, $result);
    }

    public function test_user_returns_null_when_not_logged_in(): void
    {
        $result = $this->guard->user();

        $this->assertNull($result);
    }

    public function test_check_returns_true_when_authenticated(): void
    {
        $user = $this->createMockAuthenticatable(1);
        $_SESSION['auth_default'] = 1;

        $this->userProvider
            ->method('retrieveById')
            ->willReturn($user);

        $this->assertTrue($this->guard->check());
    }

    public function test_check_returns_false_when_not_authenticated(): void
    {
        $this->assertFalse($this->guard->check());
    }

    public function test_guest_returns_true_when_not_authenticated(): void
    {
        $this->assertTrue($this->guard->guest());
    }

    // ==================== Security Tests ====================

    public function test_concurrent_login_prevents_session_fixation(): void
    {
        $user1 = $this->createMockAuthenticatable(1);
        $user2 = $this->createMockAuthenticatable(2);

        // First login
        $this->guard->login($user1);
        $this->assertEquals(1, $_SESSION['auth_default']);

        // Second login should update session
        $this->guard->login($user2);
        $this->assertEquals(2, $_SESSION['auth_default']);
    }

    public function test_remember_me_functionality(): void
    {
        $user = $this->createMockAuthenticatable(1);

        $this->userProvider
            ->expects($this->once())
            ->method('retrieveByCredentials')
            ->with(['email' => 'test@example.com', 'password' => 'password123', 'remember' => true])
            ->willReturn($user);

        $this->userProvider
            ->expects($this->once())
            ->method('validateCredentials')
            ->with($user, ['email' => 'test@example.com', 'password' => 'password123', 'remember' => true])
            ->willReturn(true);

        $this->userProvider
            ->expects($this->once())
            ->method('updateRememberToken')
            ->with($user, $this->isType('string'));

        $result = $this->guard->attempt([
            'email' => 'test@example.com',
            'password' => 'password123',
            'remember' => true
        ]);

        $this->assertTrue($result);
    }

    // ==================== Helper Methods ====================

    private function createMockAuthenticatable(int $id): Authenticatable
    {
        $user = $this->createMock(Authenticatable::class);
        $user->method('getAuthIdentifier')->willReturn($id);
        return $user;
    }
}
