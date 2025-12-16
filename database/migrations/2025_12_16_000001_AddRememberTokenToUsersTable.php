<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * AddRememberTokenToUsersTable.
 */
class AddRememberTokenToUsersTable extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $this->schema->table('users', function ($table) {
            $table->string('remember_token', 100)->nullable()->after('password');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $this->schema->table('users', function ($table) {
            $table->dropColumn('remember_token');
        });
    }
}
