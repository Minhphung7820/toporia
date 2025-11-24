<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Scheduling;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Console\Scheduling\CacheMutex;
use Toporia\Framework\Cache\Contracts\CacheInterface;

/**
 * CacheMutex Unit Tests
 *
 * Tests mutex creation, checking, and release using cache backend.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class CacheMutexTest extends TestCase
{
    private CacheInterface $cache;
    private CacheMutex $mutex;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->mutex = new CacheMutex($this->cache);
    }

    // ==================== Create Mutex Tests ====================

    public function test_create_acquires_lock_when_not_exists(): void
    {
        $this->cache->expects($this->once())
            ->method('has')
            ->with('schedule-mutex-test-task')
            ->willReturn(false);

        $this->cache->expects($this->once())
            ->method('set')
            ->with('schedule-mutex-test-task', $this->anything(), 1440 * 60);

        $result = $this->mutex->create('test-task');

        $this->assertTrue($result);
    }

    public function test_create_fails_when_lock_exists(): void
    {
        $this->cache->expects($this->once())
            ->method('has')
            ->with('schedule-mutex-test-task')
            ->willReturn(true);

        $this->cache->expects($this->never())
            ->method('set');

        $result = $this->mutex->create('test-task');

        $this->assertFalse($result);
    }

    public function test_create_respects_custom_expiration(): void
    {
        $this->cache->method('has')->willReturn(false);

        $this->cache->expects($this->once())
            ->method('set')
            ->with('schedule-mutex-test-task', $this->anything(), 120 * 60); // 2 hours in seconds

        $this->mutex->create('test-task', 120);
    }

    // ==================== Exists Check Tests ====================

    public function test_exists_returns_true_when_lock_exists(): void
    {
        $this->cache->expects($this->once())
            ->method('has')
            ->with('schedule-mutex-test-task')
            ->willReturn(true);

        $result = $this->mutex->exists('test-task');

        $this->assertTrue($result);
    }

    public function test_exists_returns_false_when_lock_not_exists(): void
    {
        $this->cache->expects($this->once())
            ->method('has')
            ->with('schedule-mutex-test-task')
            ->willReturn(false);

        $result = $this->mutex->exists('test-task');

        $this->assertFalse($result);
    }

    // ==================== Forget Mutex Tests ====================

    public function test_forget_removes_lock(): void
    {
        $this->cache->expects($this->once())
            ->method('forget')
            ->with('schedule-mutex-test-task');

        $this->mutex->forget('test-task');
    }

    // ==================== Key Generation Tests ====================

    public function test_mutex_key_has_correct_prefix(): void
    {
        $this->cache->expects($this->once())
            ->method('has')
            ->with($this->stringStartsWith('schedule-mutex-'));

        $this->mutex->exists('any-task');
    }

    // ==================== Edge Cases ====================

    public function test_create_with_default_expiration(): void
    {
        $this->cache->method('has')->willReturn(false);

        // Default expiration is 1440 minutes (24 hours)
        $this->cache->expects($this->once())
            ->method('set')
            ->with($this->anything(), $this->anything(), 1440 * 60);

        $this->mutex->create('test-task');
    }

    public function test_mutex_with_special_characters_in_name(): void
    {
        $this->cache->expects($this->once())
            ->method('has')
            ->with('schedule-mutex-task:with:special-chars');

        $this->mutex->exists('task:with:special-chars');
    }
}
