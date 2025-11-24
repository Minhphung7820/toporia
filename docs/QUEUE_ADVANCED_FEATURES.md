# Queue Advanced Features

This document covers advanced Queue/Job features including timeouts, events, and worker auto-restart.

## Table of Contents

1. [Job Timeout](#job-timeout)
2. [Queue Events](#queue-events)
3. [Worker Auto-Restart](#worker-auto-restart)

---

## Job Timeout

Control maximum execution time for jobs to prevent hung processes and resource exhaustion.

### Configuration

```php
use Toporia\Framework\Queue\Job;

class ProcessVideoJob extends Job
{
    protected int $timeout = 300; // 5 minutes

    public function handle(): void
    {
        // Long-running video processing
        $this->processVideo($this->videoPath);
    }

    /**
     * Called when job times out
     */
    public function timeout(): void
    {
        // Cleanup resources, cancel external API calls, etc.
        $this->cancelProcessing();
        $this->releaseResources();
    }
}
```

### Fluent API

```php
// Set timeout via method chaining
ProcessVideoJob::dispatch($videoPath)
    ->setTimeout(600) // 10 minutes
    ->onQueue('videos');
```

### How It Works

- Uses `pcntl_alarm()` for Linux/macOS (requires `ext-pcntl`)
- Throws `JobTimeoutException` when timeout is exceeded
- Calls `timeout()` callback for cleanup
- Automatically retries based on retry configuration
- Timeout exception counts as a failure attempt

### Requirements

- PHP extension: `ext-pcntl` (Linux/macOS only, not available on Windows)
- If `pcntl_alarm` is not available, timeout feature is silently disabled

### Example: API Call with Timeout

```php
class FetchExternalDataJob extends Job
{
    protected int $timeout = 30; // 30 seconds
    protected int $maxAttempts = 3;

    private ?resource $curlHandle = null;

    public function handle(): void
    {
        $this->curlHandle = curl_init($this->apiUrl);
        curl_setopt($this->curlHandle, CURLOPT_TIMEOUT, 25);

        $response = curl_exec($this->curlHandle);
        $this->processResponse($response);
    }

    public function timeout(): void
    {
        // Cancel ongoing curl request
        if ($this->curlHandle) {
            curl_close($this->curlHandle);
        }

        // Log timeout for monitoring
        log_warning("API call timed out", ['url' => $this->apiUrl]);
    }
}
```

---

## Queue Events

Listen to job lifecycle events for monitoring, logging, and metrics tracking.

### Available Events

| Event | Description | Properties |
|-------|-------------|------------|
| `JobQueued` | Job pushed to queue | `job`, `queue`, `delay` |
| `JobProcessing` | Job execution started | `job`, `attempt` |
| `JobProcessed` | Job completed successfully | `job`, `attempt` |
| `JobFailed` | Job failed with exception | `job`, `exception`, `attempt`, `willRetry` |
| `JobTimedOut` | Job exceeded timeout | `job`, `timeout`, `attempt` |
| `JobRetrying` | Job being retried | `job`, `attempt`, `delay`, `exception` |
| `WorkerStopping` | Worker graceful shutdown | `processedJobs` |

### Listening to Events

#### 1. Via Service Provider

```php
use Toporia\Framework\Events\Contracts\EventDispatcherInterface;
use Toporia\Framework\Queue\Events\JobFailed;

class AppServiceProvider extends ServiceProvider
{
    public function boot(ContainerInterface $container): void
    {
        $dispatcher = $container->get(EventDispatcherInterface::class);

        // Listen to job failures
        $dispatcher->listen(JobFailed::class, function (JobFailed $event) {
            log_error('Job failed', [
                'job_id' => $event->getJob()->getId(),
                'exception' => $event->getException()->getMessage(),
                'attempt' => $event->getAttempt(),
                'will_retry' => $event->willRetry()
            ]);

            // Send alert if final failure
            if (!$event->willRetry()) {
                $this->sendAlert($event);
            }
        });
    }
}
```

#### 2. Via Listener Class

```php
use Toporia\Framework\Events\Contracts\ListenerInterface;
use Toporia\Framework\Queue\Events\JobProcessed;

class JobMetricsListener implements ListenerInterface
{
    public function __construct(
        private MetricsService $metrics
    ) {}

    public function handle($event): void
    {
        if ($event instanceof JobProcessed) {
            $this->metrics->increment('jobs.processed');
            $this->metrics->gauge('jobs.attempt', $event->getAttempt());
        }
    }
}

// Register in provider
$dispatcher->listenClass(JobProcessed::class, JobMetricsListener::class);
```

#### 3. Wildcard Listeners

```php
// Listen to all Job events
$dispatcher->listen('Toporia\\Framework\\Queue\\Events\\*', function ($event) {
    log_debug('Queue event', [
        'event' => get_class($event),
        'timestamp' => time()
    ]);
});
```

### Example: Job Performance Monitoring

```php
class JobPerformanceMonitor
{
    private array $timers = [];

    public function register(EventDispatcherInterface $dispatcher): void
    {
        // Start timer when job begins
        $dispatcher->listen(JobProcessing::class, function (JobProcessing $event) {
            $this->timers[$event->getJob()->getId()] = microtime(true);
        });

        // Calculate duration when job completes
        $dispatcher->listen(JobProcessed::class, function (JobProcessed $event) {
            $jobId = $event->getJob()->getId();
            $duration = microtime(true) - $this->timers[$jobId];

            // Send to monitoring service
            $this->metrics->timing('job.duration', $duration, [
                'job_class' => get_class($event->getJob()),
                'attempt' => $event->getAttempt()
            ]);

            unset($this->timers[$jobId]);
        });
    }
}
```

### Example: Alert on Repeated Failures

```php
class JobFailureAlerter
{
    private array $failures = [];

    public function register(EventDispatcherInterface $dispatcher): void
    {
        $dispatcher->listen(JobFailed::class, function (JobFailed $event) {
            $jobClass = get_class($event->getJob());

            // Track failures per job class
            if (!isset($this->failures[$jobClass])) {
                $this->failures[$jobClass] = 0;
            }

            $this->failures[$jobClass]++;

            // Alert if more than 10 failures in a row
            if ($this->failures[$jobClass] >= 10) {
                $this->sendCriticalAlert(
                    "High failure rate for {$jobClass}",
                    $event->getException()
                );

                $this->failures[$jobClass] = 0; // Reset counter
            }
        });
    }
}
```

---

## Worker Auto-Restart

Automatically restart workers based on memory usage or runtime thresholds.

### Console Options

```bash
# Memory limit (default: 128 MB)
php console queue:work --memory=256

# Runtime limit (default: unlimited)
php console queue:work --timeout=3600  # 1 hour

# Combined with other options
php console queue:work \
    --memory=512 \
    --timeout=7200 \
    --max-jobs=1000 \
    --queue=emails,default
```

### How It Works

**Memory Monitoring:**
- Checks memory after each job using `memory_get_usage(true)`
- Gracefully stops when threshold exceeded
- Prevents memory leaks from accumulating

**Runtime Limiting:**
- Tracks worker start time
- Stops after configured seconds
- Prevents long-running workers

**Graceful Shutdown:**
- Completes current job before stopping
- Exit code 0 (success) to allow supervisor to restart
- Dispatches `WorkerStopping` event for cleanup

### Process Supervisor Configuration

#### Supervisor (Linux)

```ini
[program:queue-worker]
command=php /path/to/console queue:work --memory=256 --timeout=3600
process_name=%(program_name)s_%(process_num)02d
numprocs=4
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/queue-worker.log
```

#### systemd (Linux)

```ini
[Unit]
Description=Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/path/to/project
ExecStart=/usr/bin/php console queue:work --memory=256 --timeout=3600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

### Example: Worker Lifecycle Monitoring

```php
class WorkerMonitor
{
    public function register(EventDispatcherInterface $dispatcher): void
    {
        $dispatcher->listen(WorkerStopping::class, function (WorkerStopping $event) {
            // Log worker stats before shutdown
            log_info('Worker stopping', [
                'processed_jobs' => $event->getProcessedJobs(),
                'memory_peak' => memory_get_peak_usage(true) / 1024 / 1024 . ' MB',
                'runtime' => gmdate('H:i:s', time() - WORKER_START_TIME)
            ]);

            // Flush any pending metrics
            $this->metrics->flush();

            // Close database connections
            $this->db->disconnect();
        });
    }
}
```

---

## Performance Tips

### 1. Optimize Timeout Values

```php
// Too short: causes unnecessary retries
protected int $timeout = 5; // ❌ Too aggressive

// Too long: wastes resources on hung processes
protected int $timeout = 3600; // ❌ Too generous

// Just right: realistic expectation + buffer
protected int $timeout = 120; // ✅ 2 minutes for typical API call
```

### 2. Event Listener Efficiency

```php
// Bad: Heavy processing in event listener
$dispatcher->listen(JobProcessed::class, function ($event) {
    $this->sendSlackNotification($event); // ❌ Blocks worker
    $this->updateDashboard($event);       // ❌ Slow I/O
});

// Good: Queue notifications as separate jobs
$dispatcher->listen(JobProcessed::class, function ($event) {
    if ($this->shouldNotify($event)) {
        SendNotificationJob::dispatch($event)->onQueue('notifications'); // ✅
    }
});
```

### 3. Memory Limit Tuning

```bash
# Small jobs (emails, notifications): low memory
php console queue:work --memory=64

# Medium jobs (image processing): moderate memory
php console queue:work --memory=256

# Large jobs (video, data imports): high memory
php console queue:work --memory=512
```

### 4. Runtime Limit Strategy

```bash
# Short-lived workers (high job variability)
php console queue:work --timeout=1800  # 30 minutes

# Long-lived workers (stable job patterns)
php console queue:work --timeout=14400  # 4 hours

# No limit (careful monitoring required)
php console queue:work  # Default: unlimited
```

---

## Architecture & Design

### SOLID Principles

- **Single Responsibility**: Each event class represents one lifecycle moment
- **Open/Closed**: Extensible via event listeners without modifying Worker
- **Liskov Substitution**: All events extend base Event class
- **Interface Segregation**: Focused event interfaces
- **Dependency Inversion**: Worker depends on EventDispatcherInterface

### Clean Architecture

```
┌─────────────────────────────────────┐
│         Worker                      │
│  (Queue Processing Logic)           │
├─────────────────────────────────────┤
│   Events (Domain Events)            │
│   JobProcessing, JobFailed, etc.    │
├─────────────────────────────────────┤
│   EventDispatcher                   │
│   (Infrastructure)                  │
└─────────────────────────────────────┘
```

### Performance Characteristics

| Feature | Time Complexity | Space Complexity |
|---------|----------------|------------------|
| Timeout checking | O(1) | O(1) |
| Event dispatch | O(N) listeners | O(1) |
| Memory check | O(1) | O(1) |
| Runtime check | O(1) | O(1) |

---

## Security Considerations

### 1. Timeout Abuse Prevention

```php
// Prevent users from setting unreasonably long timeouts
class ProcessUserUploadJob extends Job
{
    protected int $timeout = 300; // Fixed at 5 minutes

    // Don't allow setTimeout() to be called externally
    private function setTimeout(int $seconds): never
    {
        throw new \RuntimeException('Timeout cannot be changed for this job');
    }
}
```

### 2. Event Data Sanitization

```php
// Don't expose sensitive data in events
$dispatcher->listen(JobFailed::class, function (JobFailed $event) {
    log_error('Job failed', [
        'job_class' => get_class($event->getJob()),
        'exception' => $event->getException()->getMessage(),
        // ❌ Don't log: $event->getJob()->creditCardNumber
        // ✅ Log sanitized data only
    ]);
});
```

### 3. Resource Cleanup

```php
class ImportFileJob extends Job
{
    private $fileHandle = null;

    public function handle(): void
    {
        $this->fileHandle = fopen($this->filePath, 'r');
        // Process file...
    }

    public function timeout(): void
    {
        // CRITICAL: Always close resources
        if ($this->fileHandle) {
            fclose($this->fileHandle);
        }
    }

    public function failed(\Throwable $e): void
    {
        // CRITICAL: Close on failure too
        if ($this->fileHandle) {
            fclose($this->fileHandle);
        }
    }
}
```

---

## Troubleshooting

### Timeouts Not Working

**Problem**: Jobs exceed timeout without being killed.

**Solutions**:
1. Check `ext-pcntl` is installed: `php -m | grep pcntl`
2. Verify OS support (Linux/macOS only, not Windows)
3. Check Worker logs for timeout messages
4. Ensure timeout > 0 (0 means disabled)

### Events Not Firing

**Problem**: Event listeners not being called.

**Solutions**:
1. Verify EventDispatcher is registered in container
2. Check listener is registered before worker starts
3. Use wildcard listener to debug: `listen('*', callback)`
4. Check Worker has access to container with EventDispatcher

### Memory Restarts Too Frequent

**Problem**: Worker constantly restarting due to memory limit.

**Solutions**:
1. Increase memory limit: `--memory=512`
2. Profile jobs for memory leaks
3. Process fewer items per job
4. Clear large arrays after use: `unset($largeArray)`
5. Disable memory limit for debug: `--memory=0`

### Runtime Restarts Unexpected

**Problem**: Worker stops before expected.

**Solutions**:
1. Check runtime calculation (seconds, not minutes)
2. Verify supervisor is configured for auto-restart
3. Monitor worker logs for restart reasons
4. Use longer runtime: `--timeout=7200` (2 hours)

---

## Related Documentation

- [Queue/Job System Overview](BUS.md)
- [Worker Configuration](../config/queue.php)
- [Event System](../src/Framework/Events/)
- [Console Commands](../src/Framework/Console/Commands/)
