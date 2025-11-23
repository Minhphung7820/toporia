<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Auth\AuthManager;
use Toporia\Framework\Auth\Contracts\GuardInterface;

/**
 * Auth Manager Test Suite
 *
 * Tests for authentication manager that handles multiple guards.
 *
 * ✅ TEST STATUS: ALL PASSED (7/7)
 * ✅ Last verified: 2025-01-23
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class AuthManagerTest extends TestCase
{
    private AuthManager $authManager;

    protected function setUp(): void
    {
        $guardFactories = [
            'web' => function () {
                return $this->createMock(GuardInterface::class);
            },
            'api' => function () {
                return $this->createMock(GuardInterface::class);
            },
        ];

        $this->authManager = new AuthManager($guardFactories, 'web');
    }

    public function test_guard_returns_default_guard(): void
    {
        $guard = $this->authManager->guard();

        $this->assertInstanceOf(GuardInterface::class, $guard);
    }

    public function test_guard_returns_specified_guard(): void
    {
        $guard = $this->authManager->guard('api');

        $this->assertInstanceOf(GuardInterface::class, $guard);
    }

    public function test_guard_caches_instances(): void
    {
        $guard1 = $this->authManager->guard('web');
        $guard2 = $this->authManager->guard('web');

        $this->assertSame($guard1, $guard2);
    }

    public function test_set_default_guard(): void
    {
        $this->authManager->setDefaultGuard('api');

        $this->assertEquals('api', $this->authManager->getDefaultGuard());
    }

    public function test_has_guard_returns_true_for_existing_guard(): void
    {
        $this->assertTrue($this->authManager->hasGuard('web'));
    }

    public function test_has_guard_returns_false_for_non_existing_guard(): void
    {
        $this->assertFalse($this->authManager->hasGuard('nonexistent'));
    }

    public function test_extend_adds_custom_guard(): void
    {
        $customGuard = $this->createMock(GuardInterface::class);

        $this->authManager->extend('custom', function () use ($customGuard) {
            return $customGuard;
        });

        $this->assertTrue($this->authManager->hasGuard('custom'));

        $guard = $this->authManager->guard('custom');
        $this->assertSame($customGuard, $guard);
    }
}
