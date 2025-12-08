<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create role_user pivot table migration.
 */
class CreateRoleUserTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('role_user', function ($table) {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('assigned_at')->useCurrent();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('role_id')->references('roles', 'id')->onDelete('cascade');
            $table->foreign('user_id')->references('users', 'id')->onDelete('cascade');
            $table->foreign('assigned_by')->references('users', 'id')->onDelete('set null');
            $table->unique(['role_id', 'user_id']);
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('role_user');
    }
}

