<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create audit_logs table for storing model change history.
 */
class CreateAuditLogsTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('audit_logs', function ($table) {
            $table->id();
            $table->string('uuid', 50)->unique(); // Unique audit identifier
            $table->string('event', 50); // created, updated, deleted, restored
            $table->string('auditable_type', 255); // Model class name
            $table->string('auditable_id', 100); // Model primary key
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('user_id', 100)->nullable();
            $table->string('user_name', 255)->nullable();
            $table->string('ip_address', 45)->nullable(); // IPv6 compatible
            $table->text('user_agent')->nullable();
            $table->text('url')->nullable();
            $table->string('tenant_id', 100)->nullable(); // Multi-tenancy support
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index('event');
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('user_id');
            $table->index('tenant_id');
            $table->index('created_at');
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('audit_logs');
    }
}
