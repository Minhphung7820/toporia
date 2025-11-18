<?php

declare(strict_types=1);

namespace App\Application\Services\Jobs;

use Toporia\Framework\Queue\Job;
use Toporia\Framework\Mail\Contracts\MailerInterface;
use Toporia\Framework\Mail\Message;
use Toporia\Framework\Support\Accessors\Log;
use Toporia\Framework\Support\Accessors\Process;

/**
 * Send Email Job
 *
 * Queued job for sending emails asynchronously.
 *
 * Clean Architecture:
 * - Depends on MailerInterface (Dependency Inversion Principle)
 * - Single Responsibility: Only handles email job execution
 * - Open/Closed: Works with any MailerInterface implementation
 * - High Reusability: Decoupled from specific mailer
 *
 * @package App\Application\Jobs
 */
final class TestProcess extends Job
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the job.
     *
     * Dependencies are auto-injected by the Worker via container.
     *
     * Note: Closures cannot be serialized, so we create them in handle() method
     * after the job is deserialized. This ensures Process::run() works correctly
     * with RabbitMQ, Redis, and Database queues.
     *
     * @return void
     * @throws \RuntimeException If sending fails
     */
    public function handle(): void
    {
        Log::info("TestProcess job started", [
            'job_id' => $this->getId(),
            'php_sapi' => PHP_SAPI,
            'has_request_method' => isset($_SERVER['REQUEST_METHOD']),
        ]);

        try {
            Log::info("Before Process::run()", [
                'job_id' => $this->getId(),
                'pcntl_available' => function_exists('pcntl_fork'),
                'fork_supported' => \Toporia\Framework\Process\ForkProcess::isSupported(),
            ]);

            // Create closures here (after deserialization) instead of in constructor
            // This ensures they work correctly with all queue drivers (RabbitMQ, Redis, Database)
            $results = Process::run([
                fn() => $this->logTest(),
                fn() => $this->logTest(),
                fn() => $this->logTest(),
                fn() => $this->logTest(),
            ]);

            Log::info("After Process::run()", [
                'job_id' => $this->getId(),
                'results_count' => count($results),
                'results' => $results,
            ]);

            Log::info("TestProcess job completed successfully", ['job_id' => $this->getId()]);
        } catch (\Throwable $e) {
            Log::error("TestProcess job failed", [
                'job_id' => $this->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }

    private function logTest(): void
    {
        Log::info("Testlog", [
            'timestamp' => date('Y-m-d H:i:s'),
            'pid' => getmypid(),
        ]);
    }
}
