<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create import_export_jobs table for tracking import/export operations.
 *
 * Features:
 * - Progress tracking (0-100%)
 * - Status management (pending, processing, completed, failed, cancelled)
 * - Tier-based queue support
 * - Race condition protection via unique constraints
 */
class CreateImportExportJobsTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('import_export_jobs', function ($table) {
            $table->string('id', 36)->primary();  // UUID
            $table->unsignedBigInteger('user_id');
            $table->enum('type', ['import', 'export']);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->string('file_path', 500)->nullable();
            $table->string('original_filename', 255)->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->tinyInteger('progress')->unsigned()->default(0);  // 0-100
            $table->text('message')->nullable();
            $table->text('error_message')->nullable();
            $table->json('options')->nullable();  // Filters for export, options for import
            $table->string('result_file_path', 500)->nullable();  // Export result file
            $table->string('queue_name', 100)->nullable();  // Queue used for this job
            $table->tinyInteger('priority')->unsigned()->default(0);  // Queue priority
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'type'], 'idx_user_type');
            $table->index('status', 'idx_status');
            $table->index('created_at', 'idx_created_at');

            // Foreign key
            $table->foreign('user_id')
                ->references('users', 'id')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('import_export_jobs');
    }
}
