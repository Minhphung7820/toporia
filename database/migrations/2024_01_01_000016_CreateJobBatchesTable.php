<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create Job Batches Table Migration
 *
 * Creates table for storing batch job information.
 */
class CreateJobBatchesTable extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $this->schema->create('job_batches', function ($table) {
            $table->string('id', 255);
            $table->string('name');
            $table->integer('total_jobs')->unsigned();
            $table->integer('processed_jobs')->unsigned()->default(0);
            $table->integer('failed_jobs')->unsigned()->default(0);
            $table->text('options')->nullable();
            $table->integer('created_at')->unsigned();
            $table->integer('finished_at')->unsigned()->nullable();
            $table->integer('cancelled_at')->unsigned()->nullable();

            // Primary key
            $table->primary('id');

            // Indexes for queries
            $table->index('created_at');
            $table->index(['finished_at', 'cancelled_at']);
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('job_batches');
    }
}
