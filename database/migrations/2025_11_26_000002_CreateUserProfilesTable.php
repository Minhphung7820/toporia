<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create user_profiles table for one-to-one relationship.
 */
class CreateUserProfilesTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('user_profiles', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('bio')->nullable();
            $table->string('website')->nullable();
            $table->string('twitter')->nullable();
            $table->string('facebook')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('github')->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->json('preferences')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->foreign('user_id')
                ->references('users', 'id')
                ->onDelete('cascade');
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('user_profiles');
    }
}
