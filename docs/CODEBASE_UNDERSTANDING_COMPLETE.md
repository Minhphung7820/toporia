# Complete Codebase Understanding - Toporia Framework

## Executive Summary

**Toporia** is a professional PHP framework (v1.0.0) built on Clean Architecture and SOLID principles. It's a zero-dependency core framework with optional integrations, designed for building scalable web applications with strict separation of concerns.

**Key Characteristics:**
- **Language**: PHP 8.1+
- **Architecture**: Clean Architecture (4 layers: Domain, Application, Infrastructure, Presentation)
- **Patterns**: SOLID, Repository, Service Provider, Dependency Injection
- **Frontend**: Vue 3 + Vite (SPA)
- **Database**: Multi-connection support (MySQL, PostgreSQL, SQLite)
- **Message Brokers**: Redis, RabbitMQ, Kafka
- **Search**: Elasticsearch integration

---

## 1. Project Structure

```
toporia/
├── bootstrap/          # Application bootstrap files
│   ├── app.php         # Main bootstrap (loads env, config, providers)
│   └── helpers.php      # Global helper functions (50+ helpers)
├── config/             # Configuration files (app, database, cache, etc.)
├── database/           # Migrations, seeders, factories
├── docker/             # Docker configuration
├── docs/               # Comprehensive documentation (40+ markdown files)
├── public/             # Web server entry point (index.php)
├── resources/          # Frontend assets (Vue.js, CSS, JS)
├── routes/             # Route definitions (web.php, api.php)
├── scripts/            # Utility scripts
├── src/
│   ├── Framework/      # Core framework (zero dependencies)
│   └── App/            # Application code (Clean Architecture layers)
├── storage/            # Logs, cache, uploads
├── tests/              # PHPUnit tests (Unit, Feature, Integration, Performance)
├── vendor/             # Composer dependencies
├── console             # CLI entry point
├── composer.json       # PHP dependencies
├── package.json        # Node.js dependencies (Vite, Vue)
└── docker-compose.yml  # Docker services (MySQL, Redis, Kafka, etc.)
```

---

## 2. Clean Architecture Layers

### 2.1 Domain Layer (`src/App/Domain/`)
**Purpose**: Pure business logic, no framework dependencies

**Structure:**
- `Entities/` - Domain entities (e.g., `User.php` - immutable with readonly properties)
- `Contracts/Repository/` - Repository interfaces (e.g., `UserRepository`)
- `ValueObjects/` - Value objects for domain concepts
- `Contracts/Auth/` - Domain authentication interfaces

**Key Principles:**
- Entities are immutable (readonly properties)
- Use `with*()` methods for state changes (returns new instance)
- No framework dependencies
- Pure PHP classes

**Example:**
```php
// Domain Entity - Immutable
final class User implements FrameworkAuthenticatable
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $email,
        public readonly string $password,
        // ...
    ) {}

    public function withId(int $id): self { /* returns new instance */ }
}
```

### 2.2 Application Layer (`src/App/Application/`)
**Purpose**: Use cases and business services

**Structure:**
- `Services/` - Application services (e.g., `RegisterUserService`, `LoginService`)
- `UseCases/` - Use case handlers (currently empty, can be used for CQRS)
- `Rules/` - Custom validation rules
- `Observers/` - Model observers (registered in `config/observers.php`)

**Pattern**: Services orchestrate domain entities and repositories

**Example:**
```php
// Application Service
final class RegisterUserService
{
    public function __construct(
        private UserRepository $userRepository,
        private Validator $validator
    ) {}

    public function execute(array $data): array
    {
        // Validation, business logic, entity creation
    }
}
```

### 2.3 Infrastructure Layer (`src/App/Infrastructure/`)
**Purpose**: External concerns and framework integrations

**Structure:**
- `Repository/` - Repository implementations (e.g., `PdoUserRepository`)
- `Persistence/Models/` - ORM models (Active Record pattern)
- `Providers/` - Service providers (Domain, Route, Event, etc.)
- `Auth/` - Authentication implementations
- `Jobs/` - Queue jobs
- `Notifications/` - Notification implementations
- `Export/`, `Import/` - Excel import/export
- `Transformer/` - API response transformers

**Key Pattern**: Repository pattern bridges Domain and Infrastructure
- Domain defines `UserRepository` interface
- Infrastructure implements `PdoUserRepository` using ORM models
- Maps between ORM `UserModel` and Domain `User` entity

