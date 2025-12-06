# Queue & Schedule System - Overview

## 🎯 Tổng quan

Toporia Framework cung cấp hai hệ thống mạnh mẽ cho background processing:

1. **Queue System**: Xử lý jobs bất đồng bộ
2. **Schedule System**: Chạy tasks theo lịch định kỳ

Cả hai hệ thống đều được thiết kế theo **Clean Architecture** và **SOLID principles**, đảm bảo code sạch, tối ưu, và dễ mở rộng.

---

## 📦 Queue System

### Tính năng chính

- ✅ **Multiple Drivers**: Database, Redis, RabbitMQ, Sync
- ✅ **Job Priorities**: Ưu tiên xử lý
- ✅ **Job Tags**: Filter và monitor
- ✅ **Unique Jobs**: Ngăn duplicate
- ✅ **Progress Tracking**: Theo dõi tiến độ (0-100%)
- ✅ **Job Cancellation**: Hủy jobs
- ✅ **Metrics**: Performance tracking
- ✅ **Retry & Backoff**: Exponential, Constant, Custom
- ✅ **Middleware**: RateLimited, WithoutOverlapping, EnsureUnique, Throttle
- ✅ **Events**: Job lifecycle events

### Khi nào dùng Queue?

- ✅ Xử lý tasks tốn thời gian (gửi email, generate reports)
- ✅ Tasks cần retry (API calls, external services)
- ✅ Tasks cần priority (urgent vs normal)
- ✅ Tasks cần scale (multiple workers)
- ✅ Tasks cần monitoring (metrics, progress)

### Quick Start

```php
// Create job
class SendEmailJob extends Job
{
    public function handle(MailerInterface $mailer): void
    {
        $mailer->send($this->to, $this->subject, $this->message);
    }
}

// Dispatch
SendEmailJob::dispatch($to, $subject, $message)
    ->onQueue('emails')
    ->priority(10);

// Run worker
php console queue:work --queue=emails
```

**Xem chi tiết**: [Queue Guide](./QUEUE_GUIDE.md)

---

## ⏰ Schedule System

### Tính năng chính

- ✅ **Cron Expressions**: Full cron syntax
- ✅ **Task Priorities**: Ưu tiên xử lý
- ✅ **Task Dependencies**: Tasks phụ thuộc
- ✅ **Task Retry**: Retry với backoff
- ✅ **Task History**: Execution history
- ✅ **Task Metrics**: Performance metrics
- ✅ **Mutex/Locking**: Prevent overlapping
- ✅ **Time Constraints**: Between, unlessBetween
- ✅ **Maintenance Mode**: Skip during maintenance

### Khi nào dùng Schedule?

- ✅ Tasks chạy định kỳ (daily backup, weekly reports)
- ✅ Tasks cần chạy đúng giờ (scheduled maintenance)
- ✅ Tasks có dependencies (backup → cleanup → report)
- ✅ Tasks cần history tracking (audit logs)
- ✅ Tasks cần monitoring (health checks)

### Quick Start

```php
// Register task
$scheduler->call(function () {
    backupDatabase();
})
    ->daily()
    ->at('02:00')
    ->taskId('backup-database')
    ->trackHistory();

// Run scheduler (via cron)
* * * * * php console schedule:run
```

**Xem chi tiết**: [Schedule Guide](./SCHEDULE_GUIDE.md)

---

## 🔄 Queue vs Schedule

| Tiêu chí | Queue | Schedule |
|----------|-------|----------|
| **Trigger** | Manual/Event-driven | Time-based (cron) |
| **Execution** | Async (background) | Sync (scheduled time) |
| **Retry** | ✅ Built-in | ✅ Built-in |
| **Priority** | ✅ Yes | ✅ Yes |
| **Dependencies** | ❌ No | ✅ Yes |
| **History** | ⚠️ Via metrics | ✅ Built-in |
| **Progress** | ✅ Yes | ❌ No |
| **Cancellation** | ✅ Yes | ❌ No |
| **Use Case** | On-demand tasks | Scheduled tasks |

