# Toporia Framework - Codebase Overview

## Executive Summary

**Toporia** is a professional PHP framework (v1.0.0) built on Clean Architecture principles and SOLID design patterns. It provides a zero-dependency core framework with comprehensive features for building scalable web applications.

**Key Characteristics:**
- **Architecture**: Clean Architecture with strict layer separation
- **Language**: PHP 8.1+ with strict types
- **Design Patterns**: SOLID principles, Dependency Injection, Service Provider pattern
- **Inspiration**: Laravel (API compatibility) + Symfony (architecture)
- **Core Philosophy**: Framework and Application layers are strictly separated

---

## Project Structure

```
toporia/
├── bootstrap/          # Application bootstrap files
│   ├── app.php        # Main bootstrap (loads providers, config)
│   └── helpers.php    # Global helper functions
├── config/            # Configuration files (app, database, cache, etc.)
├── database/          # Migrations and seeders
├── docs/              # Comprehensive documentation (15+ guides)
├── public/            # Web server entry point (index.php)
├── resources/         # Frontend assets (Vue.js SPA)
├── routes/            # Route definitions (web.php, api.php)
├── src/
│   ├── Framework/     # Reusable mini-framework (core)
│   └── App/           # Application layer (Clean Architecture)
├── storage/           # Logs, cache, uploads
├── tests/             # PHPUnit tests (Unit, Feature, Integration, Performance)
└── vendor/            # Composer dependencies
```

---

## Architecture Layers

### 1. Framework Layer (`src/Framework/`)

The reusable mini-framework providing core services. **Zero dependencies** on application code.

#### Core Components

**Container & DI** (`Container/`)
- PSR-11 inspired dependency injection container
- Auto-wiring via reflection
- Singleton pattern support
- Contextual bindings
- Circular dependency detection
- Method injection via `call()`
- Performance: O(1) singleton lookup, O(N) resolution depth

**Routing** (`Routing/`)
- Fluent OOP router with RESTful verbs
- Route parameters with regex (`{id}`, `{slug}`)
- Named routes for URL generation
- Route groups (prefix, middleware, namespace)
- Middleware pipeline (Chain of Responsibility)
- Dependency injection for controllers

**HTTP Layer** (`Http/`)
- PSR-7 inspired Request/Response abstraction
- JSON detection and helpers
- File upload handling
- Cookie management with encryption
- FormRequest validation (automatic)
- Middleware pipeline builder

**Database & ORM** (`Database/`)
- Multi-connection manager (MySQL, PostgreSQL, SQLite)
- Fluent Query Builder with parameter binding
- Eloquent-style Active Record ORM
- Model relationships (HasOne, HasMany, BelongsTo, BelongsToMany)
- Eager loading (prevents N+1 queries)
- Relationship aggregates (withCount, withSum, etc.)
- Bulk upsert (100x faster than separate queries)
- Migration system with Schema Builder
- Model lifecycle hooks (creating, created, updating, updated, deleting, deleted)

**Events** (`Events/`)
- Priority-based event dispatcher
- Event propagation control
- Event subscriber pattern
- PSR-14 inspired interface
- Queued listeners support
- Wildcard event matching

**Console** (`Console/`)
- Professional CLI framework
- Command pattern implementation
- Input parsing (arguments, options, flags)
- Colored output and formatted tables
- Interactive prompts
- Built-in commands (cache, queue, schedule, migrate)

**Bus** (`Bus/`)
- Command/Query dispatcher
- Queue integration
- Batch operations
- Chain operations (sequential jobs)
- Middleware pipeline
- Auto handler resolution (CommandName → CommandNameHandler)

**Queue** (`Queue/`)
- Multiple drivers (Sync, Database, Redis, RabbitMQ)
- Delayed job execution
- Job retries with exponential backoff
- Failed job handling
- Queue worker with graceful shutdown
- Job middleware support

**Cache** (`Cache/`)
- Multi-driver (File, Redis, Memory)
- PSR-16 inspired interface
- `remember()` pattern for lazy caching
- Increment/decrement operations
- Forever caching

**Logging** (`Log/`)
- PSR-3 compliant
- Multi-channel (Daily, Single, Stack, Syslog, Stderr)
- Daily file rotation (YYYY-MM-DD.log)
- Auto-cleanup of old logs
- Placeholder interpolation (`{user_id}`)
- Context data as structured JSON
- Thread-safe file locking

