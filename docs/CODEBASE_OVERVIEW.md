# Toporia Framework - Codebase Overview

## Project Summary

**Toporia** is a modern PHP framework (v1.0.0) built on Clean Architecture and SOLID principles. It's a zero-dependency core framework with optional integrations, designed for building scalable web applications with strict separation of concerns.

**Key Characteristics:**
- PHP 8.1+ required
- Clean Architecture with 4 layers (Domain, Application, Infrastructure, Presentation)
- SOLID principles throughout
- PSR standards compliance (PSR-3 logging, PSR-7 inspired HTTP, PSR-11 container, PSR-14 events)
- Zero framework dependencies in core
- Comprehensive feature set (ORM, Queue, Cache, Auth, Realtime, Search, etc.)

---

## Architecture Overview

### Layer Structure

```
┌─────────────────────────────────────────────────────────┐
│                  Presentation Layer                     │
│  (Controllers, Actions, Middleware, Views, API)          │
│  Location: src/App/Presentation/                        │
└─────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────┐
│                  Application Layer                      │
│     (Use Cases, Commands, Handlers, DTOs)               │
│  Location: src/App/Application/                         │
└─────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────┐
│                    Domain Layer                         │
│  (Entities, Value Objects, Repository Interfaces)       │
│  Location: src/App/Domain/                              │
│  IMPORTANT: NO framework dependencies                  │
└─────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────┐
│                 Infrastructure Layer                    │
│   (Repository Implementations, External Services)       │
│  Location: src/App/Infrastructure/                      │
└─────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────┐
│                   Framework Layer                       │
│  (HTTP, Routing, Container, Events, Database, etc.)     │
│  Location: src/Framework/                               │
└─────────────────────────────────────────────────────────┘
```

### Directory Structure

```
toporia/
├── bootstrap/              # Application bootstrap files
│   ├── app.php            # Main bootstrap (loads env, config, providers)
│   └── helpers.php        # 50+ global helper functions
├── config/                 # Configuration files
│   ├── app.php
│   ├── database.php
│   ├── queue.php
│   └── ... (20+ config files)
├── public/                 # Web root
│   └── index.php          # HTTP entry point
├── routes/                 # Route definitions
│   ├── web.php            # Web routes (SPA fallback)
│   └── api.php            # API routes
├── src/
│   ├── Framework/         # Framework layer (reusable mini-framework)
│   │   ├── Application/   # Application base classes
│   │   ├── Auth/         # Authentication & Authorization
│   │   ├── Bus/          # Command/Query bus
│   │   ├── Cache/        # Caching system
│   │   ├── Config/       # Configuration management
│   │   ├── Console/      # CLI framework
│   │   ├── Container/    # DI container
│   │   ├── Database/     # ORM, Query Builder, Migrations
│   │   ├── DateTime/     # Date/time utilities
│   │   ├── Domain/       # Framework domain models
│   │   ├── Error/        # Error handling
│   │   ├── Events/       # Event system
│   │   ├── Foundation/   # Core application class
│   │   ├── Hashing/      # Password hashing
│   │   ├── Http/         # Request/Response (PSR-7 inspired)
│   │   ├── Log/          # PSR-3 logging
│   │   ├── Mail/          # Email system
│   │   ├── Notification/ # Multi-channel notifications
│   │   ├── Observer/     # Observer pattern
│   │   ├── Pipeline/     # Middleware pipeline
│   │   ├── Process/      # Process management
│   │   ├── Providers/    # Framework service providers
│   │   ├── Queue/        # Async job processing
│   │   ├── RateLimit/    # Rate limiting
│   │   ├── Realtime/     # Broadcasting (WebSocket, SSE, etc.)
│   │   ├── Routing/      # Router with middleware
│   │   ├── Search/       # Elasticsearch integration
│   │   ├── Security/     # CSRF, XSS, security headers
│   │   ├── Session/      # Session management
│   │   ├── Storage/      # File storage (Local, S3, etc.)
│   │   ├── Support/      # Collections, helpers, facades
│   │   ├── Testing/      # Testing utilities
│   │   ├── Translation/  # i18n system
│   │   └── Validation/   # Form validation
│   └── App/              # Application layer
│       ├── Domain/       # Business entities & interfaces
│       ├── Application/  # Use cases & services
│       ├── Infrastructure/ # Repository implementations
│       └── Presentation/  # Controllers, middleware, views
├── storage/               # Logs, cache, uploads
├── tests/                 # PHPUnit tests
├── vendor/                # Composer dependencies
├── console                # CLI entry point
├── composer.json
└── README.md
```

