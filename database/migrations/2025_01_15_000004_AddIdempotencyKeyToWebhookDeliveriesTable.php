<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Add Idempotency Key to Webhook Deliveries Table
 *
 * Adds idempotency_key column for deduplication and replay protection.
 */
class AddIdempotencyKeyToWebhookDeliveriesTable extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $this->schema->table('webhook_deliveries', function ($table) {
            // Add idempotency key column for deduplication
            $table->string('idempotency_key', 64)->nullable()->after('error_message');

            // Add index for fast lookups
            $table->index('idempotency_key');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $this->schema->table('webhook_deliveries', function ($table) {
            $table->dropIndex('idempotency_key');
            $table->dropColumn('idempotency_key');
        });
    }
}
