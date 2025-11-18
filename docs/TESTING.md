# Testing Guide

Professional testing infrastructure for Toporia Framework with Clean Architecture, SOLID principles, and performance optimization.

## Architecture

The testing system follows **Clean Architecture** and **SOLID principles**:

- **Single Responsibility**: Each trait handles one concern (database, HTTP, events, etc.)
- **Open/Closed**: Extensible via traits and inheritance
- **Liskov Substitution**: All test cases extend TestCase
- **Interface Segregation**: Focused traits for specific concerns
- **Dependency Inversion**: Depends on abstractions

## Performance

- **O(1) Setup**: Lazy initialization of services
- **Fast Cleanup**: Efficient tearDown operations
- **Transaction Rollback**: Fast database cleanup
- **Memory Efficient**: Minimal memory footprint
- **Cached Mocks**: Reusable mock instances

## Quick Start

### Installation

```bash
composer install --dev
```

### Running Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test suite
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Feature
vendor/bin/phpunit --testsuite Performance

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/
```

## Test Structure

```
tests/
├── Unit/              # Unit tests (fast, isolated)
├── Feature/           # Feature tests (integration)
├── Integration/       # Integration tests
├── Performance/       # Performance tests
└── bootstrap.php      # Test bootstrap
```

## Base TestCase

All tests extend `Toporia\Framework\Testing\TestCase`:

```php
<?php

namespace Tests\Unit;

use Toporia\Framework\Testing\TestCase;

class MyTest extends TestCase
{
    public function test_something(): void
    {
        // Your test code
    }
}
```

## Available Traits

The TestCase includes multiple traits for different concerns:

### InteractsWithContainer

Container and dependency injection testing:

```php
// Bind a service
$this->bind('service', fn() => new MyService());

// Bind singleton
$this->singleton('service', fn() => new MyService());

// Mock a service
$mock = $this->mock('service', function ($mock) {
    $mock->shouldReceive('method')->andReturn('value');
});

// Resolve service
$service = $this->make('service');
```

### InteractsWithDatabase

Database testing with transactions:

```php
// Create table
$this->getDb()->exec('CREATE TABLE users (id INTEGER, name TEXT)');

// Insert data
$this->dbInsert('users', ['name' => 'John']);

// Assert database state
$this->assertDatabaseHas('users', ['name' => 'John']);
$this->assertDatabaseMissing('users', ['name' => 'Jane']);
$this->assertDatabaseCount('users', 1);

// Get data
$users = $this->dbGet('users', ['name' => 'John']);
```

**Performance**: Uses in-memory SQLite with transaction rollback for fast cleanup.

### InteractsWithHttp

HTTP request/response testing:

```php
// Make requests
$response = $this->get('/api/users');
$response = $this->post('/api/users', ['name' => 'John']);
$response = $this->put('/api/users/1', ['name' => 'Jane']);
$response = $this->delete('/api/users/1');

// Assert responses
$this->assertStatus($response, 200);
$this->assertSuccessful($response);
$this->assertJson($response, ['id' => 1]);
```

### InteractsWithTime

Time manipulation for testing:

```php
// Set fake time
$this->setFakeTime(1609459200); // 2021-01-01

// Travel forward
$this->travel(86400); // 1 day forward

// Get current time
$now = $this->now();

// Reset to real time
$this->resetTime();
```

### InteractsWithEvents

Event testing:

```php
// Fake events
$this->fakeEvents();

// Record event (integrate with your event system)
$this->recordEvent('user.created', ['user_id' => 1]);

// Assert events
$this->assertEventFired('user.created');
$this->assertEventNotFired('user.deleted');
```

### InteractsWithQueue

Queue testing:

```php
// Fake queue
$this->fakeQueue();

// Record job (integrate with your queue system)
$this->recordJob('App\Jobs\ProcessOrder', ['order_id' => 123]);

// Assert jobs
$this->assertJobPushed('App\Jobs\ProcessOrder');
$this->assertJobNotPushed('App\Jobs\SendEmail');
```

### InteractsWithCache

Cache testing:

```php
// Clear cache
$this->clearCache();

// Assert cache
$this->assertCacheHas('key');
$this->assertCacheMissing('key');
$this->assertCacheEquals('value', 'key');
```

### InteractsWithFiles

File system testing:

```php
// Create temporary file
$file = $this->createTempFile('content', 'txt');

// Assert files
$this->assertFileExists($file);
$this->assertFileNotExists('/non/existent');
$this->assertFileContent('content', $file);
```

### InteractsWithMail

Mail testing:

```php
// Fake mail
$this->fakeMail();

// Record mail (integrate with your mail system)
$this->recordMail('user@example.com', 'Subject', 'Body');

// Assert mail
$this->assertMailSent('user@example.com');
$this->assertMailNotSent('admin@example.com');
```

### InteractsWithBus

Command/Query bus testing:

```php
// Fake bus
$this->fakeBus();

