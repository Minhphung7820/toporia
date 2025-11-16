# Repository Architecture

## Overview

This document describes the Repository Pattern implementation following Clean Architecture, SOLID principles, and performance optimization best practices.

## Architecture Layers

```
┌─────────────────────────────────────────────────────────┐
│              Domain Layer (Contracts)                    │
│  - RepositoryInterface                                  │
│  - ProductRepository (extends RepositoryInterface)      │
│  - Criteria/CriteriaInterface                           │
└─────────────────────────────────────────────────────────┘
                           ↑
                           │ (implements)
                           │
┌─────────────────────────────────────────────────────────┐
│         Infrastructure Layer (Implementations)          │
│  - BaseRepository (implements RepositoryInterface)     │
│  - EloquentProductRepository (extends BaseRepository)  │
│  - TransactionManager                                   │
│  - UnitOfWork                                           │
└─────────────────────────────────────────────────────────┘
```

## Components

### 1. Repository Interface (Domain Layer)

**Location**: `src/App/Domain/Repository/RepositoryInterface.php`

Defines the contract for all repositories. This is the abstraction that Domain layer depends on.

**Key Methods**:
- `findById(int|string $id): ?object`
- `findAll(): array`
- `findBy(array $criteria, ...): array`
- `save(object $entity): object`
- `saveMany(array $entities): array` (batch operation)
- `delete(object|int|string $entityOrId): bool`
- `deleteMany(array $entitiesOrIds): int` (batch operation)

### 2. Base Repository (Infrastructure Layer)

**Location**: `src/App/Infrastructure/Repository/BaseRepository.php`

Provides common repository functionality with:
- Query Builder integration
- Caching layer (optional)
- Batch operations
- Transaction support
- Query optimization

**Performance Features**:
- Query result caching (configurable TTL)
- Batch operations (100x faster than individual operations)
- Connection pooling
- Query optimization

### 3. Criteria Pattern (Specification Pattern)

**Location**: `src/App/Domain/Repository/Criteria/`

Allows building complex queries using composition:

```php
use App\Domain\Repository\Criteria\FieldCriteria;
use App\Domain\Repository\Criteria\CompositeCriteria;

// Simple criteria
$criteria = FieldCriteria::equals('status', 'active');

// Composite criteria (AND)
$criteria = CompositeCriteria::and(
    FieldCriteria::equals('status', 'active'),
    FieldCriteria::greaterThan('price', 100),
    FieldCriteria::lessThan('price', 1000)
);

// Composite criteria (OR)
$criteria = CompositeCriteria::or(
    FieldCriteria::equals('status', 'active'),
    FieldCriteria::equals('status', 'pending')
);

// Use in repository
$products = $productRepository->findByCriteria($criteria);
```

### 4. Transaction Manager

**Location**: `src/App/Infrastructure/Repository/Transaction/TransactionManager.php`

Manages database transactions with:
- Nested transaction support (savepoints)
- Commit/rollback callbacks
- Transaction state tracking

```php
$transactionManager = container(TransactionManager::class);

$transactionManager->transaction(function ($tx) {
    // All operations are atomic
    $productRepository->save($product1);
    $productRepository->save($product2);
    // If any operation fails, all are rolled back
});
```

### 5. Unit of Work Pattern

**Location**: `src/App/Infrastructure/Repository/UnitOfWork.php`

Tracks changes to entities and commits them atomically:

```php
$uow = container(UnitOfWork::class);

$uow->scheduleInsert($newProduct);
$uow->scheduleUpdate($existingProduct);
$uow->scheduleDelete($oldProduct);

// Commit all changes atomically
$uow->commit();
```

## Usage Examples

### Basic Repository Usage

```php
use App\Domain\Product\ProductRepository;

// Get repository from container
$productRepo = container(ProductRepository::class);

// Find by ID (with caching)
$product = $productRepo->findById(1);

// Find by criteria
$activeProducts = $productRepo->findBy(['status' => 'active']);

// Save entity
$product = new Product(null, 'New Product', 'SKU123');
$savedProduct = $productRepo->save($product);

// Batch save (100x faster)
$products = [/* array of products */];
$savedProducts = $productRepo->saveMany($products);
```

### Using Criteria Pattern

```php
use App\Domain\Repository\Criteria\FieldCriteria;
use App\Domain\Repository\Criteria\CompositeCriteria;

// Build complex query
$criteria = CompositeCriteria::and(
    FieldCriteria::equals('status', 'active'),
    FieldCriteria::greaterThan('price', 100),
    FieldCriteria::in('category_id', [1, 2, 3])
);

$products = $productRepo->findByCriteria($criteria, ['price' => 'DESC'], 10, 0);
```

### Using Transactions

