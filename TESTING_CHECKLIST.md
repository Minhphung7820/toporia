# ✅ TESTING CHECKLIST - Broker v2.0

**Status:** ⚠️ PARTIAL - Cần complete trước khi deploy production

---

## 📋 TESTING STATUS

### ✅ ĐÃ CÓ (Completed)

1. **Unit Tests**
   - ✅ Infrastructure tests trong `ImprovedBrokersTest.php`
   - ✅ Basic operations test
   - ✅ Performance comparison test
   - ✅ Memory management test
   - ✅ Circuit breaker test

2. **Load Test Script**
   - ✅ `tests/load-test-brokers.php`
   - ✅ Throughput measurement
   - ✅ Latency distribution
   - ✅ Memory tracking
   - ✅ Error rate monitoring

### ⚠️ CẦN THÊM (TODO)

1. **Integration Tests với Real Brokers**
   - [ ] Redis Pub/Sub actual test
   - [ ] Kafka produce/consume test
   - [ ] RabbitMQ routing test
   - [ ] Multi-channel subscription test

2. **Stress Tests**
   - [ ] 1M messages continuous test
   - [ ] 24-hour stability test
   - [ ] Memory leak verification
   - [ ] Connection pool stress test

3. **Chaos Tests**
   - [ ] Broker restart during operation
   - [ ] Network partition simulation
   - [ ] High latency (500ms+) test
   - [ ] Concurrent access test

4. **Edge Cases**
   - [ ] Empty message handling
   - [ ] Large message (>1MB) handling
   - [ ] Invalid channel names
   - [ ] Connection timeout scenarios
   - [ ] Max connections reached

---

## 🧪 TEST PLAN - STEP BY STEP

### Phase 1: Local Development Testing (1-2 days)

#### Step 1.1: Fix All Linting Errors
```bash
# Check for errors
./vendor/bin/phpstan analyze src/Framework/Realtime/Brokers/

# Fix all issues before proceeding
```

#### Step 1.2: Unit Tests
```bash
# Run existing tests
./vendor/bin/phpunit tests/Integration/Realtime/ImprovedBrokersTest.php

# Expected: All pass ✅
```

#### Step 1.3: Basic Integration Test
```bash
# Start Redis
sudo systemctl start redis

# Test Redis broker
php -r "
require 'vendor/autoload.php';
\$broker = new Toporia\Framework\Realtime\Brokers\RedisBrokerImproved([
    'host' => '127.0.0.1',
    'port' => 6379,
]);
echo 'Connected: ' . (\$broker->isConnected() ? 'YES' : 'NO') . PHP_EOL;
\$health = \$broker->healthCheck();
echo 'Status: ' . \$health->status . PHP_EOL;
echo 'Latency: ' . \$health->latencyMs . 'ms' . PHP_EOL;
"

# Expected output:
# Connected: YES
# Status: healthy
# Latency: <5ms
```

### Phase 2: Performance Testing (1 day)

#### Step 2.1: Load Test - Redis
```bash
# Test with increasing load
php tests/load-test-brokers.php redis 1000    # Warm up
php tests/load-test-brokers.php redis 10000   # Medium
php tests/load-test-brokers.php redis 50000   # High
php tests/load-test-brokers.php redis 100000  # Stress

# Check results:
# - Throughput > 100K msg/s ✅
# - P95 latency < 5ms ✅
# - Error rate = 0% ✅
# - Memory stable ✅
```

#### Step 2.2: Load Test - Kafka (if available)
```bash
# Start Kafka
docker-compose up -d kafka

php tests/load-test-brokers.php kafka 50000

# Expected:
# - Throughput > 500K msg/s
# - P95 < 15ms
```

#### Step 2.3: Load Test - RabbitMQ (if available)
```bash
# Start RabbitMQ
docker-compose up -d rabbitmq

php tests/load-test-brokers.php rabbitmq 20000

# Expected:
# - Throughput > 100K msg/s
# - P95 < 20ms
```

### Phase 3: Stability Testing (1-2 days)

