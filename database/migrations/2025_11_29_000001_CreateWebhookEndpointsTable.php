<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create Webhook Endpoints Table Migration
 */
class CreateWebhookEndpointsTable extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $this->schema->create('webhook_endpoints', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('url', 2048);
            $table->string('secret', 255)->nullable();
            $table->json('events')->nullable(); // Array of event names or patterns
            $table->boolean('active')->default(true);
            $table->integer('timeout')->default(30); // seconds
            $table->integer('retry_count')->default(3);
            $table->integer('retry_delay')->default(1000); // milliseconds
            $table->json('headers')->nullable(); // Custom headers
            $table->json('metadata')->nullable(); // Additional metadata
            $table->timestamps();

            $table->index('active');
            // Note: URL index removed due to MySQL key length limit (2048 chars * 4 bytes = 8192 bytes > 3072 limit)
            // If URL search is needed, consider using a hash column or prefix index
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('webhook_endpoints');
    }
}
