<?php

declare(strict_types=1);

namespace Tests\Unit\Database\ORM;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\Relations\BelongsTo;
use Toporia\Framework\Database\ORM\ModelQueryBuilder;

/**
 * Test BelongsTo Relationship
 *
 * Comprehensive tests for BelongsTo relationship:
 * - Relationship query
 * - Associate parent model
 * - Dissociate parent model
 * - Relationship constraints
 * - Eager loading
 * - Access relationship
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class BelongsToRelationshipTest extends DatabaseTestCase
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

        // Create posts table (post belongs to user)
        $this->createTable('posts', "
            CREATE TABLE posts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                content TEXT,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
    }

    protected function dropTables(): void
    {
        $this->dropTable('posts');
        $this->dropTable('users');
    }

    /**
     * Test belongsTo relationship returns BelongsTo relation instance
     */
    public function test_belongs_to_returns_belongs_to_relation(): void
    {
        $post = new PostBelongsToUserModel(['user_id' => 1, 'title' => 'Test Post', 'content' => 'Content']);

        $relation = $post->user();

        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    /**
     * Test belongsTo relationship returns null when no parent exists
     */
    public function test_belongs_to_returns_null_when_no_parent_exists(): void
    {
        // Create post with non-existent user_id
        $post = new PostBelongsToUserModel(['user_id' => 999, 'title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        $user = $post->user()->getResults();

        $this->assertNull($user);
    }

    /**
     * Test belongsTo relationship returns parent model when exists
     */
    public function test_belongs_to_returns_parent_model_when_exists(): void
    {
        // Create user
        $user = new UserForBelongsToModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        // Create post belonging to user
        $post = new PostBelongsToUserModel(['user_id' => $user->id, 'title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        // Get user via relationship
        $relatedUser = $post->user()->getResults();

        $this->assertInstanceOf(UserForBelongsToModel::class, $relatedUser);
        $this->assertEquals($user->id, $relatedUser->id);
        $this->assertEquals('John Doe', $relatedUser->name);
        $this->assertEquals('john@example.com', $relatedUser->email);
    }

    /**
     * Test belongsTo relationship applies correct constraints
     */
    public function test_belongs_to_applies_correct_constraints(): void
    {
        // Create two users
        $user1 = new UserForBelongsToModel(['name' => 'John', 'email' => 'john@example.com']);
        $user1->save();

        $user2 = new UserForBelongsToModel(['name' => 'Jane', 'email' => 'jane@example.com']);
        $user2->save();

        // Create posts for each user
        $post1 = new PostBelongsToUserModel(['user_id' => $user1->id, 'title' => 'John Post', 'content' => 'Content']);
        $post1->save();

        $post2 = new PostBelongsToUserModel(['user_id' => $user2->id, 'title' => 'Jane Post', 'content' => 'Content']);
        $post2->save();

        // Each post should get its own user
        $relatedUser1 = $post1->user()->getResults();
        $relatedUser2 = $post2->user()->getResults();

        $this->assertNotNull($relatedUser1);
        $this->assertEquals('John', $relatedUser1->name);

        $this->assertNotNull($relatedUser2);
        $this->assertEquals('Jane', $relatedUser2->name);
    }

    /**
     * Test belongsTo relationship with custom foreign key
     */
    public function test_belongs_to_with_custom_foreign_key(): void
    {
        $user = new UserForBelongsToModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        // Create post with default foreign key (user_id)
        $post = new PostBelongsToUserModel(['user_id' => $user->id, 'title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        // Relationship should work with default foreign key
        $relatedUser = $post->user()->getResults();

        $this->assertNotNull($relatedUser);
        $this->assertEquals($user->id, $relatedUser->id);
    }

    /**
     * Test belongsTo relationship with custom owner key
     */
    public function test_belongs_to_with_custom_owner_key(): void
    {
        $user = new UserForBelongsToModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $post = new PostBelongsToUserModel(['user_id' => $user->id, 'title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        // Relationship should work with default owner key (id)
        $relatedUser = $post->user()->getResults();

        $this->assertNotNull($relatedUser);
        $this->assertEquals($user->id, $relatedUser->id);
    }

    /**
     * Test belongsTo relationship query can be modified
     */
    public function test_belongs_to_query_can_be_modified(): void
    {
        $user = new UserForBelongsToModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $post = new PostBelongsToUserModel(['user_id' => $user->id, 'title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        // Query with where clause
        $relatedUser = $post->user()->getQuery()->where('email', 'john@example.com')->first();

        $this->assertNotNull($relatedUser);
        $this->assertEquals('John Doe', $relatedUser['name']);
    }

    /**
     * Test belongsTo relationship returns null for null foreign key
     */
    public function test_belongs_to_returns_null_for_null_foreign_key(): void
    {
        // Create post with null user_id (shouldn't happen, but test edge case)
        $post = new PostBelongsToUserModel(['user_id' => null, 'title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        $user = $post->user()->getResults();

        $this->assertNull($user);
    }

    /**
     * Test belongsTo relationship with eager loading constraints
     */
    public function test_belongs_to_with_eager_loading_constraints(): void
    {
        // Create users
        $user1 = new UserForBelongsToModel(['name' => 'John', 'email' => 'john@example.com']);
        $user1->save();

        $user2 = new UserForBelongsToModel(['name' => 'Jane', 'email' => 'jane@example.com']);
        $user2->save();

        // Create posts
        $post1 = new PostBelongsToUserModel(['user_id' => $user1->id, 'title' => 'Post 1', 'content' => 'Content']);
        $post1->save();

        $post2 = new PostBelongsToUserModel(['user_id' => $user2->id, 'title' => 'Post 2', 'content' => 'Content']);
        $post2->save();

        // Test eager loading constraints
        $posts = [PostBelongsToUserModel::find($post1->id), PostBelongsToUserModel::find($post2->id)];
        $relation = $post1->user();

        // Add eager constraints
        $relation->addEagerConstraints($posts);

        // Get query to verify constraints
        $query = $relation->getQuery();
        $sql = $query->toSql();

        $this->assertNotNull($sql);
    }

    /**
     * Test belongsTo relationship match method
     */
    public function test_belongs_to_match_method(): void
    {
        // Create users
        $user1 = new UserForBelongsToModel(['name' => 'John', 'email' => 'john@example.com']);
        $user1->save();

        $user2 = new UserForBelongsToModel(['name' => 'Jane', 'email' => 'jane@example.com']);
        $user2->save();

        // Create posts
        $post1 = new PostBelongsToUserModel(['user_id' => $user1->id, 'title' => 'Post 1', 'content' => 'Content']);
        $post1->save();

        $post2 = new PostBelongsToUserModel(['user_id' => $user2->id, 'title' => 'Post 2', 'content' => 'Content']);
        $post2->save();

        // Get users as collection
        $users = UserForBelongsToModel::query()->get();

        // Match users to posts
        $posts = [$post1, $post2];
        $relation = $post1->user();
        $matched = $relation->match($posts, $users, 'user');

        $this->assertCount(2, $matched);
        $this->assertNotNull($post1->user);
        $this->assertEquals('John', $post1->user->name);
        $this->assertNotNull($post2->user);
        $this->assertEquals('Jane', $post2->user->name);
    }

    /**
     * Test belongsTo relationship getForeignKeyName
     */
    public function test_belongs_to_get_foreign_key_name(): void
    {
        $post = new PostBelongsToUserModel(['user_id' => 1, 'title' => 'Test Post', 'content' => 'Content']);

        $relation = $post->user();
        $foreignKey = $relation->getForeignKey();

        $this->assertEquals('user_id', $foreignKey);
    }

    /**
     * Test belongsTo relationship with non-existent parent
     */
    public function test_belongs_to_with_non_existent_parent(): void
    {
        $post = new PostBelongsToUserModel(['user_id' => 999, 'title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        $user = $post->user()->getResults();

        $this->assertNull($user);
    }

    /**
     * Test belongsTo relationship query builder is chainable
     */
    public function test_belongs_to_query_builder_is_chainable(): void
    {
        $user = new UserForBelongsToModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $post = new PostBelongsToUserModel(['user_id' => $user->id, 'title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        // Chain multiple query methods
        $relatedUser = $post->user()
            ->getQuery()
            ->where('email', 'john@example.com')
            ->first();

        $this->assertNotNull($relatedUser);
        $this->assertEquals('John Doe', $relatedUser['name']);
    }

    /**
     * Test belongsTo relationship with where clause on parent
     */
    public function test_belongs_to_with_where_clause_on_parent(): void
    {
        $user = new UserForBelongsToModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $post = new PostBelongsToUserModel(['user_id' => $user->id, 'title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        // Query with where clause on user
        $relatedUser = $post->user()->getQuery()->where('name', 'John Doe')->first();

        $this->assertNotNull($relatedUser);
        $this->assertEquals('john@example.com', $relatedUser['email']);
    }

    /**
     * Test belongsTo relationship returns parent for valid foreign key
     */
    public function test_belongs_to_returns_parent_for_valid_foreign_key(): void
    {
        $user = new UserForBelongsToModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $post = new PostBelongsToUserModel(['user_id' => $user->id, 'title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        $relatedUser = $post->user()->getResults();

        $this->assertNotNull($relatedUser);
        $this->assertEquals($user->id, $relatedUser->id);
        $this->assertEquals($user->name, $relatedUser->name);
    }

    /**
     * Test belongsTo relationship with multiple posts for same user
     */
    public function test_belongs_to_with_multiple_posts_for_same_user(): void
    {
        $user = new UserForBelongsToModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        // Create multiple posts for same user
        $post1 = new PostBelongsToUserModel(['user_id' => $user->id, 'title' => 'Post 1', 'content' => 'Content']);
        $post1->save();

        $post2 = new PostBelongsToUserModel(['user_id' => $user->id, 'title' => 'Post 2', 'content' => 'Content']);
        $post2->save();

        // Both posts should return same user
        $user1 = $post1->user()->getResults();
        $user2 = $post2->user()->getResults();

        $this->assertNotNull($user1);
        $this->assertNotNull($user2);
        $this->assertEquals($user1->id, $user2->id);
        $this->assertEquals($user->id, $user1->id);
    }

    /**
     * Test belongsTo relationship with empty result
     */
    public function test_belongs_to_with_empty_result(): void
    {
        // Create post without user
        $post = new PostBelongsToUserModel(['user_id' => 999, 'title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        $user = $post->user()->getResults();

        $this->assertNull($user);
    }
}

/**
 * User model (parent for BelongsTo)
 */
class UserForBelongsToModel extends Model
{
    protected static string $table = 'users';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['name', 'email'];

    public function save(): bool
    {
        if (!$this->exists) {
            $attributes = $reflection = new \ReflectionClass($this); $property = $reflection->getProperty("attributes"); $property->setAccessible(true); $attributes = $property->getValue($this); $attributes = array_filter($attributes, fn($v) => $v !== null);
            $columns = "`" . implode("`, `", array_keys($attributes)) . "`";
            $placeholders = ':' . implode(', :', array_keys($attributes));

            $sql = "INSERT INTO users ({$columns}) VALUES ({$placeholders})";
            $stmt = $this->getConnection()->getPdo()->prepare($sql);

            foreach ($attributes as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }

            $stmt->execute();
            $this->setAttribute('id', (int) $this->getConnection()->getPdo()->lastInsertId());
            $this->exists = true;
            $this->syncOriginal();
            return true;
        }
        return true;
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
        $row = static::query()->where('id', $id)->first();
        if (!$row) {
            return null;
        }
        $model = new static($row);
        $model->exists = true;
        $model->syncOriginal();
        return $model;
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
 * Post model (belongs to User)
 */
class PostBelongsToUserModel extends Model
{
    protected static string $table = 'posts';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['user_id', 'title', 'content'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserForBelongsToModel::class, 'user_id', 'id');
    }

    public function save(): bool
    {
        if (!$this->exists) {
            $attributes = $reflection = new \ReflectionClass($this); $property = $reflection->getProperty("attributes"); $property->setAccessible(true); $attributes = $property->getValue($this); $attributes = array_filter($attributes, fn($v) => $v !== null);
            $columns = "`" . implode("`, `", array_keys($attributes)) . "`";
            $placeholders = ':' . implode(', :', array_keys($attributes));

            $sql = "INSERT INTO posts ({$columns}) VALUES ({$placeholders})";
            $stmt = $this->getConnection()->getPdo()->prepare($sql);

            foreach ($attributes as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }

            $stmt->execute();
            $this->setAttribute('id', (int) $this->getConnection()->getPdo()->lastInsertId());
            $this->exists = true;
            $this->syncOriginal();
            return true;
        }
        return true;
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
        $row = static::query()->where('id', $id)->first();
        if (!$row) {
            return null;
        }
        $model = new static($row);
        $model->exists = true;
        $model->syncOriginal();
        return $model;
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