#### Step 3.1: Long-Running Test (24 hours)
```bash
# Create long-running test script
cat > tests/stability-test.php << 'EOF'
<?php
require 'vendor/autoload.php';

use Toporia\Framework\Realtime\Brokers\RedisBrokerImproved;
use Toporia\Framework\Realtime\Message;

$broker = new RedisBrokerImproved(['host' => '127.0.0.1']);
$startMemory = memory_get_usage(true);
$count = 0;

echo "Starting 24-hour stability test...\n";

while (true) {
    try {
        $message = Message::event('test', 'event', ['count' => $count]);
        $broker->publish('test', $message);
        $count++;

        // Report every 10K messages
        if ($count % 10000 === 0) {
            $currentMemory = memory_get_usage(true);
            $memoryGrowth = ($currentMemory - $startMemory) / 1024 / 1024;

            echo sprintf(
                "[%s] Messages: %d, Memory: %.2f MB (+%.2f MB)\n",
                date('Y-m-d H:i:s'),
                $count,
                $currentMemory / 1024 / 1024,
                $memoryGrowth
            );

            // Alert if memory grows too much
            if ($memoryGrowth > 100) {
                echo "WARNING: Memory leak detected!\n";
                break;
            }
        }

        usleep(100); // 100 microseconds between messages

    } catch (\Throwable $e) {
        echo "ERROR: {$e->getMessage()}\n";
    }
}
EOF

# Run in background
nohup php tests/stability-test.php > stability-test.log 2>&1 &

# Monitor
tail -f stability-test.log

# After 24 hours, check:
# - Memory growth < 100 MB ✅
# - No errors ✅
# - Consistent throughput ✅
```

#### Step 3.2: Connection Pool Test
```bash
# Test connection pooling under load
cat > tests/connection-pool-test.php << 'EOF'
<?php
require 'vendor/autoload.php';

use Toporia\Framework\Realtime\Brokers\RedisBrokerImproved;
use Toporia\Framework\Realtime\Brokers\ConnectionPool\BrokerConnectionPool;

$pool = BrokerConnectionPool::forBroker('redis');

echo "Testing connection pool with 100 brokers...\n";

$brokers = [];
for ($i = 0; $i < 100; $i++) {
    $brokers[] = new RedisBrokerImproved(['host' => '127.0.0.1']);
}

// Publish from all brokers
foreach ($brokers as $i => $broker) {
    $message = Toporia\Framework\Realtime\Message::event('test', 'event', ['i' => $i]);
    $broker->publish('test', $message);
}

// Check pool stats
$stats = $pool->getStats();
echo "Total pooled connections: {$stats['total_connections']}\n";

// Should be much less than 100
if ($stats['total_connections'] < 10) {
    echo "✅ Connection pooling working!\n";
} else {
    echo "⚠️ Pool might not be working correctly\n";
}

foreach ($brokers as $broker) {
    $broker->disconnect();
}
EOF

php tests/connection-pool-test.php
```

### Phase 4: Chaos Testing (1 day)

#### Step 4.1: Broker Restart Test
```bash
# Terminal 1: Start consumer
php console realtime:redis:consume &
CONSUMER_PID=$!

# Terminal 2: Monitor
while true; do
    curl -s http://localhost:8000/health/brokers | jq .
    sleep 5
done

# Terminal 3: Chaos!
sleep 30
echo "Restarting Redis..."
sudo systemctl restart redis
sleep 10
echo "Redis restarted"

# Expected: Consumer auto-reconnects ✅
# Check logs for "reconnect" messages

# Cleanup
kill $CONSUMER_PID
```

#### Step 4.2: Network Latency Test
```bash
# Simulate 500ms latency
sudo tc qdisc add dev lo root netem delay 500ms

# Test broker
php tests/load-test-brokers.php redis 1000

# Expected:
# - Still works ✅
# - Circuit breaker might open ⚠️
# - Metrics show high latency ✅

# Remove latency
sudo tc qdisc del dev lo root
```

### Phase 5: Production-Like Testing (2-3 days)

