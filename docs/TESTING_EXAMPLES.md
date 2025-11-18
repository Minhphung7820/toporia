# Testing Examples - Hướng Dẫn Sử Dụng Chi Tiết

Hướng dẫn chi tiết cách sử dụng testing framework với các ví dụ thực tế.

## 📋 Mục Lục

1. [Unit Testing](#unit-testing)
2. [Feature Testing](#feature-testing)
3. [Realtime Testing](#realtime-testing)
4. [Database Testing](#database-testing)
5. [Performance Testing](#performance-testing)

## 🧪 Unit Testing

### Ví dụ 1: Test Service Class

```php
<?php

namespace Tests\Unit;

use Toporia\Framework\Testing\TestCase;
use App\Services\UserService;

class UserServiceTest extends TestCase
{
    public function test_create_user(): void
    {
        // Arrange: Setup
        $this->bind('user.repository', fn() => new MockUserRepository());

        $service = $this->make(UserService::class);

        // Act: Execute
        $user = $service->create('john@example.com', 'John Doe');

        // Assert: Verify
        $this->assertNotNull($user);
        $this->assertEquals('john@example.com', $user->email);
    }
}
```

### Ví dụ 2: Test với Mock

```php
public function test_send_email_with_mock(): void
{
    // Mock mailer
    $mailer = $this->mock('mailer', function ($mock) {
        $mock->shouldReceive('send')
            ->once()
            ->with('user@example.com', 'Welcome', 'Hello!')
            ->andReturn(true);
    });

    $service = new EmailService($mailer);
    $result = $service->sendWelcomeEmail('user@example.com');

    $this->assertTrue($result);
}
```

## 🔌 Feature Testing

### Ví dụ 1: Test HTTP Endpoint

```php
<?php

namespace Tests\Feature;

use Toporia\Framework\Testing\TestCase;

class UserControllerTest extends TestCase
{
    public function test_create_user_endpoint(): void
    {
        $response = $this->post('/api/users', [
            'email' => 'john@example.com',
            'name' => 'John Doe',
        ]);

        $response->setStatus(201);
        $response->setContent(json_encode(['id' => 1, 'email' => 'john@example.com']));

        $this->assertStatus($response, 201);
        $this->assertJsonResponse($response, ['id' => 1]);
    }
}
```

### Ví dụ 2: Test với Events

```php
public function test_user_registration_fires_event(): void
{
    $this->fakeEvents();

    // Execute action that fires event
    $this->recordEvent('user.created', ['user_id' => 1]);

    // Assert
    $this->assertEventFired('user.created');
    $this->assertEventNotFired('user.deleted');
}
```

## 📡 Realtime Testing

### Ví dụ 1: Test Broker Publishing

```php
<?php

namespace Tests\Feature;

use Toporia\Framework\Testing\TestCase;

class RealtimeBrokerTest extends TestCase
{
    public function test_publish_message_to_broker(): void
    {
        // Fake broker để không gửi thật
        $this->fakeBroker();

        // Tạo mock broker
        $broker = $this->mockBroker();

        // Tạo message
        $message = $this->createRealtimeMessage(
            'user.1',           // channel
            'notification',     // event
            ['title' => 'New message', 'body' => 'Hello!']  // data
        );

        // Publish message
        $broker->publish('user.1', $message);

        // Assertions
        $this->assertMessagePublished('user.1', 'notification');
        $this->assertMessagePublished('user.1', 'notification', ['title' => 'New message']);
        $this->assertPublishedMessageCount(1, 'user.1');
    }

    public function test_multiple_channels(): void
    {
        $this->fakeBroker();
        $broker = $this->mockBroker();

        // Publish to multiple channels
        $broker->publish('channel.1', $this->createRealtimeMessage('channel.1', 'event', ['data' => 1]));
        $broker->publish('channel.2', $this->createRealtimeMessage('channel.2', 'event', ['data' => 2]));
        $broker->publish('channel.1', $this->createRealtimeMessage('channel.1', 'event', ['data' => 3]));

        // Assert counts
        $this->assertPublishedMessageCount(2, 'channel.1');
        $this->assertPublishedMessageCount(1, 'channel.2');
        $this->assertPublishedMessageCount(3); // Total
    }
}
```

### Ví dụ 2: Test Transport Broadcasting

```php
public function test_transport_broadcast(): void
{
    // Fake transport
    $this->fakeTransport();

    // Tạo mock transport
    $transport = $this->mockTransport();

    // Tạo message
    $message = $this->createRealtimeMessage(
        'user.1',
        'message',
        ['text' => 'Hello World']
    );

    // Broadcast to channel
    $transport->broadcastToChannel('user.1', $message);

    // Assertions
    $this->assertMessageBroadcasted('user.1', 'message');
    $this->assertMessageBroadcasted('user.1', 'message', ['text' => 'Hello World']);
    $this->assertBroadcastedMessageCount(1, 'user.1');
    $this->assertMessageNotBroadcasted('user.2');
}
```

### Ví dụ 3: Test RealtimeManager Integration

```php
public function test_realtime_manager_broadcast(): void
{
    $this->fakeRealtime(); // Fake cả broker và transport

    // Setup RealtimeManager với mock broker/transport
    $config = [
        'default_transport' => 'memory',
        'default_broker' => null, // No broker for single server
    ];

    $manager = new \Toporia\Framework\Realtime\RealtimeManager(
        $config,
        $this->getContainer()
    );

    // Inject mock broker nếu cần
    // $manager->setBroker($this->mockBroker());

    // Broadcast message
    $manager->broadcast('user.1', 'notification', [
        'title' => 'New message',
        'body' => 'You have a new message'
    ]);

    // Assertions sẽ phụ thuộc vào implementation
    // Có thể assert qua mock broker/transport
}
```

### Ví dụ 4: Test với Redis Broker

```php
public function test_redis_broker_integration(): void
{
    // Nếu muốn test với real Redis (integration test)
    // $this->withoutTransactions(); // Disable transaction rollback

    // Hoặc fake để test logic
    $this->fakeBroker();
    $broker = $this->mockBroker();

    // Test publish
    $message = $this->createRealtimeMessage('channel', 'event', ['data' => 'test']);
    $broker->publish('channel', $message);

    $this->assertMessagePublished('channel');
}
```

## 💾 Database Testing

### Ví dụ 1: Test Database Operations

```php
public function test_user_creation_in_database(): void
{
    // Setup database
    $db = $this->getDb();
    if ($db === null) {
        $this->markTestSkipped('Database not available');
        return;
    }

    // Create table
    $db->exec('CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL,
        name TEXT NOT NULL
    )');

    // Insert data
    $this->dbInsert('users', [
        'email' => 'john@example.com',
        'name' => 'John Doe'
    ]);

    // Assertions
    $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    $this->assertDatabaseCount('users', 1);

    // Get data
    $users = $this->dbGet('users', ['email' => 'john@example.com']);
    $this->assertCount(1, $users);
    $this->assertEquals('John Doe', $users[0]['name']);
}
```

### Ví dụ 2: Test với Transactions

```php
public function test_database_transaction_rollback(): void
{
    // Mặc định sử dụng transactions
    // Mỗi test sẽ tự động rollback sau khi chạy

    $db = $this->getDb();
    $db->exec('CREATE TABLE orders (id INTEGER, total REAL)');

    $this->dbInsert('orders', ['total' => 100.50]);
    $this->assertDatabaseCount('orders', 1);

    // Sau khi test, data sẽ tự động bị rollback
    // Test tiếp theo sẽ có database sạch
}
```

## ⚡ Performance Testing

### Ví dụ 1: Test Execution Time

```php
public function test_api_response_time(): void
{
    $this->assertExecutionTimeLessThan(
        function () {
            // Simulate API call
            usleep(5000); // 5ms
        },
        0.01 // 10ms max
    );
}

public function test_heavy_computation(): void
{
    $duration = $this->measureTime(function () {
        // Heavy computation
        $sum = 0;
        for ($i = 0; $i < 1000000; $i++) {
            $sum += $i;
        }
    });

    $this->assertLessThan(0.1, $duration, 'Computation should complete in < 100ms');
}
```

### Ví dụ 2: Test Memory Usage

```php
public function test_memory_efficient_processing(): void
{
    $this->assertMemoryUsageLessThan(
        function () {
            // Process large dataset
            $data = range(1, 10000);
            unset($data);
        },
        1024 * 1024 // 1MB max
    );
}
```

## 🔄 Integration Testing

### Ví dụ: Test Full Flow

```php
<?php

namespace Tests\Integration;

use Toporia\Framework\Testing\TestCase;

class UserRegistrationFlowTest extends TestCase
{
    public function test_complete_user_registration_flow(): void
    {
        // 1. Fake external services
        $this->fakeMail();
        $this->fakeQueue();
        $this->fakeEvents();

        // 2. Setup database
        $db = $this->getDb();
        $db->exec('CREATE TABLE users (id INTEGER, email TEXT, name TEXT)');

        // 3. Execute registration
        // ... your registration logic ...

        // 4. Assert database
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);

        // 5. Assert events
        $this->assertEventFired('user.created');

        // 6. Assert mail
        $this->assertMailSent('john@example.com');

        // 7. Assert queue
        $this->assertJobPushed('App\Jobs\SendWelcomeEmail');
    }
}
```

## 🎯 Best Practices

### 1. Sử dụng AAA Pattern

```php
public function test_example(): void
{
    // Arrange: Setup test data
    $user = new User('john@example.com');

    // Act: Execute code
    $result = $user->getName();

    // Assert: Verify result
    $this->assertEquals('john@example.com', $result);
}
```

### 2. Test Isolation

```php
// Mỗi test chạy độc lập
// Database tự động rollback
// Services được reset
public function test_one(): void { /* ... */ }
public function test_two(): void { /* ... */ } // Database sạch
```

### 3. Mock External Dependencies

```php
public function test_with_external_api(): void
{
    // Mock external API
    $api = $this->mock('external.api', function ($mock) {
        $mock->shouldReceive('call')
            ->andReturn(['status' => 'success']);
    });

    $service = new MyService($api);
    $result = $service->doSomething();

    $this->assertTrue($result);
}
```

### 4. Performance Testing

```php
public function test_performance_critical_path(): void
{
    // Test critical paths
    $this->assertExecutionTimeLessThan(
        fn() => $this->criticalOperation(),
        0.1 // 100ms max
    );
}
```

## 📚 Tham Khảo

- Xem `docs/TESTING.md` để biết đầy đủ API
- Xem `tests/` để xem thêm examples
- Xem `src/Framework/Testing/` để hiểu implementation

