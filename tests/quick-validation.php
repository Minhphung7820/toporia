<?php

declare(strict_types=1);

/**
 * Quick Validation Script
 *
 * Chạy script này để validate nhanh improved brokers.
 * Usage: php tests/quick-validation.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Toporia\Framework\Realtime\Brokers\{RedisBrokerImproved, BrokerFactory};
use Toporia\Framework\Realtime\Message;
use Toporia\Framework\Realtime\Metrics\BrokerMetrics;

echo "===========================================\n";
echo "QUICK VALIDATION TEST\n";
echo "===========================================\n\n";

$errors = [];
$warnings = [];
$passed = 0;
$total = 0;

// Helper function
function test(string $name, callable $fn, &$passed, &$total, &$errors): void
{
    $total++;
    echo "Testing: {$name}... ";

    try {
        $result = $fn();
        if ($result === true || $result === null) {
            echo "✅ PASS\n";
            $passed++;
        } else {
            echo "⚠️  WARNING: {$result}\n";
        }
    } catch (\Throwable $e) {
        echo "❌ FAIL\n";
        $errors[] = "{$name}: {$e->getMessage()}";
    }
}

echo "1. CLASS EXISTENCE TESTS\n";
echo "-------------------------------------------\n";

test("RedisBrokerImproved exists", function () {
    return class_exists(RedisBrokerImproved::class);
}, $passed, $total, $errors);

test("BrokerFactory exists", function () {
    return class_exists(BrokerFactory::class);
}, $passed, $total, $errors);

test("BrokerMetrics exists", function () {
    return class_exists(BrokerMetrics::class);
}, $passed, $total, $errors);

echo "\n2. REDIS EXTENSION CHECK\n";
echo "-------------------------------------------\n";

test("Redis extension loaded", function () {
    if (!extension_loaded('redis')) {
        return "Redis extension not installed. Install: sudo apt install php-redis";
    }
    return true;
}, $passed, $total, $errors);

echo "\n3. REDIS CONNECTION TEST\n";
echo "-------------------------------------------\n";

$redisAvailable = false;

test("Connect to Redis", function () use (&$redisAvailable) {
    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379, 1.0);
        $redis->ping();
        $redis->close();
        $redisAvailable = true;
        return true;
    } catch (\Throwable $e) {
        return "Redis not available: {$e->getMessage()}. Start: sudo systemctl start redis";
    }
}, $passed, $total, $errors);

if ($redisAvailable) {
    echo "\n4. BROKER FUNCTIONALITY TESTS\n";
    echo "-------------------------------------------\n";

    test("Create RedisBrokerImproved", function () {
        $broker = new RedisBrokerImproved([
            'host' => '127.0.0.1',
            'port' => 6379,
        ]);
        return $broker->isConnected();
    }, $passed, $total, $errors);

    test("Health check", function () {
        $broker = new RedisBrokerImproved(['host' => '127.0.0.1']);
        $health = $broker->healthCheck();

        if ($health->status !== 'healthy') {
            return "Health status: {$health->status}";
        }

        if ($health->latencyMs > 10) {
            return "High latency: {$health->latencyMs}ms";
        }

        return true;
    }, $passed, $total, $errors);

    test("Publish message", function () {
        $broker = new RedisBrokerImproved(['host' => '127.0.0.1']);
        $message = Message::event('test.channel', 'test.event', ['test' => true]);
        $broker->publish('test.channel', $message);
        $broker->disconnect();
        return true;
    }, $passed, $total, $errors);

    test("Circuit breaker exists", function () {
        $broker = new RedisBrokerImproved(['host' => '127.0.0.1']);
        $cb = $broker->getCircuitBreaker();
        return $cb->isClosed();
    }, $passed, $total, $errors);

    test("Memory manager exists", function () {
        $broker = new RedisBrokerImproved(['host' => '127.0.0.1']);
        $mm = $broker->getMemoryManager();
        $stats = $mm->getStats();
        return isset($stats['current_memory_mb']);
    }, $passed, $total, $errors);

    test("Metrics collection", function () {
        BrokerMetrics::reset();
        $broker = new RedisBrokerImproved(['host' => '127.0.0.1']);

        // Publish 10 messages
        for ($i = 0; $i < 10; $i++) {
            $message = Message::event('test', 'event', ['i' => $i]);
            $broker->publish('test', $message);
        }

        $metrics = BrokerMetrics::getMetrics('redis');

        if (!isset($metrics['publish_success'])) {
            return "No metrics collected";
        }

        if ($metrics['publish_success'] < 10) {
            return "Only {$metrics['publish_success']}/10 messages successful";
        }

        $broker->disconnect();
        return true;
    }, $passed, $total, $errors);

    echo "\n5. PERFORMANCE QUICK CHECK\n";
    echo "-------------------------------------------\n";

    test("Throughput test (1000 msgs)", function () {
        BrokerMetrics::reset();
        $broker = new RedisBrokerImproved(['host' => '127.0.0.1']);

        $start = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            $message = Message::event('perf.test', 'event', ['i' => $i]);
            $broker->publish('perf.test', $message);
        }
        $duration = (microtime(true) - $start) * 1000; // ms

        $throughput = 1000 / ($duration / 1000); // msg/s
        $avgLatency = $duration / 1000; // ms

        echo "\n      Throughput: " . number_format($throughput, 0) . " msg/s\n";
        echo "      Avg Latency: " . number_format($avgLatency, 2) . "ms\n";

        $metrics = BrokerMetrics::getMetrics('redis');
        if (isset($metrics['publish_latency_ms'])) {
            echo "      P95: {$metrics['publish_latency_ms']['p95']}ms\n";
            echo "      P99: {$metrics['publish_latency_ms']['p99']}ms\n";
        }

        $broker->disconnect();

        if ($throughput < 10000) {
            return "Low throughput: " . number_format($throughput, 0) . " msg/s (expect >10K)";
        }

        return true;
    }, $passed, $total, $errors);
}

echo "\n6. FACTORY TESTS\n";
echo "-------------------------------------------\n";

test("Factory validation", function () {
    try {
        BrokerFactory::create('invalid-driver', []);
        return "Should throw exception for invalid driver";
    } catch (\InvalidArgumentException $e) {
        return true; // Expected
    }
}, $passed, $total, $errors);

test("Factory capabilities", function () {
    $caps = BrokerFactory::getCapabilities('redis-improved');

    if (!$caps['connection_pooling']) {
        return "Missing connection_pooling capability";
    }

    if (!$caps['circuit_breaker']) {
        return "Missing circuit_breaker capability";
    }

    return true;
}, $passed, $total, $errors);

test("Recommended drivers", function () {
    $redis = BrokerFactory::getRecommendedDriver('redis');
    if ($redis !== 'redis-improved') {
        return "Wrong recommendation: {$redis}";
    }
    return true;
}, $passed, $total, $errors);

// Summary
echo "\n===========================================\n";
echo "SUMMARY\n";
echo "===========================================\n\n";

echo "Tests run: {$total}\n";
echo "Passed: {$passed}\n";
echo "Failed: " . ($total - $passed) . "\n";
echo "Success rate: " . number_format(($passed / $total) * 100, 1) . "%\n\n";

if (!empty($errors)) {
    echo "ERRORS:\n";
    foreach ($errors as $error) {
        echo "  ❌ {$error}\n";
    }
    echo "\n";
}

if ($passed === $total) {
    echo "✅ ALL TESTS PASSED!\n";
    echo "\nNext steps:\n";
    echo "1. Run load test: php tests/load-test-brokers.php redis 10000\n";
    echo "2. Check documentation: cat TESTING_CHECKLIST.md\n";
    echo "3. Deploy to staging for validation\n";
    exit(0);
} else {
    $failRate = (($total - $passed) / $total) * 100;

    if ($failRate > 50) {
        echo "❌ CRITICAL: {$failRate}% tests failed\n";
        echo "\nAction required:\n";
        echo "1. Fix all errors above\n";
        echo "2. Review TESTING_CHECKLIST.md\n";
        echo "3. Re-run this script\n";
        exit(1);
    } elseif ($failRate > 20) {
        echo "⚠️  WARNING: {$failRate}% tests failed\n";
        echo "\nRecommended actions:\n";
        echo "1. Fix errors above\n";
        echo "2. Run load tests with caution\n";
        exit(1);
    } else {
        echo "⚠️  MINOR ISSUES: {$failRate}% tests failed\n";
        echo "\nCan proceed but should fix:\n";
        foreach ($errors as $error) {
            echo "  - {$error}\n";
        }
        exit(0);
    }
}

