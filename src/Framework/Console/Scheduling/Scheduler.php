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
            if ($task->isDue($currentTime)) {
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
            // Check for overlap prevention
            if ($task->hasOverlapPrevention() && $this->mutex) {
                $mutexName = $task->getMutexName();

                // Skip if task is already running
                if ($this->mutex->exists($mutexName)) {
                    echo "Skipping task (already running): {$task->getDescription()}\n";
                    continue;
                }

                // Acquire mutex lock
                if (!$this->mutex->create($mutexName, $task->getExpiresAfter())) {
                    echo "Failed to acquire lock for task: {$task->getDescription()}\n";
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

                    echo "Task completed: {$task->getDescription()}\n";
                    $count++;
                } catch (\Throwable $e) {
                    $this->mutex->forget($mutexName);
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

                    echo "Task completed: {$task->getDescription()}\n";
                    $count++;
                } catch (\Throwable $e) {
                    echo "Task failed: {$task->getDescription()} - {$e->getMessage()}\n";
                }
            }
        }

        return $count;
    }

    /**
     * Execute task with hooks and output handling.
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

        try {
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

            // Execute after callback
            if ($after = $task->getAfterCallback()) {
                $after();
            }

            // Execute onSuccess callback
            if ($onSuccess = $task->getOnSuccessCallback()) {
                $onSuccess();
            }
        } catch (\Throwable $e) {
            // Capture output on failure
            if ($task->getOutputFile() || $task->getEmailOutputTo()) {
                $output = ob_get_clean();
            }

            $exception = $e;
            $success = false;

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

            // Handle email output
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
     * Email task output.
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

        // Use mail() function or queue email job
        mail($email, $subject, $body);
    }

    /**
     * Run task in background
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
                    $task->execute();
                } catch (\Throwable $e) {
                    error_log("Background task failed: {$e->getMessage()}");
                } finally {
                    if ($mutexName && $this->mutex) {
                        $this->mutex->forget($mutexName);
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