**Example:**
```php
// Infrastructure Repository Implementation
final class PdoUserRepository implements UserRepository
{
    public function findById(int $id): ?User
    {
        $model = UserModel::find($id);
        return $model ? $this->mapModelToEntity($model) : null;
    }

    private function mapModelToEntity(UserModel $model): User
    {
        // Maps ORM model to Domain entity
    }
}
```

### 2.4 Presentation Layer (`src/App/Presentation/`)
**Purpose**: HTTP interface and user interaction

**Structure:**
- `Http/Controllers/` - MVC controllers (e.g., `AuthController`)
- `Http/Middleware/` - HTTP middleware
- `Console/Commands/` - CLI commands
- `Views/` - PHP templates (for SSR if needed)

**Pattern**: Controllers delegate to Application Services

**Example:**
```php
// Presentation Controller
final class AuthController extends BaseController
{
    public function __construct(
        private RegisterUserService $registerService
    ) {}

    public function register(Request $request): void
    {
        $result = $this->registerService->execute($request->json());
        $this->json($result, 201);
    }
}
```

---

## 3. Framework Core (`src/Framework/`)

### 3.1 Foundation
- **Application** - Central application class, manages container and providers
- **ServiceProvider** - Base class for service providers
- **Bootstrap Classes** - Handle exceptions, load config, register providers

### 3.2 Container (`Container/`)
**PSR-11 inspired DI container with:**
- Auto-wiring via reflection
- Singleton pattern
- Contextual bindings
- Tagged bindings
- Circular dependency detection
- Method injection via `call()`

**Performance**: O(1) singleton lookup, O(N) dependency resolution

### 3.3 Routing (`Routing/`)
**Features:**
- RESTful HTTP verbs (GET, POST, PUT, PATCH, DELETE, ANY)
- Route parameters with regex (`{id}`, `{slug}`)
- Named routes for URL generation
- Route groups (prefix, middleware, namespace)
- Middleware pipeline
- Route caching

**Usage:**
```php
Route::get('/products/{id}', [ProductController::class, 'show'])
    ->name('products.show')
    ->middleware(['auth']);
```

### 3.4 HTTP (`Http/`)
- **Request** - PSR-7 inspired request abstraction
- **Response** - Response handling with JSON helpers
- **MiddlewarePipeline** - Middleware execution pipeline
- File upload handling
- Cookie management with encryption

### 3.5 Database (`Database/`)
**Multi-layer database system:**

1. **Connection Layer** (`Connection.php`, `DatabaseManager.php`)
   - Multi-connection support (MySQL, PostgreSQL, SQLite)
   - Connection pooling
   - Transaction management

2. **Query Builder** (`Query/`)
   - Fluent query builder
   - Automatic parameter binding (SQL injection prevention)
   - Raw queries support

3. **ORM** (`ORM/`)
   - Active Record pattern (`Model` class)
   - Relationships (HasOne, HasMany, BelongsTo, BelongsToMany)
   - Eager loading (prevents N+1 queries)
   - Relationship aggregates (withCount, withSum, etc.)
   - Bulk upsert (100x faster than separate queries)
   - Model hooks (creating, created, updating, updated, deleting, deleted)
   - Mass assignment protection (`$fillable`, `$guarded`)
   - Attribute casting
   - Global scopes

4. **Migrations** (`Migration/`)
   - Schema builder
   - Migration runner
   - Rollback support

**Example:**
```php
class ProductModel extends Model
{
    protected static string $table = 'products';
    protected static array $fillable = ['title', 'price'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryModel::class);
    }
}

// Usage
$products = ProductModel::with('category')
    ->where('price', '>', 100)
    ->orderBy('created_at', 'DESC')
    ->get();
```

### 3.6 Authentication & Authorization (`Auth/`)
- Session-based authentication
- Token-based authentication (API)
- Gate system (closure-based authorization)
- Policy classes (resource-based authorization)
- Password hashing (Bcrypt, Argon2id)
- Automatic hash algorithm migration

### 3.7 Bus (`Bus/`)
**Command/Query bus pattern:**
- Auto-wired handlers
- Queue support
- Batching
- Middleware support
- Chain commands

**Usage:**
```php
// Command
final class SendWelcomeEmail
{
    public function __construct(
        public readonly string $email,
        public readonly string $name
    ) {}
}

// Handler
final class SendWelcomeEmailHandler
{
    public function __invoke(SendWelcomeEmail $command): void
    {
        // Handle command
    }
}

// Dispatch
dispatch(new SendWelcomeEmail('user@example.com', 'John'));
```

### 3.8 Events (`Events/`)
- Priority-based event dispatcher (PSR-14 inspired)
- Event propagation control
- Event subscriber pattern
- Queued listeners

