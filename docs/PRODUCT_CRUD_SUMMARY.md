# Product CRUD - Clean Architecture Implementation

## Tổng Quan

Đã hoàn thành việc xây dựng một hệ thống CRUD hoàn chỉnh cho Product theo đúng chuẩn Clean Architecture với đầy đủ 4 layer và áp dụng SOLID principles.

## Kiến Trúc

### 1. Domain Layer (src/App/Domain/)

**Entities:**
- `Product` - Entity chính với đầy đủ business logic
  - Hoàn toàn immutable (tất cả properties đều readonly)
  - Business methods: `activate()`, `deactivate()`, `increaseStock()`, `decreaseStock()`, etc.
  - Validation rules tích hợp sẵn

**Value Objects:**
- `Money` - Đóng gói giá trị tiền tệ với currency
  - Arithmetic operations: `add()`, `subtract()`, `multiply()`
  - Comparison methods: `isGreaterThan()`, `isLessThan()`, `equals()`
  - Format theo locale: `format()` → "299.000 ₫"

- `ProductStatus` - Type-safe status với business rules
  - Constants: ACTIVE, INACTIVE, DRAFT, ARCHIVED
  - Business logic: `canBeOrdered()`, `isVisibleToCustomers()`, `canBeEdited()`
  - Factory methods: `active()`, `inactive()`, `draft()`, `archived()`

**Contracts:**
- `ProductRepositoryInterface` - Domain contract cho persistence
  - Methods: `findById()`, `findBySku()`, `save()`, `delete()`, `paginate()`
  - Không phụ thuộc vào framework hay infrastructure

### 2. Application Layer (src/App/Application/UseCases/Product/)

**Use Cases (Command/Query + Handler):**