---

## Bootstrap Flow

### HTTP Request Flow

1. **Entry Point** (`public/index.php`)
   - Starts session
   - Loads autoloader
   - Bootstraps application via `bootstrap/app.php`
   - Dispatches request through router

2. **Bootstrap** (`bootstrap/app.php`)
   - Load environment variables (`.env`)
   - Register exception handler
   - Create Application instance
   - Load helper functions
   - Load configuration files
   - Register facades (ServiceAccessor)
   - Register service providers (framework + app)
   - Boot service providers (loads routes)

3. **Request Dispatch** (`Router::dispatch()`)
   - Match route by method + path
   - Extract route parameters
   - Build middleware pipeline
   - Execute middleware (before hooks)
   - Execute controller/action
   - Execute middleware (after hooks)
   - Send response

### Console Command Flow

1. **Entry Point** (`console`)
   - Loads autoloader
   - Bootstraps application
   - Gets Console Application from container
   - Runs command with arguments

2. **Command Execution**
   - Parse arguments/options
   - Resolve command handler
   - Execute with dependency injection
   - Return exit code

---

## Core Framework Components

### 1. Dependency Injection Container

**Location:** `src/Framework/Container/Container.php`

**Features:**
- Auto-wiring via reflection
- Singleton pattern support
- Contextual bindings
- Tagged bindings
- Extending bindings
- Method injection (`call()`)
- Circular dependency detection
- Reflection caching for performance

**Usage:**
```php
$container->singleton(MyService::class, fn() => new MyService());
$service = $container->get(MyService::class);
$result = $container->call([Controller::class, 'method'], ['param' => 'value']);
```

### 2. Application Class

**Location:** `src/Framework/Foundation/Application.php`

**Responsibilities:**
- Manages DI container
- Registers service providers
- Boots service providers
- Provides path helpers

**Key Methods:**
- `register()` - Register service provider
- `boot()` - Boot all providers
- `make()` - Resolve from container
- `path()` - Get application path

### 3. Router

**Location:** `src/Framework/Routing/Router.php`

**Features:**
- RESTful methods (GET, POST, PUT, PATCH, DELETE, ANY)
- Route parameters with regex (`{id}`, `{slug}`)
- Named routes
- Route groups (prefix, middleware, namespace)
- Middleware pipeline
- Dependency injection for controllers

**Usage:**
```php
$router->get('/products/{id}', [ProductController::class, 'show'])
    ->name('products.show')
    ->middleware(['auth']);

$router->group(['prefix' => 'admin', 'middleware' => ['auth']], function($router) {
    $router->get('/users', [AdminController::class, 'users']);
});
```

### 4. HTTP Layer

**Location:** `src/Framework/Http/`

**Components:**
- `Request` - PSR-7 inspired request abstraction
- `Response` - Response builder with JSON helpers
- `FormRequest` - Validation wrapper
- `MiddlewarePipeline` - Middleware execution

**Features:**
- JSON detection
- File upload handling
- Cookie management with encryption
- Security headers

### 5. Database & ORM

**Location:** `src/Framework/Database/`

**Components:**
- `DatabaseManager` - Multi-connection manager
- `QueryBuilder` - Fluent query builder
- `Model` - Eloquent-style ORM (Active Record)
- `Migration` - Database migrations
- `Schema` - Schema builder