### 3.9 Queue (`Queue/`)
- Multiple drivers (Sync, Database, Redis)
- Delayed job execution
- Job retries with exponential backoff
- Failed job handling
- Queue worker with graceful shutdown

### 3.10 Cache (`Cache/`)
- Multi-driver (File, Redis, Memory)
- PSR-16 inspired interface
- `remember()` pattern for lazy caching
- Increment/decrement operations
- Forever caching

### 3.11 Logging (`Log/`)
- PSR-3 logger
- Multi-channel (Daily, Single, Stack, Syslog, Stderr)
- Daily file rotation (YYYY-MM-DD.log format)
- Auto-cleanup of old logs
- Placeholder interpolation (`{user_id}` syntax)
- Context data as structured JSON
- Thread-safe file locking

### 3.12 Realtime (`Realtime/`)
**Broadcasting system:**
- Multi-transport (WebSocket, SSE, Long-polling, Socket.IO)
- Broker drivers: Redis (fast fan-out), RabbitMQ (durable routing), Kafka (high-throughput replay)
- Auto topic/queue binding
- Batching, QoS, graceful shutdown

### 3.13 Search (`Search/`)
- Elasticsearch integration
- Reusable `SearchManager`
- Bulk indexing
- Queue-aware sync
- ORM trait for auto document updates
- Fluent query builder
- Console reindex command

### 3.14 Validation (`Validation/`)
- Form request validation
- 20+ built-in rules (required, email, unique, exists, min, max, regex)
- Database validation rules (unique, exists)
- Custom rule support

### 3.15 Security (`Security/`)
- CSRF protection
- XSS protection (input sanitization, output escaping)
- SQL injection prevention (parameterized queries)
- Security headers (CSP, HSTS, X-Frame-Options)
- Rate limiting
- Cookie encryption

### 3.16 Storage (`Storage/`)
- Multi-driver (Local, S3, DigitalOcean Spaces, MinIO)
- File upload handling with validation
- Hash-based filenames

### 3.17 Mail (`Mail/`)
- Multi-driver (SMTP, Log, Array)
- HTML email support
- Queue integration

### 3.18 Notification (`Notification/`)
- Multi-channel (Mail, Database, SMS, Slack, Broadcast)
- Notifiable trait for models
- Database notification storage
- Real-time WebSocket/SSE broadcast

### 3.19 Console (`Console/`)
- Professional CLI framework
- Command pattern
- Input parsing (arguments, options, flags)
- Colored output and formatted tables
- Interactive prompts
- Built-in commands (cache, queue, schedule, migrate, etc.)

### 3.20 Other Components
- **Translation** - Multi-language support
- **Session** - Session management
- **Hashing** - Password hashing
- **Observer** - Model observer pattern
- **Pipeline** - Pipeline pattern for processing
- **Support** - Collections, helpers, facades

---

## 4. Bootstrap Flow

### 4.1 HTTP Request Flow
```
1. public/index.php
   ├── Load autoloader (vendor/autoload.php)
   ├── Bootstrap application (bootstrap/app.php)
   │   ├── Load environment variables (.env)
   │   ├── Handle exceptions
   │   ├── Create Application instance
   │   ├── Load helper functions
   │   ├── Load configuration
   │   ├── Register facades
   │   ├── Register service providers
   │   └── Boot service providers (loads routes)
   └── Dispatch request via Router
       ├── Match route
       ├── Execute middleware pipeline
       └── Execute controller/action
```

### 4.2 Console Command Flow
```
1. console (CLI entry point)
   ├── Load autoloader
   ├── Bootstrap application
   └── Run console application
       ├── Parse command arguments
       └── Execute command handler
```

### 4.3 Service Provider Registration
Service providers are registered in `bootstrap/app.php` via `RegisterProviders::bootstrap()`:

1. **Framework Providers** (auto-loaded from `FrameworkServiceProvider`)
   - ConfigServiceProvider
   - HttpServiceProvider
   - RoutingServiceProvider
   - DatabaseServiceProvider
   - AuthServiceProvider
   - CacheServiceProvider
   - QueueServiceProvider
   - LogServiceProvider
   - ... (20+ framework providers)

2. **Application Providers** (in `RegisterProviders.php`)
   - DomainServiceProvider (repositories, auth)
   - AppServiceProvider (application services)
   - EventServiceProvider (event listeners)
   - RouteServiceProvider (loads routes)
   - ScheduleServiceProvider (scheduled tasks)

