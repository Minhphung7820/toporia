<?php

declare(strict_types=1);

/**
 * Load Test Script for Improved Brokers
 *
 * Tests broker performance under high load to verify:
 * - Throughput capacity
 * - Latency under load
 * - Memory stability
 * - Connection stability
 * - Error rate
 *
 * Usage:
 *   php tests/load-test-brokers.php redis 10000
 *   php tests/load-test-brokers.php kafka 50000
 *   php tests/load-test-brokers.php rabbitmq 20000
 */

require __DIR__ . '/../vendor/autoload.php';

use Toporia\Framework\Realtime\Brokers\{RedisBrokerImproved, KafkaBrokerImproved, RabbitMqBrokerImproved};
use Toporia\Framework\Realtime\Message;
use Toporia\Framework\Realtime\Metrics\BrokerMetrics;

// Parse command line arguments
$broker = $argv[1] ?? 'redis';
$messageCount = (int) ($argv[2] ?? 10000);
$concurrency = (int) ($argv[3] ?? 1);

echo "===========================================\n";
echo "BROKER LOAD TEST\n";
echo "===========================================\n";
echo "Broker: {$broker}\n";
echo "Messages: " . number_format($messageCount) . "\n";
echo "Concurrency: {$concurrency}\n";
echo "===========================================\n\n";

// Create broker based on type
$brokerInstance = match ($broker) {
    'redis' => new RedisBrokerImproved([
        'host' => '127.0.0.1',
        'port' => 6379,
        'circuit_breaker_threshold' => 10,
    ]),
    'kafka' => new KafkaBrokerImproved([
        'brokers' => ['localhost:9092'],
        'topic_prefix' => 'load_test',
        'circuit_breaker_threshold' => 10,
    ]),
    'rabbitmq' => new RabbitMqBrokerImproved([
        'host' => '127.0.0.1',
        'port' => 5672,
        'max_channels' => 10,
        'circuit_breaker_threshold' => 10,
    ]),
    default => throw new InvalidArgumentException("Unknown broker: {$broker}")
};

echo "Broker connected: " . ($brokerInstance->isConnected() ? 'YES' : 'NO') . "\n";
echo "Starting load test...\n\n";

// Reset metrics
BrokerMetrics::reset();

// Track metrics
$errors = 0;
$startMemory = memory_get_usage(true);
$startTime = microtime(true);

// Publish messages
for ($i = 1; $i <= $messageCount; $i++) {
    try {
        $message = Message::event(
            'load.test.channel',
            'load.test.event',
            [
                'iteration' => $i,
                'timestamp' => microtime(true),
                'data' => str_repeat('x', 100), // 100 bytes payload
            ]
        );

        $brokerInstance->publish('load.test.channel', $message);

        // Progress indicator
        if ($i % 1000 === 0) {
            $currentMemory = memory_get_usage(true);
            $memoryDiff = ($currentMemory - $startMemory) / 1024 / 1024;
            $elapsed = microtime(true) - $startTime;
            $rate = $i / $elapsed;

            echo sprintf(
                "Progress: %s/%s (%.1f%%) | Rate: %s msg/s | Memory: +%.2f MB\n",
                number_format($i),
                number_format($messageCount),
                ($i / $messageCount) * 100,
                number_format($rate, 0),
                $memoryDiff
            );
        }
    } catch (\Throwable $e) {
        $errors++;
        if ($errors <= 10) {
            echo "ERROR: {$e->getMessage()}\n";
        }
    }
}

$endTime = microtime(true);
$endMemory = memory_get_usage(true);

// Calculate statistics
$totalTime = $endTime - $startTime;
$throughput = $messageCount / $totalTime;
$avgLatency = ($totalTime / $messageCount) * 1000; // ms
$memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // MB

// Get broker metrics
$metrics = BrokerMetrics::getMetrics($broker);

// Get circuit breaker stats
$cbStats = $brokerInstance->getCircuitBreaker()->getStats();

// Get memory manager stats
$memStats = $brokerInstance->getMemoryManager()->getStats();

// Health check
$health = $brokerInstance->healthCheck();

echo "\n===========================================\n";
echo "LOAD TEST RESULTS\n";
echo "===========================================\n\n";

echo "Performance:\n";
echo "  Total Time: " . number_format($totalTime, 2) . "s\n";
echo "  Throughput: " . number_format($throughput, 0) . " msg/s\n";
echo "  Avg Latency: " . number_format($avgLatency, 2) . "ms\n";
echo "  Errors: {$errors} (" . number_format(($errors / $messageCount) * 100, 2) . "%)\n\n";

