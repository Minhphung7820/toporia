<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * ImportExportJob ORM Model for tracking import/export operations.
 *
 * @property string $id UUID
 * @property int $user_id
 * @property string $type 'import' | 'export'
 * @property string $status 'pending' | 'processing' | 'completed' | 'failed' | 'cancelled'
 * @property string|null $file_path
 * @property string|null $original_filename
 * @property int $total_rows
 * @property int $processed_rows
 * @property int $success_rows
 * @property int $failed_rows
 * @property int $progress 0-100
 * @property string|null $message
 * @property string|null $error_message
 * @property array|null $options
 * @property string|null $result_file_path
 * @property string|null $queue_name
 * @property int $priority
 * @property string|null $started_at
 * @property string|null $completed_at
 * @property string $created_at
 * @property string $updated_at
 */
class ImportExportJobModel extends Model
{
    protected static string $table = 'import_export_jobs';

    protected static string $primaryKey = 'id';

    protected static bool $incrementing = false;

    protected static string $keyType = 'string';

    protected static array $fillable = [
        'id',
        'user_id',
        'type',
        'status',
        'file_path',
        'original_filename',
        'total_rows',
        'processed_rows',
        'success_rows',
        'failed_rows',
        'progress',
        'message',
        'error_message',
        'options',
        'result_file_path',
        'queue_name',
        'priority',
        'started_at',
        'completed_at',
    ];

    protected static array $casts = [
        'user_id' => 'int',
        'total_rows' => 'int',
        'processed_rows' => 'int',
        'success_rows' => 'int',
        'failed_rows' => 'int',
        'progress' => 'int',
        'priority' => 'int',
        'options' => 'array',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    // Type constants
    public const TYPE_IMPORT = 'import';
    public const TYPE_EXPORT = 'export';

    /**
     * Job belongs to user.
     */
    public function user()
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    /**
     * Check if job is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if job is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Check if job is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if job is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if job is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Check if job is finished (completed, failed, or cancelled).
     */
    public function isFinished(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ], true);
    }

    /**
     * Check if job can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
        ], true);
    }

    /**
     * Update progress.
     */
    public function updateProgress(int $progress, ?string $message = null): void
    {
        $data = [
            'progress' => min(100, max(0, $progress)),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($message !== null) {
            $data['message'] = $message;
        }

        static::where('id', $this->id)->update($data);
    }

    /**
     * Update processed rows count.
     */
    public function updateProcessedRows(int $processed, int $success = 0, int $failed = 0): void
    {
        $progress = $this->total_rows > 0
            ? (int) (($processed / $this->total_rows) * 100)
            : 0;

        static::where('id', $this->id)->update([
            'processed_rows' => $processed,
            'success_rows' => $success,
            'failed_rows' => $failed,
            'progress' => min(100, $progress),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Mark job as processing.
     */
    public function markAsProcessing(): void
    {
        static::where('id', $this->id)->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Mark job as completed.
     */
    public function markAsCompleted(?string $resultFilePath = null): void
    {
        $data = [
            'status' => self::STATUS_COMPLETED,
            'progress' => 100,
            'completed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($resultFilePath !== null) {
            $data['result_file_path'] = $resultFilePath;
        }

        static::where('id', $this->id)->update($data);
    }

    /**
     * Mark job as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        static::where('id', $this->id)->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Mark job as cancelled.
     */
    public function markAsCancelled(): void
    {
        static::where('id', $this->id)->update([
            'status' => self::STATUS_CANCELLED,
            'message' => 'Job cancelled by user',
            'completed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get user's active jobs (pending or processing) for a specific type.
     *
     * @return static[]
     */
    public static function getActiveJobsForUser(int $userId, string $type): array
    {
        return static::where('user_id', $userId)
            ->where('type', $type)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_PROCESSING])
            ->get()
            ->all();
    }

    /**
     * Check if user has an active job of specific type.
     */
    public static function hasActiveJob(int $userId, string $type): bool
    {
        return static::where('user_id', $userId)
            ->where('type', $type)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_PROCESSING])
            ->exists();
    }

    /**
     * Generate UUID for new job.
     */
    public static function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Create new import job.
     *
     * @return static
     */
    public static function createImportJob(
        int $userId,
        string $filePath,
        string $originalFilename,
        string $queueName,
        int $priority,
        array $options = []
    ): self {
        return static::create([
            'id' => self::generateUuid(),
            'user_id' => $userId,
            'type' => self::TYPE_IMPORT,
            'status' => self::STATUS_PENDING,
            'file_path' => $filePath,
            'original_filename' => $originalFilename,
            'queue_name' => $queueName,
            'priority' => $priority,
            'options' => $options,
        ]);
    }

    /**
     * Create new export job.
     *
     * @return static
     */
    public static function createExportJob(
        int $userId,
        string $queueName,
        int $priority,
        array $options = []
    ): self {
        return static::create([
            'id' => self::generateUuid(),
            'user_id' => $userId,
            'type' => self::TYPE_EXPORT,
            'status' => self::STATUS_PENDING,
            'queue_name' => $queueName,
            'priority' => $priority,
            'options' => $options,
        ]);
    }

    /**
     * Convert to array for API response.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'original_filename' => $this->original_filename,
            'total_rows' => $this->total_rows,
            'processed_rows' => $this->processed_rows,
            'success_rows' => $this->success_rows,
            'failed_rows' => $this->failed_rows,
            'progress' => $this->progress,
            'message' => $this->message,
            'error_message' => $this->error_message,
            'can_download' => $this->isCompleted() && $this->result_file_path !== null,
            'can_cancel' => $this->canBeCancelled(),
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
        ];
    }
}
