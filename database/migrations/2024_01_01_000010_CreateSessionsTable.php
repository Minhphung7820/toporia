<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create Sessions Table Migration
 *
 * Creates the sessions table for database session driver.
 */
class CreateSessionsTable extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $this->schema->create('sessions', function ($table) {
            $table->string('id', 255);
            $table->text('payload');
            $table->integer('last_activity')->unsigned();
            $table->integer('expires_at')->unsigned();

            // Primary key
            $table->primary('id');

            // Index for cleanup queries
            $table->index('expires_at');
            $table->index('last_activity');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('sessions');
    }
}
