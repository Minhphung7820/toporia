# Factory & Seeder System Guide

Hướng dẫn sử dụng Factory và Seeder system trong Toporia Framework.

## 📋 Tổng Quan

Factory/Seeder system được thiết kế với:
- ✅ **Clean Architecture** - Tách biệt layers rõ ràng
- ✅ **SOLID Principles** - Dễ maintain và extend
- ✅ **Performance Optimized** - Lazy loading, batch processing, memory efficient
- ✅ **High Reusability** - Traits, interfaces, abstract classes
- ✅ **Quy mô bài bản** - Ngang hoặc hơn Laravel

---

## 🏭 Factory System

### Features

- **Faker Integration** - Generate realistic fake data
- **Lazy Loading** - Attributes generated only when needed
- **Batch Processing** - Efficient bulk operations
- **State Management** - Different model variations
- **Relationship Support** - Create related models
- **Sequence Support** - Sequential attribute variations
- **Memory Efficient** - Generator-based for large datasets

### Basic Usage

```php
use Database\Factories\UserFactory;

// Create a single user
$user = UserFactory::new()->create();

// Create multiple users
$users = UserFactory::new()->createMany(10);

// Create with specific attributes
$admin = UserFactory::new()->create([
    'role' => 'admin',
    'email' => 'admin@example.com'
]);

// Create without persisting (make)
$user = UserFactory::new()->make();
```

### State Management

```php
// Define state in factory
public function stateAdmin(): array
{
    return [
        'role' => 'admin',
        'is_verified' => true,
    ];
}

// Use state
$admin = UserFactory::new()->state('admin')->create();

// Multiple states
$user = UserFactory::new()
    ->state('admin')
    ->state('verified')
    ->create();

// Dynamic states
$user = UserFactory::new()->state(fn() => [
    'created_at' => now()->subDays(rand(1, 30))
])->create();
```

### Relationships

```php
// Create with related models
$user = UserFactory::new()
    ->has(PostFactory::new()->count(3))
    ->create();

// Belongs to relationship
$post = PostFactory::new()
    ->belongsTo(UserFactory::new(), 'user_id')
    ->create();

// Many to many
$user = UserFactory::new()
    ->hasAttached(RoleFactory::new(), 3, 'roles')
    ->create();
```

### Sequences

```php
// Define sequence
UserFactory::new()
    ->sequence(
        ['role' => 'admin'],
        ['role' => 'user'],
        ['role' => 'moderator']
    )
    ->createMany(6);
// Creates: admin, user, moderator, admin, user, moderator
```

### Batch Operations

```php
// Large datasets (memory efficient)
$users = UserFactory::new()
    ->batchSize(500)  // Insert in batches of 500
    ->createMany(10000);

// With progress tracking
UserFactory::new()
    ->setProgressCallback(function ($current, $total) {
        echo "Progress: {$current}/{$total}\n";
    })
    ->createMany(1000);
```

### Custom Faker Providers

```php
use Toporia\Framework\Database\Faker\VietnameseProvider;

class UserFactory extends Factory
{
    protected array $fakerProviders = [
        VietnameseProvider::class,
    ];

    public function definition(): array
    {
        return [
            'name' => $this->faker()->vietnameseName(),
            'phone' => $this->faker()->vietnamesePhoneNumber(),
            'address' => $this->faker()->vietnameseAddress(),
        ];
    }
}
```

---

## 🌱 Seeder System

### Features

- **Transaction Support** - Atomic seeding operations
- **Dependency Management** - Automatic dependency resolution
- **Batch Processing** - Efficient bulk operations
- **Progress Tracking** - Monitor seeding progress
- **Memory Efficient** - Handle large datasets

### Basic Usage

