<?php

/**
 * ORM Advanced Features Examples
 *
 * Demonstrates advanced ORM features in Toporia Framework.
 * Run with: php examples/orm-advanced-features-example.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "=== Toporia Framework - ORM Advanced Features Examples ===\n\n";

// Note: These examples require database connection setup
// Uncomment and configure to test

/*
use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\Concerns\SoftDeletes;
use Toporia\Framework\Database\ORM\Concerns\HasQueryScopes;
use Toporia\Framework\Database\ORM\Concerns\HasBatchOperations;
use Toporia\Framework\Database\ORM\Concerns\HasChunking;
use Toporia\Framework\Database\ORM\Concerns\HasUuid;

// Example 1: Soft Deletes
echo "1. Soft Deletes:\n";
class UserModel extends Model
{
    use SoftDeletes;
    protected static string $table = 'users';
}

$user = UserModel::find(1);
$user->delete(); // Soft delete
echo "User soft-deleted: " . ($user->trashed() ? 'Yes' : 'No') . "\n";

$user->restore(); // Restore
echo "User restored: " . ($user->trashed() ? 'No' : 'Yes') . "\n";

// Query soft-deleted
$deletedUsers = UserModel::onlyTrashed()->get();
echo "Deleted users count: " . $deletedUsers->count() . "\n\n";

// Example 2: Query Scopes
echo "2. Query Scopes:\n";
class ProductModel extends Model
{
    use HasQueryScopes;
    protected static string $table = 'products';

    protected static function boot(): void
    {
        parent::boot();
        // Global scope
        static::addGlobalScope('active', function ($query) {
            $query->where('is_active', true);
        });
    }

    // Local scope
    protected function scopePublished($query)
    {
        return $query->where('published_at', '<=', date('Y-m-d H:i:s'));
    }
}

// Global scope automatically applied
$activeProducts = ProductModel::all(); // Only active products

// Local scope
$publishedProducts = ProductModel::published()->get();

// Remove global scope
$allProducts = ProductModel::withoutGlobalScope('active')->get();
echo "Scopes applied successfully\n\n";

// Example 3: Batch Operations
echo "3. Batch Operations:\n";
class OrderModel extends Model
{
    use HasBatchOperations;
    protected static string $table = 'orders';
}

// Insert batch
OrderModel::insertBatch([
    ['user_id' => 1, 'total' => 100],
    ['user_id' => 2, 'total' => 200],
]);

// Update batch
OrderModel::updateBatch([
    1 => ['total' => 150],
    2 => ['total' => 250],
]);

// Delete batch
OrderModel::deleteBatch([1, 2, 3]);

// Upsert batch
OrderModel::upsertBatch([
    ['order_number' => 'ORD-001', 'total' => 100],
    ['order_number' => 'ORD-002', 'total' => 200],
], ['order_number']);

echo "Batch operations completed\n\n";

// Example 4: Chunking
echo "4. Chunking:\n";
// Process large datasets in chunks
foreach (UserModel::chunk(100) as $chunk) {
    echo "Processing chunk of " . $chunk->count() . " users\n";
    foreach ($chunk as $user) {
        // Process user
    }
}

// Cursor-based chunking (more efficient)
foreach (UserModel::chunkById(100) as $chunk) {
    echo "Processing chunk by ID\n";
}

// Lazy evaluation (most memory efficient)
foreach (UserModel::lazy() as $user) {
    // Process one user at a time
}
echo "Chunking completed\n\n";

// Example 5: UUID Support
echo "5. UUID Support:\n";
class CategoryModel extends Model
{
    use HasUuid;
    protected static string $table = 'categories';
    protected static string $primaryKey = 'uuid';
}

$category = new CategoryModel(['name' => 'Electronics']);
$category->save(); // UUID automatically generated
echo "Category UUID: " . $category->getKey() . "\n\n";

// Example 6: Combined Features
echo "6. Combined Features:\n";
class ProductModel extends Model
{
    use SoftDeletes;
    use HasQueryScopes;
    use HasBatchOperations;
    use HasChunking;
    use HasUuid;

    protected static string $table = 'products';
    protected static string $primaryKey = 'uuid';

    protected static function boot(): void
    {
        parent::boot();
        static::addGlobalScope('active', function ($query) {
            $query->where('is_active', true);
        });
    }
}

// Use all features together
$products = ProductModel::published()
    ->withTrashed()
    ->get();

// Batch operations
ProductModel::insertBatch([...]);

// Chunking
foreach (ProductModel::chunk(100) as $chunk) {
    // Process chunk
}

echo "Combined features working\n\n";
*/

echo "=== Examples Complete ===\n";
echo "Note: Uncomment code and configure database connection to test\n";

