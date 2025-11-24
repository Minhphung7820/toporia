# Task Scheduling

Task Scheduling (Cron-like scheduler) cho phép bạn schedule các tasks chạy định kỳ trong ứng dụng PHP.

## Table of Contents

1. [Introduction](#introduction)
2. [Basic Usage](#basic-usage)
3. [Scheduling Frequency](#scheduling-frequency)
4. [Task Constraints](#task-constraints)
5. [Overlap Prevention](#overlap-prevention)
6. [Output Handling](#output-handling)
7. [Hooks & Callbacks](#hooks--callbacks)
8. [Background Execution](#background-execution)
9. [Production Setup](#production-setup)
10. [Advanced Features](#advanced-features)

---

## Introduction

Task Scheduler của Toporia Framework cung cấp API fluent để schedule các tasks chạy định kỳ. Thay vì tạo nhiều cron entries, bạn chỉ cần một entry duy nhất.

**Architecture**: Clean, SOLID, Zero Dependencies

---

## Basic Usage

### Defining Scheduled Tasks

Định nghĩa tasks trong [src/App/Infrastructure/Providers/ScheduleServiceProvider.php](../src/App/Infrastructure/Providers/ScheduleServiceProvider.php):

```php
use Toporia\Framework\Console\Scheduling\Scheduler;

class ScheduleServiceProvider extends ServiceProvider
{
    public function boot(ContainerInterface $container): void
    {
        $scheduler = $container->get(Scheduler::class);

        // Schedule a callback
        $scheduler->call(function () {
            // Cleanup old files
            $files = glob('storage/temp/*');
            foreach ($files as $file) {
                if (filemtime($file) < strtotime('-7 days')) {
                    unlink($file);
                }
            }
        })->daily()->description('Cleanup temp files');

        // Schedule a console command
        $scheduler->command('cache:clear')
            ->hourly()
            ->description('Clear application cache');

        // Schedule a shell command
        $scheduler->exec('cp /var/www/backup.sql /backups')
            ->daily()
            ->description('Database backup');

        // Schedule a queue job
        $scheduler->job(SendNewsletterJob::class)
            ->weekly()
            ->description('Send weekly newsletter');
    }
}
```

---

## Scheduling Frequency

### Predefined Frequencies

```php
// Every minute
$schedule->call($callback)->everyMinute();

// Every X minutes
$schedule->call($callback)->everyMinutes(5);  // Every 5 minutes

// Hourly
$schedule->call($callback)->hourly();

// Hourly at specific minute
$schedule->call($callback)->hourlyAt(30);  // At :30 of every hour

// Daily
$schedule->call($callback)->daily();

// Daily at specific time
$schedule->call($callback)->dailyAt('14:30');  // At 2:30 PM

// Weekly (Sundays at midnight)
$schedule->call($callback)->weekly();

// Monthly (1st day at midnight)
$schedule->call($callback)->monthly();

// Weekdays (Monday-Friday)
$schedule->call($callback)->weekdays();

// Weekends (Saturday-Sunday)
$schedule->call($callback)->weekends();

// Specific days
$schedule->call($callback)->mondays();
$schedule->call($callback)->tuesdays();
$schedule->call($callback)->wednesdays();
$schedule->call($callback)->thursdays();
$schedule->call($callback)->fridays();
$schedule->call($callback)->saturdays();
$schedule->call($callback)->sundays();
```

### Custom Cron Expressions

```php
// Custom cron expression
$schedule->call($callback)->cron('15 8 * * 1-5');  // 8:15 AM on weekdays

// Standard cron format: minute hour day month weekday
// * * * * * = Every minute
// 0 * * * * = Every hour
// 0 0 * * * = Every day at midnight
// 0 0 * * 0 = Every Sunday at midnight
// */5 * * * * = Every 5 minutes
```

---

## Task Constraints

### Conditional Execution

```php
// Run only if condition is true
$schedule->call($callback)
    ->daily()
    ->when(function () {
        return date('d') == 1; // Run only on first day of month
    });

// Skip if condition is true
$schedule->call($callback)
    ->daily()
    ->skip(function () {
        return app()->isInMaintenanceMode();
    });
```

### Timezone Support

```php
// Run at 8 AM New York time
$schedule->call($callback)
    ->dailyAt('08:00')
    ->timezone('America/New_York');
```

---

## Overlap Prevention

Prevent tasks from running simultaneously:

```php
$schedule->call(function () {
    // Long-running task
})->everyMinute()
  ->withoutOverlapping();

// Custom mutex expiration (in minutes)
$schedule->call($callback)
    ->everyMinute()
    ->withoutOverlapping(120);  // Lock expires after 2 hours

// Custom mutex name
$schedule->call($callback)
    ->withoutOverlapping()
    ->name('my-custom-mutex');
```

**How it works:**
- Uses cache backend (Redis, File, Memory)
- Prevents task from running if previous execution hasn't finished
- Automatic lock expiration prevents deadlocks

---

## Output Handling

### File Output

```php
// Redirect output to file (overwrites)
$schedule->command('backup:database')
    ->daily()
    ->sendOutputTo('/var/log/backup.log');

// Append output to file
$schedule->command('backup:database')
    ->daily()
    ->appendOutputTo('/var/log/backup.log');
```

### Email Output

```php
// Email output after every execution
$schedule->command('report:generate')
    ->daily()
    ->emailOutputTo('admin@example.com');

// Email output only on failure
$schedule->command('critical:task')
    ->hourly()
    ->emailOutputOnFailure('admin@example.com');
```

---

## Hooks & Callbacks

### Before/After Hooks

```php
$schedule->call($callback)
    ->daily()
    ->before(function () {
        // Runs before task execution
        log_info('Task starting...');
    })
    ->after(function () {
        // Runs after task execution (success or failure)
        log_info('Task finished');
    });

// Alias for after()
$schedule->call($callback)
    ->daily()
    ->then(function () {
        // Same as after()
    });
```

### Success/Failure Callbacks

```php
$schedule->call($callback)
    ->daily()
    ->onSuccess(function () {
        // Runs only on successful execution
        notify_team('Daily backup completed successfully');
    })
    ->onFailure(function (\Throwable $exception) {
        // Runs only when task fails
        notify_admin('Backup failed: ' . $exception->getMessage());
    });
```

### Chaining Hooks

```php
$schedule->call($callback)
    ->daily()
    ->before(fn() => log_info('Starting'))
    ->onSuccess(fn() => cache('last_backup', time()))
    ->onFailure(fn($e) => log_error($e->getMessage()))
    ->after(fn() => log_info('Finished'));
```

---

## Background Execution

Run tasks in background (non-blocking):

```php
$schedule->call(function () {
    // Long-running task
    sleep(60);
})->everyMinute()
  ->runInBackground();
```

**How it works:**
- Uses `pcntl_fork()` on Unix-like systems
- Falls back to shell background execution (`&`) if PCNTL not available
- Parent process continues immediately

**Foreground** (default):
```php
$schedule->call($callback)->runInForeground();
```

---

## Production Setup

### Single Cron Entry

Add này vào crontab của server:

```bash
* * * * * cd /path/to/project && php console schedule:run >> /dev/null 2>&1
```

This single cron entry will:
- Run every minute
- Check which tasks are due
- Execute due tasks
- Handle overlap prevention
- Manage output and hooks

### Development Mode

Để test scheduler trong development:

```bash
# Run in watch mode (checks every 60 seconds)
php console schedule:work

# Custom interval
php console schedule:work --sleep=30  # Check every 30 seconds
```

### List Scheduled Tasks

```bash
php console schedule:list
```

Output:
```
Expression    Description           Next Run
------------  -------------------   ------------------
* * * * *     Cleanup temp files    Every minute
0 2 * * *     Daily backup          Daily at 02:00
0 0 * * 0     Weekly report         Weekly
```

---

## Advanced Features

### Complete Example

```php
$scheduler->call(function () {
    // Complex backup logic
    $database = app('db');
    $backup = $database->backup();
    $storage->upload($backup);
})
    ->dailyAt('02:00')
    ->timezone('America/New_York')
    ->withoutOverlapping(120)
    ->runInBackground()
    ->sendOutputTo('/var/log/backup.log')
    ->before(function () {
        log_info('Starting backup');
        notify_slack('Backup started');
    })
    ->onSuccess(function () {
        cache('last_backup', now());
        notify_slack('Backup completed ✅');
    })
    ->onFailure(function (\Throwable $e) {
        log_error('Backup failed', ['exception' => $e]);
        notify_admin('URGENT: Backup failed!');
    })
    ->after(function () {
        // Cleanup temp files
        cleanup_temp_files();
    })
    ->description('Daily database backup');
```

### Real-World Examples

#### 1. Cleanup Old Files

```php
$scheduler->call(function () {
    $files = glob('storage/temp/*');
    $deleted = 0;

    foreach ($files as $file) {
        if (filemtime($file) < strtotime('-7 days')) {
            unlink($file);
            $deleted++;
        }
    }

    echo "Deleted {$deleted} old files\n";
})
    ->daily()
    ->sendOutputTo('storage/logs/cleanup.log')
    ->description('Cleanup old temp files');
```

#### 2. Generate Reports

```php
$scheduler->call(function () {
    $report = new WeeklyReport();
    $report->generate();
    $report->sendToManagers();
})
    ->weekly()
    ->timezone('America/Los_Angeles')
    ->withoutOverlapping()
    ->onSuccess(function () {
        cache('last_report_generated', now());
    })
    ->emailOutputOnFailure('dev-team@example.com')
    ->description('Generate weekly sales report');
```

#### 3. Database Maintenance

```php
$scheduler->command('db:optimize')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->appendOutputTo('storage/logs/database.log')
    ->before(function () {
        // Notify maintenance window
        broadcast(new MaintenanceStarting());
    })
    ->after(function () {
        broadcast(new MaintenanceCompleted());
    })
    ->description('Optimize database tables');
```

#### 4. API Data Sync

```php
$scheduler->job(SyncExternalDataJob::class)
    ->everyMinutes(15)
    ->withoutOverlapping()
    ->when(function () {
        // Only sync during business hours
        $hour = date('G');
        return $hour >= 8 && $hour < 18;
    })
    ->onFailure(function (\Throwable $e) {
        log_critical('API sync failed', [
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    })
    ->description('Sync data from external API');
```

---

## Performance Tips

### 1. Use Background Execution for Long Tasks

```php
// Bad: Blocks scheduler
$schedule->call(fn() => sleep(60))->everyMinute();

// Good: Non-blocking
$schedule->call(fn() => sleep(60))
    ->everyMinute()
    ->runInBackground();
```

### 2. Always Use Overlap Prevention for Long Tasks

```php
$schedule->call(function () {
    // Task takes 5+ minutes
    process_large_dataset();
})
    ->everyMinute()
    ->withoutOverlapping();  // Prevents concurrent execution
```

### 3. Use Constraints to Reduce Execution

```php
// Bad: Runs every hour, checks condition in callback
$schedule->call(function () {
    if (date('d') != 1) return; // Only run on 1st
    generate_monthly_report();
})->hourly();

// Good: Uses when() constraint
$schedule->call(fn() => generate_monthly_report())
    ->hourly()
    ->when(fn() => date('d') == 1);
```

### 4. Queue Heavy Tasks

```php
// Instead of running directly
$schedule->call(function () {
    // Heavy processing...
    process_all_users();
})->hourly();

// Queue it for background processing
$schedule->job(ProcessAllUsersJob::class)
    ->hourly();
```

---

## Architecture & Design

### SOLID Principles

✅ **Single Responsibility**
- `Scheduler` - Task orchestration only
- `ScheduledTask` - Task configuration only
- `CacheMutex` - Overlap prevention only

✅ **Open/Closed**
- Add new frequency methods without modifying existing code
- Custom mutex implementations via `MutexInterface`

✅ **Liskov Substitution**
- Any `MutexInterface` implementation is interchangeable

✅ **Interface Segregation**
- Minimal, focused interfaces

✅ **Dependency Inversion**
- Depends on `ContainerInterface`, not concrete container
- Depends on `MutexInterface`, not concrete mutex

### Clean Architecture

```
┌─────────────────────────────────────┐
│         Task Definitions            │
│   (Application Layer)               │
├─────────────────────────────────────┤
│   Scheduler + ScheduledTask         │
│   (Framework Layer)                 │
├─────────────────────────────────────┤
│   Cache, Container, Console         │
│   (Infrastructure)                  │
└─────────────────────────────────────┘
```

### Performance Characteristics

| Operation | Time Complexity | Space Complexity |
|-----------|----------------|------------------|
| Register task | O(1) | O(1) |
| Get due tasks | O(N) | O(N) |
| Execute tasks | O(N × T) | O(1) |
| Mutex check | O(1) | O(1) |

Where:
- N = number of tasks
- T = task execution time

---

## Troubleshooting

### Tasks Not Running

**Problem**: Scheduled tasks don't execute.

**Solutions**:
1. Check cron is configured: `crontab -l`
2. Verify cron entry has correct path
3. Check PHP binary path: `which php`
4. Run manually: `php console schedule:run -v`
5. Check task is due: `php console schedule:list`

### Overlap Issues

**Problem**: Tasks running multiple times simultaneously.

**Solutions**:
1. Add `withoutOverlapping()`:
   ```php
   $schedule->call($callback)->withoutOverlapping();
   ```
2. Check mutex backend (Redis recommended for distributed systems)
3. Increase mutex expiration if tasks are long-running

### Background Execution Not Working

**Problem**: `runInBackground()` doesn't work.

**Solutions**:
1. Check `pcntl_fork` availability: `php -m | grep pcntl`
2. Install PCNTL extension (Linux/macOS only)
3. Or use queue jobs instead:
   ```php
   $schedule->job(MyJob::class)->runInBackground();
   ```

### Memory Issues

**Problem**: Scheduler using too much memory.

**Solutions**:
1. Don't store large data in closures
2. Use queue jobs for heavy tasks
3. Clear variables after use: `unset($largeArray)`
4. Use generators for large datasets

---

## Comparison with Laravel

| Feature | Toporia | Laravel |
|---------|---------|---------|
| Core scheduling | ✅ | ✅ |
| Cron expressions | ✅ | ✅ |
| Overlap prevention | ✅ | ✅ |
| Background execution | ✅ | ✅ |
| Output handling | ✅ | ✅ |
| Hooks (before/after) | ✅ | ✅ |
| Success/Failure callbacks | ✅ | ✅ |
| Email notifications | ✅ | ✅ |
| HTTP ping integration | ❌ | ✅ |
| Environment constraints | ❌ | ✅ |
| Maintenance mode | ❌ | ✅ |
| Dependencies | 0 | 2+ |
| Lines of code | 1,245 | 2,500+ |

**Toporia advantages:**
- Zero dependencies
- Simpler, cleaner codebase
- Better SOLID compliance
- Faster for simple use cases

---

## API Reference

### Scheduler Methods

```php
// Task registration
call(callable $callback, ?string $description): ScheduledTask
exec(string $command, ?string $description): ScheduledTask
command(string $command, array $options, ?string $description): ScheduledTask
job(string $jobClass, ?string $description): ScheduledTask

// Task retrieval
getTasks(): array
getDueTasks(): array
listTasks(): array

// Execution
runDueTasks(): int
```

### ScheduledTask Methods

```php
// Frequency
cron(string $expression): self
everyMinute(): self
everyMinutes(int $minutes): self
hourly(): self
hourlyAt(int $minute): self
daily(): self
dailyAt(string $time): self
weekly(): self
monthly(): self
weekdays(): self
weekends(): self
mondays/tuesdays/.../sundays(): self

// Constraints
when(callable $callback): self
skip(callable $callback): self
timezone(string $timezone): self

// Overlap prevention
withoutOverlapping(int $expiresAfter = 1440): self
name(string $mutexName): self

// Output
sendOutputTo(string $location): self
appendOutputTo(string $location): self
emailOutputTo(string $email): self
emailOutputOnFailure(string $email): self

// Hooks
before(\Closure $callback): self
after(\Closure $callback): self
then(\Closure $callback): self
onSuccess(\Closure $callback): self
onFailure(\Closure $callback): self

// Execution
runInBackground(): self
runInForeground(): self
description(string $description): self
```

---

## Related Documentation

- [Console Commands](../src/Framework/Console/)
- [Queue/Jobs](BUS.md)
- [Cache System](../src/Framework/Cache/)
- [Container/DI](../src/Framework/Container/)
