<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create OAuth2 Tables Migration
 *
 * Creates tables for OAuth2 clients, access tokens, and refresh tokens.
 */
class CreateOAuthClientsTables extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        // OAuth2 Clients
        $this->schema->create('oauth_clients', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('client_id', 64)->unique();
            $table->string('client_secret', 255)->nullable();
            $table->string('redirect_uri');
            $table->boolean('is_confidential')->default(true);
            $table->json('scopes')->nullable();
            $table->timestamps();
        });

        // OAuth2 Access Tokens
        $this->schema->create('oauth_access_tokens', function ($table) {
            $table->id();
            $table->string('token', 255)->unique();
            $table->string('client_id', 64);
            $table->string('user_id')->nullable();
            $table->json('scopes')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('client_id');
            $table->index('user_id');
            $table->index('token');
        });

        // OAuth2 Refresh Tokens
        $this->schema->create('oauth_refresh_tokens', function ($table) {
            $table->id();
            $table->string('token', 255)->unique();
            $table->string('client_id', 64);
            $table->string('user_id');
            $table->json('scopes')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('client_id');
            $table->index('user_id');
            $table->index('token');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('oauth_refresh_tokens');
        $this->schema->dropIfExists('oauth_access_tokens');
        $this->schema->dropIfExists('oauth_clients');
    }
}