#### Step 5.1: Staging Environment
```bash
# Deploy to staging with 10% traffic
# config/realtime.php
'driver' => env('BROKER_VERSION', 'redis'),

# .env.staging
BROKER_VERSION=redis-improved

# Monitor for 24 hours:
# - Error rate
# - Latency percentiles
# - Memory usage
# - CPU usage
```

#### Step 5.2: A/B Testing
```bash
# Split traffic 50/50
# service-a: redis-improved
# service-b: redis (legacy)

# Compare metrics:
# - Throughput
# - Latency
# - Error rate
# - Resource usage

# Expected: Improved should be better or equal
```

---

## 📊 ACCEPTANCE CRITERIA

### Performance Requirements

| Metric | Redis | Kafka | RabbitMQ | Status |
|--------|-------|-------|----------|--------|
| Throughput | >100K msg/s | >500K msg/s | >100K msg/s | ⏳ TODO |
| P95 Latency | <5ms | <15ms | <20ms | ⏳ TODO |
| P99 Latency | <10ms | <25ms | <30ms | ⏳ TODO |
| Error Rate | <0.1% | <0.1% | <0.1% | ⏳ TODO |
| Memory/Msg | <0.1 KB | <0.5 KB | <1 KB | ⏳ TODO |

### Reliability Requirements

| Feature | Requirement | Status |
|---------|-------------|--------|
| Auto-reconnect | 100% success within 5 retries | ⏳ TODO |
| Circuit breaker | Opens after 5 failures | ⏳ TODO |
| Memory leaks | 0 leaks after 24h | ⏳ TODO |
| Connection pool | <10 connections for 100 clients | ⏳ TODO |
| Graceful shutdown | 100% clean disconnect | ⏳ TODO |

### Scalability Requirements

| Test | Requirement | Status |
|------|-------------|--------|
| 1M messages | Complete without OOM | ⏳ TODO |
| 100 concurrent clients | No connection errors | ⏳ TODO |
| 1000 servers simulation | Pool working correctly | ⏳ TODO |

---

## 🚦 GO/NO-GO DECISION

### ✅ GO to Production IF:
- [ ] All Phase 1-3 tests pass
- [ ] No critical bugs found
- [ ] Performance meets requirements
- [ ] Memory stable for 24+ hours
- [ ] Connection pool working
- [ ] Circuit breaker tested
- [ ] Staging runs stable for 48+ hours

### ❌ NO-GO IF:
- [ ] Any critical bug
- [ ] Performance degradation vs legacy
- [ ] Memory leak detected
- [ ] Connection pool issues
- [ ] Circuit breaker not working
- [ ] Error rate > 0.1%

---

## 📝 TESTING COMMANDS QUICK REFERENCE

```bash
# 1. Linting
./vendor/bin/phpstan analyze src/Framework/Realtime/

# 2. Unit Tests
./vendor/bin/phpunit tests/Integration/Realtime/

# 3. Load Test
php tests/load-test-brokers.php redis 10000

# 4. Stability Test (24h)
nohup php tests/stability-test.php > stability.log 2>&1 &

# 5. Connection Pool Test
php tests/connection-pool-test.php

# 6. Health Check
curl http://localhost:8000/health/brokers | jq .

# 7. Metrics
php -r "print_r(Toporia\Framework\Realtime\Metrics\BrokerMetrics::getAllMetrics());"
```

---

## 🎯 RECOMMENDATION

**CURRENT STATUS:** ⚠️ **NOT READY** for production

**NEEDED:**
1. Fix Redis constant error ✅ DONE
2. Run all Phase 1-3 tests ⏳ TODO (2-3 days)
3. Pass all acceptance criteria ⏳ TODO
4. Staging validation ⏳ TODO (2-3 days)

**TIMELINE:** 5-7 days until production-ready

**PRIORITY:**
1. 🔴 HIGH: Fix linting errors
2. 🔴 HIGH: Phase 1 tests (local)
3. 🟡 MEDIUM: Phase 2 tests (performance)
4. 🟡 MEDIUM: Phase 3 tests (stability)
5. 🟢 LOW: Phase 4 tests (chaos)

