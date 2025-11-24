<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Scheduling;

use Toporia\Framework\Console\Scheduling\Contracts\MutexInterface;
use Toporia\Framework\Container\Contracts\ContainerInterface;

/**
 * Task Scheduler
 *
 * Manages scheduled tasks (cron-like functionality).
 * Provides fluent interface for defining task schedules.
 *
 * Performance:
 * - O(N) get due tasks where N = total tasks
 * - O(1) mutex operations
 * - Cached cron expression evaluation
 *
 * Clean Architecture:
 * - Single Responsibility: Task orchestration only
 * - Dependency Inversion: Uses interfaces
 * - Open/Closed: Extensible via tasks
 */
final class Scheduler
{
    /**
     * @var ScheduledTask[]
     */
    private array $tasks = [];

    /**
     * @var ContainerInterface|null
     */
    private ?ContainerInterface $container = null;

    /**
     * @var MutexInterface|null
     */
    private ?MutexInterface $mutex = null;

    /**
     * @var string|null Application base path for maintenance mode check
     */
    private ?string $basePath = null;

    /**
     * Set container for dependency injection
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * Set mutex for overlap prevention
     *
     * @param MutexInterface $mutex
     * @return void
     */
    public function setMutex(MutexInterface $mutex): void
    {
        $this->mutex = $mutex;
    }

    /**
     * Set application base path for maintenance mode check
     *
     * @param string $basePath
     * @return void
     */
    public function setBasePath(string $basePath): void
    {
        $this->basePath = $basePath;
    }

    /**
     * Schedule a callback to run
     *
     * @param callable $callback
     * @param string|null $description
     * @return ScheduledTask
     */
    public function call(callable $callback, ?string $description = null): ScheduledTask
    {
        $task = new ScheduledTask($callback, $description);
        $this->tasks[] = $task;
        return $task;
    }

    /**
     * Schedule a command to run
     *
     * @param string $command Shell command
     * @param string|null $description
     * @return ScheduledTask
     */
    public function exec(string $command, ?string $description = null): ScheduledTask
    {
        return $this->call(function () use ($command) {
            exec($command);
        }, $description ?? "Execute: {$command}");
    }

    /**
     * Schedule a job to be queued
     *
     * @param string $jobClass Job class name
     * @param string|null $description
     * @return ScheduledTask
     */
    public function job(string $jobClass, ?string $description = null): ScheduledTask
    {
        return $this->call(function () use ($jobClass) {
            $job = new $jobClass();
            app('queue')->push($job);
        }, $description ?? "Queue job: {$jobClass}");
    }

    /**
     * Schedule a console command to run
     *
     * @param string $command Command signature (e.g., 'cache:clear', 'migrate')
     * @param array $options Command options (e.g., ['--store' => 'redis'])
     * @param string|null $description
     * @return ScheduledTask
     */
    public function command(string $command, array $options = [], ?string $description = null): ScheduledTask
    {
        return $this->call(function () use ($command, $options) {
            if (!$this->container) {
                throw new \RuntimeException('Container must be set to run console commands');
            }

            // Get console application
            $console = $this->container->get(\Toporia\Framework\Console\Application::class);

            // Build arguments array
            $arguments = [$command];

            // Add options
            foreach ($options as $key => $value) {
                if (is_int($key)) {
                    // Flag without value (e.g., '--force')
                    $arguments[] = $value;
                } else {
                    // Option with value (e.g., '--store=redis')
                    if (str_starts_with($key, '--')) {
                        $arguments[] = "{$key}={$value}";
                    } else {
                        $arguments[] = "--{$key}={$value}";
                    }
                }
            }

            // Run command
            $console->run($arguments);
        }, $description ?? "Run command: {$command}");
    }

    /**
     * Get all scheduled tasks
     *
     * @return ScheduledTask[]
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    /**
     * Get tasks that are due to run
     *
     * @param \DateTime|null $currentTime
     * @return ScheduledTask[]
     */
    public function getDueTasks(?\DateTime $currentTime = null): array
    {
        $currentTime = $currentTime ?? new \DateTime();
        $dueTasks = [];

        foreach ($this->tasks as $task) {
            if ($task->isDue($currentTime, $this->basePath)) {
                $dueTasks[] = $task;
            }
        }

        return $dueTasks;
    }

