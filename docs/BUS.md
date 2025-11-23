# Command/Query/Job Bus System

Professional Command Bus implementation with Modern framework API, featuring sync/async dispatch, middleware pipeline, and batch operations.

## Architecture

The Bus system follows **Clean Architecture** and **SOLID principles**:

- **Single Responsibility**: Dispatcher only handles dispatching logic
- **Open/Closed**: Extensible via middleware and handler mapping
- **Liskov Substitution**: All implementations follow interface contracts
- **Interface Segregation**: Focused interfaces (DispatcherInterface, QueueableInterface)
- **Dependency Inversion**: Depends on abstractions, not concrete implementations

## Performance

- **O(1) handler lookup** via array map
- **Lazy handler resolution** (only when dispatched)
- **Pipeline caching** for repeated middleware
- **Zero-copy** command passing
- **Atomic batch updates** in database

## Components

### Core Classes

- `DispatcherInterface` - Contract for command/query/job dispatching
- `Dispatcher` - Main dispatcher implementation
- `PendingDispatch` - Fluent API for dispatching
- `Batch` - Batch job container with progress tracking
- `PendingBatch` - Fluent API for batch creation
- `Bus` - Static facade for convenience

### Traits

- `Queueable` - Adds queueable functionality to commands/jobs

### Repositories

- `BatchRepositoryInterface` - Contract for batch storage
- `DatabaseBatchRepository` - Database-backed batch storage

## Basic Usage

### Simple Dispatch

```php
use App\Commands\SendWelcomeEmail;

// Fluent API (recommended)
dispatch(new SendWelcomeEmail($user));

// Synchronous dispatch
$result = dispatch_sync(new SendWelcomeEmail($user));

// Using Bus facade
use Toporia\Framework\Bus\Bus;

Bus::dispatch(new SendWelcomeEmail($user));
$result = Bus::dispatchSync(new SendWelcomeEmail($user));
```

### Creating Commands

**Simple Command:**

```php
<?php

namespace App\Commands;

final class SendWelcomeEmail
{
    public function __construct(
        public readonly string $email,
        public readonly string $name
    ) {}
}
```

**Handler (Convention):**

```php
<?php

namespace App\Commands;

use Toporia\Framework\Mail\MailerInterface;

final class SendWelcomeEmailHandler
{
    public function __construct(
        private MailerInterface $mailer
    ) {} // Dependencies auto-wired!

    public function __invoke(SendWelcomeEmail $command): void
    {
        $this->mailer->send(
            to: $command->email,
            subject: 'Welcome!',
            body: "Hello {$command->name}!"
        );
    }
}
```

**Convention:** `CommandName` => `CommandNameHandler` (automatic!)

### Queueable Commands

```php
<?php

namespace App\Commands;

use Toporia\Framework\Bus\Contracts\ShouldQueueInterface;
use Toporia\Framework\Bus\Queueable;

final class ProcessVideoCommand implements ShouldQueueInterface
{
    use Queueable;

    public function __construct(
        public readonly string $videoPath
    ) {}
}

// Dispatch to queue automatically
dispatch(new ProcessVideoCommand('/path/to/video.mp4'));

// Configure queue and delay
dispatch(new ProcessVideoCommand('/path/to/video.mp4'))
    ->onQueue('videos')
    ->delay(60); // 60 seconds delay
```

## Advanced Features

### Fluent Dispatch API

```php
use App\Commands\SendEmailCommand;

// Configure before dispatch
dispatch(new SendEmailCommand($email))
    ->onQueue('emails')
    ->delay(300); // 5 minutes

// Dispatch after response sent
dispatch(new SendEmailCommand($email))
    ->afterResponse();
```

### Explicit Handler Mapping

If you don't want to follow the convention, map handlers explicitly in `config/bus.php`:

```php
return [
    'mappings' => [
        SendEmailCommand::class => CustomEmailHandler::class,
        ProcessOrder::class => OrderProcessor::class,
    ],
];
```

### Middleware Pipeline

Add middleware to run before/after command handling in `config/bus.php`:

```php
return [
    'middleware' => [
        \App\Bus\Middleware\LogCommand::class,
        \App\Bus\Middleware\WrapInTransaction::class,
    ],
];
```

**Middleware Example:**

```php
<?php

namespace App\Bus\Middleware;

final class LogCommand
{
    public function handle(mixed $command, callable $next): mixed
    {
        $commandClass = get_class($command);
        error_log("[BUS] Dispatching: {$commandClass}");

        $start = microtime(true);
        $result = $next($command);
        $duration = round((microtime(true) - $start) * 1000, 2);

        error_log("[BUS] Completed: {$commandClass} ({$duration}ms)");

        return $result;
    }
}
```

### Chain Operations

Execute jobs sequentially (one after another):

