<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * CreateWebhookFailuresTable Migration
 *
 * Dead Letter Queue for webhooks that failed after all retry attempts.
 * Stores failed webhooks for manual review and retry.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 */
class CreateWebhookFailuresTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->schema->create('webhook_failures', function ($table) {
            $table->id();

            // Endpoint reference (nullable if endpoint was deleted)
            $table->unsignedBigInteger('endpoint_id')->nullable();
            $table->string('endpoint_url', 2048); // Actual URL used

            // Event details
            $table->string('event'); // Event name
            $table->json('payload'); // Event payload
            $table->json('options')->nullable(); // Dispatch options (headers, timeout, etc.)

            // Failure details
            $table->integer('total_attempts')->default(0); // Total retry attempts made
            $table->text('last_error_message')->nullable(); // Last exception message
            $table->integer('last_status_code')->nullable(); // Last HTTP status code
            $table->text('last_response_body')->nullable(); // Last response body

            // Idempotency
            $table->string('idempotency_key', 64)->nullable()->unique();

            // Retry tracking
            $table->timestamp('retried_at')->nullable(); // When manual retry was attempted

            $table->timestamps();

            // Indexes
            $table->index('endpoint_id');
            $table->index('event');
            $table->index('retried_at');
            $table->index('created_at');
            $table->index(['endpoint_id', 'event']);
            $table->index(['event', 'created_at']);

            // Foreign key (soft reference - don't cascade delete)
            // $table->foreign('endpoint_id')->references('id')->on('webhook_endpoints')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('webhook_failures');
    }
}
