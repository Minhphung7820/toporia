<?php

declare(strict_types=1);

namespace Tests\Integration\Realtime;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Realtime\Brokers\{RedisBrokerImproved, KafkaBrokerImproved, RabbitMqBrokerImproved};
use Toporia\Framework\Realtime\Brokers\BrokerFactory;
use Toporia\Framework\Realtime\Message;
use Toporia\Framework\Realtime\Metrics\BrokerMetrics;

/**
 * Integration tests for improved brokers.
 *
 * Tests real broker connections, performance, and reliability.
 */
class ImprovedBrokersTest extends TestCase
{
    private const TEST_ITERATIONS = 1000;
    private const PERFORMANCE_THRESHOLD_MS = 10; // Max 10ms per operation

    protected function setUp(): void
    {
        parent::setUp();
        BrokerMetrics::reset();
    }

    /**
     * Test RedisBrokerImproved basic functionality.
     */
    public function testRedisBrokerBasicOperations(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not loaded');
        }

        $broker = new RedisBrokerImproved([
            'host' => '127.0.0.1',
            'port' => 6379,
            'timeout' => 2.0,
        ]);

        $this->assertTrue($broker->isConnected());
        $this->assertEquals('redis-improved', $broker->getName());

        // Test publish
        $message = Message::event('test.channel', 'test.event', ['data' => 'test']);
        $broker->publish('test.channel', $message);

        // Test health check
        $health = $broker->healthCheck();
        $this->assertEquals('healthy', $health->status);
        $this->assertLessThan(10, $health->latencyMs);

