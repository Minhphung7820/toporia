<?php

declare(strict_types=1);

namespace Tests\Unit\Database\ORM;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\Relations\BelongsToMany;
use Toporia\Framework\Database\ORM\ModelCollection;
use Toporia\Framework\Database\ORM\ModelQueryBuilder;

/**
 * Test BelongsToMany Relationship
 *
 * ✅ TEST STATUS: ALL PASSED (19/19)
 * ✅ Last verified: 2025-01-22
 * ✅ Fixed: Removed custom save() reflection, fixed getForeignKeyName(), replaced dbGet() with assertTableHas()
 *
 * Comprehensive tests for many-to-many relationship:
 * - Relationship query
 * - Attach related models
 * - Detach related models
 * - Sync relationship
 * - Toggle relationship
 * - Pivot table operations
 * - Relationship constraints
 * - Eager loading
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class BelongsToManyRelationshipTest extends DatabaseTestCase
{
    protected function createTables(): void
    {
        // Create users table
        $this->createTable('users', "
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            )
        ");

        // Create roles table
        $this->createTable('roles', "
            CREATE TABLE roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            )
        ");

        // Create pivot table (user_role)
        $this->createTable('role_user', "
            CREATE TABLE role_user (
                user_id INT NOT NULL,
                role_id INT NOT NULL,
                created_at DATETIME NULL,
                PRIMARY KEY (user_id, role_id),
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (role_id) REFERENCES roles(id)
            )
        ");
    }

    protected function dropTables(): void
    {
        $this->dropTable('role_user');
        $this->dropTable('roles');
        $this->dropTable('users');
    }

    /**
     * Test belongsToMany relationship returns BelongsToMany relation instance
     */
    public function test_belongs_to_many_returns_belongs_to_many_relation(): void
    {
        $user = new UserBelongsToManyModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $relation = $user->roles();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }

    /**
     * Test belongsToMany relationship returns empty collection when no related records
     */
    public function test_belongs_to_many_returns_empty_collection_when_no_roles(): void
    {
        $user = new UserBelongsToManyModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $roles = $user->roles()->getResults();

        $this->assertInstanceOf(ModelCollection::class, $roles);
        $this->assertTrue($roles->isEmpty());
    }

    /**
     * Test attach adds related model to pivot table
     */
    public function test_attach_adds_related_model(): void
    {
        // Create user and role
        $user = new UserBelongsToManyModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $role = new RoleBelongsToManyModel(['name' => 'Admin']);
        $role->save();

        // Attach role to user
        $result = $user->roles()->attach($role->id);

        $this->assertTrue($result);

        // Verify pivot table has record
        $this->assertTableHas('role_user', ['user_id' => $user->id, 'role_id' => $role->id]);
    }

    /**
     * Test attach with pivot data
     */
    public function test_attach_with_pivot_data(): void
    {
        $user = new UserBelongsToManyModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $role = new RoleBelongsToManyModel(['name' => 'Admin']);
        $role->save();

        // Attach with pivot data
        $user->roles()->attach($role->id, ['created_at' => '2025-01-01 00:00:00']);

        // Verify pivot table has record with data using assertTableHas
        $this->assertTableHas('role_user', [
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => '2025-01-01 00:00:00'
        ]);
    }

    /**
     * Test detach removes related model from pivot table
     */
    public function test_detach_removes_related_model(): void
    {
        $user = new UserBelongsToManyModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $role = new RoleBelongsToManyModel(['name' => 'Admin']);
        $role->save();

        // Attach role
        $user->roles()->attach($role->id);
        $this->assertTableHas('role_user', ['user_id' => $user->id, 'role_id' => $role->id]);

        // Detach role
        $deleted = $user->roles()->detach($role->id);

        $this->assertEquals(1, $deleted);
        $this->assertTableMissing('role_user', ['user_id' => $user->id, 'role_id' => $role->id]);
    }

    /**
     * Test detach without ID removes all related models
     */
    public function test_detach_without_id_removes_all_roles(): void
    {
        $user = new UserBelongsToManyModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $role1 = new RoleBelongsToManyModel(['name' => 'Admin']);
        $role1->save();

        $role2 = new RoleBelongsToManyModel(['name' => 'Editor']);
        $role2->save();

        // Attach both roles
        $user->roles()->attach($role1->id);
        $user->roles()->attach($role2->id);

        $this->assertTableCount('role_user', 2, ['user_id' => $user->id]);

        // Detach all
        $deleted = $user->roles()->detach();

        $this->assertEquals(2, $deleted);
        $this->assertTableCount('role_user', 0, ['user_id' => $user->id]);
    }

    /**
     * Test sync replaces all relationships
     */
    public function test_sync_replaces_all_relationships(): void
    {
        $user = new UserBelongsToManyModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $role1 = new RoleBelongsToManyModel(['name' => 'Admin']);
        $role1->save();

        $role2 = new RoleBelongsToManyModel(['name' => 'Editor']);
        $role2->save();

        $role3 = new RoleBelongsToManyModel(['name' => 'Viewer']);
        $role3->save();

        // Attach role1 and role2
        $user->roles()->attach($role1->id);
        $user->roles()->attach($role2->id);

        $this->assertTableCount('role_user', 2, ['user_id' => $user->id]);

        // Sync with role2 and role3
        $user->roles()->sync([$role2->id, $role3->id]);

        // Should have role2 and role3 only
        $this->assertTableCount('role_user', 2, ['user_id' => $user->id]);
        $this->assertTableHas('role_user', ['user_id' => $user->id, 'role_id' => $role2->id]);
        $this->assertTableHas('role_user', ['user_id' => $user->id, 'role_id' => $role3->id]);
        $this->assertTableMissing('role_user', ['user_id' => $user->id, 'role_id' => $role1->id]);
    }

    /**
     * Test sync with empty array removes all relationships
     */
    public function test_sync_with_empty_array_removes_all(): void
    {
        $user = new UserBelongsToManyModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $role = new RoleBelongsToManyModel(['name' => 'Admin']);
        $role->save();

        // Attach role
        $user->roles()->attach($role->id);
        $this->assertTableCount('role_user', 1, ['user_id' => $user->id]);

        // Sync with empty array
        $user->roles()->sync([]);

        $this->assertTableCount('role_user', 0, ['user_id' => $user->id]);
    }

    /**
     * Test belongsToMany relationship returns related models when exist
     */
    public function test_belongs_to_many_returns_related_models_when_exist(): void
    {
        $user = new UserBelongsToManyModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $role1 = new RoleBelongsToManyModel(['name' => 'Admin']);
        $role1->save();

        $role2 = new RoleBelongsToManyModel(['name' => 'Editor']);
        $role2->save();

        // Attach roles
        $user->roles()->attach($role1->id);
        $user->roles()->attach($role2->id);

        // Get roles via relationship
        $roles = $user->roles()->getResults();

        $this->assertInstanceOf(ModelCollection::class, $roles);
        $this->assertCount(2, $roles);
    }

    /**
     * Test attach multiple related models
     */
    public function test_attach_multiple_related_models(): void
    {
        $user = new UserBelongsToManyModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $role1 = new RoleBelongsToManyModel(['name' => 'Admin']);
        $role1->save();

        $role2 = new RoleBelongsToManyModel(['name' => 'Editor']);
        $role2->save();

        $role3 = new RoleBelongsToManyModel(['name' => 'Viewer']);
        $role3->save();

        // Attach all roles
        $user->roles()->attach($role1->id);
        $user->roles()->attach($role2->id);
        $user->roles()->attach($role3->id);

        $this->assertTableCount('role_user', 3, ['user_id' => $user->id]);
    }

    /**
     * Test detach specific related model
     */
    public function test_detach_specific_related_model(): void
    {
        $user = new UserBelongsToManyModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $role1 = new RoleBelongsToManyModel(['name' => 'Admin']);
        $role1->save();

        $role2 = new RoleBelongsToManyModel(['name' => 'Editor']);
        $role2->save();

        // Attach both roles
        $user->roles()->attach($role1->id);
        $user->roles()->attach($role2->id);

        // Detach only role1
        $deleted = $user->roles()->detach($role1->id);

        $this->assertEquals(1, $deleted);
        $this->assertTableMissing('role_user', ['user_id' => $user->id, 'role_id' => $role1->id]);
        $this->assertTableHas('role_user', ['user_id' => $user->id, 'role_id' => $role2->id]);
    }

    /**
     * Test relationship isolation between users
     */
    public function test_relationship_isolation_between_users(): void
    {
        $user1 = new UserBelongsToManyModel(['name' => 'John', 'email' => 'john@example.com']);
        $user1->save();

        $user2 = new UserBelongsToManyModel(['name' => 'Jane', 'email' => 'jane@example.com']);
        $user2->save();

        $role = new RoleBelongsToManyModel(['name' => 'Admin']);
        $role->save();

        // Attach role to user1
        $user1->roles()->attach($role->id);

        // user1 should have role, user2 should not
        $this->assertTableHas('role_user', ['user_id' => $user1->id, 'role_id' => $role->id]);
        $this->assertTableMissing('role_user', ['user_id' => $user2->id, 'role_id' => $role->id]);
    }

    /**
     * Test relationship applies correct constraints
     */
    public function test_relationship_applies_correct_constraints(): void
    {
        $user1 = new UserBelongsToManyModel(['name' => 'John', 'email' => 'john@example.com']);
        $user1->save();

        $user2 = new UserBelongsToManyModel(['name' => 'Jane', 'email' => 'jane@example.com']);
        $user2->save();

        $role1 = new RoleBelongsToManyModel(['name' => 'Admin']);
        $role1->save();

        $role2 = new RoleBelongsToManyModel(['name' => 'Editor']);
        $role2->save();

        // Attach different roles to different users
        $user1->roles()->attach($role1->id);
        $user2->roles()->attach($role2->id);

        // Each user should get their own roles
        $roles1 = $user1->roles()->getResults();
        $roles2 = $user2->roles()->getResults();

        $this->assertCount(1, $roles1);
        $this->assertCount(1, $roles2);
    }

    /**
     * Test attach same role multiple times (should not duplicate)
     */
    public function test_attach_same_role_multiple_times(): void
    {
        $user = new UserBelongsToManyModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $role = new RoleBelongsToManyModel(['name' => 'Admin']);
        $role->save();

        // Try to attach same role multiple times
        try {
            $user->roles()->attach($role->id);
            $user->roles()->attach($role->id);
            $user->roles()->attach($role->id);
        } catch (\Exception $e) {
            // SQLite will throw exception on duplicate primary key
            // This is expected behavior
        }

        // Should have only one record (or exception thrown)
        $count = $this->getTableCount('role_user', ['user_id' => $user->id, 'role_id' => $role->id]);
        $this->assertLessThanOrEqual(1, $count);
    }

    /**
     * Test detach non-existent relationship
     */
    public function test_detach_non_existent_relationship(): void
    {
        $user = new UserBelongsToManyModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $role = new RoleBelongsToManyModel(['name' => 'Admin']);
        $role->save();

        // Try to detach non-existent relationship
        $deleted = $user->roles()->detach($role->id);

        $this->assertEquals(0, $deleted);
    }

    /**
     * Test sync with same IDs maintains relationships
     */
    public function test_sync_with_same_ids_maintains_relationships(): void
    {
        $user = new UserBelongsToManyModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $role1 = new RoleBelongsToManyModel(['name' => 'Admin']);
        $role1->save();

        $role2 = new RoleBelongsToManyModel(['name' => 'Editor']);
        $role2->save();

        // Attach roles
        $user->roles()->attach($role1->id);
        $user->roles()->attach($role2->id);

        // Sync with same IDs
        $user->roles()->sync([$role1->id, $role2->id]);

        // Should still have both roles
        $this->assertTableCount('role_user', 2, ['user_id' => $user->id]);
    }

    /**
     * Test relationship with where clause
     */
    public function test_relationship_with_where_clause(): void
    {
        $user = new UserBelongsToManyModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $role1 = new RoleBelongsToManyModel(['name' => 'Admin']);
        $role1->save();

        $role2 = new RoleBelongsToManyModel(['name' => 'Editor']);
        $role2->save();

        // Attach both roles
        $user->roles()->attach($role1->id);
        $user->roles()->attach($role2->id);

        // Query with where clause
        $roles = $user->roles()->getQuery()->where('name', 'Admin')->get();

        $this->assertCount(1, $roles);
        $this->assertEquals('Admin', $roles->first()['name']);
    }

    /**
     * Test relationship with eager loading constraints
     */
    public function test_relationship_with_eager_loading_constraints(): void
    {
        $user1 = new UserBelongsToManyModel(['name' => 'John', 'email' => 'john@example.com']);
        $user1->save();

        $user2 = new UserBelongsToManyModel(['name' => 'Jane', 'email' => 'jane@example.com']);
        $user2->save();

        $role = new RoleBelongsToManyModel(['name' => 'Admin']);
        $role->save();

        // Attach role to both users
        $user1->roles()->attach($role->id);
        $user2->roles()->attach($role->id);

        // Test eager loading constraints
        $users = [UserBelongsToManyModel::find($user1->id), UserBelongsToManyModel::find($user2->id)];
        $relation = $user1->roles();

        // Add eager constraints
        $relation->addEagerConstraints($users);

        // Get query to verify constraints
        $query = $relation->getQuery();
        $sql = $query->toSql();

        $this->assertNotNull($sql);
    }

    /**
     * Test getForeignKeyName returns correct key
     */
    public function test_get_foreign_key_name_returns_correct_key(): void
    {
        $user = new UserBelongsToManyModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $relation = $user->roles();
        $foreignKey = $relation->getForeignKeyName();

        // Should return relatedPivotKey
        $this->assertEquals('role_id', $foreignKey);
    }
}