### Kết hợp Queue + Schedule

```php
// Schedule triggers queue job
$scheduler->job(new GenerateReportJob())
    ->daily()
    ->at('09:00')
    ->taskId('generate-report');

// Job runs in queue (async)
class GenerateReportJob extends Job
{
    protected bool $trackProgress = true;

    public function handle(): void
    {
        // Long-running task with progress
        $this->reportProgress(50, "Generating report...");
    }
}
```

---

## 📊 Monitoring & Metrics

### Queue Metrics

```php
use Toporia\Framework\Queue\Support\JobMetrics;
use Toporia\Framework\Queue\Support\QueueMetrics;

$jobMetrics = new JobMetrics(app('cache'));
$stats = $jobMetrics->get(SendEmailJob::class);

$queueMetrics = new QueueMetrics(app('cache'));
$stats = $queueMetrics->get('emails');
```

### Schedule Metrics

```php
use Toporia\Framework\Console\Scheduling\Support\TaskHistory;

$stats = TaskHistory::getStatistics('backup-database');
```

---

## 🎯 Best Practices

### 1. Chọn đúng hệ thống

```php
// ✅ Use Queue for on-demand tasks
SendEmailJob::dispatch($to, $subject, $message);

// ✅ Use Schedule for periodic tasks
$scheduler->call(fn() => cleanup())->daily();
```

### 2. Error Handling

```php
// Queue: Handle in failed() method
class SendEmailJob extends Job
{
    public function failed(\Throwable $e): void
    {
        error_log("Email failed: {$e->getMessage()}");
    }
}

// Schedule: Handle in task
$scheduler->call(function () {
    try {
        processData();
    } catch (\Throwable $e) {
        error_log("Task failed: {$e->getMessage()}");
        throw $e; // Trigger retry
    }
})->retry(3);
```

### 3. Performance

```php
// Queue: Use priorities
SendEmailJob::dispatch(...)->priority(10);

// Schedule: Use dependencies
$task1->dependsOn('task2');
```

### 4. Monitoring

```php
// Queue: Track progress
$job->trackProgress()->reportProgress(50);

// Schedule: Track history
$task->trackHistory();
```

---

## 📚 Documentation Links

- [Queue Guide](./QUEUE_GUIDE.md) - Hướng dẫn sử dụng Queue
- [Schedule Guide](./SCHEDULE_GUIDE.md) - Hướng dẫn sử dụng Schedule
- [Queue API](./QUEUE_API.md) - API Reference cho Queue
- [Schedule API](./SCHEDULE_API.md) - API Reference cho Schedule
- [Best Practices](./BEST_PRACTICES.md) - Best practices

---

## 🚀 Quick Examples

### Queue Example

```php
// Job
class ProcessOrderJob extends Job
{
    protected bool $trackProgress = true;

    public function handle(): void
    {
        $this->reportProgress(50, "Processing order...");
        // Process order
    }
}

// Dispatch
ProcessOrderJob::dispatch($orderId)
    ->onQueue('orders')
    ->priority(10)
    ->tag(['order', 'processing']);
```

### Schedule Example

```php
// Register
$scheduler->call(function () {
    generateDailyReport();
})
    ->daily()
    ->at('09:00')
    ->taskId('daily-report')
    ->priority(10)
    ->timeout(600)
    ->trackHistory();
```

---

## ✅ Kết luận

Cả hai hệ thống Queue và Schedule đều:
- ✅ **Đầy đủ tính năng**: Ngang hoặc hơn Laravel
- ✅ **Tối ưu**: Performance cao với các optimizations
- ✅ **Clean**: Clean Architecture + SOLID
- ✅ **Bài bản**: Cấu trúc rõ ràng, dễ mở rộng

Sử dụng Queue cho on-demand tasks và Schedule cho periodic tasks để có hệ thống background processing mạnh mẽ!