echo "Memory:\n";
echo "  Start: " . number_format($startMemory / 1024 / 1024, 2) . " MB\n";
echo "  End: " . number_format($endMemory / 1024 / 1024, 2) . " MB\n";
echo "  Used: " . number_format($memoryUsed, 2) . " MB\n";
echo "  Peak: " . number_format(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB\n";
echo "  Per Message: " . number_format(($memoryUsed / $messageCount) * 1024, 2) . " KB\n\n";

if (isset($metrics['publish_latency_ms'])) {
    echo "Latency Distribution:\n";
    echo "  Min: {$metrics['publish_latency_ms']['min']}ms\n";
    echo "  P50: {$metrics['publish_latency_ms']['p50']}ms\n";
    echo "  P95: {$metrics['publish_latency_ms']['p95']}ms\n";
    echo "  P99: {$metrics['publish_latency_ms']['p99']}ms\n";
    echo "  Max: {$metrics['publish_latency_ms']['max']}ms\n\n";
}

echo "Reliability:\n";
echo "  Success: " . ($metrics['publish_success'] ?? 0) . "\n";
echo "  Failed: " . ($metrics['publish_failed'] ?? 0) . "\n";
echo "  Success Rate: " . number_format((($metrics['publish_success'] ?? 0) / $messageCount) * 100, 2) . "%\n\n";

echo "Circuit Breaker:\n";
echo "  State: {$cbStats['state']}\n";
echo "  Failures: {$cbStats['failure_count']}\n";
echo "  Successes: {$cbStats['success_count']}\n\n";

echo "Memory Manager:\n";
echo "  Current: {$memStats['current_memory_mb']} MB\n";
echo "  Peak: {$memStats['peak_memory_mb']} MB\n";
echo "  Usage: {$memStats['memory_usage_percent']}%\n\n";

echo "Health Check:\n";
echo "  Status: {$health->status}\n";
echo "  Latency: {$health->latencyMs}ms\n\n";

// Performance assessment
echo "===========================================\n";
echo "ASSESSMENT\n";
echo "===========================================\n\n";

$assessment = [];

// Throughput assessment
if ($throughput >= 100000) {
    $assessment[] = "✅ EXCELLENT throughput (>100K msg/s)";
} elseif ($throughput >= 50000) {
    $assessment[] = "✅ GOOD throughput (>50K msg/s)";
} elseif ($throughput >= 10000) {
    $assessment[] = "⚠️  ACCEPTABLE throughput (>10K msg/s)";
} else {
    $assessment[] = "❌ LOW throughput (<10K msg/s)";
}

// Latency assessment
if ($avgLatency < 1) {
    $assessment[] = "✅ EXCELLENT latency (<1ms)";
} elseif ($avgLatency < 5) {
    $assessment[] = "✅ GOOD latency (<5ms)";
} elseif ($avgLatency < 10) {
    $assessment[] = "⚠️  ACCEPTABLE latency (<10ms)";
} else {
    $assessment[] = "❌ HIGH latency (>10ms)";
}

// Error rate assessment
$errorRate = ($errors / $messageCount) * 100;
if ($errorRate === 0.0) {
    $assessment[] = "✅ PERFECT reliability (0% errors)";
} elseif ($errorRate < 0.1) {
    $assessment[] = "✅ EXCELLENT reliability (<0.1% errors)";
} elseif ($errorRate < 1) {
    $assessment[] = "⚠️  ACCEPTABLE reliability (<1% errors)";
} else {
    $assessment[] = "❌ HIGH error rate (>1%)";
}

// Memory assessment
$memoryPerMsg = ($memoryUsed / $messageCount) * 1024; // KB
if ($memoryPerMsg < 0.1) {
    $assessment[] = "✅ EXCELLENT memory efficiency (<0.1 KB/msg)";
} elseif ($memoryPerMsg < 1) {
    $assessment[] = "✅ GOOD memory efficiency (<1 KB/msg)";
} elseif ($memoryPerMsg < 10) {
    $assessment[] = "⚠️  ACCEPTABLE memory usage (<10 KB/msg)";
} else {
    $assessment[] = "❌ HIGH memory usage (>10 KB/msg)";
}

foreach ($assessment as $item) {
    echo $item . "\n";
}

echo "\n===========================================\n";
echo "PRODUCTION READINESS\n";
echo "===========================================\n\n";

$productionReady = true;
$recommendations = [];

// Check circuit breaker
if ($cbStats['state'] !== 'closed') {
    $productionReady = false;
    $recommendations[] = "Circuit breaker is {$cbStats['state']} - investigate broker stability";
}

// Check memory usage
if ($memStats['memory_usage_percent'] > 80) {
    $productionReady = false;
    $recommendations[] = "Memory usage > 80% - increase memory_limit or optimize";
}

// Check error rate
if ($errorRate > 1) {
    $productionReady = false;
    $recommendations[] = "Error rate > 1% - investigate connection/broker issues";
}

// Check health
if ($health->status !== 'healthy') {
    $productionReady = false;
    $recommendations[] = "Health check status is {$health->status}";
}

if ($productionReady) {
    echo "✅ PRODUCTION READY\n\n";
    echo "This broker configuration is ready for production deployment.\n";
    echo "Recommended capacity: " . number_format($throughput * 0.7, 0) . " msg/s (70% of max)\n";
} else {
    echo "❌ NOT PRODUCTION READY\n\n";
    echo "Issues found:\n";
    foreach ($recommendations as $rec) {
        echo "  - {$rec}\n";
    }
}

echo "\n===========================================\n";

// Disconnect
$brokerInstance->disconnect();

echo "\nTest completed successfully!\n";