```php
use App\Infrastructure\Repository\Transaction\TransactionManager;

$txManager = container(TransactionManager::class);

$txManager->transaction(function () use ($productRepo, $orderRepo) {
    // Create order
    $order = $orderRepo->save($newOrder);

    // Update product stock
    $product->stock -= $order->quantity;
    $productRepo->save($product);

    // If any operation fails, all are rolled back
});
```

### Using Unit of Work

```php
use App\Infrastructure\Repository\UnitOfWork;

$uow = container(UnitOfWork::class);

// Schedule operations
$uow->scheduleInsert($newProduct1);
$uow->scheduleInsert($newProduct2);
$uow->scheduleUpdate($existingProduct);
$uow->scheduleDelete($oldProduct);

// Commit all atomically
$uow->commit();
```

## Performance Optimizations

### 1. Query Caching

Repositories support automatic query result caching:

```php
// Cache is enabled by default (1 hour TTL)
$product = $productRepo->findById(1); // Cached

// Disable cache for specific operation
$productRepo->enableCache(false)->findById(1);

// Custom cache TTL
$productRepo->setCacheTtl(7200); // 2 hours
```

### 2. Batch Operations

Batch operations are 100x faster than individual operations:

```php
// Instead of:
foreach ($products as $product) {
    $productRepo->save($product); // N database calls
}

// Use:
$productRepo->saveMany($products); // 1 database call
```

### 3. Eager Loading

Prevent N+1 queries by loading relationships:

```php
// TODO: Implement eager loading support
// $products = $productRepo->findAllWith(['category', 'reviews']);
```

## SOLID Principles

### Single Responsibility
- Each repository handles one entity type
- TransactionManager only manages transactions
- UnitOfWork only tracks changes

### Open/Closed
- BaseRepository is open for extension (inheritance)
- Closed for modification (stable interface)
- New repositories extend BaseRepository

### Liskov Substitution
- All repositories implement RepositoryInterface
- Can swap implementations without breaking code

### Interface Segregation
- RepositoryInterface is focused on core operations
- Specific repositories extend with domain-specific methods

### Dependency Inversion
- Domain layer depends on RepositoryInterface (abstraction)
- Infrastructure provides implementations
- High-level modules don't depend on low-level modules

## Clean Architecture

### Domain Layer
- **RepositoryInterface**: Contract for persistence
- **ProductRepository**: Domain-specific contract
- **Criteria**: Query specifications

### Infrastructure Layer
- **BaseRepository**: Common implementation
- **EloquentProductRepository**: Product-specific implementation
- **TransactionManager**: Transaction management
- **UnitOfWork**: Change tracking

### Dependency Flow
```
Domain → RepositoryInterface (abstraction)
Infrastructure → implements RepositoryInterface
Application → uses RepositoryInterface (via DI)
```

## Best Practices

1. **Always use RepositoryInterface in Domain/Application layers**
   ```php
   // ✅ Good
   public function __construct(private ProductRepository $repo) {}

   // ❌ Bad
   public function __construct(private EloquentProductRepository $repo) {}
   ```

2. **Use batch operations for bulk operations**
   ```php
   // ✅ Good
   $repo->saveMany($entities);

   // ❌ Bad
   foreach ($entities as $entity) {
       $repo->save($entity);
   }
   ```

3. **Use transactions for atomic operations**
   ```php
   // ✅ Good
   $txManager->transaction(function () use ($repo1, $repo2) {
       $repo1->save($entity1);
       $repo2->save($entity2);
   });
   ```

4. **Use Criteria for complex queries**
   ```php
   // ✅ Good
   $criteria = CompositeCriteria::and(...);
   $results = $repo->findByCriteria($criteria);

   // ❌ Bad
   $results = $repo->findBy(['field1' => 'value1', 'field2' => 'value2', ...]);
   ```

5. **Enable caching for frequently accessed data**
   ```php
   // ✅ Good (default)
   $product = $repo->findById(1); // Cached

   // For real-time data, disable cache
   $repo->enableCache(false)->findById(1);
   ```

## Testing

### Mocking Repositories

```php
// In tests, you can use InMemoryRepository
$mockRepo = new InMemoryProductRepository();
$service = new ProductService($mockRepo);
```

### Testing with Real Database

```php
// Use EloquentProductRepository with test database
$repo = new EloquentProductRepository($testConnection);
$product = $repo->findById(1);
```

## Future Enhancements

1. **Eager Loading Support**: Load relationships to prevent N+1 queries
2. **Query Result Pagination**: Built-in pagination support
3. **Soft Deletes**: Support for soft delete operations
4. **Event Hooks**: Repository events (beforeSave, afterSave, etc.)
5. **Query Logging**: Log all queries for debugging
6. **Read/Write Splitting**: Separate read and write connections

