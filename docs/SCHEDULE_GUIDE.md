# Task Scheduling System - Complete Guide

## 📚 Mục lục

1. [Giới thiệu](#giới-thiệu)
2. [Cài đặt & Cấu hình](#cài-đặt--cấu-hình)
3. [Định nghĩa Tasks](#định-nghĩa-tasks)
4. [Cron Expressions](#cron-expressions)
5. [Task Features](#task-features)
6. [Task Management](#task-management)
7. [Task History & Metrics](#task-history--metrics)
8. [Best Practices](#best-practices)

---

## 🎯 Giới thiệu

Toporia Scheduling System là một hệ thống task scheduling mạnh mẽ, tương tự như Laravel Scheduler nhưng với nhiều tính năng nâng cao hơn. Hệ thống hỗ trợ cron expressions, task dependencies, priority, retry, và metrics tracking.

### Tính năng chính

- ✅ **Cron Expressions**: Full cron syntax support
- ✅ **Task Priorities**: Ưu tiên xử lý tasks
- ✅ **Task Dependencies**: Tasks phụ thuộc lẫn nhau
- ✅ **Task Retry**: Retry với exponential backoff
- ✅ **Task Timeout**: Timeout handling
- ✅ **Task History**: Track execution history
- ✅ **Task Metrics**: Performance metrics
- ✅ **Maintenance Mode**: Skip tasks during maintenance
- ✅ **Mutex/Locking**: Prevent overlapping execution
- ✅ **Time Constraints**: Between, unlessBetween
- ✅ **Clean Architecture**: Strict layer separation

---

## ⚙️ Cài đặt & Cấu hình

### 1. Cấu hình Schedule

File: `config/schedule.php`

```php
return [
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'cache' => env('CACHE_DRIVER', 'file'),
];
```

### 2. Đăng ký Schedule Service Provider

File: `config/app.php`

```php
'providers' => [
    // ...
    \Toporia\Framework\Providers\ConsoleServiceProvider::class,
],
```

### 3. Setup Cron Job

Thêm vào crontab:

```bash
* * * * * cd /path-to-your-project && php console schedule:run >> /dev/null 2>&1
```

Hoặc sử dụng systemd timer (Linux):

```ini
[Unit]
Description=Toporia Schedule Runner
After=network.target

[Service]
Type=oneshot
User=www-data
WorkingDirectory=/path-to-your-project
ExecStart=/usr/bin/php console schedule:run

[Timer]
OnCalendar=*:0/1
Persistent=true

[Install]
WantedBy=timers.target
```

---

## 📝 Định nghĩa Tasks

### 1. Đăng ký Tasks

File: `app/Console/Kernel.php` hoặc `routes/console.php`

```php
use Toporia\Framework\Console\Scheduling\Scheduler;

$scheduler = app(Scheduler::class);

// Simple task
$scheduler->call(function () {
    // Do something
})->everyMinute();

// Artisan command
$scheduler->command('cache:clear')
    ->daily()
    ->at('02:00');

// Queue job
$scheduler->job(new SendReportJob())
    ->daily()
    ->at('09:00');

// Shell command
$scheduler->exec('php backup.php')
    ->daily()
    ->at('03:00');
```

### 2. Task Scheduling Methods

```php
// Frequency methods
$scheduler->call(fn() => ...)->everyMinute();
$scheduler->call(fn() => ...)->everyFiveMinutes();
$scheduler->call(fn() => ...)->everyTenMinutes();
$scheduler->call(fn() => ...)->everyFifteenMinutes();
$scheduler->call(fn() => ...)->everyThirtyMinutes();
$scheduler->call(fn() => ...)->hourly();
$scheduler->call(fn() => ...)->hourlyAt(15);
$scheduler->call(fn() => ...)->daily();
$scheduler->call(fn() => ...)->dailyAt('13:00');
$scheduler->call(fn() => ...)->twiceDaily(1, 13);
$scheduler->call(fn() => ...)->weekly();
$scheduler->call(fn() => ...)->weeklyOn(1, '8:00'); // Monday 8:00
$scheduler->call(fn() => ...)->monthly();
$scheduler->call(fn() => ...)->monthlyOn(4, '15:00');
$scheduler->call(fn() => ...->quarterly();
$scheduler->call(fn() => ...)->yearly();

// Cron expression
$scheduler->call(fn() => ...)->cron('0 0 * * *'); // Daily at midnight
```

### 3. Task Constraints

```php
// Time constraints
$scheduler->call(fn() => ...)
    ->daily()
    ->between('09:00', '17:00'); // Only between 9 AM and 5 PM

$scheduler->call(fn() => ...)
    ->hourly()
    ->unlessBetween('22:00', '06:00'); // Skip between 10 PM and 6 AM

// Day constraints
$scheduler->call(fn() => ...)
    ->daily()
    ->weekdays(); // Monday to Friday

$scheduler->call(fn() => ...)
    ->daily()
    ->weekends(); // Saturday and Sunday

$scheduler->call(fn() => ...)
    ->daily()
    ->mondays();

$scheduler->call(fn() => ...)
    ->daily()
    ->sundays();

// Environment constraints
$scheduler->call(fn() => ...)
    ->daily()
    ->environments('production');

$scheduler->call(fn() => ...)
    ->daily()
    ->when(fn() => config('app.debug') === false);
```

---

## ⏰ Cron Expressions

### 1. Cron Syntax

```
* * * * *
│ │ │ │ │
│ │ │ │ └─── Day of week (0-7, 0 or 7 = Sunday)
│ │ │ └───── Month (1-12)
│ │ └─────── Day of month (1-31)
│ └───────── Hour (0-23)
└─────────── Minute (0-59)
```

### 2. Cron Examples

```php
// Every minute
$scheduler->call(fn() => ...)->cron('* * * * *');

// Every 5 minutes
$scheduler->call(fn() => ...)->cron('*/5 * * * *');

// Every hour at minute 0
$scheduler->call(fn() => ...)->cron('0 * * * *');

// Daily at midnight
$scheduler->call(fn() => ...)->cron('0 0 * * *');

// Daily at 2:30 AM
$scheduler->call(fn() => ...)->cron('30 2 * * *');

// Every Monday at 8:00 AM
$scheduler->call(fn() => ...)->cron('0 8 * * 1');

// First day of every month at midnight
$scheduler->call(fn() => ...)->cron('0 0 1 * *');

// Every weekday at 9:00 AM
$scheduler->call(fn() => ...)->cron('0 9 * * 1-5');

// Every weekend at 10:00 AM
$scheduler->call(fn() => ...)->cron('0 10 * * 0,6');
```

### 3. Cron Expression Parser

```php
use Toporia\Framework\Console\Scheduling\Support\CronExpression;

$cron = new CronExpression('0 0 * * *');

// Check if matches current time
if ($cron->matches(new \DateTime())) {
    // Task is due
}

// Get next run time
$nextRun = $cron->getNextRunTime(new \DateTime());

// Get description
$description = $cron->getDescription(); // "Daily at midnight"
```

---

## 🎨 Task Features

### 1. Task Priorities

```php
$scheduler->call(fn() => ...)
    ->daily()
    ->priority(10); // Higher = runs first

// Tasks with same priority run in registration order
```

### 2. Task Dependencies

```php
$backupTask = $scheduler->call(fn() => backup())
    ->daily()
    ->taskId('backup-database');

$cleanupTask = $scheduler->call(fn() => cleanup())
    ->daily()
    ->dependsOn('backup-database'); // Runs after backup completes
```

### 3. Task Retry

```php
$scheduler->call(fn() => syncData())
    ->hourly()
    ->retry(3)                    // Max 3 retries
    ->retryDelay(60)              // 60 seconds between retries
    ->exponentialBackoff();       // Use exponential backoff
```

### 4. Task Timeout

```php
$scheduler->call(fn() => longRunningTask())
    ->daily()
    ->timeout(300); // 5 minutes timeout
```

### 5. Task Memory Limit

```php
$scheduler->call(fn() => memoryIntensiveTask())
    ->daily()
    ->memoryLimit(512); // 512 MB limit
```

### 6. Task Mutex (Prevent Overlapping)

```php
$scheduler->call(fn() => syncData())
    ->everyFiveMinutes()
    ->withoutOverlapping(300); // Prevent overlap, release after 5 minutes
```

### 7. Task History Tracking

```php
$scheduler->call(fn() => generateReport())
    ->daily()
    ->taskId('generate-report')  // Required for history
    ->trackHistory();            // Enable history tracking
```

### 8. Task Description

```php
$scheduler->call(fn() => cleanup())
    ->daily()
    ->description('Clean up old files');
```

### 9. Maintenance Mode

```php
// Tasks automatically skip during maintenance mode
$scheduler->call(fn() => sendNotifications())
    ->hourly()
    ->evenInMaintenanceMode(); // Run even during maintenance
```

---

## 🔧 Task Management

### 1. List All Tasks

```bash
# List all registered tasks
php console schedule:list

# Output:
# ┌─────────────────────────────────────────────────────────────┐
# │ Scheduled Tasks                                             │
# ├─────────────────────────────────────────────────────────────┤
# │ Task ID: backup-database                                    │
# │ Description: Backup database                                │
# │ Cron: 0 0 * * *                                             │
# │ Next Run: 2025-01-24 00:00:00                               │
# │ Priority: 10                                                 │
# │ Dependencies: []                                             │
# │ Timeout: 300s                                                │
# │ Memory Limit: 256 MB                                         │
# │ Status: Active                                               │
# └─────────────────────────────────────────────────────────────┘
```

### 2. Test Tasks

```bash
# Test all due tasks
php console schedule:test --due

# Test specific task
php console schedule:test backup-database

# Test all tasks
php console schedule:test --all

# Verbose output
php console schedule:test backup-database --verbose
```

### 3. Run Schedule

```bash
# Run due tasks (called by cron)
php console schedule:run

# Run with verbose output
php console schedule:run --verbose
```

---

## 📊 Task History & Metrics

### 1. Task History

```php
use Toporia\Framework\Console\Scheduling\Support\TaskHistory;

// Set cache (done automatically by framework)
TaskHistory::setCache(app('cache'));

// Get task history
$history = TaskHistory::getRecords('backup-database');

// Get latest record
$latest = TaskHistory::getLatestRecord('backup-database');

// Check if task completed successfully
if (TaskHistory::hasCompletedSuccessfully('backup-database', new \DateTime())) {
    // Task completed successfully recently
}

// Get statistics
$stats = TaskHistory::getStatistics('backup-database');

// Returns:
// [
//     'total_runs' => 100,
//     'successful_runs' => 95,
//     'failed_runs' => 5,
//     'success_rate' => 95.0,
//     'avg_duration' => 2.5,
//     'avg_memory_mb' => 50.0,
//     'last_run' => '2025-01-23 12:00:00',
//     'last_status' => 'Success'
// ]
```

### 2. Task Metrics Integration

Tasks automatically record metrics when history tracking is enabled:

```php
$scheduler->call(fn() => generateReport())
    ->daily()
    ->taskId('generate-report')
    ->trackHistory();

// Metrics are automatically recorded:
// - Execution duration
// - Memory usage
// - Success/failure status
// - Timestamp
```

---

## 🎯 Best Practices

### 1. Task Design

```php
// ✅ GOOD: Small, focused tasks
$scheduler->call(function () {
    // Single responsibility
    cleanupOldFiles();
})->daily();

// ❌ BAD: Large, complex tasks
$scheduler->call(function () {
    // Too much logic
    cleanupOldFiles();
    sendNotifications();
    generateReports();
    // ...
})->daily();
```

### 2. Task Dependencies

```php
// ✅ GOOD: Clear dependencies
$backup = $scheduler->call(fn() => backup())
    ->daily()
    ->taskId('backup');

$cleanup = $scheduler->call(fn() => cleanup())
    ->daily()
    ->dependsOn('backup');

// ❌ BAD: Circular dependencies
$task1 = $scheduler->call(fn() => ...)->dependsOn('task2');
$task2 = $scheduler->call(fn() => ...)->dependsOn('task1');
```

### 3. Error Handling

```php
$scheduler->call(function () {
    try {
        // Task logic
        processData();
    } catch (\Throwable $e) {
        error_log("Task failed: {$e->getMessage()}");
        throw $e; // Re-throw to trigger retry
    }
})
    ->hourly()
    ->retry(3)
    ->retryDelay(60);
```

### 4. Time Constraints

```php
// ✅ Use time constraints to avoid peak hours
$scheduler->call(fn() => heavyTask())
    ->hourly()
    ->unlessBetween('09:00', '17:00'); // Skip business hours

// ✅ Use between for time-sensitive tasks
$scheduler->call(fn() => sendNotifications())
    ->hourly()
    ->between('09:00', '18:00'); // Only during business hours
```

### 5. Mutex for Critical Tasks

```php
// ✅ Prevent overlapping for critical tasks
$scheduler->call(fn() => syncData())
    ->everyFiveMinutes()
    ->withoutOverlapping(300); // Prevent overlap
```

### 6. Task Monitoring

```php
// ✅ Enable history for important tasks
$scheduler->call(fn() => generateReport())
    ->daily()
    ->taskId('generate-report')
    ->trackHistory()
    ->description('Generate daily report');
```

---

## 📚 Examples

### Example 1: Database Backup

```php
$scheduler->call(function () {
    $backup = new DatabaseBackup();
    $backup->execute();
})
    ->daily()
    ->at('02:00')
    ->taskId('backup-database')
    ->description('Backup database')
    ->priority(10)
    ->timeout(600)
    ->trackHistory();
```

### Example 2: Email Notifications

```php
$scheduler->job(new SendDailyReportJob())
    ->daily()
    ->at('09:00')
    ->between('09:00', '17:00')
    ->weekdays()
    ->taskId('send-daily-report')
    ->description('Send daily report email')
    ->retry(3)
    ->retryDelay(60);
```

### Example 3: Data Cleanup với Dependencies

```php
// Step 1: Archive old data
$archive = $scheduler->call(function () {
    archiveOldData();
})
    ->daily()
    ->at('01:00')
    ->taskId('archive-data')
    ->timeout(1800);

// Step 2: Delete archived data (depends on archive)
$cleanup = $scheduler->call(function () {
    deleteArchivedData();
})
    ->daily()
    ->at('02:00')
    ->taskId('cleanup-data')
    ->dependsOn('archive-data')
    ->timeout(600);
```

### Example 4: Health Check

```php
$scheduler->call(function () {
    $health = new HealthChecker();
    $health->check();
})
    ->everyFiveMinutes()
    ->taskId('health-check')
    ->description('System health check')
    ->timeout(30)
    ->trackHistory();
```

### Example 5: Report Generation

```php
$scheduler->call(function () {
    $report = new ReportGenerator();
    $report->generate();
})
    ->monthly()
    ->on(1, '00:00') // First day of month at midnight
    ->taskId('generate-monthly-report')
    ->description('Generate monthly report')
    ->priority(5)
    ->timeout(3600)
    ->memoryLimit(512)
    ->trackHistory();
```

---

## 🔗 Xem thêm

- [Queue Guide](./QUEUE_GUIDE.md)
- [Schedule API Reference](./SCHEDULE_API.md)
- [Best Practices](./BEST_PRACTICES.md)



