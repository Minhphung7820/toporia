# Quick Start - Testing Framework

## 🚀 Cách Sử Dụng Nhanh

### 1. Chạy Tests (Giống Laravel)

```bash
# Chạy tất cả tests
php console test

# Chạy với filter
php console test --filter=test_broker_publishing

# Chạy test suite cụ thể
php console test --testsuite=Unit
php console test --testsuite=Feature
php console test --testsuite=Performance

# Chạy với coverage
php console test --coverage
php console test --coverage-html

# Stop on failure
php console test --stop-on-failure

# Verbose output
php console test --verbose
```

### 2. Hoặc Dùng Composer

```bash
composer test
composer test:unit
composer test:feature
composer test:filter test_name
```

### 3. Tạo Test Mới

```php
<?php

namespace Tests\Feature;

use Toporia\Framework\Testing\TestCase;

class MyTest extends TestCase
{
    public function test_something(): void
    {
        $this->assertTrue(true);
    }
}
```

### 4. Test Realtime

```php
public function test_realtime(): void
{
    // Fake broker
    $this->fakeBroker();

    // Tạo mock broker
    $broker = $this->mockBroker();

    // Tạo message
    $message = $this->createRealtimeMessage('user.1', 'event', ['data' => 'test']);

    // Publish
    $broker->publish('user.1', $message);

    // Assert
    $this->assertMessagePublished('user.1', 'event');
}
```

## 📚 Xem Thêm

- `tests/README.md` - Hướng dẫn chi tiết
- `docs/TESTING.md` - Full API documentation
- `docs/TESTING_EXAMPLES.md` - Ví dụ đầy đủ