```php
use Toporia\Framework\Database\Seeder;
use Toporia\Framework\Database\Contracts\FactoryInterface;
use Database\Factories\UserFactory;

class UserSeeder extends Seeder
{
    public function dependencies(): array
    {
        return [
            RoleSeeder::class,  // Run RoleSeeder first
        ];
    }

    protected function seed(): void
    {
        // Using factory
        $this->factory(UserFactory::new(), 10);

        // Or with specific attributes
        $this->factory(UserFactory::new(), 5, [
            'role' => 'admin'
        ]);

        // Using factory with progress
        $this->factoryWithProgress(UserFactory::new(), 1000);
    }

    public function useTransaction(): bool
    {
        return true;  // Wrap in transaction
    }
}
```

### Dependency Management

```php
class PostSeeder extends Seeder
{
    public function dependencies(): array
    {
        return [
            UserSeeder::class,      // Users must exist first
            CategorySeeder::class,  // Categories must exist first
        ];
    }

    protected function seed(): void
    {
        // Seed posts after users and categories are seeded
        $this->factory(PostFactory::new(), 50);
    }
}
```

### Batch Operations

```php
class ProductSeeder extends Seeder
{
    protected int $batchSize = 500;

    protected function seed(): void
    {
        // Insert in batches of 500
        $this->factory(ProductFactory::new(), 10000);
    }
}
```

### Raw Inserts (Performance)

```php
class OrderSeeder extends Seeder
{
    protected function seed(): void
    {
        // Direct database insert (faster for large datasets)
        $data = [];
        for ($i = 0; $i < 10000; $i++) {
            $data[] = [
                'user_id' => rand(1, 100),
                'total' => rand(1000, 100000),
                'status' => ['pending', 'completed', 'cancelled'][rand(0, 2)],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->insert('orders', $data);
    }
}
```

### Multiple Seeders

```php
class DatabaseSeeder extends Seeder
{
    public function dependencies(): array
    {
        return [
            RoleSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            PostSeeder::class,
        ];
    }

    protected function seed(): void
    {
        // Or use call() method
        $this->callMany([
            RoleSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            PostSeeder::class,
        ]);
    }
}
```

---

## 🚀 Performance Optimizations

### Factory Optimizations

1. **Lazy Evaluation** - Attributes generated only when needed
2. **Batch Inserts** - `createMany()` uses batch insert for performance
3. **Memory Management** - Generator-based `makeMany()` for large datasets
4. **Faker Caching** - Faker instance cached per factory

### Seeder Optimizations

1. **Batch Processing** - Insert in batches (configurable batch size)
2. **Transaction Batching** - Process in smaller transactions
3. **Raw Inserts** - Direct database inserts for large datasets
4. **Memory Efficient** - Handle large datasets without memory issues

### Best Practices

```php
// ✅ GOOD: Batch operations
Factory::new()->createMany(1000);

// ❌ BAD: Individual creates in loop
for ($i = 0; $i < 1000; $i++) {
    Factory::new()->create();
}

// ✅ GOOD: Raw inserts for very large datasets
$this->insert('table', $largeDataset);

// ✅ GOOD: Progress tracking for long operations
$this->factoryWithProgress(Factory::new(), 10000);
```

---

## 📝 Examples

Xem thêm examples trong:
- `database/factories/` - Factory examples
- `database/seeders/` - Seeder examples
- `tests/Integration/Database/` - Integration tests

---

## 🎯 SOLID Principles

### Single Responsibility
- Factory: Only creates model instances
- Seeder: Only seeds specific data
- Trait: Only provides specific functionality

### Open/Closed
- Extend via child classes without modifying base
- Add new providers without changing core

### Liskov Substitution
- All factories can be used interchangeably
- All seeders follow same interface

### Interface Segregation
- Specific interfaces for specific operations
- Small, focused interfaces

### Dependency Inversion
- Depend on interfaces, not concrete classes
- Factory depends on Model interface
- Seeder depends on FactoryInterface

---

## 🏗️ Clean Architecture

- **Contracts/Interfaces** - Define contracts (Domain/Application layers)
- **Factory/Seeder Classes** - Implementation (Infrastructure layer)
- **Traits** - Reusable functionality (Infrastructure layer)
- **Providers** - Extendable functionality (Infrastructure layer)

---

**Cập nhật lần cuối**: 2024-11-22

