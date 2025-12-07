# Queue System - Complete Guide

## 📚 Mục lục

1. [Giới thiệu](#giới-thiệu)
2. [Cài đặt & Cấu hình](#cài-đặt--cấu-hình)
3. [Tạo Jobs](#tạo-jobs)
4. [Dispatching Jobs](#dispatching-jobs)
5. [Queue Workers](#queue-workers)
6. [Job Features](#job-features)
7. [Middleware](#middleware)
8. [Failed Jobs](#failed-jobs)
9. [Monitoring & Metrics](#monitoring--metrics)
10. [Best Practices](#best-practices)

---

## 🎯 Giới thiệu

Toporia Queue System là một hệ thống queue mạnh mẽ, được thiết kế theo Clean Architecture và SOLID principles. Hệ thống hỗ trợ nhiều queue drivers, job features phong phú, và performance tối ưu.

### Tính năng chính

- ✅ **Multiple Queue Drivers**: Database, Redis, RabbitMQ, Sync
- ✅ **Job Priorities**: Ưu tiên xử lý jobs
- ✅ **Job Tags**: Gắn tag để filter và monitor
- ✅ **Unique Jobs**: Ngăn chặn duplicate jobs
- ✅ **Job Progress Tracking**: Theo dõi tiến độ (0-100%)
- ✅ **Job Cancellation**: Hủy jobs đang chờ/chạy
- ✅ **Job Metrics**: Track performance metrics
- ✅ **Queue Metrics**: Track queue throughput/latency
- ✅ **Retry & Backoff**: Exponential, Constant, Custom strategies
- ✅ **Middleware**: RateLimited, WithoutOverlapping, EnsureUnique, Throttle
- ✅ **Events**: Job lifecycle events
- ✅ **Clean Architecture**: Strict layer separation

---

## ⚙️ Cài đặt & Cấu hình

### 1. Cấu hình Queue

File: `config/queue.php`

```php
return [
    'default' => env('QUEUE_DRIVER', 'database'),

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => 'default',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
        ],

        'redis' => [
            'driver' => 'redis',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => env('REDIS_DB', 0),
            'prefix' => 'queues',
        ],

        'rabbitmq' => [
            'driver' => 'rabbitmq',
            'host' => env('RABBITMQ_HOST', '127.0.0.1'),
            'port' => env('RABBITMQ_PORT', 5672),
            'user' => env('RABBITMQ_USER', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'vhost' => env('RABBITMQ_VHOST', '/'),
            'exchange' => 'toporia',
            'queue' => 'default',
        ],
    ],
];
```

### 2. Tạo Database Table (cho Database Queue)

```sql
CREATE TABLE jobs (
    id VARCHAR(255) PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    available_at INT NOT NULL,
    created_at INT NOT NULL,
    priority INT NOT NULL DEFAULT 0,
    INDEX idx_queue_available_priority (queue, available_at, priority)
);

CREATE TABLE failed_jobs (
    id VARCHAR(255) PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    exception TEXT NOT NULL,
    failed_at INT NOT NULL
);
```

---

## 📝 Tạo Jobs

### 1. Tạo Job Class

```bash
php console make:job SendEmailJob
```

### 2. Job Structure

```php
<?php

namespace App\Infrastructure\Jobs;

use Toporia\Framework\Queue\Job;
use Toporia\Framework\Mail\Contracts\MailerInterface;

class SendEmailJob extends Job
{
    public function __construct(
        private string $to,
        private string $subject,
        private string $message
    ) {
        parent::__construct();
    }

    /**
     * Execute the job.
     * Dependencies are automatically injected by Worker.
     */
    public function handle(MailerInterface $mailer): void
    {
        $mailer->send($this->to, $this->subject, $this->message);
    }
}
```

### 3. Job với Progress Tracking

```php
class ProcessLargeFileJob extends Job
{
    protected bool $trackProgress = true;

    public function handle(): void
    {
        $items = $this->getItems();
        $total = count($items);

        foreach ($items as $index => $item) {
            $this->processItem($item);

            // Report progress
            $progress = (int)(($index + 1) / $total * 100);
            $this->reportProgress($progress, "Processing item {$index}/{$total}");
        }
    }
}
```

---

## 🚀 Dispatching Jobs

### 1. Basic Dispatch

```php
// Simple dispatch
SendEmailJob::dispatch($to, $subject, $message);

// Auto-dispatches when PendingDispatch is destroyed
dispatch(new SendEmailJob($to, $subject, $message));
```

### 2. Fluent API

```php
SendEmailJob::dispatch($to, $subject, $message)
    ->onQueue('emails')           // Specify queue
    ->delay(60)                    // Delay 60 seconds
    ->priority(10)                 // High priority
    ->tag(['email', 'urgent'])     // Add tags
    ->unique("email-{$to}");       // Prevent duplicates
```

### 3. Dispatch After Delay

```php
// Dispatch after 5 minutes
SendEmailJob::dispatchAfter(300, $to, $subject, $message);
```

### 4. Synchronous Dispatch

```php
// Execute immediately (no queue)
$result = SendEmailJob::dispatchSync($to, $subject, $message);
```

### 5. Conditional Dispatch

```php
if ($shouldQueue) {
    SendEmailJob::dispatch($to, $subject, $message);
} else {
    SendEmailJob::dispatchSync($to, $subject, $message);
}
```

---

## 👷 Queue Workers

### 1. Start Worker

```bash
# Process jobs from default queue
php console queue:work

# Process specific queue
php console queue:work --queue=emails

# Process multiple queues (priority order)
php console queue:work --queue=high,medium,low

# With options
php console queue:work \
    --queue=emails \
    --max-jobs=1000 \
    --memory=256 \
    --timeout=3600 \
    --sleep=1
```

### 2. Worker Options

| Option | Description | Default |
|--------|-------------|---------|
| `--queue` | Queue name(s) | `default` |
| `--max-jobs` | Max jobs before restart | `0` (unlimited) |
| `--memory` | Memory limit (MB) | `128` |
| `--timeout` | Max runtime (seconds) | `0` (unlimited) |
| `--sleep` | Sleep between iterations | `1` |

### 3. Worker Features

- ✅ **Graceful Shutdown**: Waits for current job to finish
- ✅ **Auto-restart**: Restarts on memory limit
- ✅ **Signal Handling**: Responds to SIGTERM, SIGINT
- ✅ **Multi-queue Support**: Processes multiple queues with priority

---

## 🎨 Job Features

### 1. Job Priorities

```php
class HighPriorityJob extends Job
{
    protected int $priority = 10; // Higher = processed first
}

// Or via fluent API
SendEmailJob::dispatch($to, $subject, $message)
    ->priority(10);
```

### 2. Job Tags

```php
SendEmailJob::dispatch($to, $subject, $message)
    ->tag(['email', 'notification', 'urgent']);

// In job class
class SendEmailJob extends Job
{
    protected array $tags = ['email'];
}
```

### 3. Unique Jobs

```php
// Prevent duplicate jobs
SendEmailJob::dispatch($to, $subject, $message)
    ->unique("email-{$to}", 3600); // Unique for 1 hour

// In job class
class SendEmailJob extends Job
{
    protected ?string $uniqueId = null;
    protected int $uniqueFor = 3600;
}
```

### 4. Job Progress Tracking

```php
class ProcessDataJob extends Job
{
    protected bool $trackProgress = true;

    public function handle(): void
    {
        $items = $this->getItems();
        $total = count($items);

        foreach ($items as $index => $item) {
            $this->process($item);

            $progress = (int)(($index + 1) / $total * 100);
            $this->reportProgress($progress, "Processed {$index}/{$total}");
        }
    }
}

// Get progress
$progress = app('cache')->get("job_progress:{$jobId}");
// Returns: ['progress' => 50, 'message' => '...', 'updated_at' => ...]
```

### 5. Job Cancellation

```php
use Toporia\Framework\Queue\Support\JobCancellation;

$cancellation = new JobCancellation(app('cache'));

// Cancel a job
$cancellation->cancel($jobId);

// Check if cancelled
if ($cancellation->isCancelled($jobId)) {
    // Job is cancelled
}
```

### 6. Retry & Backoff

```php
class SendEmailJob extends Job
{
    protected int $maxAttempts = 5;
    protected ?int $retryAfter = 60; // Simple constant delay

    // Or use backoff strategy
    public function __construct()
    {
        parent::__construct();
        $this->backoff(new \Toporia\Framework\Queue\Backoff\ExponentialBackoff(
            base: 2,
            max: 300
        ));
    }
}
```

### 7. Job Timeout

```php
class LongRunningJob extends Job
{
    protected int $timeout = 300; // 5 minutes
}
```

### 8. Job Memory Limit

```php
class MemoryIntensiveJob extends Job
{
    protected int $memoryLimit = 512; // 512 MB
}
```

---

## 🛡️ Middleware

### 1. Rate Limited

```php
use Toporia\Framework\Queue\Middleware\RateLimited;

class SendEmailJob extends Job
{
    public function middleware(): array
    {
        return [
            new RateLimited(
                limiter: app('limiter'),
                maxAttempts: 10,  // Max 10 jobs
                decayMinutes: 1   // Per minute
            )
        ];
    }
}

// Per-user rate limiting
new RateLimited($limiter, 10, 1)
    ->by(fn($job) => "user:{$job->userId}");
```

### 2. Without Overlapping

```php
use Toporia\Framework\Queue\Middleware\WithoutOverlapping;

class SyncDataJob extends Job
{
    public function middleware(): array
    {
        return [
            new WithoutOverlapping(
                cache: app('cache'),
                key: 'sync-data',
                releaseAfter: 300 // Release after 5 minutes
            )
        ];
    }
}
```

### 3. Ensure Unique

```php
use Toporia\Framework\Queue\Middleware\EnsureUnique;

// Auto-applied when job has uniqueId
SendEmailJob::dispatch($to, $subject, $message)
    ->unique("email-{$to}");
```

### 4. Throttle

```php
use Toporia\Framework\Queue\Middleware\Throttle;

class ProcessDataJob extends Job
{
    public function middleware(): array
    {
        return [
            new Throttle(
                cache: app('cache'),
                maxJobs: 10,        // Max 10 jobs
                decaySeconds: 60   // Per 60 seconds
            )
        ];
    }
}
```

---

## ❌ Failed Jobs

### 1. Handle Failed Jobs

```php
class SendEmailJob extends Job
{
    public function failed(\Throwable $exception): void
    {
        // Log failure
        error_log("Email job failed: {$exception->getMessage()}");

        // Notify admin
        // Send alert, etc.
    }
}
```

### 2. Retry Failed Jobs

```bash
# Retry all failed jobs
php console queue:retry all

# Retry specific job
php console queue:retry {jobId}
```

### 3. View Failed Jobs

```bash
# List failed jobs
php console queue:failed

# Show failed job details
php console queue:failed {jobId}
```

### 4. Delete Failed Jobs

```bash
# Delete specific failed job
php console queue:forget {jobId}

# Flush all failed jobs
php console queue:flush
```

---

## 📊 Monitoring & Metrics

### 1. Job Metrics

```php
use Toporia\Framework\Queue\Support\JobMetrics;

$metrics = new JobMetrics(app('cache'));

// Get metrics for a job class
$stats = $metrics->get(SendEmailJob::class);

// Returns:
// [
//     'total' => 1000,
//     'success' => 950,
//     'failed' => 50,
//     'success_rate' => 95.0,
//     'avg_duration' => 0.5,
//     'min_duration' => 0.1,
//     'max_duration' => 2.0,
//     'avg_memory_mb' => 10.5,
//     ...
// ]
```

### 2. Queue Metrics

```php
use Toporia\Framework\Queue\Support\QueueMetrics;

$metrics = new QueueMetrics(app('cache'));

// Get metrics for a queue
$stats = $metrics->get('emails');

// Returns:
// [
//     'pushes' => 1000,
//     'pops' => 950,
//     'processes' => 950,
//     'avg_duration' => 0.5,
//     'throughput' => 100.0, // per hour
//     ...
// ]
```

### 3. Job Progress

```php
use Toporia\Framework\Queue\Support\JobProgress;

$progress = new JobProgress(app('cache'));

// Get job progress
$data = $progress->get($jobId);

// Returns:
// [
//     'progress' => 75,
//     'message' => 'Processing 75%',
//     'updated_at' => 1234567890
// ]
```

### 4. Events

```php
use Toporia\Framework\Queue\Events\JobProcessed;
use Toporia\Framework\Events\Contracts\EventDispatcherInterface;

$dispatcher = app(EventDispatcherInterface::class);

$dispatcher->listen(JobProcessed::class, function (JobProcessed $event) {
    $job = $event->getJob();
    $attempt = $event->getAttempt();

    // Log, notify, etc.
});
```

**Available Events:**
- `JobQueued`: Job được queue
- `JobProcessing`: Job bắt đầu xử lý
- `JobProcessed`: Job hoàn thành
- `JobFailed`: Job thất bại
- `JobTimedOut`: Job timeout
- `JobRetrying`: Job đang retry
- `WorkerStopping`: Worker đang dừng

---

## 🎯 Best Practices

### 1. Job Design

```php
// ✅ GOOD: Small, focused jobs
class SendEmailJob extends Job
{
    public function handle(MailerInterface $mailer): void
    {
        $mailer->send($this->to, $this->subject, $this->message);
    }
}

// ❌ BAD: Large, complex jobs
class ProcessEverythingJob extends Job
{
    public function handle(): void
    {
        // Too much logic in one job
    }
}
```

### 2. Error Handling

```php
class SendEmailJob extends Job
{
    protected int $maxAttempts = 3;

    public function handle(MailerInterface $mailer): void
    {
        try {
            $mailer->send($this->to, $this->subject, $this->message);
        } catch (\Throwable $e) {
            // Log error
            error_log("Email failed: {$e->getMessage()}");

            // Re-throw to trigger retry
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Handle final failure
        error_log("Email job permanently failed");
    }
}
```

### 3. Queue Selection

```php
// High priority jobs
SendEmailJob::dispatch($to, $subject, $message)
    ->onQueue('high')
    ->priority(10);

// Normal jobs
ProcessDataJob::dispatch($data)
    ->onQueue('default');

// Low priority jobs
GenerateReportJob::dispatch($reportId)
    ->onQueue('low')
    ->priority(-10);
```

### 4. Performance Optimization

```php
// ✅ Use unique jobs to prevent duplicates
SendEmailJob::dispatch($to, $subject, $message)
    ->unique("email-{$to}");

// ✅ Use tags for filtering
ProcessOrderJob::dispatch($orderId)
    ->tag(['order', 'processing']);

// ✅ Set appropriate timeouts
class LongRunningJob extends Job
{
    protected int $timeout = 600; // 10 minutes
}
```

### 5. Monitoring

```php
// Track job progress for long-running jobs
class ProcessLargeFileJob extends Job
{
    protected bool $trackProgress = true;

    public function handle(): void
    {
        // Report progress periodically
        $this->reportProgress(50, "Halfway done");
    }
}
```

---

## 📚 Examples

### Example 1: Email Job với Retry

```php
class SendEmailJob extends Job
{
    protected int $maxAttempts = 5;
    protected int $timeout = 30;

    public function __construct(
        private string $to,
        private string $subject,
        private string $body
    ) {
        parent::__construct();

        // Exponential backoff: 2^attempt seconds
        $this->backoff(new \Toporia\Framework\Queue\Backoff\ExponentialBackoff(
            base: 2,
            max: 300
        ));
    }

    public function handle(MailerInterface $mailer): void
    {
        $mailer->send($this->to, $this->subject, $this->body);
    }

    public function failed(\Throwable $exception): void
    {
        error_log("Email failed after {$this->maxAttempts} attempts: {$exception->getMessage()}");
    }
}

// Dispatch
SendEmailJob::dispatch($to, $subject, $body)
    ->onQueue('emails')
    ->tag(['email', 'notification']);
```

### Example 2: Data Processing với Progress

```php
class ProcessDataJob extends Job
{
    protected bool $trackProgress = true;
    protected int $timeout = 600;

    public function __construct(
        private array $data
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $total = count($this->data);

        foreach ($this->data as $index => $item) {
            $this->processItem($item);

            $progress = (int)(($index + 1) / $total * 100);
            $this->reportProgress($progress, "Processed {$index}/{$total}");
        }
    }

    private function processItem(array $item): void
    {
        // Process item
    }
}
```

### Example 3: Rate Limited Job

```php
class SendNotificationJob extends Job
{
    public function middleware(): array
    {
        return [
            new RateLimited(
                limiter: app('limiter'),
                maxAttempts: 100,
                decayMinutes: 1
            )->by(fn($job) => "user:{$job->userId}") // Per-user limit
        ];
    }

    public function handle(NotificationService $service): void
    {
        $service->send($this->userId, $this->message);
    }
}
```

---

## 🔗 Xem thêm

- [Schedule Guide](./SCHEDULE_GUIDE.md)
- [Queue API Reference](./QUEUE_API.md)
- [Best Practices](./BEST_PRACTICES.md)