// Record command (integrate with your bus system)
$this->recordCommand('App\Commands\CreateUser', ['name' => 'John']);

// Assert commands
$this->assertCommandDispatched('App\Commands\CreateUser');
$this->assertCommandNotDispatched('App\Commands\DeleteUser');
```

### InteractsWithRealtime

Realtime broker and transport testing:

```php
// Fake broker and transport
$this->fakeRealtime();
// Or separately:
$this->fakeBroker();
$this->fakeTransport();

// Create mock broker/transport
$broker = $this->mockBroker();
$transport = $this->mockTransport();

// Publish message to broker
$message = $this->createRealtimeMessage('user.1', 'message', ['text' => 'Hello']);
$broker->publish('user.1', $message);

// Assert broker messages
$this->assertMessagePublished('user.1', 'message', ['text' => 'Hello']);
$this->assertMessageNotPublished('user.2');
$this->assertPublishedMessageCount(1, 'user.1');

// Broadcast message via transport
$transport->broadcastToChannel('user.1', $message);

// Assert transport messages
$this->assertMessageBroadcasted('user.1', 'message', ['text' => 'Hello']);
$this->assertMessageNotBroadcasted('user.2');
$this->assertBroadcastedMessageCount(1, 'user.1');
```

### PerformanceAssertions

Performance testing:

```php
// Assert execution time
$this->assertExecutionTimeLessThan(
    fn() => doSomething(),
    0.1 // 100ms max
);

// Assert memory usage
$this->assertMemoryUsageLessThan(
    fn() => doSomething(),
    1024 * 1024 // 1MB max
);

// Measure performance
$duration = $this->measureTime(fn() => doSomething());
$memory = $this->measureMemory(fn() => doSomething());
```

## Factories

Create test data with factories:

```php
use Toporia\Framework\Testing\Factories\Factory;

class UserFactory extends Factory
{
    protected string $model = User::class;

    protected function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->email(),
        ];
    }
}

// Usage
$user = UserFactory::new()->make();
$users = UserFactory::new()->makeMany(10);
$admin = UserFactory::new()->state(['role' => 'admin'])->make();
```

## Builders

Fluent builders for test scenarios:

```php
use Toporia\Framework\Testing\Builders\TestBuilder;

$data = TestBuilder::new()
    ->with(['name' => 'John'])
    ->withAttribute('email', 'john@example.com')
    ->withRelationship('posts', [])
    ->afterCreating(fn($data) => $data + ['created_at' => time()])
    ->build();
```

## Best Practices

### 1. Use Appropriate Test Types

- **Unit Tests**: Fast, isolated, test single units
- **Feature Tests**: Integration tests for features
- **Performance Tests**: Measure and assert performance

### 2. Keep Tests Fast

- Use in-memory SQLite for database tests
- Use transactions for fast cleanup
- Mock external services
- Avoid real network calls

### 3. Follow AAA Pattern

```php
public function test_user_creation(): void
{
    // Arrange
    $userData = ['name' => 'John', 'email' => 'john@example.com'];

    // Act
    $user = $this->createUser($userData);

    // Assert
    $this->assertDatabaseHas('users', $userData);
}
```

### 4. Use Descriptive Test Names

```php
// Good
public function test_user_cannot_login_with_invalid_credentials(): void

// Bad
public function test_login(): void
```

### 5. Test Edge Cases

- Empty inputs
- Null values
- Boundary conditions
- Error conditions

### 6. Performance Testing

```php
public function test_api_response_time(): void
{
    $this->assertExecutionTimeLessThan(
        fn() => $this->get('/api/users'),
        0.5 // 500ms max
    );
}
```

## Example Tests

See `tests/Unit/ExampleTest.php`, `tests/Feature/ExampleFeatureTest.php`, and `tests/Performance/ExamplePerformanceTest.php` for complete examples.

## Configuration

### phpunit.xml

The `phpunit.xml` file configures:
- Test suites (Unit, Feature, Integration, Performance)
- Bootstrap file
- Environment variables
- Coverage settings

### Environment Variables

Tests use separate environment:
- `APP_ENV=testing`
- `DB_CONNECTION=sqlite`
- `DB_DATABASE=:memory:`
- `CACHE_DRIVER=array`
- `QUEUE_CONNECTION=sync`

## Troubleshooting

### Tests are slow

- Check for real database connections
- Ensure transactions are being used
- Check for real network calls
- Review mock usage

### Memory issues

- Use `tearDown()` properly
- Clear large data structures
- Use transactions for database cleanup

### Flaky tests

- Avoid time-dependent tests (use `InteractsWithTime`)
- Use transactions for database isolation
- Mock external services

## License

Part of the Toporia Framework. See LICENSE for details.

