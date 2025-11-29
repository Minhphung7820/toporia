<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create Social Accounts Table Migration
 */
class CreateSocialAccountsTable extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $this->schema->create('social_accounts', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('provider'); // google, facebook, github, etc.
            $table->string('provider_id'); // User ID from provider
            $table->text('provider_token')->nullable(); // Access token
            $table->text('provider_refresh_token')->nullable(); // Refresh token
            $table->timestamp('provider_expires_at')->nullable(); // Token expiration
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('avatar')->nullable();
            $table->string('nickname')->nullable();
            $table->json('metadata')->nullable(); // Additional provider data
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
            $table->index('user_id');
            $table->index('provider');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('social_accounts');
    }
}