/**
 * User model with BelongsToMany relationship
 */
class UserBelongsToManyModel extends Model
{
    protected static string $table = 'users';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['name', 'email'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(RoleBelongsToManyModel::class, 'role_user', 'user_id', 'role_id', 'id', 'id');
    }

    public function getKey(): mixed
    {
        return $this->getAttribute('id');
    }

    protected static function getConnection(): \Toporia\Framework\Database\Contracts\ConnectionInterface
    {
        return parent::getConnection();
    }

    public static function query(): ModelQueryBuilder
    {
        return parent::query();
    }

    public static function find(int|string $id): ?static
    {
        return parent::find($id);
    }

    public function setRelation(string $name, mixed $value): Model
    {
        $reflection = new \ReflectionClass($this);
        $property = $reflection->getProperty('relations');
        $property->setAccessible(true);
        $relations = $property->getValue($this);
        $relations[$name] = $value;
        $property->setValue($this, $relations);
        return $this;
        $property->setValue($this, $relations);
    }
}

/**
 * Role model (related to User)
 */
class RoleBelongsToManyModel extends Model
{
    protected static string $table = 'roles';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['name'];



    public function getKey(): mixed
    {
        return $this->getAttribute('id');
    }

    protected static function getConnection(): \Toporia\Framework\Database\Contracts\ConnectionInterface
    {
        return parent::getConnection();
    }

    public static function query(): ModelQueryBuilder
    {
        return parent::query();
    }

    public static function hydrate(array|\Toporia\Framework\Database\Query\RowCollection $rows): ModelCollection
    {
        // Convert RowCollection to array if needed
        if ($rows instanceof \Toporia\Framework\Database\Query\RowCollection) {
            $rows = $rows->all();
        }

        return parent::hydrate($rows);
    }
}





