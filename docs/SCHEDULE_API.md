# Task Scheduling System - API Reference

## 📚 Mục lục

1. [Scheduler](#scheduler)
2. [ScheduledTask](#scheduledtask)
3. [CronExpression](#cronexpression)
4. [TaskHistory](#taskhistory)
5. [Commands](#commands)

---

## Scheduler

### Methods

```php
// Register a callable task
public function call(callable $callback): ScheduledTask

// Register an Artisan command
public function command(string $command, array $parameters = []): ScheduledTask

// Register a queue job
public function job(JobInterface $job): ScheduledTask

// Register a shell command
public function exec(string $command, array $parameters = []): ScheduledTask

// Get all tasks
public function getTasks(): array

// Get due tasks
public function getDueTasks(?\DateTime $currentTime = null): array

// Execute a task
public function executeTask(ScheduledTask $task): void

// Run due tasks
public function runDueTasks(): void
```

---

## ScheduledTask

### Frequency Methods

```php
// Every minute
public function everyMinute(): self

// Every N minutes
public function everyFiveMinutes(): self
public function everyTenMinutes(): self
public function everyFifteenMinutes(): self
public function everyThirtyMinutes(): self

// Hourly
public function hourly(): self
public function hourlyAt(int $minute): self

// Daily
public function daily(): self
public function dailyAt(string $time): self // Format: 'HH:MM'
public function twiceDaily(int $first = 1, int $second = 13): self

// Weekly
public function weekly(): self
public function weeklyOn(int $day, string $time = '0:0'): self

// Monthly
public function monthly(): self
public function monthlyOn(int $day = 1, string $time = '0:0'): self

// Quarterly
public function quarterly(): self

// Yearly
public function yearly(): self

// Custom cron
public function cron(string $expression): self
```

### Constraint Methods

```php
// Time constraints
public function between(string $start, string $end): self // Format: 'HH:MM'
public function unlessBetween(string $start, string $end): self

// Day constraints
public function weekdays(): self
public function weekends(): self
public function mondays(): self
public function tuesdays(): self
public function wednesdays(): self
public function thursdays(): self
public function fridays(): self
public function saturdays(): self
public function sundays(): self

// Environment constraints
public function environments(string|array $environments): self

// Conditional
public function when(callable $callback): self
public function skip(callable $callback): self
```

### Feature Methods

```php
// Set task ID (required for history/dependencies)
public function taskId(string $id): self

// Set description
public function description(string $description): self

// Set priority
public function priority(int $priority): self

// Set dependencies
public function dependsOn(string|array $taskIds): self

// Set retry
public function retry(int $maxRetries): self
public function retryDelay(int $seconds): self
public function exponentialBackoff(): self

// Set timeout
public function timeout(int $seconds): self

// Set memory limit
public function memoryLimit(int $mb): self

// Mutex (prevent overlapping)
public function withoutOverlapping(int $releaseAfter = 300): self

// Maintenance mode
public function evenInMaintenanceMode(): self

// History tracking
public function trackHistory(): self
```

### Getter Methods

```php
public function getTaskId(): ?string
public function getDescription(): string
public function getCronExpression(): ?string
public function getPriority(): int
public function getDependencies(): array
public function getMaxRetries(): int
public function getRetryDelay(): int
public function hasExponentialBackoff(): bool
public function getTimeout(): ?int
public function getMemoryLimit(): int
public function isDue(?\DateTime $currentTime = null, ?string $basePath = null): bool
```

---

## CronExpression

### Constructor

```php
public function __construct(string $expression)
```

### Methods

```php
// Check if matches time
public function matches(\DateTime $time): bool

// Get next run time
public function getNextRunTime(\DateTime $fromTime): \DateTime

// Get description
public function getDescription(): string

// Get expression
public function getExpression(): string

// Validate expression
public static function isValid(string $expression): bool
```

### Examples

```php
$cron = new CronExpression('0 0 * * *');
$cron->matches(new \DateTime()); // Check if due now
$next = $cron->getNextRunTime(new \DateTime()); // Get next run
$desc = $cron->getDescription(); // "Daily at midnight"
```

---

## TaskHistory

### Static Methods

```php
// Set cache instance
public static function setCache(CacheInterface $cache): void

// Add history record
public static function addRecord(
    string $taskId,
    bool $success,
    float $duration,
    int $memory,
    ?string $message = null
): void

// Get history records
public static function getRecords(string $taskId): array

// Get latest record
public static function getLatestRecord(string $taskId): ?array

// Check if completed successfully
public static function hasCompletedSuccessfully(
    string $taskId,
    \DateTime $currentTime,
    int $withinSeconds = 60
): bool

// Get statistics
public static function getStatistics(string $taskId): array

// Clear task history
public static function clearTaskHistory(string $taskId): void

// Clear all history
public static function clearAllHistory(): void
```

### Statistics Format

```php
[
    'total_runs' => 100,
    'successful_runs' => 95,
    'failed_runs' => 5,
    'success_rate' => 95.0,
    'avg_duration' => 2.5,
    'avg_memory_mb' => 50.0,
    'last_run' => '2025-01-23 12:00:00',
    'last_status' => 'Success'
]
```

---

## Commands

### schedule:run

Run due scheduled tasks.

```bash
php console schedule:run [--verbose]
```

### schedule:list

List all registered scheduled tasks.

```bash
php console schedule:list
```

Output includes:
- Task ID
- Description
- Cron expression
- Next run time
- Priority
- Dependencies
- Timeout
- Memory limit
- Statistics

### schedule:test

Test scheduled tasks.

```bash
# Test all due tasks
php console schedule:test --due

# Test specific task
php console schedule:test {taskId}

# Test all tasks
php console schedule:test --all

# Verbose output
php console schedule:test {taskId} --verbose
```

---

## Examples

### Complete Task Example

```php
$scheduler->call(function () {
    // Task logic
    processData();
})
    ->daily()
    ->at('02:00')
    ->taskId('process-data')
    ->description('Process daily data')
    ->priority(10)
    ->dependsOn(['backup-database'])
    ->retry(3)
    ->retryDelay(60)
    ->exponentialBackoff()
    ->timeout(600)
    ->memoryLimit(256)
    ->withoutOverlapping(300)
    ->trackHistory()
    ->weekdays()
    ->between('01:00', '05:00');
```

### Task with Dependencies

```php
// Task 1: Backup
$backup = $scheduler->call(fn() => backup())
    ->daily()
    ->at('01:00')
    ->taskId('backup-database');

// Task 2: Cleanup (depends on backup)
$cleanup = $scheduler->call(fn() => cleanup())
    ->daily()
    ->at('02:00')
    ->taskId('cleanup-files')
    ->dependsOn('backup-database');

// Task 3: Report (depends on cleanup)
$report = $scheduler->call(fn() => generateReport())
    ->daily()
    ->at('03:00')
    ->taskId('generate-report')
    ->dependsOn(['backup-database', 'cleanup-files']);
```

### Task with Retry

```php
$scheduler->call(fn() => syncExternalAPI())
    ->everyFiveMinutes()
    ->taskId('sync-api')
    ->retry(5)
    ->retryDelay(120)
    ->exponentialBackoff()
    ->timeout(60);
```

### Task with History

```php
$scheduler->call(fn() => generateReport())
    ->daily()
    ->taskId('generate-report')
    ->trackHistory();

// Later, get statistics
$stats = TaskHistory::getStatistics('generate-report');
```

---

## Maintenance Mode

Tasks automatically skip during maintenance mode unless explicitly allowed:

```php
// Skip during maintenance (default)
$scheduler->call(fn() => sendNotifications())
    ->hourly();

// Run even during maintenance
$scheduler->call(fn() => healthCheck())
    ->everyFiveMinutes()
    ->evenInMaintenanceMode();
```

---

## Mutex/Locking

Prevent overlapping task execution:

```php
$scheduler->call(fn() => syncData())
    ->everyFiveMinutes()
    ->withoutOverlapping(300); // Release lock after 5 minutes if stuck
```

The mutex uses cache to track running tasks and prevents multiple instances from running simultaneously.