**Authentication** (`Auth/`)
- Session-based authentication
- Token-based authentication (API)
- Gate system (closure-based authorization)
- Policy classes (resource-based authorization)
- Password hashing (Bcrypt, Argon2id)
- Automatic hash algorithm migration

**Security** (`Security/`)
- CSRF protection (token-based)
- XSS protection (input sanitization, output escaping)
- SQL injection prevention (parameterized queries)
- Security headers (CSP, HSTS, X-Frame-Options)
- Rate limiting (configurable throttling)
- Cookie encryption (automatic)
- Replay attack protection

**Storage** (`Storage/`)
- Multi-driver (Local, S3, DigitalOcean Spaces, MinIO)
- Laravel-style Storage facade
- File upload handling with validation
- Hash-based filenames

**Mail** (`Mail/`)
- Multi-driver (SMTP, Log, Array)
- HTML email support
- Queue integration

**Notifications** (`Notification/`)
- Multi-channel (Mail, Database, SMS, Slack, Broadcast)
- Notifiable trait for models
- Database notification storage
- Real-time WebSocket/SSE broadcast

**Realtime** (`Realtime/`)
- Multi-transport (WebSocket, SSE, Long-polling, Socket.IO)
- Broker drivers: Redis, RabbitMQ, Kafka, NATS, PostgreSQL
- Auto topic/queue binding
- Enterprise performance (batching, QoS, graceful shutdown)
- Producer/Consumer pattern (producer anywhere, consumer only in CLI)

**Search** (`Search/`)
- Elasticsearch integration
- Reusable SearchManager
- Bulk indexing
- Queue-aware sync
- ORM trait for auto document updates
- Fluent query builder
- Console reindex command

**Scheduling** (`Schedule/`)
- Cron-like task scheduler
- Frequency helpers (everyMinute, hourly, daily, weekly, monthly)
- Conditional execution (when, skip)
- Custom cron expressions
- Timezone support

**Collections** (`Support/Collections/`)
- **Collection**: Eager collection with 40+ methods
- **LazyCollection**: Generator-based lazy evaluation
- Functional programming patterns
- Statistical operations
- Set operations

**Validation** (`Validation/`)
- Form request validation (automatic error handling)
- 20+ built-in rules
- Database validation rules (unique, exists)
- Custom rule support
- Laravel-compatible API

**Error Handling** (`Error/`)
- Beautiful error pages with syntax highlighting
- Stack trace with file links
- Request information panel
- JSON error responses for APIs
- Environment-aware (debug vs production)

#### Service Providers (`Providers/`)

Framework service providers register and boot framework services:
- `ConfigServiceProvider` - Configuration loading
- `HttpServiceProvider` - HTTP request/response
- `RoutingServiceProvider` - Router and route loading
- `DatabaseServiceProvider` - Database connections and ORM
- `CacheServiceProvider` - Cache drivers
- `QueueServiceProvider` - Queue drivers
- `EventServiceProvider` - Event dispatcher
- `AuthServiceProvider` - Authentication services
- `LogServiceProvider` - Logging channels
- `MailServiceProvider` - Mail drivers
- `StorageServiceProvider` - File storage drivers
- `RealtimeServiceProvider` - Realtime communication
- `SearchServiceProvider` - Elasticsearch
- `ConsoleServiceProvider` - CLI commands
- And more...

### 2. Application Layer (`src/App/`)

The application code following Clean Architecture with strict layer separation.

#### Domain Layer (`App/Domain/`)

**Pure business logic** - No framework dependencies.

- **Entities** (`Entities/`): Plain PHP classes with readonly properties
  - Example: `User.php`, `Product.php`
- **Value Objects** (`ValueObjects/`): Immutable value objects
- **Repository Interfaces** (`Contracts/`): Persistence contracts
  - Example: `UserRepositoryInterface`, `ProductRepositoryInterface`

#### Application Layer (`App/Application/`)

**Use cases** - Business logic orchestration.

- **UseCases** (`UseCases/`): Command/Handler pattern
  - Commands: DTOs with data
  - Handlers: Business logic with auto-wired dependencies
  - Example: `CreateProductCommand` → `CreateProductHandler`
- **Observers** (`Observers/`): Domain event observers

