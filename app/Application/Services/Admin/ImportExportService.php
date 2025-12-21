<?php

declare(strict_types=1);

namespace App\Application\Services\Admin;

use App\Infrastructure\Jobs\ExportPostsJob;
use App\Infrastructure\Jobs\ImportPostsJob;
use App\Infrastructure\Persistence\Models\ImportExportJobModel;
use App\Infrastructure\Persistence\Models\UserModel;
use Toporia\Framework\Support\Accessors\Log;

/**
 * Import Export Service
 *
 * Handles import/export operations with tier-based queue priority.
 * - Admin: High priority queue, 8 workers
 * - Moderator: Medium priority queue, 5 workers
 * - Author/User: Low priority queue, 2 workers
 */
final class ImportExportService
{
    /**
     * Queue configuration by user role (tier-based).
     * Higher priority = processed first
     */
    private const QUEUE_TIERS = [
        'admin' => [
            'queue' => 'import-export-high',
            'priority' => 10,
            'workers' => 8,
        ],
        'moderator' => [
            'queue' => 'import-export-medium',
            'priority' => 5,
            'workers' => 5,
        ],
        'author' => [
            'queue' => 'import-export-low',
            'priority' => 2,
            'workers' => 2,
        ],
        'user' => [
            'queue' => 'import-export-low',
            'priority' => 1,
            'workers' => 2,
        ],
    ];

    /**
     * Default tier for unknown roles.
     */
    private const DEFAULT_TIER = [
        'queue' => 'import-export-low',
        'priority' => 1,
        'workers' => 2,
    ];