**ORM Features:**
- Relationships (HasOne, HasMany, BelongsTo, BelongsToMany, HasManyThrough, HasOneThrough, MorphOne)
- Eager loading (prevents N+1 queries)
- Relationship aggregates (withCount, withSum, withAvg, etc.)
- Bulk upsert (100x faster than separate queries)
- Model events (creating, created, updating, updated, deleting, deleted)
- Soft deletes
- Query scopes
- Model caching
- Chunking for large datasets

**Supported Databases:**
- MySQL
- PostgreSQL
- SQLite
- MongoDB

### 6. Queue System

**Location:** `src/Framework/Queue/`

**Components:**
- `QueueManager` - Multi-driver queue manager
- `Worker` - Job processor with retry logic
- `Job` - Base job class
- `DatabaseQueue` - Database driver
- `RedisQueue` - Redis driver
- `SyncQueue` - Synchronous driver

**Features:**
- Multiple drivers (Sync, Database, Redis)
- Delayed job execution
- Job retries with exponential backoff
- Failed job handling
- Job middleware (RateLimit, Throttle, EnsureUnique)
- Graceful shutdown
- Memory/runtime limits
- Job cancellation
- Job progress tracking
- Metrics collection

**Worker Features** (from `Worker.php`):
- Multi-queue support with priority
- Signal handling (graceful shutdown)
- Timeout handling (SIGALRM)
- Auto-restart on memory/runtime limits
- Middleware pipeline execution
- Event dispatching (JobProcessing, JobProcessed, JobFailed, etc.)

### 7. Event System

**Location:** `src/Framework/Events/`

**Features:**
- Priority-based event dispatcher
- Event propagation control
- Event subscriber pattern
- PSR-14 inspired interface design

**Usage:**
```php
event(new UserRegistered($user));
$dispatcher->subscribe(UserEventSubscriber::class);
```

### 8. Command Bus

**Location:** `src/Framework/Bus/`

**Features:**
- Command/Query dispatcher
- Auto-wired handlers
- Queue support
- Middleware pipeline
- Batching support

**Usage:**
```php
dispatch(new SendWelcomeEmail('user@example.com', 'John'));
$result = dispatch_sync(new GetUserQuery($id));
```

### 9. Cache System

**Location:** `src/Framework/Cache/`

**Drivers:**
- File
- Redis
- Memory

**Features:**
- PSR-16 inspired interface
- `remember()` pattern
- Increment/decrement
- Forever caching

### 10. Authentication & Authorization

**Location:** `src/Framework/Auth/`

**Features:**
- Session-based auth
- Token-based auth (API)
- Gate system (closure-based)
- Policy classes (resource-based)
- Password hashing (Bcrypt, Argon2id)
- Automatic hash migration

### 11. Security

**Location:** `src/Framework/Security/`

**Features:**
- CSRF protection
- XSS protection (input sanitization, output escaping)
- SQL injection prevention (parameterized queries)
- Security headers (CSP, HSTS, X-Frame-Options)
- Rate limiting
- Cookie encryption

### 12. Logging

**Location:** `src/Framework/Log/`

**Features:**
- PSR-3 compliant
- Multi-channel (Daily, Single, Stack, Syslog, Stderr)
- Daily file rotation (YYYY-MM-DD.log)
- Auto-cleanup of old logs
- Placeholder interpolation (`{user_id}`)
- Context data as structured JSON
- Thread-safe file locking

### 13. Realtime/Broadcasting

**Location:** `src/Framework/Realtime/`

**Transports:**
- WebSocket
- SSE (Server-Sent Events)
- Long-polling
- Socket.IO
- Memory (testing)

**Brokers:**
- Redis Pub/Sub
- RabbitMQ
- Kafka
- NATS
- PostgreSQL

**Architecture:**
- Producer: Can be called from anywhere (HTTP, CLI, Jobs, Events)
- Consumer: Only in CLI commands (long-lived processes)
- Multi-server support via brokers

### 14. Search (Elasticsearch)

**Location:** `src/Framework/Search/`

**Features:**
- Reusable `SearchManager`
- Bulk indexing
- Queue-aware sync
- ORM trait for auto document updates
- Fluent query builder
- Console reindex command
- Connection pooling and retries