**Provider Lifecycle:**
1. `register()` - Bind services (don't resolve yet)
2. `boot()` - Safe to resolve services (routes loaded here)

---

## 5. Key Patterns & Conventions

### 5.1 Dependency Injection
- Constructor injection (preferred)
- Method injection via `$container->call()`
- Auto-wiring via type hints
- Interface-based design

### 5.2 Repository Pattern
- Domain defines interfaces (`UserRepository`)
- Infrastructure implements (`PdoUserRepository`)
- Maps between ORM models and Domain entities

### 5.3 Service Provider Pattern
- Modular service registration
- Lazy loading
- Two-phase: register → boot

### 5.4 Command Bus Pattern
- Commands are simple DTOs
- Handlers follow naming: `CommandName` → `CommandNameHandler`
- Auto-wired via container
- Queue support

### 5.5 Facade Pattern
- Static accessors via `ServiceAccessor`
- Examples: `Route::`, `Auth::`, `Log::`, `Cache::`

### 5.6 Code Conventions
- All files use `declare(strict_types=1)`
- Namespace structure mirrors directory structure
- One class per file
- Interfaces end with `Interface` suffix
- Abstract classes start with `Abstract` prefix
- Domain entities are immutable (readonly properties)
- Use `with*()` methods for state changes

---

## 6. Technology Stack

### 6.1 Backend
- **PHP**: 8.1+
- **Framework**: Toporia (custom, zero-dependency core)
- **Database**: MySQL 8.0, PostgreSQL, SQLite
- **Cache/Queue**: Redis 7
- **Message Brokers**:
  - Redis (Pub/Sub)
  - RabbitMQ 3 (AMQP)
  - Kafka (high-throughput)

### 6.2 Frontend
- **Vue.js**: 3.5.24
- **Vite**: 7.2.2 (build tool)
- **Vue Router**: 4.6.3
- **Pinia**: 3.0.4 (state management)

### 6.3 External Services
- **Elasticsearch**: 8.13.0 (search engine)
- **PHPMailer**: 7.0 (email)
- **AWS SDK**: 3.359 (S3 storage)
- **OpenSpout**: 4.28 (Excel export)
- **PHPSpreadsheet**: 5.2 (Excel import/export)

### 6.4 Development Tools
- **PHPUnit**: 11.0 (testing)
- **Mockery**: 1.6 (mocking)
- **Faker**: 1.24 (test data)
- **PHPStan**: Static analysis

### 6.5 Infrastructure
- **Docker Compose**: Multi-container setup
- **Nginx**: Web server
- **MySQL**: Database
- **Redis**: Cache/Queue
- **Kafka + Zookeeper**: Message broker
- **RabbitMQ**: AMQP broker
- **Elasticsearch**: Search engine

---

## 7. Configuration

### 7.1 Environment Variables
Loaded from `.env` file:
- `APP_NAME`, `APP_ENV`, `APP_DEBUG`
- `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `REDIS_HOST`, `REDIS_PORT`
- `KAFKA_BROKERS`
- `RABBITMQ_HOST`, `RABBITMQ_PORT`
- `ELASTICSEARCH_HOST`
- And more...

### 7.2 Configuration Files (`config/`)
- `app.php` - Application settings
- `database.php` - Database connections
- `cache.php` - Cache configuration
- `queue.php` - Queue configuration
- `realtime.php` - Broadcasting configuration
- `search.php` - Elasticsearch configuration
- `middleware.php` - Middleware groups and aliases
- `observers.php` - Model observers
- And more...

### 7.3 Routes
- `routes/web.php` - Web routes (SPA fallback)
- `routes/api.php` - API routes (prefixed with `/api`)

---

## 8. Testing

### 8.1 Test Structure
```
tests/
├── Unit/           # Unit tests (isolated components)
├── Feature/        # Feature tests (HTTP endpoints)
├── Integration/    # Integration tests (database, external services)
└── Performance/    # Performance benchmarks
```

### 8.2 Test Suites
Configured in `phpunit.xml`:
- Unit
- Feature
- Integration
- Performance

### 8.3 Running Tests
```bash
composer test                    # All tests
composer test:unit               # Unit tests only
composer test:feature            # Feature tests only
composer test:coverage           # With coverage report
```

---

## 9. Development Workflow

### 9.1 Setup
```bash
composer install
cp .env.example .env
php console key:generate
php console migrate
```

### 9.2 Development Server
```bash
# PHP built-in server
php -S localhost:8000 -t public

# Or use Docker Compose
docker compose up -d
```

### 9.3 Console Commands
```bash
php console list                 # List all commands
php console migrate              # Run migrations
php console queue:work           # Start queue worker
php console cache:clear         # Clear cache
php console route:list          # List routes
php console search:reindex       # Reindex Elasticsearch
```

### 9.4 Frontend Development
```bash
npm install
npm run dev                      # Vite dev server
npm run build                    # Production build
```

---

## 10. Key Features Summary

### 10.1 Architecture
✅ Clean Architecture (4 layers)
✅ SOLID principles
✅ Dependency Injection
✅ Repository pattern
✅ Service Provider pattern

### 10.2 Framework Core
✅ Zero-dependency core
✅ PSR-11 container
✅ PSR-7 inspired HTTP
✅ PSR-14 inspired events
✅ PSR-3 logging

### 10.3 Database & ORM
✅ Multi-connection support
✅ Fluent query builder
✅ Active Record ORM
✅ Relationships (HasOne, HasMany, BelongsTo, BelongsToMany)
✅ Eager loading
✅ Bulk upsert
✅ Migrations

### 10.4 Features
✅ Authentication & Authorization
✅ Caching (File, Redis, Memory)
✅ Queue system (Sync, Database, Redis)
✅ Real-time broadcasting (Redis, RabbitMQ, Kafka)
✅ Elasticsearch integration
✅ File storage (Local, S3, DigitalOcean Spaces, MinIO)
✅ Email system
✅ Notifications
✅ Validation
✅ Security (CSRF, XSS, SQL injection prevention)
✅ Rate limiting
✅ Task scheduling
✅ Collections (eager + lazy)
✅ Translation system

### 10.5 Developer Experience
✅ 50+ helper functions
✅ Static facades
✅ Comprehensive documentation (40+ markdown files)
✅ Type safety (PHP 8.1+)
✅ PHPDoc comments
✅ PSR-12 coding standards

---

## 11. Application-Specific Code

### 11.1 Domain
- **User Entity** - Immutable user domain model
- **UserRepository Interface** - Domain contract

### 11.2 Application Services
- `RegisterUserService` - User registration
- `LoginService` - User authentication
- `ForgotPasswordService` - Password reset request
- `ResetPasswordService` - Password reset with token
- `ChangePasswordService` - Password change for authenticated users

### 11.3 Infrastructure
- `PdoUserRepository` - Database repository implementation
- `RepositoryUserProvider` - Auth provider bridge
- `TransactionManager` - Transaction management
- `UnitOfWork` - Unit of Work pattern

### 11.4 Presentation
- `AuthController` - API authentication endpoints
- `AppController` - SPA fallback route
- Console commands for Excel import/export, Kafka consumers, etc.

---

## 12. Documentation

Comprehensive documentation in `/docs`:
- Architecture guides
- Feature documentation (ORM, Bus, Validation, etc.)
- Security guides
- Testing guides
- Migration guides
- And more (40+ markdown files)

---

## 13. Performance Characteristics

**Benchmarks** (on modest hardware):
- Logger: ~0.5ms per write (2000 writes/sec)
- Router: ~0.1ms per route match
- Container: ~0.05ms per resolution
- ORM Query: ~1-5ms per database query
- Upsert: 100x faster than separate insert/update

**Optimizations:**
- O(1) container singleton lookup
- O(1) route matching (optimized regex)
- Lazy loading
- Query optimization (eager loading prevents N+1)
- File locking (thread-safe)
- Opcode caching compatible

---

## 14. Security Features

✅ **CSRF Protection** - Token-based validation
✅ **XSS Protection** - Input sanitization, output escaping
✅ **SQL Injection Prevention** - Parameterized queries
✅ **Security Headers** - CSP, HSTS, X-Frame-Options
✅ **Rate Limiting** - Configurable request throttling
✅ **Cookie Encryption** - Automatic encryption/decryption
✅ **Password Hashing** - Bcrypt, Argon2id with auto-migration
✅ **Session Security** - HttpOnly cookies, secure flags

---

## 15. Conclusion

Toporia is a **professional, production-ready PHP framework** built with:
- **Clean Architecture** for maintainability
- **SOLID principles** for extensibility
- **Zero-dependency core** for flexibility
- **Comprehensive features** for rapid development
- **Enterprise-grade** components (Kafka, Elasticsearch, etc.)
- **Modern tooling** (Vue 3, Vite, Docker)

The codebase is well-organized, thoroughly documented, and follows industry best practices. It's designed for building scalable web applications while maintaining strict separation of concerns between framework and application layers.

---

**Version**: 1.0.0
**Last Updated**: November 11, 2025
**Framework**: Toporia
**Developer**: TMP DEV

