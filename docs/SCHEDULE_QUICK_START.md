# Schedule System - Quick Start Guide

## 📚 Overview

Toporia Framework has a powerful Cron-like schedule system for running tasks at specific times or intervals.

**Location:** `src/App/Infrastructure/Providers/ScheduleServiceProvider.php`

---

## 🚀 Quick Example

```php
// In ScheduleServiceProvider::defineSchedule()

// Run every minute
$scheduler->call(function () {
    // Your code here
})->everyMinute()
    ->description('My task description');

// Run daily at 2 AM
$scheduler->call(function () {
    // Cleanup old files
})->dailyAt('02:00')
    ->description('Daily cleanup');
```

---

## ⏰ Frequency Options

```php
->everyMinute()                 // Every minute
->everyMinutes(5)               // Every 5 minutes
->hourly()                      // Every hour at :00
->hourlyAt(30)                  // Every hour at :30
->daily()                       // Daily at 00:00
->dailyAt('14:00')              // Daily at 14:00
->weekly()                      // Weekly on Sunday 00:00
->monthly()                     // Monthly on 1st at 00:00
->weekdays()                    // Monday-Friday
->weekends()                    // Saturday-Sunday
->mondays()                     // Every Monday
->tuesdays()                    // Every Tuesday
->wednesdays()                  // Every Wednesday
->thursdays()                   // Every Thursday
->fridays()                     // Every Friday
->saturdays()                   // Every Saturday
->sundays()                     // Every Sunday
->cron('0 0 * * *')            // Custom cron expression
```

---

## 🎯 Conditions

```php
// Only run during business hours
->when(function() {
    $hour = date('H');
    return $hour >= 9 && $hour < 18;
})

// Skip during late night
->skip(function() {
    $hour = date('H');
    return $hour >= 23 || $hour < 6;
})

// Run between 9 AM and 5 PM
->between('09:00', '17:00')

// Don't run between midnight and 6 AM
->unlessBetween('00:00', '06:00')

// Only in specific environments
->environments(['local', 'development'])
```

---

## 🔒 Overlap Prevention

```php
// Prevent task from running if previous instance is still running
->withoutOverlapping()

// With custom expiry time (in minutes)
->withoutOverlapping(120)  // Expires after 2 hours
```

---

## 🎭 Background Execution

```php
// Run task in background (non-blocking)
->runInBackground()
```

---

## 🎣 Callbacks

```php
// Before task execution
->before(function() {
    echo "Starting task...\n";
})

// After task execution (always runs)
->after(function() {
    echo "Task finished\n";
})

// Only on success
->onSuccess(function() {
    echo "Task succeeded!\n";
})

// Only on failure
->onFailure(function(\Throwable $e) {
    echo "Task failed: {$e->getMessage()}\n";
})

// Alias for after()
->then(function() {
    echo "Task completed\n";
})
```

---

## 📝 Output & Logging

```php
// Send output to file (overwrite)
->sendOutputTo('/path/to/output.log')

// Append output to file
->appendOutputTo('/path/to/output.log')

// Email output (requires mail config)
->emailOutputTo('admin@example.com')

// Email only on failure
->emailOutputOnFailure('admin@example.com')
```

---

## 🔄 Retry Mechanism

```php
// Retry up to 3 times on failure, with 5-second delay
->retry(3, 5)

// With exponential backoff
->retry(3, 5, true)  // 5s, 10s, 20s delays
```

---

## ⚙️ Advanced Features

```php
// Set task priority (higher = runs first)
->priority(100)

// Set timeout (in seconds)
->timeout(300)  // 5 minutes

// Set memory limit (in MB)
->memory(128)

// Task dependencies (wait for other tasks)
->dependsOn('task-id-1', 'task-id-2')

// Custom task name (for mutex)
->name('my-unique-task')

// Custom timezone
->timezone('Asia/Ho_Chi_Minh')

// Skip during maintenance mode
->skipMaintenanceMode()

// Run on only one server (distributed systems)
->onOneServer()
```

---

## 🌐 HTTP Pings

```php
// Ping URL before execution
->pingBefore('https://example.com/ping?before')

// Ping URL after execution
->pingAfter('https://example.com/ping?after')

// Ping URL on success
->pingOnSuccess('https://example.com/ping?success')

// Ping URL on failure
->pingOnFailure('https://example.com/ping?failure')
```

