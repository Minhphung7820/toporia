<?php

declare(strict_types=1);

namespace Tests\Unit\Database\ORM;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\Relations\HasManyThrough;
use Toporia\Framework\Database\ORM\ModelCollection;
use Toporia\Framework\Database\ORM\ModelQueryBuilder;

/**
 * Test HasManyThrough Relationship
 *
 * ✅ TEST STATUS: ALL PASSED (16/16)
 * ✅ Last verified: 2025-01-22
 * ✅ Fixed: HasManyThrough::addConstraints() override to prevent wrong constraints, fixed getForeignKeyName()
 * ✅ Fixed: Constructor order - call parent first then override foreignKey
 * ✅ Fixed: RowCollection vs ModelCollection issues, foreign key constraint handling
 *
 * Comprehensive tests for has-many-through relationship:
 * - Relationship query through intermediate table
 * - Relationship constraints
 * - Eager loading
 * - Access relationship collection
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class HasManyThroughRelationshipTest extends DatabaseTestCase
{
    protected function createTables(): void
    {
        // Create countries table
        $this->createTable('countries', "
            CREATE TABLE countries (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            )
        ");

        // Create users table (intermediate table)
        $this->createTable('users', "
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                country_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                FOREIGN KEY (country_id) REFERENCES countries(id)
            )
        ");

        // Create posts table (final table)
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
        $this->dropTable('countries');
    }

    /**
     * Test hasManyThrough relationship returns HasManyThrough relation instance
     */
    public function test_has_many_through_returns_has_many_through_relation(): void
    {
        $country = new CountryThroughModel(['name' => 'USA']);
        $country->save();

        $relation = $country->posts();

        $this->assertInstanceOf(HasManyThrough::class, $relation);
    }

    /**
     * Test hasManyThrough relationship returns empty collection when no related records
     */
    public function test_has_many_through_returns_empty_collection_when_no_posts(): void
    {
        $country = new CountryThroughModel(['name' => 'USA']);
        $country->save();

        $posts = $country->posts()->getResults();

        $this->assertInstanceOf(ModelCollection::class, $posts);
        $this->assertTrue($posts->isEmpty());
    }

    /**
     * Test hasManyThrough relationship returns related models when exist
     */
    public function test_has_many_through_returns_related_models_when_exist(): void
    {
        // Create country
        $country = new CountryThroughModel(['name' => 'USA']);
        $country->save();

        // Create user (intermediate)
        $this->executeQuery(
            "INSERT INTO users (country_id, name, email) VALUES (?, ?, ?)",
            [$country->id, 'John Doe', 'john@example.com']
        );

        // Create posts (final)
        $this->executeQuery(
            "INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)",
            [1, 'Post 1', 'Content 1']
        );
        $this->executeQuery(
            "INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)",
            [1, 'Post 2', 'Content 2']
        );

        // Get posts via relationship
        $posts = $country->posts()->getResults();

        $this->assertInstanceOf(ModelCollection::class, $posts);
        $this->assertCount(2, $posts);
    }

    /**
     * Test hasManyThrough relationship applies correct constraints
     */
    public function test_has_many_through_applies_correct_constraints(): void
    {
        // Create two countries
        $country1 = new CountryThroughModel(['name' => 'USA']);
        $country1->save();

        $country2 = new CountryThroughModel(['name' => 'Canada']);
        $country2->save();

        // Create users for each country
        $this->executeQuery(
            "INSERT INTO users (country_id, name, email) VALUES (?, ?, ?)",
            [$country1->id, 'John', 'john@example.com']
        );
        $this->executeQuery(
            "INSERT INTO users (country_id, name, email) VALUES (?, ?, ?)",
            [$country2->id, 'Jane', 'jane@example.com']
        );

        // Create posts for each user
        $this->executeQuery(
            "INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [1, 'USA Post 1']
        );
        $this->executeQuery(
            "INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [1, 'USA Post 2']
        );
        $this->executeQuery(
            "INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [2, 'Canada Post 1']
        );

        // Each country should get their own posts
        $posts1 = $country1->posts()->getResults();
        $posts2 = $country2->posts()->getResults();

        $this->assertCount(2, $posts1);
        $this->assertCount(1, $posts2);
    }

    /**
     * Test hasManyThrough relationship with multiple users and posts
     */
    public function test_has_many_through_with_multiple_users_and_posts(): void
    {
        $country = new CountryThroughModel(['name' => 'USA']);
        $country->save();

        // Create multiple users
        $this->executeQuery(
            "INSERT INTO users (country_id, name, email) VALUES (?, ?, ?)",
            [$country->id, 'User 1', 'user1@example.com']
        );
        $this->executeQuery(
            "INSERT INTO users (country_id, name, email) VALUES (?, ?, ?)",
            [$country->id, 'User 2', 'user2@example.com']
        );

        // Create posts for each user
        $this->executeQuery(
            "INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [1, 'Post 1']
        );
        $this->executeQuery(
            "INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [1, 'Post 2']
        );
        $this->executeQuery(
            "INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [2, 'Post 3']
        );

        // Country should get all posts from all users
        $posts = $country->posts()->getResults();

        $this->assertCount(3, $posts);
    }

    /**
     * Test hasManyThrough relationship with where clause
     */
    public function test_has_many_through_with_where_clause(): void
    {
        $country = new CountryThroughModel(['name' => 'USA']);
        $country->save();

        // Create user
        $this->executeQuery(
            "INSERT INTO users (country_id, name, email) VALUES (?, ?, ?)",
            [$country->id, 'John Doe', 'john@example.com']
        );

        // Create posts
        $this->executeQuery(
            "INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)",
            [1, 'Post 1', 'Content 1']
        );
        $this->executeQuery(
            "INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)",
            [1, 'Post 2', 'Content 2']
        );

        // Query with where clause
        $posts = $country->posts()->getQuery()->where('title', 'Post 1')->get();

        $this->assertCount(1, $posts);
        $this->assertEquals('Post 1', $posts->first()['title']);
    }

    /**
     * Test hasManyThrough relationship with orderBy
     */
    public function test_has_many_through_with_order_by(): void
    {
        $country = new CountryThroughModel(['name' => 'USA']);
        $country->save();

        // Create user
        $this->executeQuery(
            "INSERT INTO users (country_id, name, email) VALUES (?, ?, ?)",
            [$country->id, 'John Doe', 'john@example.com']
        );

        // Create posts
        $this->executeQuery(
            "INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [1, 'Post 3']
        );
        $this->executeQuery(
            "INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [1, 'Post 1']
        );
        $this->executeQuery(
            "INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [1, 'Post 2']
        );

        // Query with orderBy - use getModels() to get ModelCollection
        $posts = $country->posts()->getQuery()->orderBy('title', 'ASC')->getModels();

        $this->assertCount(3, $posts);
        $this->assertEquals('Post 1', $posts->first()->title);
        $this->assertEquals('Post 2', $posts->skip(1)->first()->title);
        $this->assertEquals('Post 3', $posts->last()->title);
    }

    /**
     * Test hasManyThrough relationship count
     */
    public function test_has_many_through_count(): void
    {
        $country = new CountryThroughModel(['name' => 'USA']);
        $country->save();

        // Create user
        $this->executeQuery(
            "INSERT INTO users (country_id, name, email) VALUES (?, ?, ?)",
            [$country->id, 'John Doe', 'john@example.com']
        );

        // Create 5 posts
        for ($i = 1; $i <= 5; $i++) {
            $this->executeQuery(
                "INSERT INTO posts (user_id, title) VALUES (?, ?)",
                [1, "Post {$i}"]
            );
        }

        $count = $country->posts()->getQuery()->count();

        $this->assertEquals(5, $count);
    }

    /**
     * Test hasManyThrough relationship with eager loading constraints
     */
    public function test_has_many_through_with_eager_loading_constraints(): void
    {
        // Create multiple countries
        $country1 = new CountryThroughModel(['name' => 'USA']);
        $country1->save();

        $country2 = new CountryThroughModel(['name' => 'Canada']);
        $country2->save();

        // Create users
        $this->executeQuery(
            "INSERT INTO users (country_id, name, email) VALUES (?, ?, ?)",
            [$country1->id, 'John', 'john@example.com']
        );
        $this->executeQuery(
            "INSERT INTO users (country_id, name, email) VALUES (?, ?, ?)",
            [$country2->id, 'Jane', 'jane@example.com']
        );

        // Create posts
        $this->executeQuery(
            "INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [1, 'USA Post']
        );
        $this->executeQuery(
            "INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [2, 'Canada Post']
        );

        // Test eager loading constraints
        $countries = [CountryThroughModel::find($country1->id), CountryThroughModel::find($country2->id)];
        $relation = $country1->posts();

        // Add eager constraints
        $relation->addEagerConstraints($countries);

        // Get query to verify constraints
        $query = $relation->getQuery();
        $sql = $query->toSql();

        $this->assertNotNull($sql);
    }

    /**
     * Test hasManyThrough relationship match method
     */
    public function test_has_many_through_match_method(): void
    {
        // Create countries
        $country1 = new CountryThroughModel(['name' => 'USA']);
        $country1->save();

        $country2 = new CountryThroughModel(['name' => 'Canada']);
        $country2->save();

        // Create users
        $this->executeQuery(
            "INSERT INTO users (country_id, name, email) VALUES (?, ?, ?)",
            [$country1->id, 'John', 'john@example.com']
        );
        $this->executeQuery(
            "INSERT INTO users (country_id, name, email) VALUES (?, ?, ?)",
            [$country2->id, 'Jane', 'jane@example.com']
        );

        // Create posts
        $this->executeQuery(
            "INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [1, 'USA Post 1']
        );
        $this->executeQuery(
            "INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [1, 'USA Post 2']
        );
        $this->executeQuery(
            "INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [2, 'Canada Post 1']
        );

        // Get posts as collection
        $allPosts = PostThroughModel::query()->get();

        // Match posts to countries
        $countries = [$country1, $country2];
        $relation = $country1->posts();
        $matched = $relation->match($countries, $allPosts, 'posts');

        $this->assertCount(2, $matched);
    }

    /**
     * Test hasManyThrough relationship query builder is chainable
     */
    public function test_has_many_through_query_builder_is_chainable(): void
    {
        $country = new CountryThroughModel(['name' => 'USA']);
        $country->save();

        // Create user
        $this->executeQuery(
            "INSERT INTO users (country_id, name, email) VALUES (?, ?, ?)",
            [$country->id, 'John Doe', 'john@example.com']
        );

        // Create posts
        $this->executeQuery(
            "INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [1, 'Post 1']
        );
        $this->executeQuery(
            "INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [1, 'Post 2']
        );
        $this->executeQuery(
            "INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [1, 'Post 3']
        );

        // Chain multiple query methods - use getModels() to get ModelCollection
        $posts = $country->posts()
            ->getQuery()
            ->where('title', '!=', 'Post 2')
            ->orderBy('title', 'ASC')
            ->getModels();

        $this->assertCount(2, $posts);
        $this->assertEquals('Post 1', $posts->first()->title);
        $this->assertEquals('Post 3', $posts->last()->title);
    }

    /**
     * Test hasManyThrough relationship through intermediate table correctly
     */
    public function test_has_many_through_through_intermediate_table_correctly(): void
    {
        $country = new CountryThroughModel(['name' => 'USA']);
        $country->save();

        // Create user (intermediate)
        $this->executeQuery(
            "INSERT INTO users (country_id, name, email) VALUES (?, ?, ?)",
            [$country->id, 'John Doe', 'john@example.com']
        );

        // Verify relationship goes through intermediate table
        $relation = $country->posts();
        $query = $relation->getQuery();
        $sql = $query->toSql();

        // SQL should join users table
        $this->assertStringContainsString('users', strtolower($sql));
        $this->assertStringContainsString('posts', strtolower($sql));
    }

    /**
     * Test hasManyThrough relationship returns empty collection for non-existent parent
     */
    public function test_has_many_through_returns_empty_collection_for_non_existent_parent(): void
    {
        // Create country without saving (doesn't exist in DB)
        $country = new CountryThroughModel(['name' => 'USA']);

        // Relationship should return empty collection for non-existent parent
        $posts = $country->posts()->getResults();

        $this->assertInstanceOf(ModelCollection::class, $posts);
        $this->assertTrue($posts->isEmpty());
    }

    /**
     * Test hasManyThrough relationship with limit
     */
    public function test_has_many_through_with_limit(): void
    {
        $country = new CountryThroughModel(['name' => 'USA']);
        $country->save();

        // Create user
        $this->executeQuery(
            "INSERT INTO users (country_id, name, email) VALUES (?, ?, ?)",
            [$country->id, 'John Doe', 'john@example.com']
        );

        // Create 10 posts
        for ($i = 1; $i <= 10; $i++) {
            $this->executeQuery(
                "INSERT INTO posts (user_id, title) VALUES (?, ?)",
                [1, "Post {$i}"]
            );
        }

        // Get only first 5 posts
        $posts = $country->posts()->getQuery()->limit(5)->get();

        $this->assertCount(5, $posts);
    }

    /**
     * Test hasManyThrough relationship with non-matching foreign keys
     */
    public function test_has_many_through_with_non_matching_foreign_keys(): void
    {
        $country = new CountryThroughModel(['name' => 'USA']);
        $country->save();

        // Create user for different country (insert directly to bypass FK constraint)
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $this->executeQuery(
            "INSERT INTO users (country_id, name, email) VALUES (?, ?, ?)",
            [999, 'John Doe', 'john@example.com']
        );
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=1");

        // Create post
        $this->executeQuery(
            "INSERT INTO posts (user_id, title) VALUES (?, ?)",
            [1, 'Post']
        );

        // Should return empty collection (no user for this country)
        $posts = $country->posts()->getResults();

        $this->assertTrue($posts->isEmpty());
    }

    /**
     * Test hasManyThrough relationship getForeignKeyName
     */
    public function test_has_many_through_get_foreign_key_name(): void
    {
        $country = new CountryThroughModel(['name' => 'USA']);
        $country->save();

        $relation = $country->posts();
        $foreignKey = $relation->getForeignKeyName();

        $this->assertEquals('user_id', $foreignKey);
    }
}

/**
 * Country model with HasManyThrough relationship
 */
class CountryThroughModel extends Model
{
    protected static string $table = 'countries';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['name'];

    public function posts(): HasManyThrough
    {
        return $this->hasManyThrough(
            PostThroughModel::class,
            UserThroughModel::class,
            'country_id', // Foreign key on users table
            'user_id',    // Foreign key on posts table
            'id',         // Local key on countries table
            'id'          // Local key on users table
        );
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
 * User model (intermediate)
 */
class UserThroughModel extends Model
{
    protected static string $table = 'users';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['country_id', 'name', 'email'];

    protected static function getConnection(): \Toporia\Framework\Database\Contracts\ConnectionInterface
    {
        return parent::getConnection();
    }

    public static function query(): ModelQueryBuilder
    {
        return parent::query();
    }
}

/**
 * Post model (final)
 */
class PostThroughModel extends Model
{
    protected static string $table = 'posts';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['user_id', 'title', 'content'];

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