#### Infrastructure Layer (`App/Infrastructure/`)

**External concerns** - Framework integrations.

- **Repository** (`Repository/`): Repository implementations
  - PDO-based repositories
  - In-memory repositories (testing)
- **Persistence** (`Persistence/`): ORM models (Active Record)
  - Example: `UserModel`, `ProductModel`
- **Auth** (`Auth/`): Authentication implementations
- **Services** (`Services/`): External service integrations
- **Export/Import** (`Export/`, `Import/`): Excel import/export
- **Mails** (`Mails/`): Email templates
- **Notifications** (`Notifications/`): Notification implementations
- **Observers** (`Observers/`): Model observers
- **Pipes** (`Pipes/`): Pipeline processors
- **Transformer** (`Transformer/`): Data transformers
- **Providers** (`Providers/`): Application service providers
  - `AppServiceProvider` - Application services
  - `RepositoryServiceProvider` - Repository bindings
  - `EventServiceProvider` - Event listeners
  - `RouteServiceProvider` - Route loading
  - `ScheduleServiceProvider` - Scheduled tasks

#### Presentation Layer (`App/Presentation/`)

**UI concerns** - HTTP interface.

- **Http/Controllers** (`Http/Controllers/`): MVC-style controllers
  - Example: `AppController`, `ProductController`
- **Http/Actions** (`Http/Actions/`): ADR-style single-purpose handlers
- **Http/Middleware** (`Http/Middleware/`): Application middleware
- **Http/Requests** (`Http/Requests/`): Form request validation
- **Console** (`Console/`): Application CLI commands
  - `Kernel.php` - Command registration
  - `Commands/` - Custom commands
- **Views** (`Views/`): PHP templates (Blade-like)

---

## Bootstrap Flow

### HTTP Request Flow

1. **Entry Point** (`public/index.php`)
   - Loads Composer autoloader
   - Starts session
   - Bootstraps application

2. **Bootstrap** (`bootstrap/app.php`)
   - Load environment variables
   - Handle exceptions
   - Create Application instance
   - Load helper functions
   - Load configuration
   - Register facades
   - Register service providers
   - Boot service providers (loads routes)

3. **Routing** (`Router::dispatch()`)
   - Match request to route
   - Build middleware pipeline
   - Execute middleware (in order)
   - Resolve controller/action via DI
   - Execute handler
   - Return response

### Console Command Flow

1. **Entry Point** (`console`)
   - Loads autoloader
   - Bootstraps application
   - Gets Console Application from container
   - Parses command line arguments
   - Executes command with DI

---

## Key Design Patterns

### 1. Service Provider Pattern

```php
class MyServiceProvider extends ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        // Bind services (don't resolve yet)
        $container->singleton(MyService::class, fn() => new MyService());
    }

    public function boot(ContainerInterface $container): void
    {
        // Safe to resolve services here
    }
}
```

### 2. Dependency Injection

```php
// Auto-wiring via type hints
$container->get(ProductsController::class); // Resolves dependencies automatically

// Method invocation with DI
$container->call([Controller::class, 'method'], ['param' => 'value']);
```

### 3. Command/Handler Pattern

```php
// Command - Simple DTO
class CreateProductCommand extends AbstractCommand {
    public function __construct(public string $title, public ?string $sku = null) {}
}

// Handler - Auto-wired dependencies
class CreateProductHandler extends AbstractHandler {
    public function __construct(private ProductRepository $repo) {}

    public function __invoke(CreateProductCommand $cmd): Product {
        return $this->repo->store(new Product(null, $cmd->title, $cmd->sku));
    }
}

// Dispatch
Bus::dispatch(new CreateProductCommand('Laptop', 'LAP-001'));
```

### 4. Repository Pattern

```php
// Domain Interface
interface ProductRepositoryInterface {
    public function store(Product $product): Product;
}

// Infrastructure Implementation
class PdoProductRepository implements ProductRepositoryInterface {
    // PDO implementation
}
```

### 5. Middleware Pipeline

```php
// Onion pattern - each middleware wraps the next
$pipeline = $coreHandler;
foreach (array_reverse($middlewareStack) as $middleware) {
    $pipeline = $middleware->wrap($pipeline);
}
```

---

## Configuration