    /**
     * Run all tasks that are due
     *
     * @param \DateTime|null $currentTime
     * @return int Number of tasks executed
     */
    public function runDueTasks(?\DateTime $currentTime = null): int
    {
        $dueTasks = $this->getDueTasks($currentTime);
        $count = 0;

        foreach ($dueTasks as $task) {
            // Check for one-server execution
            if ($task->shouldRunOnOneServer() && $this->mutex) {
                $oneServerMutex = $task->getOneServerMutexName();
                if ($oneServerMutex && $this->mutex->exists($oneServerMutex)) {
                    echo "Skipping task (running on another server): {$task->getDescription()}\n";
                    continue;
                }
                if ($oneServerMutex && !$this->mutex->create($oneServerMutex, 5)) {
                    // Lock expires in 5 minutes (should be enough for most tasks)
                    echo "Failed to acquire one-server lock: {$task->getDescription()}\n";
                    continue;
                }
            }

            // Check for overlap prevention
            if ($task->hasOverlapPrevention() && $this->mutex) {
                $mutexName = $task->getMutexName();

                // Skip if task is already running
                if ($this->mutex->exists($mutexName)) {
                    echo "Skipping task (already running): {$task->getDescription()}\n";
                    if ($task->shouldRunOnOneServer()) {
                        $this->mutex->forget($task->getOneServerMutexName());
                    }
                    continue;
                }

                // Acquire mutex lock
                if (!$this->mutex->create($mutexName, $task->getExpiresAfter())) {
                    echo "Failed to acquire lock for task: {$task->getDescription()}\n";
                    if ($task->shouldRunOnOneServer()) {
                        $this->mutex->forget($task->getOneServerMutexName());
                    }
                    continue;
                }

                // Execute task
                try {
                    echo "Running task: {$task->getDescription()}\n";

                    if ($task->shouldRunInBackground()) {
                        $this->runTaskInBackground($task, $mutexName);
                    } else {
                        $this->executeTask($task);
                        $this->mutex->forget($mutexName);
                    }

                    // Release one-server lock
                    if ($task->shouldRunOnOneServer()) {
                        $this->mutex->forget($task->getOneServerMutexName());
                    }

                    echo "Task completed: {$task->getDescription()}\n";
                    $count++;
                } catch (\Throwable $e) {
                    $this->mutex->forget($mutexName);
                    if ($task->shouldRunOnOneServer()) {
                        $this->mutex->forget($task->getOneServerMutexName());
                    }
                    echo "Task failed: {$task->getDescription()} - {$e->getMessage()}\n";
                }
            } else {
                // No overlap prevention - just run the task
                try {
                    echo "Running task: {$task->getDescription()}\n";

                    if ($task->shouldRunInBackground()) {
                        $this->runTaskInBackground($task);
                    } else {
                        $this->executeTask($task);
                    }

                    // Release one-server lock
                    if ($task->shouldRunOnOneServer() && $this->mutex) {
                        $this->mutex->forget($task->getOneServerMutexName());
                    }

                    echo "Task completed: {$task->getDescription()}\n";
                    $count++;
                } catch (\Throwable $e) {
                    if ($task->shouldRunOnOneServer() && $this->mutex) {
                        $this->mutex->forget($task->getOneServerMutexName());
                    }
                    echo "Task failed: {$task->getDescription()} - {$e->getMessage()}\n";
                }
            }
        }

        return $count;
    }

    /**
     * Execute task with hooks and output handling.
     *
     * Performance: O(1) hooks execution, O(1) HTTP ping
     *
     * @param ScheduledTask $task
     * @return void
     * @throws \Throwable
     */
    private function executeTask(ScheduledTask $task): void
    {
        $output = '';
        $success = false;
        $exception = null;

        // Get HTTP client for ping (if available)
        $httpClient = $this->container?->has('http.client')
            ? $this->container->get('http.client')
            : null;

        try {
            // HTTP ping before execution
            if ($pingUrl = $task->getPingBeforeUrl()) {
                \Toporia\Framework\Console\Scheduling\Support\HttpPing::send($pingUrl, [
                    'task' => $task->getDescription(),
                    'event' => 'before',
                    'time' => date('Y-m-d H:i:s')
                ], $httpClient);
            }

            // Execute before callback
            if ($before = $task->getBeforeCallback()) {
                $before();
            }

            // Capture output if needed
            if ($task->getOutputFile() || $task->getEmailOutputTo()) {
                ob_start();
            }

            // Execute the task
            $task->execute();
            $success = true;

            // Capture output
            if ($task->getOutputFile() || $task->getEmailOutputTo()) {
                $output = ob_get_clean();
            }

            // HTTP ping on success
            if ($success && $pingUrl = $task->getPingSuccessUrl()) {
                \Toporia\Framework\Console\Scheduling\Support\HttpPing::send($pingUrl, [
                    'task' => $task->getDescription(),
                    'event' => 'success',
                    'time' => date('Y-m-d H:i:s')
                ], $httpClient);
            }

            // Execute after callback
            if ($after = $task->getAfterCallback()) {
                $after();
            }

            // Execute onSuccess callback
            if ($onSuccess = $task->getOnSuccessCallback()) {
                $onSuccess();
            }

            // HTTP ping after execution
            if ($pingUrl = $task->getPingAfterUrl()) {
                \Toporia\Framework\Console\Scheduling\Support\HttpPing::send($pingUrl, [
                    'task' => $task->getDescription(),
                    'event' => 'after',
                    'success' => $success,
                    'time' => date('Y-m-d H:i:s')
                ], $httpClient);
            }
        } catch (\Throwable $e) {
            // Capture output on failure
            if ($task->getOutputFile() || $task->getEmailOutputTo()) {
                $output = ob_get_clean();
            }

            $exception = $e;
            $success = false;

            // HTTP ping on failure
            if ($pingUrl = $task->getPingFailureUrl()) {
                \Toporia\Framework\Console\Scheduling\Support\HttpPing::send($pingUrl, [
                    'task' => $task->getDescription(),
                    'event' => 'failure',
                    'error' => $e->getMessage(),
                    'time' => date('Y-m-d H:i:s')
                ], $httpClient);
            }

            // Execute onFailure callback
            if ($onFailure = $task->getOnFailureCallback()) {
                $onFailure($e);
            }

            throw $e;
        } finally {
            // Handle output file
            if ($outputFile = $task->getOutputFile()) {
                $this->writeOutput($outputFile, $output, $task->shouldAppendOutput());
            }

            // Handle email output (improved - uses MailManager)
            if ($emailTo = $task->getEmailOutputTo()) {
                $shouldEmail = !$task->shouldEmailOnlyOnFailure() || !$success;
                if ($shouldEmail) {
                    $this->emailOutput($emailTo, $task, $output, $success, $exception);
                }
            }
        }
    }

