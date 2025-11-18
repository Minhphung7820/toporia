<?php

declare(strict_types=1);

use Toporia\Framework\Database\Schema\SchemaBuilder;

/**
 * Create Sessions Table Migration
 *
 * Creates the sessions table for database session driver.
 */
return new class
{
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('sessions', function ($table) {
            $table->string('id', 255);
            $table->text('payload');
            $table->integer('last_activity')->unsigned();
            $table->integer('expires_at')->unsigned();

            // Primary key
            $table->primaryKey('id');

            // Index for cleanup queries
            $table->index('expires_at');
            $table->index('last_activity');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->drop('sessions');
    }
};
