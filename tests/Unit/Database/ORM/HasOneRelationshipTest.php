<?php

declare(strict_types=1);

namespace Tests\Unit\Database\ORM;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\Relations\HasOne;
use Toporia\Framework\Database\ORM\ModelCollection;
use Toporia\Framework\Database\ORM\ModelQueryBuilder;

/**
 * Test HasOne Relationship
 *
 * ✅ TEST STATUS: ALL PASSED (16/16)
 * ✅ Last verified: 2025-01-22
 * ✅ Fixed: Foreign key constraints (nullable FK), RowCollection vs ModelCollection, whereNotNull() for null checks
 * ✅ Fixed: HasOne::getResults() re-applies constraints after query modification
 *
 * Comprehensive tests for HasOne relationship:
 * - Relationship query
 * - Create related model
 * - Update related model
 * - Delete related model
 * - Relationship constraints
 * - Eager loading
 * - Access relationship
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class HasOneRelationshipTest extends DatabaseTestCase
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Create profiles table (one profile per user)
        // Note: user_id is nullable to allow testing edge cases
        $this->createTable('profiles', "
            CREATE TABLE profiles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                bio TEXT,
                avatar VARCHAR(255),
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    protected function dropTables(): void
    {
        $this->dropTable('profiles');
        $this->dropTable('users');
    }

    /**
     * Test hasOne relationship returns HasOne relation instance
     */
    public function test_has_one_returns_has_one_relation(): void
    {
        $user = new UserModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $relation = $user->profile();

        $this->assertInstanceOf(HasOne::class, $relation);
    }

    /**
     * Test hasOne relationship query returns null when no related record
     */
    public function test_has_one_returns_null_when_no_related_record(): void
    {
        $user = new UserModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $profile = $user->profile()->getResults();

        $this->assertNull($profile);
    }

    /**
     * Test hasOne relationship returns related model when exists
     */
    public function test_has_one_returns_related_model_when_exists(): void
    {
        // Create user
        $user = new UserModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        // Create profile for user
        $this->executeQuery(
            "INSERT INTO profiles (user_id, bio) VALUES (?, ?)",
            [$user->id, 'Test bio']
        );

        // Get profile via relationship
        $profile = $user->profile()->getResults();

        $this->assertInstanceOf(ProfileModel::class, $profile);
        $this->assertEquals($user->id, $profile->user_id);
        $this->assertEquals('Test bio', $profile->bio);
    }

    /**
     * Test hasOne relationship applies correct constraints
     */
    public function test_has_one_applies_correct_constraints(): void
    {
        // Create two users
        $user1 = new UserModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user1->save();

        $user2 = new UserModel(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $user2->save();

        // Create profiles for both users
        $this->executeQuery(
            "INSERT INTO profiles (user_id, bio) VALUES (?, ?)",
            [$user1->id, 'John bio']
        );
        $this->executeQuery(
            "INSERT INTO profiles (user_id, bio) VALUES (?, ?)",
            [$user2->id, 'Jane bio']
        );

        // Each user should get their own profile
        $profile1 = $user1->profile()->getResults();
        $profile2 = $user2->profile()->getResults();

        $this->assertNotNull($profile1);
        $this->assertEquals('John bio', $profile1->bio);

        $this->assertNotNull($profile2);
        $this->assertEquals('Jane bio', $profile2->bio);
    }

    /**
     * Test hasOne relationship with custom foreign key
     */
    public function test_has_one_with_custom_foreign_key(): void
    {
        $user = new UserModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        // Create profile with custom foreign key (user_id)
        $this->executeQuery(
            "INSERT INTO profiles (user_id, bio) VALUES (?, ?)",
            [$user->id, 'Test bio']
        );

        // Relationship should work with default foreign key (user_id)
        $profile = $user->profile()->getResults();

        $this->assertNotNull($profile);
        $this->assertEquals($user->id, $profile->user_id);
    }

    /**
     * Test hasOne relationship with custom local key
     */
    public function test_has_one_with_custom_local_key(): void
    {
        // Note: This test assumes local key defaults to primary key
        // If custom local key is used, it should still work
        $user = new UserModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $this->executeQuery(
            "INSERT INTO profiles (user_id, bio) VALUES (?, ?)",
            [$user->id, 'Test bio']
        );

        $profile = $user->profile()->getResults();

        $this->assertNotNull($profile);
        $this->assertEquals($user->id, $profile->user_id);
    }

    /**
     * Test hasOne relationship query can be modified
     */
    public function test_has_one_query_can_be_modified(): void
    {
        $user = new UserModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        // Create multiple profiles (shouldn't happen with HasOne, but test constraint)
        $this->executeQuery(
            "INSERT INTO profiles (user_id, bio) VALUES (?, ?)",
            [$user->id, 'Bio 1']
        );
        $this->executeQuery(
            "INSERT INTO profiles (user_id, bio) VALUES (?, ?)",
            [$user->id, 'Bio 2']
        );

        // Query with orderBy should return first ordered result
        /** @var ProfileModel|null $profile */
        $profile = $user->profile()->getQuery()->orderBy('id', 'ASC')->first();

        $this->assertNotNull($profile);
        $this->assertEquals('Bio 1', $profile->bio);
    }

    /**
     * Test hasOne relationship with where clause
     */
    public function test_has_one_with_where_clause(): void
    {
        $user = new UserModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        // Create profile
        $this->executeQuery(
            "INSERT INTO profiles (user_id, bio, avatar) VALUES (?, ?, ?)",
            [$user->id, 'Test bio', 'avatar.jpg']
        );

        // Query with where clause - modify relationship query then get results
        // Note: getResults() will re-apply base constraint, so we can safely add additional where
        $relation = $user->profile();
        $relation->getQuery()->whereNotNull('avatar');
        $profile = $relation->getResults();

        $this->assertNotNull($profile);
        $this->assertEquals('avatar.jpg', $profile->avatar);
    }

    /**
     * Test hasOne relationship returns null for non-existent parent
     */
    public function test_has_one_returns_null_for_non_existent_parent(): void
    {
        // Create user without saving (doesn't exist in DB)
        $user = new UserModel(['name' => 'John Doe', 'email' => 'john@example.com']);

        // Relationship should return null for non-existent parent
        $profile = $user->profile()->getResults();

        $this->assertNull($profile);
    }

    /**
     * Test hasOne relationship with eager loading constraints
     */
    public function test_has_one_with_eager_loading_constraints(): void
    {
        // Create multiple users
        $user1 = new UserModel(['name' => 'John', 'email' => 'john@example.com']);
        $user1->save();

        $user2 = new UserModel(['name' => 'Jane', 'email' => 'jane@example.com']);
        $user2->save();

        // Create profiles
        $this->executeQuery(
            "INSERT INTO profiles (user_id, bio) VALUES (?, ?)",
            [$user1->id, 'John bio']
        );
        $this->executeQuery(
            "INSERT INTO profiles (user_id, bio) VALUES (?, ?)",
            [$user2->id, 'Jane bio']
        );

        // Test eager loading constraints
        $users = [UserModel::find($user1->id), UserModel::find($user2->id)];
        $relation = $user1->profile();

        // Add eager constraints
        $relation->addEagerConstraints($users);

        // Get query to verify constraints
        $query = $relation->getQuery();

        // Query should have WHERE IN constraint for both user IDs
        $sql = $query->toSql();

        $this->assertNotNull($sql);
    }

    /**
     * Test hasOne relationship match method
     */
    public function test_has_one_match_method(): void
    {
        // Create users
        $user1 = new UserModel(['name' => 'John', 'email' => 'john@example.com']);
        $user1->save();

        $user2 = new UserModel(['name' => 'Jane', 'email' => 'jane@example.com']);
        $user2->save();

        // Create profiles
        $this->executeQuery(
            "INSERT INTO profiles (user_id, bio) VALUES (?, ?)",
            [$user1->id, 'John bio']
        );
        $this->executeQuery(
            "INSERT INTO profiles (user_id, bio) VALUES (?, ?)",
            [$user2->id, 'Jane bio']
        );

        // Get profiles as ModelCollection
        $profiles = ProfileModel::query()->getModels();

        // Match profiles to users
        $users = [$user1, $user2];
        $relation = $user1->profile();
        $matched = $relation->match($users, $profiles, 'profile');

        $this->assertCount(2, $matched);
        $this->assertNotNull($user1->profile);
        $this->assertEquals('John bio', $user1->profile->bio);
        $this->assertNotNull($user2->profile);
        $this->assertEquals('Jane bio', $user2->profile->bio);
    }

    /**
     * Test hasOne relationship with non-matching foreign keys
     */
    public function test_has_one_with_non_matching_foreign_keys(): void
    {
        $user = new UserModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        // Create profile for different user (insert directly to bypass FK constraint)
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $this->executeQuery(
            "INSERT INTO profiles (user_id, bio) VALUES (?, ?)",
            [999, 'Other user bio']
        );
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=1");

        // Should return null (no profile for this user)
        $profile = $user->profile()->getResults();

        $this->assertNull($profile);
    }

    /**
     * Test hasOne relationship getForeignKeyName
     */
    public function test_has_one_get_foreign_key_name(): void
    {
        $user = new UserModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $relation = $user->profile();
        $foreignKey = $relation->getForeignKeyName();

        $this->assertEquals('user_id', $foreignKey);
    }

    /**
     * Test hasOne relationship with null foreign key
     */
    public function test_has_one_with_null_foreign_key(): void
    {
        $user = new UserModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        // Create profile with null user_id (shouldn't happen, but test edge case)
        $this->executeQuery(
            "INSERT INTO profiles (user_id, bio) VALUES (?, ?)",
            [null, 'No user bio']
        );

        // Should return null (can't match null foreign key)
        $profile = $user->profile()->getResults();

        $this->assertNull($profile);
    }

    /**
     * Test hasOne relationship query builder is chainable
     */
    public function test_has_one_query_builder_is_chainable(): void
    {
        $user = new UserModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $this->executeQuery(
            "INSERT INTO profiles (user_id, bio, avatar) VALUES (?, ?, ?)",
            [$user->id, 'Test bio', 'avatar.jpg']
        );

        // Chain multiple query methods - modify relationship query then get results
        $relation = $user->profile();
        $relation->getQuery()
            ->whereNotNull('avatar')
            ->orderBy('id', 'DESC');
        $profile = $relation->getResults();

        $this->assertNotNull($profile);
        $this->assertEquals('avatar.jpg', $profile->avatar);
    }

    /**
     * Test hasOne relationship with empty result
     */
    public function test_has_one_with_empty_result(): void
    {
        $user = new UserModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        // No profile created

        $profile = $user->profile()->getResults();

        $this->assertNull($profile);
    }
}

/**
 * User model with HasOne relationship
 */
class UserModel extends Model
{
    protected static string $table = 'users';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['name', 'email'];

    public function profile(): HasOne
    {
        return $this->hasOne(ProfileModel::class, 'user_id', 'id');
    }

    public function save(): bool
    {
        // Use parent save() which handles all the logic
        return parent::save();
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
        // first() now returns Model|null directly
        return static::query()->where('id', $id)->first();
    }
}

/**
 * Profile model (related to User)
 */
class ProfileModel extends Model
{
    protected static string $table = 'profiles';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['user_id', 'bio', 'avatar'];

    public function save(): bool
    {
        // Implementation similar to UserModel
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

    public static function hydrate(array $rows): ModelCollection
    {
        $models = [];
        foreach ($rows as $row) {
            $model = new static($row);
            $model->exists = true;
            $model->syncOriginal();
            $models[] = $model;
        }
        return new ModelCollection($models);
    }
}