**CreateProduct/**
- `CreateProductCommand` - DTO chứa data cho việc tạo product
- `CreateProductHandler` - Business logic:
  - Validate SKU uniqueness (business rule)
  - Create Money Value Object
  - Create Product Entity với ProductStatus::draft()
  - Save qua Repository

**UpdateProduct/**
- `UpdateProductCommand` - DTO cho update
- `UpdateProductHandler` - Logic:
  - Find existing product
  - Check SKU uniqueness (exclude current product)
  - Update với immutable pattern (returns new instance)
  - Save changes

**DeleteProduct/**
- `DeleteProductCommand` - Contains product ID
- `DeleteProductHandler` - Verify existence then delete

**GetProduct/**
- `GetProductQuery` - Query DTO
- `GetProductHandler` - Retrieve single product by ID

**ListProducts/**
- `ListProductsQuery` - Pagination parameters
- `ListProductsHandler` - Return paginated list

### 3. Infrastructure Layer (src/App/Infrastructure/)

**Repository Implementation:**
- `PdoProductRepository` extends `BaseRepository`
  - Implements `ProductRepositoryInterface`
  - Hydrate: Database row → Domain Entity (with Value Objects)
  - Dehydrate: Domain Entity → Database row
  - Mapping: `is_active` (boolean) ↔ `ProductStatus` (Value Object)
  - Adapts to existing database schema (no migration needed)

**Key Methods:**
```php
protected function mapToEntity(array $row): object
{
    // Maps is_active to ProductStatus
    // Maps price to Money Value Object
    // Returns fully hydrated Product entity
}

protected function mapToRow(object $entity): array
{
    // Extracts primitive values from Value Objects
    // Maps ProductStatus back to is_active
    // Returns array ready for database
}
```

### 4. Presentation Layer (src/App/Presentation/Http/Actions/Product/)

**Actions (ADR Pattern):**
- `CreateProductAction` - POST /api/v1/products
- `UpdateProductAction` - PUT /api/v1/products/{id}
- `DeleteProductAction` - DELETE /api/v1/products/{id}
- `GetProductAction` - GET /api/v1/products/{id}
- `ListProductsAction` - GET /api/v1/products (with pagination)

**Đặc điểm:**
- Single responsibility per action
- Auto-wired dependencies (handlers injected)
- Input validation & error handling
- JSON responses với proper HTTP status codes
- Delegates business logic to Application layer

## API Endpoints

```
GET    /api/v1/products         - List products (paginated)
GET    /api/v1/products/{id}    - Get single product
POST   /api/v1/products         - Create product
PUT    /api/v1/products/{id}    - Update product
DELETE /api/v1/products/{id}    - Delete product
```

### Request/Response Examples

**POST /api/v1/products** - Create Product
```json
Request:
{
  "title": "Clean Architecture Book",
  "price": 299000,
  "currency": "VND",
  "sku": "BOOK-CA-001",
  "description": "A guide to Clean Architecture",
  "stock": 50
}

Response (201 Created):
{
  "success": true,
  "message": "Product created successfully",
  "data": {
    "id": 1,
    "title": "Clean Architecture Book",
    "sku": "BOOK-CA-001",
    "description": "A guide to Clean Architecture",
    "price": 299000,
    "currency": "VND",
    "stock": 50,
    "status": "draft",
    "created_at": "2025-01-16 15:30:00",
    "updated_at": null
  }
}
```

**GET /api/v1/products?page=1&per_page=10** - List Products
```json
Response (200 OK):
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Product 1",
      "sku": "SKU-001",
      "price": 299000,
      "currency": "VND",
      "stock": 50,
      "status": "active"
    }
  ],
  "pagination": {
    "total": 100,
    "page": 1,
    "per_page": 10,
    "last_page": 10
  }
}
```

## Clean Architecture Compliance

### ✅ Dependency Rule
- Domain không phụ thuộc gì cả (pure PHP)
- Application chỉ phụ thuộc Domain
- Infrastructure phụ thuộc Domain + Framework
- Presentation phụ thuộc Application + Framework

### ✅ SOLID Principles

**Single Responsibility:**
- Mỗi class chỉ có 1 lý do để thay đổi
- Product Entity: Business logic của product
- Money VO: Logic về tiền tệ
- CreateProductHandler: Logic tạo product
- CreateProductAction: HTTP handling cho create

**Open/Closed:**
- Extend via interfaces (ProductRepositoryInterface)
- Có thể swap repository implementation
- Có thể add new use cases without modifying existing

**Liskov Substitution:**
- PdoProductRepository thay thế được cho bất kỳ ProductRepositoryInterface impl nào
- Product entity tuân theo Domain contract

**Interface Segregation:**
- Interfaces nhỏ, tập trung (ProductRepositoryInterface chỉ có product methods)
- Không ép implement methods không dùng

**Dependency Inversion:**
- High-level (Application) không phụ thuộc low-level (Infrastructure)
- Cả hai phụ thuộc abstractions (interfaces)
- Dependency injection qua constructor

### ✅ Design Patterns

**Repository Pattern:**
- Abstraction layer cho data access
- Domain defines interface
- Infrastructure implements

**Value Object Pattern:**
- Money và ProductStatus immutable
- Encapsulate domain concepts
- Self-validating

**Command/Query Pattern (CQRS):**
- Commands: CreateProduct, UpdateProduct, DeleteProduct
- Queries: GetProduct, ListProducts
- Separation of concerns

**ADR (Action-Domain-Responder):**
- Single-purpose Actions
- Cleaner than traditional MVC controllers

**Adapter Pattern:**
- AuthenticatableAdapter bridges Domain ↔ Framework
- Allows Domain to stay pure

## Testing Results

```
✓ CREATE PRODUCT - Works with unique SKU validation
✓ GET PRODUCT - Retrieves product by ID
✓ UPDATE PRODUCT - Updates product details
✓ LIST PRODUCTS - Paginated list (5 products found)
✓ DELETE PRODUCT - Removes product
✓ VERIFY DELETION - Confirms product no longer exists

========================================
✓ ALL TESTS PASSED
========================================
```

## Key Learnings & Best Practices

### 1. Immutability is Key
```php
// ❌ Mutable (bad)
public string $title;

// ✅ Immutable (good)
public readonly string $title;

// Update via method returning new instance
public function update(string $title): self
{
    return new self($this->id, $title, ...);
}
```

### 2. Value Objects Encapsulate Domain Logic
```php
// ❌ Primitive obsession (bad)
public float $price;
public string $currency;

// ✅ Value Object (good)
public readonly Money $price;

// With rich behavior
$newPrice = $product->price->add(Money::fromAmount(50000));
$formatted = $product->price->format(); // "299.000 ₫"
```

### 3. Use Case = Single Business Operation
```php
// Each use case = 1 file for Command + 1 file for Handler
CreateProduct/
  ├── CreateProductCommand.php
  └── CreateProductHandler.php
```

### 4. Repository Hides Infrastructure Details
```php
// Domain code doesn't know about database
$product = $repository->findBySku('BOOK-001');
$repository->save($product);

// Infrastructure handles DB mapping
protected function mapToEntity(array $row): Product
{
    // Maps is_active → ProductStatus
    // Maps price → Money
}
```

### 5. Presentation Layer is Thin
```php
// Action just validates input and delegates
protected function handle(...): mixed
{
    // 1. Validate input
    if (empty($title)) return error;

    // 2. Create command
    $command = new CreateProductCommand(...);

    // 3. Execute use case
    $product = ($this->handler)($command);

    // 4. Return response
    return $response->json(['data' => $product]);
}
```

## Performance Considerations

- ✅ BaseRepository provides query builder optimization
- ✅ Pagination to handle large datasets
- ✅ Immutable entities prevent accidental mutations
- ✅ Value Objects validated once at construction
- ✅ Repository can be decorated with caching layer

## Future Enhancements

1. **Add Caching Layer:**
   ```php
   CachedProductRepository extends PdoProductRepository
   ```

2. **Add Event Sourcing:**
   ```php
   ProductCreated event
   ProductUpdated event
   ```

3. **Add Specifications Pattern:**
   ```php
   ActiveProductsSpec
   LowStockSpec
   ```

4. **Add Search:**
   ```php
   SearchProductsQuery/Handler
   ```

## Kết Luận

Đã xây dựng thành công một hệ thống CRUD hoàn chỉnh theo đúng chuẩn Clean Architecture với:

- ✅ 4 layers rõ ràng với dependency đúng hướng
- ✅ Domain layer hoàn toàn độc lập
- ✅ SOLID principles được áp dụng triệt để
- ✅ Immutability và type safety
- ✅ Rich domain model với Value Objects
- ✅ Testable và maintainable
- ✅ Production-ready code

Đây là foundation tốt để scale và extend cho các features khác!