### 15. Task Scheduling

**Location:** `src/Framework/Console/Scheduling/`

**Features:**
- Cron-like scheduler
- Frequency helpers (everyMinute, hourly, daily, etc.)
- Conditional execution (when, skip)
- Custom cron expressions
- Timezone support

### 16. Storage

**Location:** `src/Framework/Storage/`

**Drivers:**
- Local
- S3
- DigitalOcean Spaces
- MinIO

**Features:**
- Clean Storage facade
- File upload handling
- Hash-based filenames

### 17. Mail

**Location:** `src/Framework/Mail/`

**Drivers:**
- SMTP
- Log
- Array

**Features:**
- HTML email support
- Queue integration

### 18. Notifications

**Location:** `src/Framework/Notification/`

**Channels:**
- Mail
- Database
- SMS
- Slack
- Broadcast (WebSocket/SSE)

**Features:**
- Notifiable trait for models
- Database notification storage
- Real-time broadcast notifications

### 19. Validation

**Location:** `src/Framework/Validation/`

**Features:**
- Form request validation
- 20+ built-in rules
- Database validation rules (unique, exists)
- Custom rule support

### 20. Collections

**Location:** `src/Framework/Support/Collections/`

**Types:**
- `Collection` - Eager collection (40+ methods)
- `LazyCollection` - Generator-based lazy evaluation

**Features:**
- Functional programming patterns
- Statistical operations
- Set operations

---

## Application Layer Structure

### Domain Layer (`src/App/Domain/`)

**Purpose:** Pure business logic, no framework dependencies

**Structure:**
- `Entities/` - Business entities (plain PHP classes)
- `Contracts/Repository/` - Repository interfaces
- `ValueObjects/` - Value objects

**Example:**
```php
// Domain/Entities/Product.php
class Product {
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly float $price
    ) {}
}

// Domain/Contracts/Repository/ProductRepositoryInterface.php
interface ProductRepositoryInterface {
    public function findById(string $id): ?Product;
    public function save(Product $product): void;
}
```

### Application Layer (`src/App/Application/`)

**Purpose:** Use cases and business services

**Structure:**
- `UseCases/` - Use case handlers
- `Services/` - Application services
- `Rules/` - Custom validation rules
- `Observers/` - Model observers

**Example:**
```php
// Application/UseCases/CreateProduct/CreateProductHandler.php
final class CreateProductHandler {
    public function __construct(
        private ProductRepositoryInterface $repository
    ) {}

    public function __invoke(CreateProductCommand $command): Product {
        $product = new Product(...);
        $this->repository->save($product);
        return $product;
    }
}
```

### Infrastructure Layer (`src/App/Infrastructure/`)

**Purpose:** External concerns and implementations

**Structure:**
- `Repository/` - Repository implementations
- `Persistence/` - ORM models
- `Providers/` - Service providers
- `Jobs/` - Queue jobs
- `Notifications/` - Notification classes
- `Auth/` - Auth implementations
- `Services/` - External service integrations

**Example:**
```php
// Infrastructure/Repository/ProductRepository.php
class ProductRepository implements ProductRepositoryInterface {
    public function __construct(private ProductModel $model) {}

    public function findById(string $id): ?Product {
        $model = $this->model->find($id);
        return $model ? $this->toEntity($model) : null;
    }
}
```

### Presentation Layer (`src/App/Presentation/`)

**Purpose:** HTTP interface

**Structure:**
- `Http/Controllers/` - MVC controllers
- `Http/Middleware/` - HTTP middleware
- `Console/Commands/` - CLI commands
- `Views/` - View templates

**Example:**
```php
// Presentation/Http/Controllers/ProductController.php
class ProductController extends BaseController {
    public function store(CreateProductRequest $request): JsonResponse {
        $product = dispatch(new CreateProductCommand(...));
        return response()->json($product);
    }
}
```

---

## Service Providers

### Framework Providers

**Location:** `src/Framework/Providers/`

**Auto-registered via:** `FrameworkServiceProvider::providers()`