    /**
     * Create import job for posts.
     *
     * @param int $userId User ID
     * @param string $filePath Path to uploaded CSV file
     * @param string $originalFilename Original file name
     * @return array{success: bool, data?: array, message: string}
     */
    public function createImportJob(int $userId, string $filePath, string $originalFilename): array
    {
        // Check if user already has an active import job
        if (ImportExportJobModel::hasActiveJob($userId, ImportExportJobModel::TYPE_IMPORT)) {
            return [
                'success' => false,
                'message' => 'You already have an import job in progress. Please wait for it to complete.',
            ];
        }

        // Validate file exists - resolve relative path if needed
        $absolutePath = str_starts_with($filePath, 'app/')
            ? storage_path($filePath)
            : $filePath;

        if (!file_exists($absolutePath)) {
            return [
                'success' => false,
                'message' => 'Uploaded file not found.',
            ];
        }

        // Get user and determine tier
        $user = UserModel::find($userId);
        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
            ];
        }

        $tier = $this->getTierForRole($user->role);

        // Create job record
        $job = ImportExportJobModel::createImportJob(
            userId: $userId,
            filePath: $filePath,
            originalFilename: $originalFilename,
            queueName: $tier['queue'],
            priority: $tier['priority'],
            options: ['workers' => $tier['workers']]
        );

        // Dispatch to queue with priority
        $importJob = new ImportPostsJob(
            jobId: $job->id,
            userId: $userId,
            filePath: $filePath,
            workers: $tier['workers']
        );

        $importJob->onQueue($tier['queue']);

        dispatch($importJob);

        Log::info('Import job created', [
            'job_id' => $job->id,
            'user_id' => $userId,
            'role' => $user->role,
            'queue' => $tier['queue'],
            'workers' => $tier['workers'],
        ]);

        return [
            'success' => true,
            'data' => $job->toApiArray(),
            'message' => 'Import job created successfully. Processing will begin shortly.',
        ];
    }

    /**
     * Create export job for posts.
     *
     * @param int $userId User ID
     * @param array $filters Export filters
     * @return array{success: bool, data?: array, message: string}
     */
    public function createExportJob(int $userId, array $filters = []): array
    {
        // Check if user already has an active export job
        if (ImportExportJobModel::hasActiveJob($userId, ImportExportJobModel::TYPE_EXPORT)) {
            return [
                'success' => false,
                'message' => 'You already have an export job in progress. Please wait for it to complete.',
            ];
        }

        // Get user and determine tier
        $user = UserModel::find($userId);
        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
            ];
        }

        $tier = $this->getTierForRole($user->role);

        // Create job record
        $job = ImportExportJobModel::createExportJob(
            userId: $userId,
            queueName: $tier['queue'],
            priority: $tier['priority'],
            options: array_merge($filters, ['workers' => $tier['workers']])
        );

        // Dispatch to queue with priority
        $exportJob = new ExportPostsJob(
            jobId: $job->id,
            userId: $userId,
            filters: $filters
        );

        $exportJob->onQueue($tier['queue']);

        dispatch($exportJob);

        Log::info('Export job created', [
            'job_id' => $job->id,
            'user_id' => $userId,
            'role' => $user->role,
            'queue' => $tier['queue'],
            'filters' => $filters,
        ]);

        return [
            'success' => true,
            'data' => $job->toApiArray(),
            'message' => 'Export job created successfully. Processing will begin shortly.',
        ];
    }

    /**
     * Get job status.
     *
     * @param string $jobId Job UUID
     * @param int $userId User ID (for authorization)
     * @return array{success: bool, data?: array, message: string}
     */
    public function getJobStatus(string $jobId, int $userId): array
    {
        $job = ImportExportJobModel::find($jobId);

        if (!$job) {
            return [
                'success' => false,
                'message' => 'Job not found.',
            ];
        }

        // Check authorization
        if ($job->user_id !== $userId) {
            $user = UserModel::find($userId);
            if (!$user || !in_array($user->role, ['admin', 'moderator'])) {
                return [
                    'success' => false,
                    'message' => 'Unauthorized to view this job.',
                ];
            }
        }

        return [
            'success' => true,
            'data' => $job->toApiArray(),
            'message' => 'Job status retrieved.',
        ];
    }

    /**
     * Cancel a pending/processing job.
     *
     * @param string $jobId Job UUID
     * @param int $userId User ID (for authorization)
     * @return array{success: bool, message: string}
     */
    public function cancelJob(string $jobId, int $userId): array
    {
        $job = ImportExportJobModel::find($jobId);

        if (!$job) {
            return [
                'success' => false,
                'message' => 'Job not found.',
            ];
        }

        // Check authorization
        if ($job->user_id !== $userId) {
            $user = UserModel::find($userId);
            if (!$user || $user->role !== 'admin') {
                return [
                    'success' => false,
                    'message' => 'Unauthorized to cancel this job.',
                ];
            }
        }

        if (!$job->canBeCancelled()) {
            return [
                'success' => false,
                'message' => 'This job cannot be cancelled.',
            ];
        }

        $job->markAsCancelled();

        Log::info('Job cancelled', [
            'job_id' => $jobId,
            'user_id' => $userId,
        ]);

        return [
            'success' => true,
            'message' => 'Job cancelled successfully.',
        ];
    }

    /**
     * Get user's jobs (paginated).
     *
     * @param int $userId User ID
     * @param string|null $type Filter by type (import/export)
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array{success: bool, data: array, message: string}
     */
    public function getUserJobs(int $userId, ?string $type = null, int $page = 1, int $perPage = 10): array
    {
        $query = ImportExportJobModel::where('user_id', $userId);

        if ($type) {
            $query = $query->where('type', $type);
        }

        $total = $query->count();
        $offset = ($page - 1) * $perPage;

        $jobs = $query
            ->orderBy('created_at', 'desc')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return [
            'success' => true,
            'data' => [
                'jobs' => array_map(fn($job) => $job->toApiArray(), $jobs->all()),
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => $total > 0 ? (int) ceil($total / $perPage) : 1,
            ],
            'message' => 'Jobs retrieved successfully.',
        ];
    }

    /**
     * Get download URL for completed export.
     *
     * @param string $jobId Job UUID
     * @param int $userId User ID (for authorization)
     * @return array{success: bool, data?: array, message: string}
     */
    public function getDownloadUrl(string $jobId, int $userId): array
    {
        $job = ImportExportJobModel::find($jobId);

        if (!$job) {
            return [
                'success' => false,
                'message' => 'Job not found.',
            ];
        }

        // Check authorization
        if ($job->user_id !== $userId) {
            $user = UserModel::find($userId);
            if (!$user || !in_array($user->role, ['admin', 'moderator'])) {
                return [
                    'success' => false,
                    'message' => 'Unauthorized to download this file.',
                ];
            }
        }

        if (!$job->isCompleted()) {
            return [
                'success' => false,
                'message' => 'Job is not completed yet.',
            ];
        }

        if (!$job->result_file_path || !file_exists($job->result_file_path)) {
            return [
                'success' => false,
                'message' => 'Export file not found.',
            ];
        }

        return [
            'success' => true,
            'data' => [
                'file_path' => $job->result_file_path,
                'filename' => basename($job->result_file_path),
                'size' => filesize($job->result_file_path),
            ],
            'message' => 'Download ready.',
        ];
    }

    /**
     * Get tier configuration for user role.
     */
    private function getTierForRole(string $role): array
    {
        return self::QUEUE_TIERS[$role] ?? self::DEFAULT_TIER;
    }

    /**
     * Get active jobs for user.
     *
     * @param int $userId User ID
     * @return array{import: ?array, export: ?array}
     */
    public function getActiveJobs(int $userId): array
    {
        $importJobs = ImportExportJobModel::getActiveJobsForUser($userId, ImportExportJobModel::TYPE_IMPORT);
        $exportJobs = ImportExportJobModel::getActiveJobsForUser($userId, ImportExportJobModel::TYPE_EXPORT);

        return [
            'import' => !empty($importJobs) ? $importJobs[0]->toApiArray() : null,
            'export' => !empty($exportJobs) ? $exportJobs[0]->toApiArray() : null,
        ];
    }

    /**
     * Get job history with cursor pagination.
     *
     * @param int $userId User ID
     * @param string|null $type Filter by type
     * @param string|null $cursor Cursor for pagination
     * @param int $limit Items per page
     * @param string|null $status Filter by status
     * @return array{data: array, next_cursor: ?string, has_more: bool}
     */
    public function getJobHistory(
        int $userId,
        ?string $type = null,
        ?string $cursor = null,
        int $limit = 15,
        ?string $status = null
    ): array {
        return ImportExportJobModel::getJobsWithCursor($userId, $type, $cursor, $limit, $status);
    }

    /**
     * Get job statistics for user.
     *
     * @param int $userId User ID
     * @return array
     */
    public function getJobStats(int $userId): array
    {
        return ImportExportJobModel::getJobStats($userId);
    }
}