Configuration files in `config/`:
- `app.php` - Application settings
- `database.php` - Database connections
- `cache.php` - Cache drivers
- `queue.php` - Queue drivers
- `realtime.php` - Realtime brokers/transports
- `search.php` - Elasticsearch settings
- `security.php` - Security settings (CSRF, CORS, rate limiting)
- `middleware.php` - Middleware groups and aliases
- And more...

---

## Testing

Test structure in `tests/`:
- **Unit/** - Unit tests (isolated components)
- **Feature/** - Feature tests (HTTP requests)
- **Integration/** - Integration tests (database, external services)
- **Performance/** - Performance benchmarks

Run tests:
```bash
composer test                    # Run all tests
composer test:coverage           # With coverage
composer test:filter TestName    # Filter tests
```

---

## Key Features Summary

### Core Framework
✅ Dependency Injection Container (auto-wiring)
✅ Routing with middleware pipeline
✅ HTTP Request/Response abstraction
✅ Database ORM with relationships
✅ Query Builder
✅ Migrations
✅ Events & Listeners
✅ Command Bus
✅ Queue System
✅ Cache System
✅ Logging (PSR-3)
✅ Console Framework
✅ Validation
✅ Error Handling

### Security
✅ CSRF Protection
✅ XSS Protection
✅ SQL Injection Prevention
✅ Security Headers
✅ Rate Limiting
✅ Cookie Encryption
✅ Replay Attack Protection

### Advanced Features
✅ Authentication & Authorization
✅ File Storage (Local, S3, etc.)
✅ Mail System
✅ Notifications
✅ Realtime Broadcasting (WebSocket, SSE, Kafka, RabbitMQ)
✅ Elasticsearch Integration
✅ Task Scheduling
✅ Collections (Eager & Lazy)
✅ Excel Import/Export

---

## Performance Characteristics

- **Container Resolution**: O(1) singleton lookup, O(N) dependency depth
- **Route Matching**: O(1) with optimized regex compilation
- **Logger**: ~0.5ms per write (2000 writes/sec)
- **Router**: ~0.1ms per route match
- **ORM Query**: ~1-5ms per database query
- **Upsert**: 100x faster than separate insert/update

---

## Dependencies

### Required
- PHP >= 8.1
- Composer

### Optional PHP Extensions
- `ext-redis` - Redis cache/queue/broker
- `ext-pdo_mysql` / `ext-pdo_pgsql` / `ext-pdo_sqlite` - Database support
- `ext-pcntl` - Multi-process execution (Linux/macOS)

### Composer Dependencies
- `phpmailer/phpmailer` - Email
- `aws/aws-sdk-php` - S3 storage
- `php-amqplib/php-amqplib` - RabbitMQ
- `nmred/kafka-php` - Kafka
- `elasticsearch/elasticsearch` - Elasticsearch
- `phpoffice/phpspreadsheet` - Excel
- `openspout/openspout` - Excel streaming

---

## Development Workflow

### Setup
```bash
composer install
cp .env.example .env
php console key:generate
```

### Run Development Server
```bash
php -S localhost:8000 -t public
```

### Console Commands
```bash
php console list                    # List commands
php console cache:clear             # Clear cache
php console queue:work              # Process queue
php console migrate                 # Run migrations
php console search:reindex          # Reindex Elasticsearch
```

### Testing
```bash
composer test
composer test:coverage
```

---

## Code Conventions

- All files use `declare(strict_types=1)`
- Namespace structure mirrors directory structure
- One class per file
- Interfaces end with `Interface` suffix
- Abstract classes start with `Abstract` prefix
- Repository interfaces in Domain, implementations in Infrastructure
- Prefer composition over inheritance
- Program to interfaces, not implementations

---

## Documentation

Comprehensive documentation in `/docs`:
- Architecture guides
- Feature documentation (ORM, Validation, Logging, etc.)
- Security guides
- Testing guides
- Migration guides
- And more...

---

## Summary

Toporia is a **professional, production-ready PHP framework** that:
- Follows **Clean Architecture** principles
- Implements **SOLID** design patterns
- Provides **zero-dependency core** with optional integrations
- Offers **Laravel-compatible API** for familiar development
- Includes **comprehensive features** for modern web applications
- Maintains **strict separation** between framework and application layers
- Emphasizes **type safety** and **code quality**

The codebase is well-organized, thoroughly documented, and designed for scalability and maintainability.

