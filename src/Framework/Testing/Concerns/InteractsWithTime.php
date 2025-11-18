<?php

declare(strict_types=1);

namespace Toporia\Framework\Testing\Concerns;

/**
 * Time Testing Trait
 *
 * Provides utilities for time manipulation in tests.
 *
 * Performance:
 * - O(1) time setting
 * - Fast time travel
 */
trait InteractsWithTime
{
    /**
     * Current fake time.
     */
    protected ?int $fakeTime = null;

    /**
     * Set fake time.
     *
     * Performance: O(1)
     */
    protected function setFakeTime(int $timestamp): void
    {
        $this->fakeTime = $timestamp;
    }

    /**
     * Travel to a specific time.
     *
     * Performance: O(1)
     */
    protected function travelTo(int $timestamp): void
    {
        $this->setFakeTime($timestamp);
    }

    /**
     * Travel forward in time.
     *
     * Performance: O(1)
     */
    protected function travel(int $seconds): void
    {
        $current = $this->fakeTime ?? time();
        $this->setFakeTime($current + $seconds);
    }

    /**
     * Get current time (fake or real).
     *
     * Performance: O(1)
     */
    protected function now(): int
    {
        return $this->fakeTime ?? time();
    }

    /**
     * Reset time to real time.
     *
     * Performance: O(1)
     */
    protected function resetTime(): void
    {
        $this->fakeTime = null;
    }

    /**
     * Cleanup time after test.
     */
    protected function tearDownTime(): void
    {
        $this->resetTime();
    }
}

