<?php

declare(strict_types=1);

namespace Tests\Performance;

use Toporia\Framework\Testing\TestCase;

/**
 * Example Performance Test
 *
 * Demonstrates performance testing capabilities.
 */
class ExamplePerformanceTest extends TestCase
{
    /**
     * Test execution time.
     */
    public function test_execution_time(): void
    {
        $duration = $this->measureTime(function () {
            // Simulate work
            for ($i = 0; $i < 1000; $i++) {
                $x = $i * 2;
            }
        });

        $this->assertLessThan(0.1, $duration, 'Operation should complete in less than 100ms');
    }

    /**
     * Test memory usage.
     */
    public function test_memory_usage(): void
    {
        $memory = $this->measureMemory(function () {
            $array = range(1, 1000);
            unset($array);
        });

        $this->assertLessThan(1024 * 1024, $memory, 'Operation should use less than 1MB');
    }

    /**
     * Test performance assertion.
     */
    public function test_performance_assertion(): void
    {
        $this->assertExecutionTimeLessThan(
            function () {
                // Fast operation
                array_sum(range(1, 100));
            },
            0.001 // 1ms max
        );
    }
}