```php
use App\Jobs\ProcessVideo;
use Toporia\Framework\Bus\Bus;

// Create chain (jobs execute sequentially)
$result = Bus::chain([
    new DownloadVideoCommand('video1.mp4'),
    new TranscodeVideoCommand('video1.mp4'),
    new GenerateThumbnailCommand('video1.mp4'),
])
->onQueue('videos') // Optional: set queue for all jobs
->delay(60) // Optional: set delay for all jobs
->catch(function ($exception, $jobIndex, $job) {
    // Handle failure
    error_log("Job {$jobIndex} failed: " . $exception->getMessage());
})
->finally(function ($success, $exception) {
    // Cleanup or logging
    log_info("Chain completed: " . ($success ? 'success' : 'failed'));
})
->dispatch();

// Result is the return value from the last job
```

**Performance:**
- O(1) creation (lazy execution)
- O(N) sequential execution (N = number of jobs)
- Early termination on failure (stops immediately)
- Zero-copy job passing

**Key Differences from Batch:**
- **Chain**: Jobs execute **sequentially** (one after another)
- **Batch**: Jobs execute **in parallel** (simultaneously)
- **Chain**: Stops on first failure (unless catch callback handles it)
- **Batch**: Continues processing other jobs even if one fails (if `allowFailures()` is set)

### Batch Operations

Process multiple jobs and track progress:

```php
use App\Jobs\ProcessVideo;
use Toporia\Framework\Bus\Bus;

// Create batch
$batch = Bus::batch([
    new ProcessVideo('video1.mp4'),
    new ProcessVideo('video2.mp4'),
    new ProcessVideo('video3.mp4'),
])
->name('Video Processing Batch')
->then(function ($batch) {
    // All jobs completed successfully
    notify("All videos processed!");
})
->catch(function ($batch, $e) {
    // At least one job failed
    error_log("Batch failed: " . $e->getMessage());
})
->finally(function ($batch) {
    // Batch completed (success or failure)
    log_info("Batch {$batch->id()} finished");
})
->allowFailures() // Don't stop on failures
->dispatch();

// Track progress
echo "Progress: {$batch->progress()}%\n";
echo "Processed: {$batch->processedJobs()} / {$batch->totalJobs()}\n";
echo "Failed: {$batch->failedJobs()}\n";

// Check status
if ($batch->finished()) {
    echo "Batch completed!\n";
}

if ($batch->hasFailures()) {
    echo "Some jobs failed\n";
}

// Cancel batch
$batch->cancel();
```

**Find Batch Later:**

```php
$batch = Bus::findBatch('batch_12345');
if ($batch) {
    echo "Progress: {$batch->progress()}%\n";
}
```

## Real-World Examples

### Example 1: User Registration

```php
// Command
final class RegisterUserCommand
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $name
    ) {}
}

// Handler
final class RegisterUserCommandHandler
{
    public function __construct(
        private UserRepository $users,
        private EventDispatcherInterface $events
    ) {}

    public function __invoke(RegisterUserCommand $cmd): User
    {
        $user = new User(
            email: $cmd->email,
            password: hash_make($cmd->password),
            name: $cmd->name
        );

        $this->users->create($user);
        $this->events->dispatch(new UserRegistered($user));

        return $user;
    }
}

// Usage in controller
public function register(RegisterRequest $request)
{
    $user = dispatch_sync(new RegisterUserCommand(
        $request->input('email'),
        $request->input('password'),
        $request->input('name')
    ));

    // Send welcome email asynchronously
    dispatch(new SendWelcomeEmail($user->email, $user->name));

    return response()->json($user, 201);
}
```

### Example 2: Bulk Data Import

```php
// Job
final class ImportProductJob implements ShouldQueueInterface
{
    use Queueable;

    public function __construct(
        public readonly array $productData,
        public readonly ?string $batchId = null
    ) {}

    public function setBatchId(string $batchId): void
    {
        $this->batchId = $batchId;
    }
}

// Handler
final class ImportProductJobHandler
{
    public function __construct(
        private ProductRepository $products,
        private BatchRepositoryInterface $batches
    ) {}

    public function __invoke(ImportProductJob $job): void
    {
        try {
            $product = Product::create($job->productData);
            $this->products->save($product);

            // Update batch progress
            if ($job->batchId) {
                $batch = $this->batches->find($job->batchId);
                $batch?->incrementCounts(processed: 1, failed: 0);
            }
        } catch (\Throwable $e) {
            if ($job->batchId) {
                $batch = $this->batches->find($job->batchId);
                $batch?->incrementCounts(processed: 1, failed: 1);
            }
            throw $e;
        }
    }
}

// Usage
$products = /* load from CSV */;

$jobs = array_map(
    fn($data) => new ImportProductJob($data),
    $products
);

$batch = batch($jobs)
    ->name('Product Import')
    ->then(fn($batch) => notify("Imported {$batch->totalJobs()} products"))
    ->allowFailures()
    ->dispatch();

echo "Batch ID: {$batch->id()}\n";
```

### Example 3: Video Processing Pipeline

**Using Chain (Recommended):**

