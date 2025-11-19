<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Example Advanced Migration
 *
 * Demonstrates all advanced migration features:
 * - All column types
 * - Composite primary keys
 * - Foreign keys with onDelete/onUpdate
 * - Fulltext indexes
 * - Spatial indexes
 * - Table modifiers
 * - ALTER TABLE operations
 */
class ExampleAdvancedMigration extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        // Example 1: Complete table with all features
        $this->schema->create('posts', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('title', 255);
            $table->text('content');
            $table->string('slug', 255)->unique();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->decimal('rating', 3, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_id', 'created_at']);
            $table->fullText(['title', 'content']);
            $table->index('slug');

            // Table options
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->comment('Blog posts table');
        });

        // Example 2: Composite primary key
        $this->schema->create('user_roles', function ($table) {
            $table->foreignId('user_id');
            $table->foreignId('role_id');
            $table->timestamps();

            // Composite primary key
            $table->primary(['user_id', 'role_id']);

            // Foreign keys with actions
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade')
                ->onUpdate('restrict');

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');
        });

        // Example 3: Table with spatial data
        $this->schema->create('locations', function ($table) {
            $table->id();
            $table->string('name', 255);
            $table->point('coordinates');
            $table->polygon('boundary')->nullable();
            $table->timestamps();

            // Spatial index
            $table->spatialIndex('coordinates');
        });

        // Example 4: ALTER TABLE example
        $this->schema->table('users', function ($table) {
            // Add columns
            $table->string('phone', 20)->nullable()->after('email');
            $table->text('bio')->nullable()->after('phone');

            // Modify column
            $table->string('name', 150)->change();

            // Add index
            $table->index('phone');

            // Add unique
            $table->unique('phone', 'users_phone_unique');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('locations');
        $this->schema->dropIfExists('user_roles');
        $this->schema->dropIfExists('posts');

        // Reverse ALTER TABLE
        $this->schema->table('users', function ($table) {
            $table->dropColumn(['phone', 'bio']);
            $table->dropIndex('users_phone_unique');
            $table->string('name', 255)->change(); // Revert name length
        });
    }
}

