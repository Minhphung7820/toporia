<?php

declare(strict_types=1);

namespace Tests\Unit\Database\ORM;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\Concerns\SoftDeletes;
use Toporia\Framework\Database\ORM\ModelQueryBuilder;

/**
 * Test SoftDeletes
 *
 * Comprehensive tests for soft delete functionality:
 * - Soft delete (sets deleted_at timestamp)
 * - Restore (removes deleted_at)
 * - Force delete (hard delete)
 * - trashed() method
 * - withTrashed() scope
 * - onlyTrashed() scope
 * - Batch soft delete operations
 * - Global scope exclusion
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class SoftDeletesTest extends DatabaseTestCase
{
    protected function createTables(): void
    {
        // Create users table with deleted_at column
        $this->createTable('users', "
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                deleted_at DATETIME NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            )
        ");
    }

    protected function dropTables(): void
    {
        $this->dropTable('users');
    }

    protected function tearDown(): void
    {
        // Clean up event listeners
        SoftDeleteUser::flushEventListeners();
        parent::tearDown();
    }

    /**
     * Test soft delete sets deleted_at timestamp
     */
    public function test_soft_delete_sets_deleted_at(): void
    {
        $user = new SoftDeleteUser(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $this->assertNull($user->deleted_at);
        $this->assertFalse($user->trashed());

        // Soft delete
        $result = $user->delete();

        $this->assertTrue($result);
        $this->assertNotNull($user->deleted_at);
        $this->assertTrue($user->trashed());

        // Check database
        $rows = $this->getTableRows('users');
        $this->assertCount(1, $rows);
        $this->assertNotNull($rows[0]['deleted_at']);
    }

    /**
     * Test soft deleted records are excluded from queries
     */
    public function test_soft_deleted_records_excluded_from_queries(): void
    {
        // Create active user
        $user1 = new SoftDeleteUser(['name' => 'Active User', 'email' => 'active@example.com']);
        $user1->save();

        // Create and soft delete user
        $user2 = new SoftDeleteUser(['name' => 'Deleted User', 'email' => 'deleted@example.com']);
        $user2->save();
        $user2->delete();

        // Normal query should only return active users
        $users = SoftDeleteUser::all();
        $this->assertCount(1, $users);
        $this->assertEquals('Active User', $users->first()->name);

        // Check database has both records
        $rows = $this->getTableRows('users');
        $this->assertCount(2, $rows);
    }

    /**
     * Test restore removes deleted_at
     */
    public function test_restore_removes_deleted_at(): void
    {
        $user = new SoftDeleteUser(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();
        $user->delete();

        $this->assertTrue($user->trashed());

        // Restore
        $result = $user->restore();

        $this->assertTrue($result);
        $this->assertNull($user->deleted_at);
        $this->assertFalse($user->trashed());

        // Should now appear in queries
        $users = SoftDeleteUser::all();
        $this->assertCount(1, $users);
        $this->assertEquals('John Doe', $users->first()->name);
    }

    /**
     * Test restore returns false if not trashed
     */
    public function test_restore_returns_false_if_not_trashed(): void
    {
        $user = new SoftDeleteUser(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $this->assertFalse($user->trashed());

        $result = $user->restore();

        $this->assertFalse($result);
    }

    /**
     * Test force delete permanently removes record
     */
    public function test_force_delete_permanently_removes_record(): void
    {
        $user = new SoftDeleteUser(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();
        $id = $user->id;

        // Force delete
        $result = $user->forceDelete();

        $this->assertTrue($result);

        // Record should be completely removed from database
        $this->assertTableMissing('users', ['id' => $id]);
        $this->assertTableCount('users', 0);
    }

    /**
     * Test force delete works on already soft deleted records
     */
    public function test_force_delete_works_on_soft_deleted_records(): void
    {
        $user = new SoftDeleteUser(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();
        $id = $user->id;
        $user->delete();

        $this->assertTrue($user->trashed());

        // Force delete
        $result = $user->forceDelete();

        $this->assertTrue($result);
        $this->assertTableMissing('users', ['id' => $id]);
    }

    /**
     * Test trashed() returns true for soft deleted models
     */
    public function test_trashed_returns_true_for_soft_deleted(): void
    {
        $user = new SoftDeleteUser(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $this->assertFalse($user->trashed());

        $user->delete();

        $this->assertTrue($user->trashed());
    }

    /**
     * Test withTrashed() includes soft deleted records
     */
    public function test_with_trashed_includes_soft_deleted(): void
    {
        // Create active user
        $user1 = new SoftDeleteUser(['name' => 'Active User', 'email' => 'active@example.com']);
        $user1->save();

        // Create and soft delete user
        $user2 = new SoftDeleteUser(['name' => 'Deleted User', 'email' => 'deleted@example.com']);
        $user2->save();
        $user2->delete();

        // Normal query
        $users = SoftDeleteUser::all();
        $this->assertCount(1, $users);

        // withTrashed should include both
        $allUsers = SoftDeleteUser::withTrashed()->get();
        $this->assertCount(2, $allUsers);

        // Verify one is trashed
        $trashedCount = $allUsers->filter(fn($u) => $u->trashed())->count();
        $this->assertEquals(1, $trashedCount);
    }

    /**
     * Test onlyTrashed() returns only soft deleted records
     */
    public function test_only_trashed_returns_only_soft_deleted(): void
    {
        // Create active user
        $user1 = new SoftDeleteUser(['name' => 'Active User', 'email' => 'active@example.com']);
        $user1->save();

        // Create and soft delete user
        $user2 = new SoftDeleteUser(['name' => 'Deleted User', 'email' => 'deleted@example.com']);
        $user2->save();
        $user2->delete();

        // onlyTrashed should return only deleted user
        $trashedUsers = SoftDeleteUser::onlyTrashed()->get();
        $this->assertCount(1, $trashedUsers);
        $this->assertEquals('Deleted User', $trashedUsers->first()->name);
        $this->assertTrue($trashedUsers->first()->trashed());
    }

    /**
     * Test batch soft delete
     */
    public function test_batch_soft_delete(): void
    {
        // Create multiple users
        $user1 = new SoftDeleteUser(['name' => 'User 1', 'email' => 'user1@example.com']);
        $user1->save();
        $user2 = new SoftDeleteUser(['name' => 'User 2', 'email' => 'user2@example.com']);
        $user2->save();
        $user3 = new SoftDeleteUser(['name' => 'User 3', 'email' => 'user3@example.com']);
        $user3->save();

        $ids = [$user1->id, $user2->id];

        // Batch soft delete
        $count = SoftDeleteUser::softDeleteBatch($ids);

        $this->assertEquals(2, $count);

        // Check only 2 are trashed
        $trashedUsers = SoftDeleteUser::onlyTrashed()->get();
        $this->assertCount(2, $trashedUsers);

        // Check user3 is still active
        $activeUsers = SoftDeleteUser::all();
        $this->assertCount(1, $activeUsers);
        $this->assertEquals('User 3', $activeUsers->first()->name);
    }

    /**
     * Test batch restore
     */
    public function test_batch_restore(): void
    {
        // Create and soft delete users
        $user1 = new SoftDeleteUser(['name' => 'User 1', 'email' => 'user1@example.com']);
        $user1->save();
        $user1->delete();
        $user2 = new SoftDeleteUser(['name' => 'User 2', 'email' => 'user2@example.com']);
        $user2->save();
        $user2->delete();
        $user3 = new SoftDeleteUser(['name' => 'User 3', 'email' => 'user3@example.com']);
        $user3->save();
        $user3->delete();

        $ids = [$user1->id, $user2->id];

        // Batch restore
        $count = SoftDeleteUser::restoreBatch($ids);

        $this->assertEquals(2, $count);

        // Check only 2 are restored
        $activeUsers = SoftDeleteUser::all();
        $this->assertCount(2, $activeUsers);

        // Check user3 is still trashed
        $trashedUsers = SoftDeleteUser::onlyTrashed()->get();
        $this->assertCount(1, $trashedUsers);
        $this->assertEquals('User 3', $trashedUsers->first()->name);
    }

    /**
     * Test batch soft delete with empty array
     */
    public function test_batch_soft_delete_with_empty_array(): void
    {
        $count = SoftDeleteUser::softDeleteBatch([]);
        $this->assertEquals(0, $count);
    }

    /**
     * Test batch restore with empty array
     */
    public function test_batch_restore_with_empty_array(): void
    {
        $count = SoftDeleteUser::restoreBatch([]);
        $this->assertEquals(0, $count);
    }

    /**
     * Test usesSoftDeletes() returns true
     */
    public function test_uses_soft_deletes_returns_true(): void
    {
        $this->assertTrue(SoftDeleteUser::usesSoftDeletes());
    }

    /**
     * Test find works with soft deleted records
     */
    public function test_find_excludes_soft_deleted(): void
    {
        $user = new SoftDeleteUser(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();
        $id = $user->id;
        $user->delete();

        // Normal find should not find soft deleted
        $found = SoftDeleteUser::find($id);
        $this->assertNull($found);

        // withTrashed should find it
        $found = SoftDeleteUser::withTrashed()->find($id);
        $this->assertNotNull($found);
        $this->assertTrue($found->trashed());
    }

    /**
     * Test findOrFail throws exception for soft deleted
     */
    public function test_find_or_fail_throws_for_soft_deleted(): void
    {
        $user = new SoftDeleteUser(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();
        $id = $user->id;
        $user->delete();

        $this->expectException(\RuntimeException::class);
        SoftDeleteUser::findOrFail($id);
    }

    /**
     * Test where conditions work with soft deletes
     */
    public function test_where_conditions_work_with_soft_deletes(): void
    {
        $user1 = new SoftDeleteUser(['name' => 'John', 'email' => 'john@example.com']);
        $user1->save();
        $user2 = new SoftDeleteUser(['name' => 'John', 'email' => 'john2@example.com']);
        $user2->save();
        $user2->delete();

        // Query should only return active
        $users = SoftDeleteUser::where('name', 'John')->get();
        $this->assertCount(1, $users);
        $this->assertEquals('john@example.com', $users->first()->email);
    }

    /**
     * Test update works on soft deleted records via withTrashed
     */
    public function test_update_works_on_soft_deleted_with_with_trashed(): void
    {
        $user = new SoftDeleteUser(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();
        $user->delete();

        // Update via withTrashed
        $updated = SoftDeleteUser::withTrashed()
            ->where('id', $user->id)
            ->update(['name' => 'Updated Name']);

        $this->assertEquals(1, $updated);

        // Verify update
        $user = SoftDeleteUser::withTrashed()->find($user->id);
        $this->assertEquals('Updated Name', $user->name);
        $this->assertTrue($user->trashed());
    }

    /**
     * Test delete on already soft deleted record
     */
    public function test_delete_on_already_soft_deleted(): void
    {
        $user = new SoftDeleteUser(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();
        $user->delete();

        $deletedAt1 = $user->deleted_at;

        // Delete again (should update deleted_at)
        $result = $user->delete();

        $this->assertTrue($result);
        $this->assertTrue($user->trashed());
        // deleted_at should be updated
        $this->assertNotNull($user->deleted_at);
    }

    /**
     * Test soft delete preserves other attributes
     */
    public function test_soft_delete_preserves_other_attributes(): void
    {
        $user = new SoftDeleteUser(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();
        $originalName = $user->name;
        $originalEmail = $user->email;

        $user->delete();

        $this->assertEquals($originalName, $user->name);
        $this->assertEquals($originalEmail, $user->email);
        $this->assertTrue($user->trashed());
    }

    /**
     * Test multiple soft delete and restore cycles
     */
    public function test_multiple_soft_delete_and_restore_cycles(): void
    {
        $user = new SoftDeleteUser(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        // First soft delete
        $user->delete();
        $this->assertTrue($user->trashed());

        // Restore
        $user->restore();
        $this->assertFalse($user->trashed());

        // Second soft delete
        $user->delete();
        $this->assertTrue($user->trashed());

        // Restore again
        $user->restore();
        $this->assertFalse($user->trashed());

        // Should appear in queries
        $users = SoftDeleteUser::all();
        $this->assertCount(1, $users);
    }
}

/**
 * Test model with SoftDeletes trait
 */
class SoftDeleteUser extends Model
{
    use SoftDeletes;

    protected static string $table = 'users';
    protected static string $primaryKey = 'id';

    protected static array $fillable = ['name', 'email'];

    public function save(): bool
    {
        // Use parent save() which handles all the logic
        return parent::save();
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        // Use SoftDeletes delete method
        return parent::delete();
    }

    public function forceDelete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $sql = "DELETE FROM users WHERE id = :id";
        $stmt = $this->getConnection()->getPdo()->prepare($sql);
        $stmt->bindValue(':id', $this->getAttribute('id'));
        $stmt->execute();

        $this->exists = false;
        return true;
    }

    protected static function getConnection(): \Toporia\Framework\Database\Contracts\ConnectionInterface
    {
        return parent::getConnection();
    }

    public static function query(): ModelQueryBuilder
    {
        return parent::query();
    }

    public static function getPrimaryKey(): string
    {
        return 'id';
    }

    public function getKey(): mixed
    {
        return $this->getAttribute('id');
    }

    public function setKey(mixed $value): void
    {
        $this->setAttribute('id', $value);
    }
}
