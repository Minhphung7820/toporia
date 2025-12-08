<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create permission_role pivot table migration.
 */
class CreatePermissionRoleTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('permission_role', function ($table) {
            $table->id();
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();

            $table->foreign('permission_id')->references('permissions', 'id')->onDelete('cascade');
            $table->foreign('role_id')->references('roles', 'id')->onDelete('cascade');
            $table->unique(['permission_id', 'role_id']);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('permission_role');
    }
}

