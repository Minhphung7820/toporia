# Testing Guide - Hướng Dẫn Nhanh

## 🚀 Cách Chạy Tests (Giống Laravel)

### Cách 1: Dùng Console Command (Giống Laravel - Khuyến nghị)

**⚠️ Lưu ý:** Nếu chạy trên host thiếu PHP extensions (như `mbstring`), hãy chạy trong Docker container:

**Cách 1: Dùng script helper (Dễ nhất - Khuyến nghị)**

```bash
# Chạy tất cả tests
./test.sh

# Chạy với filter
./test.sh --filter=test_name

# Chạy test suite cụ thể
./test.sh --testsuite=Unit
./test.sh --testsuite=Feature
./test.sh --testsuite=Performance

# Chạy với coverage
./test.sh --coverage
./test.sh --coverage-html
```

**Cách 2: Chạy trực tiếp trong Docker**

```bash
# Chạy tất cả tests
docker exec project_topo_app sh -c "cd /var/www/html && php console test"

# Chạy với filter
docker exec project_topo_app sh -c "cd /var/www/html && php console test --filter=test_name"

# Chạy test suite cụ thể
docker exec project_topo_app sh -c "cd /var/www/html && php console test --testsuite=Unit"
docker exec project_topo_app sh -c "cd /var/www/html && php console test --testsuite=Feature"
docker exec project_topo_app sh -c "cd /var/www/html && php console test --testsuite=Performance"

# Chạy với coverage
php console test --coverage
php console test --coverage-html

# Stop on failure
php console test --stop-on-failure

# Verbose output
php console test --verbose
```

### Cách 2: Dùng Composer

```bash
# Chạy tất cả tests
composer test

# Chạy Unit tests
composer test:unit

# Chạy Feature tests
composer test:feature

# Chạy Performance tests
composer test:performance

# Chạy test cụ thể (filter)
composer test:filter test_broker_publishing

# Chạy với coverage
composer test:coverage
```

### Cách 2: Dùng Script Helper

```bash
# Chạy trong Docker container
./tests/run.sh

# Chạy test cụ thể
./tests/run.sh tests/Feature/RealtimeTest.php

# Chạy với filter
./tests/run.sh --filter test_broker_publishing
```

### Cách 3: Dùng PHPUnit trực tiếp

```bash
# Trong Docker container
docker exec project_topo_app sh -c "cd /var/www/html && vendor/bin/phpunit"

# Hoặc trên host (nếu có PHPUnit)
vendor/bin/phpunit
```

## 📝 Tạo Test Mới

### 1. Tạo Unit Test

```bash
# Tạo file: tests/Unit/UserServiceTest.php
```

```php
<?php

namespace Tests\Unit;

use Toporia\Framework\Testing\TestCase;

class UserServiceTest extends TestCase
{
    public function test_create_user(): void
    {
        $this->assertTrue(true);
    }
}
```

### 2. Tạo Feature Test

```bash
# Tạo file: tests/Feature/UserControllerTest.php
```

```php
<?php

namespace Tests\Feature;

use Toporia\Framework\Testing\TestCase;

class UserControllerTest extends TestCase
{
    public function test_user_endpoint(): void
    {
        $response = $this->get('/api/users');
        $this->assertSuccessful($response);
    }
}
```

## 🎯 Test Realtime

### Ví dụ đơn giản:

```php
<?php

namespace Tests\Feature;

use Toporia\Framework\Testing\TestCase;

class RealtimeTest extends TestCase
{
    public function test_broker(): void
    {
        // 1. Fake broker
        $this->fakeBroker();

        // 2. Tạo mock broker
        $broker = $this->mockBroker();

        // 3. Tạo message
        $message = $this->createRealtimeMessage('user.1', 'event', ['data' => 'test']);

        // 4. Publish
        $broker->publish('user.1', $message);

        // 5. Kiểm tra
        $this->assertMessagePublished('user.1');
    }
}
```

## 📚 Xem Thêm

- `docs/TESTING.md` - Full documentation
- `docs/TESTING_EXAMPLES.md` - Ví dụ chi tiết
- `tests/Feature/RealtimeTest.php` - Example tests