    /**
     * Write output to file.
     *
     * @param string $file
     * @param string $output
     * @param bool $append
     * @return void
     */
    private function writeOutput(string $file, string $output, bool $append): void
    {
        $directory = dirname($file);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $mode = $append ? 'a' : 'w';
        file_put_contents($file, $output, $append ? FILE_APPEND : 0);
    }

    /**
     * Email task output using MailManager (if available) or fallback to mail().
     *
     * Performance: O(1) - Single email send
     *
     * @param string $email
     * @param ScheduledTask $task
     * @param string $output
     * @param bool $success
     * @param \Throwable|null $exception
     * @return void
     */
    private function emailOutput(
        string $email,
        ScheduledTask $task,
        string $output,
        bool $success,
        ?\Throwable $exception
    ): void {
        $subject = $success
            ? "Scheduled Task Completed: {$task->getDescription()}"
            : "Scheduled Task Failed: {$task->getDescription()}";

        $body = "Task: {$task->getDescription()}\n";
        $body .= "Status: " . ($success ? 'Success' : 'Failed') . "\n";
        $body .= "Time: " . date('Y-m-d H:i:s') . "\n\n";

        if ($exception) {
            $body .= "Error: {$exception->getMessage()}\n\n";
            $body .= "Stack Trace:\n{$exception->getTraceAsString()}\n\n";
        }

        $body .= "Output:\n{$output}";

        // Try to use MailManager if available (Clean Architecture - DIP)
        if ($this->container && $this->container->has(\Toporia\Framework\Mail\Contracts\MailManagerInterface::class)) {
            try {
                $mailer = $this->container->get(\Toporia\Framework\Mail\Contracts\MailManagerInterface::class);
                $message = new \Toporia\Framework\Mail\Message();
                $message->to($email)
                    ->subject($subject)
                    ->html(nl2br(htmlspecialchars($body)));
                $mailer->send($message);
                return;
            } catch (\Throwable $e) {
                // Fallback to mail() if MailManager fails
                error_log("MailManager failed, using mail() fallback: {$e->getMessage()}");
            }
        }

        // Fallback: Use mail() function
        mail($email, $subject, $body);
    }

    /**
     * Run task in background.
     *
     * Performance: O(1) - Process fork or shell execution
     *
     * @param ScheduledTask $task
     * @param string|null $mutexName
     * @return void
     */
    private function runTaskInBackground(ScheduledTask $task, ?string $mutexName = null): void
    {
        // Fork process to run in background (Unix-like systems only)
        if (function_exists('pcntl_fork')) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                throw new \RuntimeException('Failed to fork process');
            }

            if ($pid === 0) {
                // Child process
                try {
                    $this->executeTask($task);
                } catch (\Throwable $e) {
                    error_log("Background task failed: {$e->getMessage()}");
                } finally {
                    if ($mutexName && $this->mutex) {
                        $this->mutex->forget($mutexName);
                    }
                    if ($task->shouldRunOnOneServer() && $this->mutex) {
                        $this->mutex->forget($task->getOneServerMutexName());
                    }
                }
                exit(0);
            }

            // Parent process continues
            echo "Task started in background (PID: {$pid})\n";
        } else {
            // Fallback: Use shell background execution
            $phpBinary = PHP_BINARY;
            $script = $_SERVER['SCRIPT_FILENAME'] ?? 'console';

            $command = sprintf(
                '%s %s schedule:run-task %s > /dev/null 2>&1 &',
                escapeshellarg($phpBinary),
                escapeshellarg($script),
                escapeshellarg($task->getDescription())
            );

            exec($command);
            echo "Task started in background (shell)\n";
        }
    }

    /**
     * List all scheduled tasks
     *
     * @return array
     */
    public function listTasks(): array
    {
        $list = [];

        foreach ($this->tasks as $task) {
            $list[] = [
                'description' => $task->getDescription(),
                'expression' => $task->getExpression(),
            ];
        }

        return $list;
    }

    /**
     * Clear all scheduled tasks
     *
     * @return void
     */
    public function clear(): void
    {
        $this->tasks = [];
    }
}
