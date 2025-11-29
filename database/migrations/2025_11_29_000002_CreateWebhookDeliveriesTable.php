<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create Webhook Deliveries Table Migration
 */
class CreateWebhookDeliveriesTable extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $this->schema->create('webhook_deliveries', function ($table) {
            $table->id();
            $table->foreignId('endpoint_id')->nullable()->constrained('webhook_endpoints')->onDelete('set null');
            $table->string('event');
            $table->json('payload');
            $table->integer('status_code')->nullable();
            $table->text('response_body')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('endpoint_id');
            $table->index('event');
            $table->index('succeeded_at');
            $table->index('failed_at');
            $table->index(['endpoint_id', 'created_at']);
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('webhook_deliveries');
    }
}

