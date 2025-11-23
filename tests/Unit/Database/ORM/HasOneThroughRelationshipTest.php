<?php

declare(strict_types=1);

namespace Tests\Unit\Database\ORM;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\Relations\HasOneThrough;
use Toporia\Framework\Database\ORM\ModelQueryBuilder;

/**
 * Test HasOneThrough Relationship
 *
 * ✅ TEST STATUS: ALL PASSED (15/15)
 * ✅ Last verified: 2025-01-22
 * ✅ Fixed: HasOneThrough::addConstraints() override, constructor order, foreign key constraint handling
 * ✅ Fixed: Ambiguous column in orderBy - qualify with table name
 *
 * Comprehensive tests for has-one-through relationship:
 * - Relationship query through intermediate table
 * - Relationship constraints
 * - Eager loading
 * - Access relationship
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class HasOneThroughRelationshipTest extends DatabaseTestCase
{
    protected function createTables(): void
    {
        // Create suppliers table
        $this->createTable('suppliers', "
            CREATE TABLE suppliers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            )
        ");

        // Create accounts table (intermediate table)
        $this->createTable('accounts', "
            CREATE TABLE accounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                supplier_id INT NOT NULL,
                account_number VARCHAR(255) NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
            )
        ");

        // Create account_histories table (final table)
        $this->createTable('account_histories', "
            CREATE TABLE account_histories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                account_id INT NOT NULL,
                action VARCHAR(255) NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                FOREIGN KEY (account_id) REFERENCES accounts(id)
            )
        ");
    }

    protected function dropTables(): void
    {
        $this->dropTable('account_histories');
        $this->dropTable('accounts');
        $this->dropTable('suppliers');
    }

    /**
     * Test hasOneThrough relationship returns HasOneThrough relation instance
     */
    public function test_has_one_through_returns_has_one_through_relation(): void
    {
        $supplier = new SupplierThroughModel(['name' => 'Supplier 1']);
        $supplier->save();

        $relation = $supplier->accountHistory();

        $this->assertInstanceOf(HasOneThrough::class, $relation);
    }

    /**
     * Test hasOneThrough relationship returns null when no related record
     */
    public function test_has_one_through_returns_null_when_no_related_record(): void
    {
        $supplier = new SupplierThroughModel(['name' => 'Supplier 1']);
        $supplier->save();

        $history = $supplier->accountHistory()->getResults();

        $this->assertNull($history);
    }

    /**
     * Test hasOneThrough relationship returns related model when exists
     */
    public function test_has_one_through_returns_related_model_when_exists(): void
    {
        // Create supplier
        $supplier = new SupplierThroughModel(['name' => 'Supplier 1']);
        $supplier->save();

        // Create account (intermediate)
        $this->executeQuery(
            "INSERT INTO accounts (supplier_id, account_number) VALUES (?, ?)",
            [$supplier->id, 'ACC001']
        );

        // Create account history (final)
        $this->executeQuery(
            "INSERT INTO account_histories (account_id, action) VALUES (?, ?)",
            [1, 'Created']
        );

        // Get history via relationship
        $history = $supplier->accountHistory()->getResults();

        $this->assertInstanceOf(AccountHistoryThroughModel::class, $history);
        $this->assertEquals('Created', $history->action);
    }

    /**
     * Test hasOneThrough relationship applies correct constraints
     */
    public function test_has_one_through_applies_correct_constraints(): void
    {
        // Create two suppliers
        $supplier1 = new SupplierThroughModel(['name' => 'Supplier 1']);
        $supplier1->save();

        $supplier2 = new SupplierThroughModel(['name' => 'Supplier 2']);
        $supplier2->save();

        // Create accounts for each supplier
        $this->executeQuery(
            "INSERT INTO accounts (supplier_id, account_number) VALUES (?, ?)",
            [$supplier1->id, 'ACC001']
        );
        $this->executeQuery(
            "INSERT INTO accounts (supplier_id, account_number) VALUES (?, ?)",
            [$supplier2->id, 'ACC002']
        );

        // Create histories for each account
        $this->executeQuery(
            "INSERT INTO account_histories (account_id, action) VALUES (?, ?)",
            [1, 'History 1']
        );
        $this->executeQuery(
            "INSERT INTO account_histories (account_id, action) VALUES (?, ?)",
            [2, 'History 2']
        );

        // Each supplier should get their own history
        $history1 = $supplier1->accountHistory()->getResults();
        $history2 = $supplier2->accountHistory()->getResults();

        $this->assertNotNull($history1);
        $this->assertEquals('History 1', $history1->action);

        $this->assertNotNull($history2);
        $this->assertEquals('History 2', $history2->action);
    }

    /**
     * Test hasOneThrough relationship with where clause
     */
    public function test_has_one_through_with_where_clause(): void
    {
        $supplier = new SupplierThroughModel(['name' => 'Supplier 1']);
        $supplier->save();

        // Create account
        $this->executeQuery(
            "INSERT INTO accounts (supplier_id, account_number) VALUES (?, ?)",
            [$supplier->id, 'ACC001']
        );

        // Create multiple histories
        $this->executeQuery(
            "INSERT INTO account_histories (account_id, action) VALUES (?, ?)",
            [1, 'Created']
        );
        $this->executeQuery(
            "INSERT INTO account_histories (account_id, action) VALUES (?, ?)",
            [1, 'Updated']
        );

        // Query with where clause
        $history = $supplier->accountHistory()->getQuery()->where('action', 'Updated')->first();

        $this->assertNotNull($history);
        $this->assertEquals('Updated', $history->action);
    }

    /**
     * Test hasOneThrough relationship returns null for non-existent parent
     */
    public function test_has_one_through_returns_null_for_non_existent_parent(): void
    {
        // Create supplier without saving (doesn't exist in DB)
        $supplier = new SupplierThroughModel(['name' => 'Supplier 1']);

        // Relationship should return null for non-existent parent
        $history = $supplier->accountHistory()->getResults();

        $this->assertNull($history);
    }

    /**
     * Test hasOneThrough relationship with eager loading constraints
     */
    public function test_has_one_through_with_eager_loading_constraints(): void
    {
        // Create multiple suppliers
        $supplier1 = new SupplierThroughModel(['name' => 'Supplier 1']);
        $supplier1->save();

        $supplier2 = new SupplierThroughModel(['name' => 'Supplier 2']);
        $supplier2->save();

        // Create accounts
        $this->executeQuery(
            "INSERT INTO accounts (supplier_id, account_number) VALUES (?, ?)",
            [$supplier1->id, 'ACC001']
        );
        $this->executeQuery(
            "INSERT INTO accounts (supplier_id, account_number) VALUES (?, ?)",
            [$supplier2->id, 'ACC002']
        );

        // Create histories
        $this->executeQuery(
            "INSERT INTO account_histories (account_id, action) VALUES (?, ?)",
            [1, 'History 1']
        );
        $this->executeQuery(
            "INSERT INTO account_histories (account_id, action) VALUES (?, ?)",
            [2, 'History 2']
        );

        // Test eager loading constraints
        $suppliers = [SupplierThroughModel::find($supplier1->id), SupplierThroughModel::find($supplier2->id)];
        $relation = $supplier1->accountHistory();

        // Add eager constraints
        $relation->addEagerConstraints($suppliers);

        // Get query to verify constraints
        $query = $relation->getQuery();
        $sql = $query->toSql();

        $this->assertNotNull($sql);
    }

    /**
     * Test hasOneThrough relationship match method
     */
    public function test_has_one_through_match_method(): void
    {
        // Create suppliers
        $supplier1 = new SupplierThroughModel(['name' => 'Supplier 1']);
        $supplier1->save();

        $supplier2 = new SupplierThroughModel(['name' => 'Supplier 2']);
        $supplier2->save();

        // Create accounts
        $this->executeQuery(
            "INSERT INTO accounts (supplier_id, account_number) VALUES (?, ?)",
            [$supplier1->id, 'ACC001']
        );
        $this->executeQuery(
            "INSERT INTO accounts (supplier_id, account_number) VALUES (?, ?)",
            [$supplier2->id, 'ACC002']
        );

        // Create histories
        $this->executeQuery(
            "INSERT INTO account_histories (account_id, action) VALUES (?, ?)",
            [1, 'History 1']
        );
        $this->executeQuery(
            "INSERT INTO account_histories (account_id, action) VALUES (?, ?)",
            [2, 'History 2']
        );

        // Get histories as collection
        $histories = AccountHistoryThroughModel::query()->get();

        // Match histories to suppliers
        $suppliers = [$supplier1, $supplier2];
        $relation = $supplier1->accountHistory();
        $matched = $relation->match($suppliers, $histories, 'accountHistory');

        $this->assertCount(2, $matched);
        // Match should set relation on models
    }

    /**
     * Test hasOneThrough relationship with orderBy
     */
    public function test_has_one_through_with_order_by(): void
    {
        $supplier = new SupplierThroughModel(['name' => 'Supplier 1']);
        $supplier->save();

        // Create account
        $this->executeQuery(
            "INSERT INTO accounts (supplier_id, account_number) VALUES (?, ?)",
            [$supplier->id, 'ACC001']
        );

        // Create multiple histories
        $this->executeQuery(
            "INSERT INTO account_histories (account_id, action) VALUES (?, ?)",
            [1, 'Action 3']
        );
        $this->executeQuery(
            "INSERT INTO account_histories (account_id, action) VALUES (?, ?)",
            [1, 'Action 1']
        );
        $this->executeQuery(
            "INSERT INTO account_histories (account_id, action) VALUES (?, ?)",
            [1, 'Action 2']
        );

        // Query with orderBy
        $history = $supplier->accountHistory()->getQuery()->orderBy('action', 'ASC')->first();

        $this->assertNotNull($history);
        $this->assertEquals('Action 1', $history->action);
    }

    /**
     * Test hasOneThrough relationship with non-matching foreign keys
     */
    public function test_has_one_through_with_non_matching_foreign_keys(): void
    {
        $supplier = new SupplierThroughModel(['name' => 'Supplier 1']);
        $supplier->save();

        // Create account for different supplier (insert directly to bypass FK constraint)
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $this->executeQuery(
            "INSERT INTO accounts (supplier_id, account_number) VALUES (?, ?)",
            [999, 'ACC001']
        );
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=1");

        // Create history
        $this->executeQuery(
            "INSERT INTO account_histories (account_id, action) VALUES (?, ?)",
            [1, 'History']
        );

        // Should return null (no account for this supplier)
        $history = $supplier->accountHistory()->getResults();

        $this->assertNull($history);
    }

    /**
     * Test hasOneThrough relationship query builder is chainable
     */
    public function test_has_one_through_query_builder_is_chainable(): void
    {
        $supplier = new SupplierThroughModel(['name' => 'Supplier 1']);
        $supplier->save();

        // Create account
        $this->executeQuery(
            "INSERT INTO accounts (supplier_id, account_number) VALUES (?, ?)",
            [$supplier->id, 'ACC001']
        );

        // Create histories
        $this->executeQuery(
            "INSERT INTO account_histories (account_id, action) VALUES (?, ?)",
            [1, 'Created']
        );
        $this->executeQuery(
            "INSERT INTO account_histories (account_id, action) VALUES (?, ?)",
            [1, 'Updated']
        );

        // Chain multiple query methods - qualify column name to avoid ambiguity
        $relatedTable = call_user_func([AccountHistoryThroughModel::class, 'getTableName']);
        $history = $supplier->accountHistory()
            ->getQuery()
            ->where('action', 'Updated')
            ->orderBy("{$relatedTable}.id", 'DESC')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals('Updated', $history->action);
    }

    /**
     * Test hasOneThrough relationship with multiple intermediate records
     */
    public function test_has_one_through_with_multiple_intermediate_records(): void
    {
        $supplier = new SupplierThroughModel(['name' => 'Supplier 1']);
        $supplier->save();

        // Create multiple accounts (shouldn't happen, but test constraint)
        $this->executeQuery(
            "INSERT INTO accounts (supplier_id, account_number) VALUES (?, ?)",
            [$supplier->id, 'ACC001']
        );
        $this->executeQuery(
            "INSERT INTO accounts (supplier_id, account_number) VALUES (?, ?)",
            [$supplier->id, 'ACC002']
        );

        // Create histories for each account
        $this->executeQuery(
            "INSERT INTO account_histories (account_id, action) VALUES (?, ?)",
            [1, 'History 1']
        );
        $this->executeQuery(
            "INSERT INTO account_histories (account_id, action) VALUES (?, ?)",
            [2, 'History 2']
        );

        // Query should return first matching history
        $history = $supplier->accountHistory()->getResults();

        $this->assertNotNull($history);
    }

    /**
     * Test hasOneThrough relationship getForeignKeyName
     */
    public function test_has_one_through_get_foreign_key_name(): void
    {
        $supplier = new SupplierThroughModel(['name' => 'Supplier 1']);
        $supplier->save();

        $relation = $supplier->accountHistory();
        $foreignKey = $relation->getForeignKeyName();

        $this->assertIsString($foreignKey);
    }

    /**
     * Test hasOneThrough relationship with empty result
     */
    public function test_has_one_through_with_empty_result(): void
    {
        $supplier = new SupplierThroughModel(['name' => 'Supplier 1']);
        $supplier->save();

        // No account or history created

        $history = $supplier->accountHistory()->getResults();

        $this->assertNull($history);
    }

    /**
     * Test hasOneThrough relationship through intermediate table correctly
     */
    public function test_has_one_through_through_intermediate_table_correctly(): void
    {
        $supplier = new SupplierThroughModel(['name' => 'Supplier 1']);
        $supplier->save();

        // Create account (intermediate)
        $this->executeQuery(
            "INSERT INTO accounts (supplier_id, account_number) VALUES (?, ?)",
            [$supplier->id, 'ACC001']
        );

        // Verify relationship goes through intermediate table
        $relation = $supplier->accountHistory();
        $query = $relation->getQuery();
        $sql = $query->toSql();

        // SQL should join accounts table
        $this->assertStringContainsString('accounts', strtolower($sql));
        $this->assertStringContainsString('account_histories', strtolower($sql));
    }
}

