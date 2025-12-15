<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create OAuth2 Authorization Codes Table Migration
 *
 * Creates table for OAuth2 authorization codes with PKCE support.
 * Authorization codes are temporary, single-use codes exchanged for access tokens.
 */
class CreateOAuthAuthorizationCodesTable extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $this->schema->create('oauth_authorization_codes', function ($table) {
            $table->id();
            $table->string('code', 255)->unique();
            $table->string('client_id', 64);
            $table->string('user_id');
            $table->string('redirect_uri');
            $table->json('scopes')->nullable();

            // PKCE (Proof Key for Code Exchange) support
            $table->string('code_challenge', 255)->nullable();
            $table->string('code_challenge_method', 10)->nullable(); // 'plain' or 'S256'

            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable(); // Single-use enforcement
            $table->timestamps();

            // Indexes for performance
            $table->index('code');
            $table->index('client_id');
            $table->index('user_id');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('oauth_authorization_codes');
    }
}