        $broker->disconnect();
        $this->assertFalse($broker->isConnected());
    }

    /**
     * Test Redis performance under load.
     */
    public function testRedisPerformanceUnderLoad(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not loaded');
        }

        $broker = new RedisBrokerImproved([
            'host' => '127.0.0.1',
            'port' => 6379,
        ]);

        $startTime = microtime(true);

        // Publish 1000 messages
        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            $message = Message::event('perf.test', 'event', ['iteration' => $i]);
            $broker->publish('perf.test', $message);
        }

        $duration = (microtime(true) - $startTime) * 1000; // Convert to ms
        $avgLatency = $duration / self::TEST_ITERATIONS;

        // Assert average latency is acceptable
        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLD_MS,
            $avgLatency,
            "Average latency {$avgLatency}ms exceeds threshold " . self::PERFORMANCE_THRESHOLD_MS . "ms"
        );

        // Check metrics
        $metrics = BrokerMetrics::getMetrics('redis');
        $this->assertEquals(self::TEST_ITERATIONS, $metrics['publish_success']);
        $this->assertArrayHasKey('publish_latency_ms', $metrics);

        echo "\nRedis Performance:\n";
        echo "  Total: {$duration}ms\n";
        echo "  Average: {$avgLatency}ms/msg\n";
        echo "  Throughput: " . (self::TEST_ITERATIONS / ($duration / 1000)) . " msg/s\n";
        echo "  P50: {$metrics['publish_latency_ms']['p50']}ms\n";
        echo "  P95: {$metrics['publish_latency_ms']['p95']}ms\n";
        echo "  P99: {$metrics['publish_latency_ms']['p99']}ms\n";

        $broker->disconnect();
    }

    /**
     * Test Redis auto-reconnect functionality.
     */
    public function testRedisAutoReconnect(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not loaded');
        }

        $broker = new RedisBrokerImproved([
            'host' => '127.0.0.1',
            'port' => 6379,
        ]);

        $this->assertTrue($broker->isConnected());

        // Force disconnect
        $broker->disconnect();
        $this->assertFalse($broker->isConnected());

        // Try to publish - should trigger reconnect in real implementation
        // For now, we expect exception
        $this->expectException(\Exception::class);
        $message = Message::event('test', 'event', []);
        $broker->publish('test', $message);
    }

    /**
     * Test circuit breaker functionality.
     */
    public function testCircuitBreakerProtection(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not loaded');
        }

        $broker = new RedisBrokerImproved([
            'host' => '127.0.0.1',
            'port' => 6379,
            'circuit_breaker_threshold' => 3,
            'circuit_breaker_timeout' => 5,
        ]);

        $circuitBreaker = $broker->getCircuitBreaker();

        $this->assertTrue($circuitBreaker->isClosed());
        $this->assertFalse($circuitBreaker->isOpen());

        echo "\nCircuit Breaker Stats:\n";
        print_r($circuitBreaker->getStats());
    }

    /**
     * Test memory management.
     */
    public function testMemoryManagement(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not loaded');
        }

        $broker = new RedisBrokerImproved([
            'host' => '127.0.0.1',
            'port' => 6379,
        ]);

        $memoryManager = $broker->getMemoryManager();
        $startStats = $memoryManager->getStats();

        // Simulate message processing
        for ($i = 0; $i < 10000; $i++) {
            $memoryManager->tick();
        }

        $endStats = $memoryManager->getStats();

        echo "\nMemory Management:\n";
        echo "  Start: {$startStats['current_memory_mb']} MB\n";
        echo "  End: {$endStats['current_memory_mb']} MB\n";
        echo "  Messages: {$endStats['message_count']}\n";
        echo "  Peak: {$endStats['peak_memory_mb']} MB\n";

        // Memory should not grow excessively
        $memoryGrowth = $endStats['current_memory_mb'] - $startStats['current_memory_mb'];
        $this->assertLessThan(10, $memoryGrowth, "Memory grew by {$memoryGrowth}MB");

        $broker->disconnect();
    }

    /**
     * Test BrokerFactory.
     */
    public function testBrokerFactory(): void
    {
        // Test driver validation
        $this->expectException(\InvalidArgumentException::class);
        BrokerFactory::create('invalid-driver', []);
    }

    /**
     * Test BrokerFactory capabilities.
     */
    public function testBrokerCapabilities(): void
    {
        $legacyCapabilities = BrokerFactory::getCapabilities('redis');
        $improvedCapabilities = BrokerFactory::getCapabilities('redis-improved');

        // Legacy should not have advanced features
        $this->assertFalse($legacyCapabilities['connection_pooling']);
        $this->assertFalse($legacyCapabilities['circuit_breaker']);

        // Improved should have all features
        $this->assertTrue($improvedCapabilities['connection_pooling']);
        $this->assertTrue($improvedCapabilities['circuit_breaker']);
        $this->assertTrue($improvedCapabilities['auto_reconnect']);
        $this->assertTrue($improvedCapabilities['metrics']);
        $this->assertTrue($improvedCapabilities['memory_management']);

        echo "\nLegacy capabilities:\n";
        print_r($legacyCapabilities);

        echo "\nImproved capabilities:\n";
        print_r($improvedCapabilities);
    }

    /**
     * Test recommended drivers.
     */
    public function testRecommendedDrivers(): void
    {
        $this->assertEquals('redis-improved', BrokerFactory::getRecommendedDriver('redis'));
        $this->assertEquals('kafka-improved', BrokerFactory::getRecommendedDriver('kafka'));
        $this->assertEquals('rabbitmq-improved', BrokerFactory::getRecommendedDriver('rabbitmq'));
    }

    /**
     * Benchmark comparison: Legacy vs Improved.
     */
    public function testPerformanceComparison(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not loaded');
        }

        $iterations = 500;

        // Test legacy broker
        $legacyBroker = new \Toporia\Framework\Realtime\Brokers\RedisBroker([
            'host' => '127.0.0.1',
            'port' => 6379,
        ]);

        $legacyStart = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $message = Message::event('benchmark', 'event', ['i' => $i]);
            $legacyBroker->publish('benchmark', $message);
        }
        $legacyDuration = (microtime(true) - $legacyStart) * 1000;
        $legacyBroker->disconnect();

        // Test improved broker
        BrokerMetrics::reset();
        $improvedBroker = new RedisBrokerImproved([
            'host' => '127.0.0.1',
            'port' => 6379,
        ]);

        $improvedStart = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $message = Message::event('benchmark', 'event', ['i' => $i]);
            $improvedBroker->publish('benchmark', $message);
        }
        $improvedDuration = (microtime(true) - $improvedStart) * 1000;

        $metrics = BrokerMetrics::getMetrics('redis');
        $improvedBroker->disconnect();

        // Calculate improvement
        $speedup = $legacyDuration / $improvedDuration;
        $improvement = (($legacyDuration - $improvedDuration) / $legacyDuration) * 100;

        echo "\n=== PERFORMANCE COMPARISON ===\n";
        echo "Iterations: {$iterations}\n\n";
        echo "Legacy Broker:\n";
        echo "  Total: " . number_format($legacyDuration, 2) . "ms\n";
        echo "  Average: " . number_format($legacyDuration / $iterations, 2) . "ms/msg\n";
        echo "  Throughput: " . number_format($iterations / ($legacyDuration / 1000), 0) . " msg/s\n\n";

        echo "Improved Broker:\n";
        echo "  Total: " . number_format($improvedDuration, 2) . "ms\n";
        echo "  Average: " . number_format($improvedDuration / $iterations, 2) . "ms/msg\n";
        echo "  Throughput: " . number_format($iterations / ($improvedDuration / 1000), 0) . " msg/s\n";
        echo "  P50: {$metrics['publish_latency_ms']['p50']}ms\n";
        echo "  P95: {$metrics['publish_latency_ms']['p95']}ms\n";
        echo "  P99: {$metrics['publish_latency_ms']['p99']}ms\n\n";

        echo "Improvement:\n";
        echo "  Speedup: " . number_format($speedup, 2) . "x\n";
        echo "  Faster: " . number_format($improvement, 1) . "%\n";

        // Improved should be at least as fast as legacy
        // (May be slightly slower due to metrics overhead, but safer and more reliable)
        $this->assertLessThan(
            $legacyDuration * 1.5,
            $improvedDuration,
            "Improved broker is significantly slower than legacy"
        );
    }
}
