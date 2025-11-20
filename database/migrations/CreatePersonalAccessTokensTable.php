<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create Personal Access Tokens Table Migration
 *
 * Creates the database table for storing personal access tokens.
 *
 * Table Structure:
 * - id: Primary key
 * - tokenable_type: Polymorphic owner type (e.g., 'App\\Domain\\User\\User')
 * - tokenable_id: Polymorphic owner ID
 * - name: Token name/identifier (e.g., 'mobile-app', 'api-client')
 * - token: Hashed token (SHA-256, 64 chars)
 * - abilities: JSON array of scopes/permissions
 * - last_used_at: Last usage timestamp
 * - expires_at: Expiration timestamp
 * - created_at, updated_at: Timestamps
 *
 * Indexes:
 * - UNIQUE on token (O(1) lookup)
 * - INDEX on (tokenable_type, tokenable_id) (fast user token queries)
 *
 * Performance:
 * - O(1) token lookup via UNIQUE index
 * - O(1) user token queries via composite index
 */
class CreatePersonalAccessTokensTable extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $this->schema->create('personal_access_tokens', function ($table) {
            // Primary key
            $table->id();

            // Polymorphic relationship to token owner (User, etc.)
            $table->string('tokenable_type');
            $table->integer('tokenable_id')->unsigned();

            // Token details
            $table->string('name'); // Token name (e.g., 'mobile-app')
            $table->string('token', 64)->unique(); // Hashed token (SHA-256)

            // Abilities/scopes stored as JSON
            $table->text('abilities')->nullable();

            // Timestamps
            $table->datetime('last_used_at')->nullable();
            $table->datetime('expires_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['tokenable_type', 'tokenable_id']); // Composite index for user queries
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('personal_access_tokens');
    }
}