```php
// Commands
final class DownloadVideoCommand
{
    public function __construct(public readonly string $url) {}
}

final class TranscodeVideoCommand
{
    public function __construct(public readonly string $path) {}
}

final class GenerateThumbnailCommand
{
    public function __construct(public readonly string $path) {}
}

// Usage with chain (sequential execution)
public function processVideo(string $url): void
{
    $result = chain([
        new DownloadVideoCommand($url),
        new TranscodeVideoCommand($url), // Will receive result from previous job
        new GenerateThumbnailCommand($url),
    ])
    ->catch(function ($exception, $index, $job) {
        error_log("Video processing failed at step {$index}");
    })
    ->dispatch();
}
```

**Alternative: Manual Chaining in Handler**

```php
// Handler with manual chaining
final class DownloadVideoCommandHandler
{
    public function __invoke(DownloadVideoCommand $cmd): string
    {
        $path = $this->download($cmd->url);

        // Chain next commands manually
        dispatch(new TranscodeVideoCommand($path));
        dispatch(new GenerateThumbnailCommand($path));

        return $path; // Return value can be used by next job in chain
    }
}
```

## Configuration

### config/bus.php

```php
return [
    // Explicit command => handler mappings
    'mappings' => [
        SendEmailCommand::class => CustomEmailHandler::class,
    ],

    // Global middleware
    'middleware' => [
        \App\Bus\Middleware\LogCommand::class,
        \App\Bus\Middleware\WrapInTransaction::class,
    ],

    // Batch settings
    'batch' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'job_batches',
    ],
];
```

## Database Schema

Run migration to create `job_batches` table:

```bash
php console migrate
```

**Table Schema:**

```sql
CREATE TABLE job_batches (
    id VARCHAR(255) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    total_jobs INT UNSIGNED NOT NULL,
    processed_jobs INT UNSIGNED DEFAULT 0,
    failed_jobs INT UNSIGNED DEFAULT 0,
    options TEXT,
    created_at INT UNSIGNED NOT NULL,
    finished_at INT UNSIGNED,
    cancelled_at INT UNSIGNED,
    INDEX idx_created_at (created_at),
    INDEX idx_finished_cancelled (finished_at, cancelled_at)
);
```

## API Reference

### Bus Facade

```php
// Dispatch
Bus::dispatch($command): mixed
Bus::dispatchSync($command): mixed
Bus::dispatchAfterResponse($command): PendingDispatch

// Batches (parallel execution)
Bus::batch(array $jobs): PendingBatch
Bus::findBatch(string $id): ?Batch

// Chains (sequential execution)
Bus::chain(array $jobs): PendingChain

// Configuration
Bus::map(array $mappings): void
Bus::pipeThrough(array $middleware): void
```

### PendingDispatch

```php
dispatch($command)
    ->onQueue(string $queue): self
    ->delay(int $seconds): self
    ->afterResponse(): self
```

### PendingBatch

```php
batch($jobs)
    ->name(string $name): self
    ->then(callable $callback): self
    ->catch(callable $callback): self
    ->finally(callable $callback): self
    ->allowFailures(bool $allow = true): self
    ->dispatch(): Batch
```

### PendingChain

```php
chain($jobs)
    ->onQueue(string $queue): self
    ->delay(int $seconds): self
    ->catch(callable $callback): self  // Receives (Throwable $exception, int $jobIndex, mixed $job)
    ->finally(callable $callback): self  // Receives (bool $success, ?Throwable $exception)
    ->dispatch(): mixed  // Returns result from last job, or null if failed
```

### Batch

```php
$batch->id(): string
$batch->name(): string
$batch->totalJobs(): int
$batch->processedJobs(): int
$batch->failedJobs(): int
$batch->pendingJobs(): int
$batch->progress(): int  // 0-100
$batch->finished(): bool
$batch->cancelled(): bool
$batch->hasFailures(): bool
$batch->cancel(): void
$batch->toArray(): array
```

## Performance Optimization

1. **Use conventions** - Avoid explicit mappings when possible (faster lookup)
2. **Minimize middleware** - Each middleware adds overhead
3. **Batch operations** - Group jobs for progress tracking
4. **Queue heavy tasks** - Implement `ShouldQueueInterface` for long-running jobs
5. **Database indexes** - Ensure `job_batches` table has proper indexes

## Testing

```php
use Toporia\Framework\Bus\Dispatcher;
use Toporia\Framework\Container\Container;

class BusTest extends TestCase
{
    public function test_dispatch_command(): void
    {
        $container = new Container();
        $dispatcher = new Dispatcher($container);

        $result = $dispatcher->dispatchSync(new TestCommand('data'));

        $this->assertEquals('expected', $result);
    }
}
```

## Comparison with Laravel

| Feature | This Implementation | Laravel |
|---------|-------------------|---------|
| Dispatch API | ✅ Identical | ✅ |
| Queueable | ✅ Identical | ✅ |
| Batch Operations | ✅ Identical | ✅ |
| Middleware Pipeline | ✅ Identical | ✅ |
| Handler Auto-Discovery | ✅ Convention-based | ✅ |
| Performance | **O(1) lookup** | O(N) reflection |
| Memory Usage | **Lighter** | Heavier |
| Dependencies | Minimal | Many |

## License

Part of the Toporia Framework. See LICENSE for details.
