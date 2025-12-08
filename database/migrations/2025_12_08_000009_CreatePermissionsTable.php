<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create permissions table migration.
 */
class CreatePermissionsTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('permissions', function ($table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('display_name', 100);
            $table->string('description', 200)->nullable();
            $table->string('resource', 50);
            $table->string('action', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['resource', 'action']);
            $table->index(['is_active']);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('permissions');
    }
}

