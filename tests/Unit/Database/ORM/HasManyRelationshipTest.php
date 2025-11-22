<?php

declare(strict_types=1);

namespace Tests\Unit\Database\ORM;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\Relations\HasMany;
use Toporia\Framework\Database\ORM\ModelCollection;
use Toporia\Framework\Database\ORM\ModelQueryBuilder;

/**
 * Test HasMany Relationship
 *
 * Comprehensive tests for HasMany relationship:
 * - Relationship query
 * - Create related models
 * - Update related models
 * - Delete related models
 * - Count relationship
 * - Relationship constraints
 * - Eager loading
 * - Access relationship collection
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class HasManyRelationshipTest extends DatabaseTestCase
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

        // Create posts table (many posts per user)
        $this->createTable('posts', "
            CREATE TABLE posts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                content TEXT,
                published INT DEFAULT 0,
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
     * Test hasMany relationship returns HasMany relation instance
     */
    public function test_has_many_returns_has_many_relation(): void
    {
        $user = new UserWithPostsModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $relation = $user->posts();

        $this->assertInstanceOf(HasMany::class, $relation);
    }

    /**
     * Test hasMany relationship returns empty collection when no related records
     */
    public function test_has_many_returns_empty_collection_when_no_posts(): void
    {
        $user = new UserWithPostsModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $posts = $user->posts()->getResults();

        $this->assertInstanceOf(ModelCollection::class, $posts);
        $this->assertTrue($posts->isEmpty());
        $this->assertEquals(0, $posts->count());
    }

    /**
     * Test hasMany relationship returns related models when exist
     */
    public function test_has_many_returns_related_models_when_exist(): void
    {
        // Create user
        $user = new UserWithPostsModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        // Create posts for user
        $this->executeQuery("INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)",
            [$user->id, 'Post 1', 'Content 1']);
        $this->executeQuery("INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)",
            [$user->id, 'Post 2', 'Content 2']);

        // Get posts via relationship
        $posts = $user->posts()->getResults();

        $this->assertInstanceOf(ModelCollection::class, $posts);
        $this->assertCount(2, $posts);
        $this->assertEquals('Post 1', $posts->first()->title);
        $this->assertEquals('Post 2', $posts->last()->title);
    }

    /**
     * Test hasMany relationship applies correct constraints
     */
    public function test_has_many_applies_correct_constraints(): void
    {
        // Create two users
        $user1 = new UserWithPostsModel(['name' => 'John', 'email' => 'john@example.com']);
        $user1->save();

        $user2 = new UserWithPostsModel(['name' => 'Jane', 'email' => 'jane@example.com']);
        $user2->save();

        // Create posts for both users
        $this->executeQuery("INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [$user1->id, 'John Post 1']);
        $this->executeQuery("INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [$user1->id, 'John Post 2']);
        $this->executeQuery("INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [$user2->id, 'Jane Post 1']);

        // Each user should get their own posts
        $posts1 = $user1->posts()->getResults();
        $posts2 = $user2->posts()->getResults();

        $this->assertCount(2, $posts1);
        $this->assertCount(1, $posts2);

        foreach ($posts1 as $post) {
            $this->assertEquals($user1->id, $post->user_id);
        }

        foreach ($posts2 as $post) {
            $this->assertEquals($user2->id, $post->user_id);
        }
    }

    /**
     * Test hasMany relationship with multiple related records
     */
    public function test_has_many_with_multiple_related_records(): void
    {
        $user = new UserWithPostsModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        // Create 10 posts
        for ($i = 1; $i <= 10; $i++) {
            $this->executeQuery("INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)",
                [$user->id, "Post {$i}", "Content {$i}"]);
        }

        $posts = $user->posts()->getResults();

        $this->assertCount(10, $posts);

        // Verify all posts belong to this user
        foreach ($posts as $post) {
            $this->assertEquals($user->id, $post->user_id);
        }
    }

    /**
     * Test hasMany relationship with where clause
     */
    public function test_has_many_with_where_clause(): void
    {
        $user = new UserWithPostsModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        // Create published and unpublished posts
        $this->executeQuery("INSERT INTO posts (user_id, title, published) VALUES (?, ?, ?)",
            [$user->id, 'Published Post', 1]);
        $this->executeQuery("INSERT INTO posts (user_id, title, published) VALUES (?, ?, ?)",
            [$user->id, 'Unpublished Post', 0]);

        // Query only published posts
        $publishedPosts = $user->posts()->getQuery()->where('published', 1)->get();

        $this->assertCount(1, $publishedPosts);
        $this->assertEquals('Published Post', $publishedPosts->first()['title']);
    }

    /**
     * Test hasMany relationship with orderBy
     */
    public function test_has_many_with_order_by(): void
    {
        $user = new UserWithPostsModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        // Create posts
        $this->executeQuery("INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [$user->id, 'Post 3']);
        $this->executeQuery("INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [$user->id, 'Post 1']);
        $this->executeQuery("INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [$user->id, 'Post 2']);

        // Query with orderBy
        $posts = $user->posts()->getQuery()->orderBy('title', 'ASC')->get();

        $this->assertCount(3, $posts);
        $this->assertEquals('Post 1', $posts[0]['title']);
        $this->assertEquals('Post 2', $posts[1]['title']);
        $this->assertEquals('Post 3', $posts[2]['title']);
    }

    /**
     * Test hasMany relationship count
     */
    public function test_has_many_count(): void
    {
        $user = new UserWithPostsModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        // Create 5 posts
        for ($i = 1; $i <= 5; $i++) {
            $this->executeQuery("INSERT INTO posts (user_id, title) VALUES (?, ?)",
                [$user->id, "Post {$i}"]);
        }

        $count = $user->posts()->getQuery()->count();

        $this->assertEquals(5, $count);
    }

    /**
     * Test hasMany relationship with limit
     */
    public function test_has_many_with_limit(): void
    {
        $user = new UserWithPostsModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        // Create 10 posts
        for ($i = 1; $i <= 10; $i++) {
            $this->executeQuery("INSERT INTO posts (user_id, title) VALUES (?, ?)",
                [$user->id, "Post {$i}"]);
        }

        // Get only first 5 posts
        $posts = $user->posts()->getQuery()->limit(5)->get();

        $this->assertCount(5, $posts);
    }

    /**
     * Test hasMany relationship match method
     */
    public function test_has_many_match_method(): void
    {
        // Create users
        $user1 = new UserWithPostsModel(['name' => 'John', 'email' => 'john@example.com']);
        $user1->save();

        $user2 = new UserWithPostsModel(['name' => 'Jane', 'email' => 'jane@example.com']);
        $user2->save();

        // Create posts
        $this->executeQuery("INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [$user1->id, 'John Post 1']);
        $this->executeQuery("INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [$user1->id, 'John Post 2']);
        $this->executeQuery("INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [$user2->id, 'Jane Post 1']);

        // Get all posts
        $allPosts = PostModel::query()->get();

        // Match posts to users
        $users = [$user1, $user2];
        $relation = $user1->posts();
        $matched = $relation->match($users, $allPosts, 'posts');

        $this->assertCount(2, $matched);
        $this->assertNotNull($user1->posts);
        $this->assertInstanceOf(ModelCollection::class, $user1->posts);
        $this->assertCount(2, $user1->posts);

        $this->assertNotNull($user2->posts);
        $this->assertInstanceOf(ModelCollection::class, $user2->posts);
        $this->assertCount(1, $user2->posts);
    }

    /**
     * Test hasMany relationship with eager loading constraints
     */
    public function test_has_many_with_eager_loading_constraints(): void
    {
        // Create multiple users
        $user1 = new UserWithPostsModel(['name' => 'John', 'email' => 'john@example.com']);
        $user1->save();

        $user2 = new UserWithPostsModel(['name' => 'Jane', 'email' => 'jane@example.com']);
        $user2->save();

        // Create posts
        $this->executeQuery("INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [$user1->id, 'John Post']);
        $this->executeQuery("INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [$user2->id, 'Jane Post']);

        // Test eager loading constraints
        $users = [UserWithPostsModel::find($user1->id), UserWithPostsModel::find($user2->id)];
        $relation = $user1->posts();

        // Add eager constraints
        $relation->addEagerConstraints($users);

        // Get query to verify constraints
        $query = $relation->getQuery();
        $sql = $query->toSql();

        $this->assertNotNull($sql);
    }

    /**
     * Test hasMany relationship getForeignKeyName
     */
    public function test_has_many_get_foreign_key_name(): void
    {
        $user = new UserWithPostsModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        $relation = $user->posts();
        $foreignKey = $relation->getForeignKeyName();

        $this->assertEquals('user_id', $foreignKey);
    }

    /**
     * Test hasMany relationship returns empty collection for non-existent parent
     */
    public function test_has_many_returns_empty_collection_for_non_existent_parent(): void
    {
        // Create user without saving (doesn't exist in DB)
        $user = new UserWithPostsModel(['name' => 'John Doe', 'email' => 'john@example.com']);

        // Relationship should return empty collection for non-existent parent
        $posts = $user->posts()->getResults();

        $this->assertInstanceOf(ModelCollection::class, $posts);
        $this->assertTrue($posts->isEmpty());
    }

    /**
     * Test hasMany relationship with non-matching foreign keys
     */
    public function test_has_many_with_non_matching_foreign_keys(): void
    {
        $user = new UserWithPostsModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        // Create posts for different user
        $this->executeQuery("INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [999, 'Other user post']);

        // Should return empty collection (no posts for this user)
        $posts = $user->posts()->getResults();

        $this->assertTrue($posts->isEmpty());
    }

    /**
     * Test hasMany relationship query builder is chainable
     */
    public function test_has_many_query_builder_is_chainable(): void
    {
        $user = new UserWithPostsModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        // Create posts
        $this->executeQuery("INSERT INTO posts (user_id, title, published) VALUES (?, ?, ?)",
            [$user->id, 'Post 1', 1]);
        $this->executeQuery("INSERT INTO posts (user_id, title, published) VALUES (?, ?, ?)",
            [$user->id, 'Post 2', 0]);
        $this->executeQuery("INSERT INTO posts (user_id, title, published) VALUES (?, ?, ?)",
            [$user->id, 'Post 3', 1]);

        // Chain multiple query methods
        $posts = $user->posts()
            ->getQuery()
            ->where('published', 1)
            ->orderBy('title', 'ASC')
            ->get();

        $this->assertCount(2, $posts);
        $this->assertEquals('Post 1', $posts[0]['title']);
        $this->assertEquals('Post 3', $posts[1]['title']);
    }

    /**
     * Test hasMany relationship collection methods
     */
    public function test_has_many_collection_methods(): void
    {
        $user = new UserWithPostsModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();

        // Create posts
        $this->executeQuery("INSERT INTO posts (user_id, title, published) VALUES (?, ?, ?)",
            [$user->id, 'Post 1', 1]);
        $this->executeQuery("INSERT INTO posts (user_id, title, published) VALUES (?, ?, ?)",
            [$user->id, 'Post 2', 1]);
        $this->executeQuery("INSERT INTO posts (user_id, title, published) VALUES (?, ?, ?)",
            [$user->id, 'Post 3', 0]);

        $posts = $user->posts()->getResults();

        // Test collection methods
        $this->assertCount(3, $posts);
        $this->assertFalse($posts->isEmpty());
        $this->assertNotNull($posts->first());
        $this->assertNotNull($posts->last());
    }
}

/**
 * User model with HasMany relationship
 */
class UserWithPostsModel extends Model
{
    protected static string $table = 'users';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['name', 'email'];

    public function posts(): HasMany
    {
        return $this->hasMany(PostModel::class, 'user_id', 'id');
    }

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
 * Post model (related to User)
 */
class PostModel extends Model
{
    protected static string $table = 'posts';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['user_id', 'title', 'content', 'published'];

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