**Key Providers:**
- `ConfigServiceProvider` - Configuration management
- `HttpServiceProvider` - Request/Response
- `EventServiceProvider` - Event dispatcher
- `RoutingServiceProvider` - Router
- `DatabaseServiceProvider` - Database manager
- `QueueServiceProvider` - Queue manager
- `AuthServiceProvider` - Authentication
- `CacheServiceProvider` - Cache manager
- `LogServiceProvider` - Logger
- `RealtimeServiceProvider` - Broadcasting
- `SearchServiceProvider` - Elasticsearch
- ... (25+ providers)

### Application Providers

**Location:** `src/App/Infrastructure/Providers/`

**Registered in:** `bootstrap/app.php` → `RegisterProviders::bootstrap()`

**Key Providers:**
- `DomainServiceProvider` - Repository bindings (MUST be first)
- `AppServiceProvider` - Application services
- `EventServiceProvider` - Event listeners
- `RouteServiceProvider` - Route loading
- `ScheduleServiceProvider` - Task scheduling

**Provider Pattern:**
```php
class MyServiceProvider extends ServiceProvider {
    public function register(ContainerInterface $container): void {
        // Bind services (don't resolve yet)
        $container->singleton(MyService::class, fn() => new MyService());
    }

    public function boot(ContainerInterface $container): void {
        // Safe to resolve services here
        // Routes loaded here
    }
}
```

---

## Key Patterns & Conventions

### 1. Clean Architecture

- **Dependency Rule:** Dependencies point inward (Framework ← Infrastructure ← Application ← Domain)
- **Domain Independence:** Domain layer has NO framework dependencies
- **Interface-Based:** Program to interfaces, not implementations

### 2. SOLID Principles

- **Single Responsibility:** Each class has one reason to change
- **Open/Closed:** Open for extension, closed for modification
- **Liskov Substitution:** Subtypes must be substitutable
- **Interface Segregation:** Small, focused interfaces
- **Dependency Inversion:** Depend on abstractions

### 3. Service Provider Pattern

- `register()` - Bind services (no resolution)
- `boot()` - Initialize services (safe to resolve)

### 4. Command Bus Pattern

- Commands are simple DTOs
- Handlers auto-wired via DI
- Naming: `CommandName` → `CommandNameHandler`
- Queue support via `ShouldQueue` interface

### 5. Repository Pattern

- Interfaces in Domain layer
- Implementations in Infrastructure layer
- ORM models in Infrastructure/Persistence

### 6. Observer Pattern

- Model observers for lifecycle hooks
- Event listeners for domain events
- Registered in `config/observers.php`

### 7. Middleware Pipeline

- Before hooks → Controller → After hooks
- Can modify request/response
- Can short-circuit execution

---

## Configuration

**Location:** `config/`

**Key Files:**
- `app.php` - Application config (name, timezone, debug)
- `database.php` - Database connections
- `queue.php` - Queue configuration
- `cache.php` - Cache drivers
- `middleware.php` - Middleware groups and aliases
- `auth.php` - Authentication config
- `realtime.php` - Broadcasting config
- `search.php` - Elasticsearch config
- ... (20+ config files)

**Access:**
```php
config('app.name');
config('database.default');
```

---

## Routes

**Location:** `routes/`

**Files:**
- `web.php` - Web routes (SPA fallback route)
- `api.php` - API routes (prefixed with `/api`)

**Loading:**
- Loaded in `RouteServiceProvider::boot()`
- API routes loaded first (before web catch-all)
- Middleware groups applied automatically

---

## Helper Functions

**Location:** `bootstrap/helpers.php`

**50+ Global Helpers:**
- `app()` - Get application/service
- `config()` - Get config value
- `env()` - Get environment variable
- `route()` - Generate URL from route name
- `response()` - Create response
- `auth()` - Get auth instance
- `e()` - Escape HTML
- `dispatch()` - Dispatch command
- `event()` - Dispatch event
- `log_info()`, `log_error()` - Logging
- `cache()` - Cache operations
- ... (many more)