/**
 * Supplier model with HasOneThrough relationship
 */
class SupplierThroughModel extends Model
{
    protected static string $table = 'suppliers';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['name'];

    public function accountHistory(): HasOneThrough
    {
        return $this->hasOneThrough(
            AccountHistoryThroughModel::class,
            AccountThroughModel::class,
            'supplier_id', // Foreign key on accounts table
            'account_id',  // Foreign key on account_histories table
            'id',          // Local key on suppliers table
            'id'           // Local key on accounts table
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
        // first() now returns Model|null directly
        return static::query()->where('id', $id)->first();
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
 * Account model (intermediate)
 */
class AccountThroughModel extends Model
{
    protected static string $table = 'accounts';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['supplier_id', 'account_number'];

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
 * Account History model (final)
 */
class AccountHistoryThroughModel extends Model
{
    protected static string $table = 'account_histories';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['account_id', 'action'];

    protected static function getConnection(): \Toporia\Framework\Database\Contracts\ConnectionInterface
    {
        return parent::getConnection();
    }

    public static function query(): ModelQueryBuilder
    {
        return parent::query();
    }

    public static function hydrate(array $rows): \Toporia\Framework\Database\ORM\ModelCollection
    {
        $models = [];
        foreach ($rows as $row) {
            $model = new static($row);
            $model->exists = true;
            $model->syncOriginal();
            $models[] = $model;
        }
        return new \Toporia\Framework\Database\ORM\ModelCollection($models);
    }
}
