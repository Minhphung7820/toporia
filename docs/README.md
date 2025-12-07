# Toporia Framework - Documentation

## 📚 Queue & Schedule Documentation

Bộ tài liệu hoàn chỉnh cho Queue System và Schedule System của Toporia Framework.

### 📖 Tài liệu chính

1. **[Queue & Schedule Overview](./QUEUE_AND_SCHEDULE_OVERVIEW.md)**
   - Tổng quan về cả hai hệ thống
   - So sánh Queue vs Schedule
   - Khi nào dùng cái nào
   - Quick examples

2. **[Queue Guide](./QUEUE_GUIDE.md)** ⭐
   - Hướng dẫn sử dụng Queue System
   - Tạo jobs, dispatching, workers
   - Tất cả tính năng: priorities, tags, unique, progress, cancellation, metrics
   - Middleware, failed jobs, monitoring
   - Best practices và examples

3. **[Schedule Guide](./SCHEDULE_GUIDE.md)** ⭐
   - Hướng dẫn sử dụng Schedule System
   - Định nghĩa tasks, cron expressions
   - Tất cả tính năng: priorities, dependencies, retry, timeout, history
   - Task management, metrics
   - Best practices và examples

4. **[Queue API Reference](./QUEUE_API.md)**
   - API reference đầy đủ cho Queue System
   - Job class, PendingDispatch, Worker
   - Queue drivers, middleware, support classes
   - Events, exceptions

5. **[Schedule API Reference](./SCHEDULE_API.md)**
   - API reference đầy đủ cho Schedule System
   - Scheduler, ScheduledTask, CronExpression
   - TaskHistory, commands

### 📊 So sánh & Phân tích

6. **[Queue Features Comparison](./QUEUE_FEATURES_COMPARISON.md)**
   - So sánh chi tiết với Laravel
   - Tính năng vượt trội
   - Performance optimizations
   - Architecture quality

7. **[Queue Analysis](./QUEUE_ANALYSIS.md)**
   - Phân tích files/classes usage
   - Files dư thừa
   - Cải thiện đã thực hiện

8. **[Schedule Analysis](./SCHEDULE_ANALYSIS.md)**
   - Phân tích schedule system
   - Tính năng và cải thiện

---

## 🚀 Quick Start

### Queue System

```php
// 1. Create job
class SendEmailJob extends Job
{
    public function handle(MailerInterface $mailer): void
    {
        $mailer->send($this->to, $this->subject, $this->message);
    }
}

// 2. Dispatch
SendEmailJob::dispatch($to, $subject, $message)
    ->onQueue('emails')
    ->priority(10);

// 3. Run worker
php console queue:work --queue=emails
```

### Schedule System

```php
// 1. Register task
$scheduler->call(function () {
    backupDatabase();
})
    ->daily()
    ->at('02:00')
    ->taskId('backup-database');

// 2. Setup cron
* * * * * php console schedule:run
```

---

## 📋 Tính năng chính

### Queue System

- ✅ Multiple Queue Drivers (Database, Redis, RabbitMQ, Sync)
- ✅ Job Priorities, Tags, Unique Jobs
- ✅ Job Progress Tracking (0-100%)
- ✅ Job Cancellation
- ✅ Job & Queue Metrics
- ✅ Retry & Backoff (Exponential, Constant, Custom)
- ✅ Middleware (RateLimited, WithoutOverlapping, EnsureUnique, Throttle)
- ✅ Events (JobQueued, JobProcessing, JobProcessed, JobFailed, etc.)
- ✅ Failed Jobs Management

### Schedule System

- ✅ Cron Expressions (Full syntax support)
- ✅ Task Priorities & Dependencies
- ✅ Task Retry với Exponential Backoff
- ✅ Task Timeout & Memory Limit
- ✅ Task History & Metrics
- ✅ Mutex/Locking (Prevent overlapping)
- ✅ Time Constraints (Between, unlessBetween)
- ✅ Maintenance Mode Support

---

## 🎯 Architecture

### Clean Architecture

- ✅ **Strict Layer Separation**: Presentation, Application, Domain, Infrastructure
- ✅ **Dependency Rule**: Dependencies chỉ hướng vào trong
- ✅ **Framework Independence**: Core logic không phụ thuộc framework

### SOLID Principles

- ✅ **Single Responsibility**: Mỗi class một trách nhiệm
- ✅ **Open/Closed**: Dễ mở rộng, không cần sửa code cũ
- ✅ **Liskov Substitution**: Tất cả implementations thay thế được
- ✅ **Interface Segregation**: Interfaces nhỏ, focused
- ✅ **Dependency Inversion**: Phụ thuộc vào abstractions

### Performance

- ✅ **O(1) Operations**: Most operations are O(1)
- ✅ **Efficient Locking**: FOR UPDATE SKIP LOCKED
- ✅ **Connection Pooling**: Redis, RabbitMQ
- ✅ **Atomic Operations**: Database transactions
- ✅ **Graceful Shutdown**: Signal handling

---

## 📚 Examples

### Queue Examples

Xem [Queue Guide - Examples](./QUEUE_GUIDE.md#-examples)

### Schedule Examples

Xem [Schedule Guide - Examples](./SCHEDULE_GUIDE.md#-examples)

---

## 🔗 Links

- [Queue Guide](./QUEUE_GUIDE.md)
- [Schedule Guide](./SCHEDULE_GUIDE.md)
- [Queue API](./QUEUE_API.md)
- [Schedule API](./SCHEDULE_API.md)
- [Overview](./QUEUE_AND_SCHEDULE_OVERVIEW.md)

---

## ✅ Kết luận

Hệ thống Queue và Schedule của Toporia Framework:
- ✅ **Đầy đủ tính năng**: Ngang hoặc hơn Laravel
- ✅ **Tối ưu**: Performance cao
- ✅ **Clean**: Clean Architecture + SOLID
- ✅ **Bài bản**: Cấu trúc rõ ràng, dễ mở rộng

**Sẵn sàng cho Production!** 🚀