---

## 🖥️ CLI Commands

### List all scheduled tasks
```bash
php console schedule:list
```

### Run due tasks (call this from cron)
```bash
php console schedule:run
```

### Test a specific task
```bash
php console schedule:test task-id
```

### Test all due tasks
```bash
php console schedule:test --due
```

### Run scheduler continuously (development)
```bash
php console schedule:work
```

---

## 📋 Production Setup

Add this to your crontab:

```bash
* * * * * cd /path/to/project && php console schedule:run >> /dev/null 2>&1
```

This runs the scheduler every minute, and the scheduler will determine which tasks should run.

---

## 🎯 Real-World Examples

### Daily Database Cleanup
```php
$scheduler->call(function () {
    // Delete old sessions
    DB::table('sessions')
        ->where('last_activity', '<', time() - 86400)
        ->delete();
})->dailyAt('03:00')
    ->withoutOverlapping()
    ->description('Database cleanup');
```

### Hourly Health Check
```php
$scheduler->call(function () {
    $healthy = checkApplicationHealth();
    if (!$healthy) {
        sendAlert('Health check failed!');
    }
})->everyMinutes(5)
    ->between('06:00', '22:00')
    ->onFailure(function($e) {
        sendAlert('Critical: Health check exception - ' . $e->getMessage());
    })
    ->description('Health check');
```

### Weekly Backup
```php
$scheduler->call(function () {
    exec('mysqldump mydb > backup.sql');
    exec('tar -czf backup_' . date('Y-m-d') . '.tar.gz backup.sql');
})->sundays()
    ->dailyAt('23:00')
    ->withoutOverlapping()
    ->timeout(600)  // 10 minutes
    ->description('Weekly database backup');
```

### Weekday Report Generation
```php
$scheduler->call(function () {
    generateDailyReport();
    sendReportToManagement();
})->weekdays()
    ->dailyAt('18:00')
    ->timezone('Asia/Ho_Chi_Minh')
    ->runInBackground()
    ->onSuccess(function() {
        echo "Report sent successfully\n";
    })
    ->description('Daily report generation');
```

### Long-Running Task with Retry
```php
$scheduler->call(function () {
    syncLargeDataset();  // May fail due to network
})->everyMinutes(30)
    ->withoutOverlapping()
    ->retry(3, 60, true)  // 3 retries, exponential backoff
    ->timeout(1800)       // 30 minutes max
    ->memory(512)         // 512 MB limit
    ->description('Data synchronization');
```

---

## 🐛 Debugging

### View task logs
```bash
cat storage/logs/schedule/test_$(date +%Y-%m-%d).log
```

### Test a task without waiting
```bash
php console schedule:test task-id
```

### Check if task is due
```bash
php console schedule:list | grep "Due"
```

---

## 📊 Task History

Task execution history is tracked automatically in memory:

```php
use Toporia\Framework\Console\Scheduling\Support\TaskHistory;

// Get history for a task
$history = TaskHistory::getHistory('task-id');

// Get statistics
$stats = TaskHistory::getStatistics('task-id');
// Returns: ['total_runs', 'successes', 'failures', 'avg_duration', 'avg_memory']
```

---

## ⚠️ Important Notes

1. **Cron Expression Format:** Standard 5-field format `minute hour day month weekday`
2. **Leading Zeros:** Automatically removed (e.g., `02:00` → `0 2 * * *`)
3. **Timezone:** Default is server timezone unless specified
4. **Overlap:** Use `withoutOverlapping()` for long-running tasks
5. **Priority:** Higher priority tasks run first (0 = default)
6. **Retry:** Only retries on exceptions, not on return false
7. **Background:** Uses `pcntl_fork()` on Unix systems
8. **Mutex:** Uses cache for lock storage (shared across servers)

---

## 🔗 Related Files

- **Schedule Definition:** `src/App/Infrastructure/Providers/ScheduleServiceProvider.php`
- **Scheduler Class:** `src/Framework/Console/Scheduling/Scheduler.php`
- **Task Class:** `src/Framework/Console/Scheduling/ScheduledTask.php`
- **Commands:** `src/Framework/Console/Commands/Schedule*.php`
- **Test Report:** `docs/SCHEDULE_TEST_REPORT.md`

---

**Last Updated:** 2025-12-09
**Framework:** Toporia v1.0