---

## Testing

**Location:** `tests/`

**Test Suites:**
- Unit
- Feature
- Integration
- Performance

**Commands:**
```bash
composer test              # Run all tests
composer test:unit         # Unit tests only
composer test:coverage     # With coverage
```

---

## Console Commands

**Framework Commands:**
- `cache:clear` - Clear cache
- `queue:work` - Start queue worker
- `schedule:run` - Run scheduled tasks
- `migrate` - Run migrations
- `db:seed` - Run seeders
- `search:reindex` - Reindex Elasticsearch
- ... (many more)

**Application Commands:**
- Registered in `src/App/Presentation/Console/Kernel.php`

---

## Key Files Reference

### Bootstrap
- `public/index.php` - HTTP entry point
- `console` - CLI entry point
- `bootstrap/app.php` - Application bootstrap
- `bootstrap/helpers.php` - Helper functions

### Configuration
- `config/app.php` - Application config
- `config/middleware.php` - Middleware groups
- `config/observers.php` - Model observers

### Routes
- `routes/web.php` - Web routes
- `routes/api.php` - API routes

### Application
- `src/App/Presentation/Console/Kernel.php` - Console command registration
- `src/App/Infrastructure/Providers/RouteServiceProvider.php` - Route loading

### Framework
- `src/Framework/Foundation/Application.php` - Application class
- `src/Framework/Container/Container.php` - DI container
- `src/Framework/Routing/Router.php` - Router
- `src/Framework/Queue/Worker.php` - Queue worker
- `src/Framework/Database/ORM/Model.php` - Base model

---

## Dependencies

**Core Requirements:**
- PHP >= 8.1
- Composer

**Optional Extensions:**
- `ext-redis` - Redis support
- `ext-rdkafka` - High-performance Kafka
- `ext-pdo_mysql` / `ext-pdo_pgsql` / `ext-pdo_sqlite` - Database support
- `ext-pcntl` - Multi-process execution
- `ext-zip` - Excel import/export

**Composer Packages:**
- `phpmailer/phpmailer` - Email
- `aws/aws-sdk-php` - AWS S3
- `php-amqplib/php-amqplib` - RabbitMQ
- `nmred/kafka-php` - Kafka (pure PHP)
- `elasticsearch/elasticsearch` - Elasticsearch
- `phpoffice/phpspreadsheet` - Excel
- `openspout/openspout` - Excel (streaming)

---

## Performance Characteristics

**Benchmarks:**
- Logger: ~0.5ms per write (2000 writes/sec)
- Router: ~0.1ms per route match
- Container: ~0.05ms per resolution
- ORM Query: ~1-5ms per database query
- Upsert: 100x faster than separate insert/update

**Optimizations:**
- O(1) container singleton lookup
- O(1) route matching (optimized regex)
- Lazy loading of services
- Query optimization (eager loading prevents N+1)
- File locking for thread-safety
- Opcode caching compatible

---

## Development Workflow

### Setup
```bash
composer install
cp .env.example .env
php console key:generate
php console migrate
php -S localhost:8000 -t public
```

### Running Tests
```bash
composer test
composer test:coverage
```

### Queue Worker
```bash
php console queue:work
```

### Scheduled Tasks
```bash
php console schedule:run
# Or via cron: * * * * * php /path/to/console schedule:run
```

---

## Summary

Toporia is a comprehensive PHP framework built with Clean Architecture principles. It provides:

1. **Strong Architecture** - Clear layer separation, SOLID principles
2. **Rich Feature Set** - ORM, Queue, Cache, Auth, Realtime, Search, etc.
3. **Developer Experience** - Fluent APIs, helper functions, comprehensive docs
4. **Performance** - Optimized for speed with caching and lazy loading
5. **Flexibility** - Zero-dependency core with optional integrations
6. **Type Safety** - PHP 8.1+ type hints throughout
7. **Testing** - Comprehensive test suite with multiple test types

The codebase is well-organized, follows consistent patterns, and is designed for maintainability and scalability.

