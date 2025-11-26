# Queue System - API Reference

## 📚 Mục lục

1. [Job Class](#job-class)
2. [PendingDispatch](#pendingdispatch)
3. [Worker](#worker)
4. [Queue Drivers](#queue-drivers)
5. [Middleware](#middleware)
6. [Support Classes](#support-classes)

---

## Job Class

### Properties

```php
protected string $id;                    // Job ID
protected string $queue = 'default';     // Queue name
protected int $attempts = 0;             // Current attempt number
protected int $delay = 0;                // Delay in seconds
protected int $maxAttempts = 3;          // Max retry attempts
protected ?int $retryAfter = null;       // Simple retry delay
protected ?BackoffStrategy $backoff = null; // Backoff strategy
protected array $middleware = [];        // Job middleware
protected int $timeout = 0;              // Timeout in seconds
protected int $priority = 0;             // Job priority
protected array $tags = [];             // Job tags
protected ?string $uniqueId = null;     // Unique job ID
protected int $uniqueFor = 3600;        // Unique lock expiration
protected bool $trackProgress = false;  // Enable progress tracking
```

### Methods

#### Static Dispatch Methods

```php
// Dispatch job
public static function dispatch(...$args): PendingDispatch

// Dispatch synchronously
public static function dispatchSync(...$args): mixed

// Dispatch after delay
public static function dispatchAfter(int $delay, ...$args): PendingDispatch
```

#### Instance Methods

```php
// Get job ID
public function getId(): string

// Get queue name
public function getQueue(): ?string

// Get attempts
public function attempts(): int

// Get max attempts
public function getMaxAttempts(): int

// Increment attempts
public function incrementAttempts(): void

// Get timeout
public function getTimeout(): int

// Set timeout
public function timeout(int $seconds): self

// Get priority
public function getPriority(): int

// Set priority
public function priority(int $priority): self

// Get tags
public function getTags(): array

// Add tags
public function tag(string|array $tags): self

// Get unique ID
public function getUniqueId(): ?string

// Get unique expiration
public function getUniqueFor(): int

// Make unique
public function unique(string $uniqueId, int $for = 3600): self

// Get backoff delay
public function getBackoffDelay(): int

// Set backoff strategy
public function backoff(BackoffStrategy $strategy): self

// Get middleware
public function middleware(): array

// Add middleware
public function middleware(JobMiddleware ...$middleware): self

// Enable progress tracking
public function trackProgress(): self

// Check if tracking progress
public function shouldTrackProgress(): bool

// Report progress
public function reportProgress(int $progress, ?string $message = null): void

// Handle job failure
public function failed(\Throwable $exception): void

// Handle job timeout
public function timeout(): void
```

---

## PendingDispatch

### Methods

```php
// Set queue
public function onQueue(string $queue): self

// Set delay
public function delay(int $seconds): self

// Set priority
public function priority(int $priority): self

// Add tags
public function tag(string|array $tags): self

// Make unique
public function unique(string $uniqueId, int $for = 3600): self

// Dispatch synchronously
public function dispatchSync(): mixed

// Dispatch explicitly
public function dispatch(): mixed
```

### Example

```php
SendEmailJob::dispatch($to, $subject, $message)
    ->onQueue('emails')
    ->delay(60)
    ->priority(10)
    ->tag(['email', 'urgent'])
    ->unique("email-{$to}");
```

---

## Worker

### Constructor

```php
public function __construct(
    QueueInterface $queue,
    ?ContainerInterface $container = null,
    int $maxJobs = 0,
    int $sleep = 1,
    ?string $timezone = null,
    int $memoryLimit = 128,
    int $maxRuntime = 0
)
```

### Methods

```php
// Start processing jobs
public function work(string|array $queues = 'default'): void

// Stop worker gracefully
public function stop(): void

// Check if should quit
public function shouldQuit(): bool
```

---

## Queue Drivers

### QueueInterface

```php
// Push job to queue
public function push(JobInterface $job, string $queue = 'default'): string

// Push job with delay
public function later(JobInterface $job, int $delay, string $queue = 'default'): string

// Pop job from queue
public function pop(string $queue = 'default'): ?JobInterface

// Get queue size
public function size(string $queue = 'default'): int

// Clear queue
public function clear(string $queue = 'default'): void

// Store failed job
public function failed(JobInterface $job, \Throwable $exception): void
```

### DatabaseQueue

```php
public function __construct(Connection $connection)

// Get failed jobs
public function getFailedJobs(int $limit = 100): array

// Store failed job
public function storeFailed(JobInterface $job, \Throwable $exception): void
```

### RedisQueue

```php
public function __construct(
    array $config,
    ?ContainerInterface $container = null
)
```

### RabbitMQQueue

```php
public function __construct(
    array $config,
    ?ContainerInterface $container = null
)
```

### SyncQueue

```php
public function __construct(ContainerInterface $container)
```

---

## Middleware

### JobMiddleware Interface

```php
interface JobMiddleware
{
    public function handle(JobInterface $job, callable $next): mixed;
}
```

### RateLimited

```php
public function __construct(
    RateLimiterInterface $limiter,
    int $maxAttempts = 60,
    int $decayMinutes = 1,
    string|callable|null $key = null
)

// Set custom key
public function by(string|callable $key): self
```

### WithoutOverlapping

```php
public function __construct(
    CacheInterface $cache,
    string $key,
    int $releaseAfter = 300
)
```

### EnsureUnique

```php
public function __construct(CacheInterface $cache)
```

### Throttle

```php
public function __construct(
    CacheInterface $cache,
    int $maxJobs = 10,
    int $decaySeconds = 60,
    ?string $key = null
)

// Set custom key
public function by(string $key): self
```

---

## Support Classes

### JobProgress

```php
public function __construct(CacheInterface $cache)

// Set progress
public function set(string $jobId, int $progress, ?string $message = null): void

// Get progress
public function get(string $jobId): ?array

// Increment progress
public function increment(string $jobId, int $by = 1, ?string $message = null): int

// Clear progress
public function clear(string $jobId): void

// Check if has progress
public function has(string $jobId): bool
```

### JobCancellation

```php
public function __construct(CacheInterface $cache)

// Cancel job
public function cancel(string $jobId): void

// Check if cancelled
public function isCancelled(string $jobId): bool

// Get cancelled timestamp
public function getCancelledAt(string $jobId): ?int

// Remove cancellation
public function remove(string $jobId): void
```

### JobMetrics

```php
public function __construct(CacheInterface $cache)

// Record metrics
public function record(string $jobClass, bool $success, float $duration, int $memory): void

// Get metrics
public function get(string $jobClass): array

// Clear metrics
public function clear(string $jobClass): void
```

### QueueMetrics

```php
public function __construct(CacheInterface $cache)

// Record operation
public function record(string $queueName, string $operation, float $duration = 0.0): void

// Get metrics
public function get(string $queueName): array

// Clear metrics
public function clear(string $queueName): void
```

---

## Backoff Strategies

### BackoffStrategy Interface

```php
interface BackoffStrategy
{
    public function calculate(int $attempts): int;
}
```

### ExponentialBackoff

```php
public function __construct(int $base = 2, int $max = 300)
```

### ConstantBackoff

```php
public function __construct(int $delay = 10)
```

### CustomBackoff

```php
public function __construct(array|callable $delays)
```

---

## Events

### JobQueued

```php
public function __construct(
    JobInterface $job,
    string $queue,
    int $delay = 0
)

public function getJob(): JobInterface
public function getQueue(): string
public function getDelay(): int
```

### JobProcessing

```php
public function __construct(JobInterface $job, int $attempt)
```

### JobProcessed

```php
public function __construct(JobInterface $job, int $attempt)
```

### JobFailed

```php
public function __construct(
    JobInterface $job,
    \Throwable $exception,
    int $attempt,
    bool $willRetry
)
```

### JobTimedOut

```php
public function __construct(JobInterface $job, int $timeout, int $attempt)
```

### JobRetrying

```php
public function __construct(
    JobInterface $job,
    int $attempt,
    int $delay,
    \Throwable $exception
)
```

### WorkerStopping

```php
public function __construct(int $processed)
```

---

## Exceptions

### RateLimitExceededException

```php
public function __construct(string $message, int $retryAfter)
public function getRetryAfter(): int
```

### JobAlreadyRunningException

```php
public function __construct(string $message)
```

### JobTimeoutException

```php
public function __construct(string $jobId, int $timeout)
```



