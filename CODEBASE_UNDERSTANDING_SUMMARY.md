# Toporia Framework - Complete Codebase Understanding

## Executive Summary

**Toporia Framework** is a professional-grade PHP framework (v1.0.0) built on **Clean Architecture** and **SOLID principles**. The codebase demonstrates enterprise-level engineering with:

- **~565 framework files** in `src/Framework/`
- **~94 application files** in `src/App/`
- **Zero-dependency core** framework
- **Vue 3 SPA** frontend with Vite
- **Full Docker setup** with 8 services (MySQL, Redis, Kafka, RabbitMQ, Elasticsearch, etc.)

---

## Architecture Overview

### Clean Architecture Layers

The codebase follows strict Clean Architecture with 4 layers:

1. **Framework Layer** (`src/Framework/`)
   - Zero dependencies on application code
   - Provides reusable components (Container, Router, ORM, etc.)
   - Interface-based design
   - Service Provider pattern for modular registration

2. **Infrastructure Layer** (`src/App/Infrastructure/`)
   - Concrete implementations of domain contracts
   - Repository implementations
   - External service integrations (Email, Storage, etc.)
   - Queue jobs, observers, transformers

3. **Domain Layer** (`src/App/Domain/`)
   - Business entities (e.g., `Product.php`)
   - Value objects
   - Repository interfaces (contracts)
   - Domain logic

4. **Application Layer** (`src/App/Application/`)
   - Use cases and application services
   - Business logic coordination
   - Validation rules
   - Domain observers

5. **Presentation Layer** (`src/App/Presentation/`)
   - HTTP controllers
   - Console commands
   - Views (PHP templates)

---

## Request Lifecycle

### HTTP Request Flow

```
1. public/index.php
   ├─ Starts session
   ├─ Loads Composer autoloader
   └─ Calls bootstrap/app.php

2. bootstrap/app.php (Bootstrap Process)
   ├─ LoadEnvironmentVariables::bootstrap()
   │   └─ Parses .env file into $_ENV
   ├─ HandleExceptions::bootstrap()
   │   └─ Registers error/exception handlers
   ├─ new Application($basePath)
   │   └─ Creates Container instance
   │   └─ Registers Application itself in container
   ├─ Load Helper Functions
   │   └─ bootstrap/helpers.php
   ├─ LoadConfiguration::bootstrap($app)
   │   └─ Loads all config files from config/
   ├─ RegisterFacades::bootstrap($app)
   │   └─ Sets container reference for ServiceAccessor
   ├─ RegisterProviders::bootstrap($app)
   │   └─ Registers all service providers from config/app.php
   └─ BootProviders::bootstrap($app)
       └─ Calls boot() on each provider
       └─ Routes are loaded here (RoutingServiceProvider)

3. Router::dispatch()
   ├─ Request created from $_SERVER superglobal
   ├─ Router matches route from RouteCollection
   ├─ MiddlewarePipeline builds middleware stack
   ├─ Executes middleware (before hooks)
   ├─ Resolves controller via Container (auto-wiring)
   ├─ Calls controller method with route parameters
   ├─ Executes middleware (after hooks)
   └─ Sends response to client
```

---

## Core Components

### 1. Dependency Injection Container

**Location**: `src/Framework/Container/Container.php`

**Key Features**:
- **Auto-wiring**: Uses Reflection to resolve constructor dependencies
- **Singleton Support**: Cached instances for shared services
- **Circular Dependency Detection**: Prevents infinite loops
- **Contextual Bindings**: Different implementations for same interface
- **Tagged Bindings**: Group related services
- **Method Injection**: Inject dependencies into method calls via `call()`

**Performance**:
- Reflection caching (`$reflectionClassCache`, `$reflectionMethodCache`)
- Singleton lookup: O(1) via `$instances` array
- Dependency resolution: O(N) where N = dependency depth

**Example**:
```php
// Singleton binding
$container->singleton(DatabaseInterface::class, MySQLDatabase::class);

// Contextual binding
$container->when(OrderController::class)
    ->needs(DatabaseInterface::class)
    ->give(PostgreSQLDatabase::class);

// Method injection
$container->call([$controller, 'process'], ['userId' => 123]);
```

### 2. Router System

**Location**: `src/Framework/Routing/Router.php`

**Key Features**:
- Fluent API for route definition
- Route parameters with regex validation (`{id}`, `{slug}`)
- Named routes for URL generation
- Route groups with shared prefix, middleware, namespace
- Middleware pipeline (Onion pattern)
- Controller resolution via Container (auto-wiring)

**Route Matching Algorithm**:
1. Normalize path (remove leading/trailing slashes)
2. Extract route parameters using regex
3. Match HTTP method
4. Validate parameter constraints (if any)
5. Return route + extracted parameters

### 3. Database & ORM

#### Query Builder (`src/Framework/Database/Query/QueryBuilder.php`)

**Features**:
- Fluent interface for query building
- Automatic parameter binding (prevents SQL injection)
- Join support (INNER, LEFT, RIGHT, FULL OUTER)
- Subqueries with proper scoping
- Unions, locks (SELECT FOR UPDATE/SHARE)

#### ORM Model (`src/Framework/Database/ORM/Model.php`)

**Features**:
- **Active Record Pattern**: Models represent database rows
- **Relationships**: HasOne, HasMany, BelongsTo, BelongsToMany, etc.
- **Eager Loading**: Prevents N+1 queries via `with()`
- **Mass Assignment Protection**: Fillable/guarded attributes
- **Attribute Casting**: Automatic type conversion
- **Global Scopes**: Automatic query filters
- **Model Events**: creating, created, updating, updated, deleting, deleted
- **Collections**: Rich collection methods (map, filter, groupBy, etc.)

**Model Lifecycle**:
```
1. new Model() → creating event
2. $model->save() → saving event
3. Database INSERT → created event → saved event
4. $model->update() → updating event
5. Database UPDATE → updated event → saved event
6. $model->delete() → deleting event → Database DELETE → deleted event
```

**Eager Loading Example**:
```php
// Without eager loading (N+1 problem)
$users = User::all();
foreach ($users as $user) {
    echo $user->posts()->count(); // 1 query per user = N+1 queries
}

// With eager loading (2 queries total)
$users = User::with('posts')->all();
foreach ($users as $user) {
    echo $user->posts->count(); // Already loaded, no query
}
```

### 4. Service Providers

**27 Framework Service Providers** located in `src/Framework/Providers/`:

1. **RoutingServiceProvider** - Loads routes from `routes/` directory
2. **EventServiceProvider** - Registers event listeners
3. **HttpServiceProvider** - HTTP request/response services
4. **ConfigServiceProvider** - Configuration loading
5. **AuthServiceProvider** - Authentication services
6. **DatabaseServiceProvider** - Database connections
7. **ViteServiceProvider** - Vite integration
8. **UrlServiceProvider** - URL generation
9. **TranslationServiceProvider** - Translation services
10. **SecurityServiceProvider** - Security services
11. **StorageServiceProvider** - File storage
12. **SessionServiceProvider** - Session management
13. **SearchServiceProvider** - Search services (Elasticsearch)
14. **ScheduleServiceProvider** - Task scheduling
15. **RealtimeServiceProvider** - Realtime messaging
16. **QueueServiceProvider** - Queue system
17. **ProcessServiceProvider** - Process management
18. **ObserverServiceProvider** - Model observers
19. **NotificationServiceProvider** - Notifications
20. **MailServiceProvider** - Mail services
21. **HttpClientServiceProvider** - HTTP client
22. **LogServiceProvider** - Logging (PSR-3)
23. **HashServiceProvider** - Password hashing
24. **DateTimeServiceProvider** - Date/time utilities
25. **ConsoleServiceProvider** - CLI commands
26. **CacheServiceProvider** - Cache services
27. **BusServiceProvider** - Command bus

**Service Provider Lifecycle**:
```
1. register() → Bind services to container
2. boot() → Initialize services, load routes, register listeners
```

### 5. Event System

**Location**: `src/Framework/Events/`

**Features**:
- Priority-based event dispatchers
- Event propagation control
- PSR-14 inspired interface design
- Event subscriber pattern

**Event Flow**:
1. Event dispatched via `$dispatcher->dispatch($event)`
2. Listeners sorted by priority (higher first)
3. Each listener called with event object
4. Listener can stop propagation
5. Returns modified event

### 6. Queue System

**Location**: `src/Framework/Queue/`

**Features**:
- Multiple drivers: Sync, Database, Redis, RabbitMQ
- Job interface with dependency injection
- Failed job handling
- Queue workers with graceful shutdown

**Queue Architecture**:
```
Producer (anywhere) → Queue Driver → Queue Storage
                                      ↓
Consumer (CLI worker) ← Queue Driver ← Queue Storage
```

### 7. Authentication & Authorization

**Location**: `src/Framework/Auth/`

**Components**:
- **AuthManager**: Manages multiple authentication guards
- **Guards**: Session, Token, Personal Access Token
- **Gate System**: Closure-based authorization
- **Policies**: Resource-based authorization classes

### 8. Security Features

- **CSRF Protection**: Token-based validation with Laravel-compatible cookies
- **XSS Protection**: HTML escaping service
- **Replay Attack Protection**: Nonce-based protection
- **Rate Limiting**: Cache-based rate limiting for API
- **Security Headers**: CSP, HSTS, X-Frame-Options
- **Cookie Encryption**: Encrypted cookies with Laravel compatibility
- **Password Hashing**: Bcrypt and Argon2id support

---

## Application Structure

### Domain Layer (`src/App/Domain/`)

**Example**: `Product.php`
```php
final class Product extends Model
{
    protected static string $table = 'products';
    protected static array $fillable = ['title', 'description', 'price', 'stock'];
    protected static array $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
```

### Presentation Layer (`src/App/Presentation/`)

**Controllers**:
- `AppController` - Vue SPA controller (serves `app.php` view)
- `Api/AuthController` - Authentication API endpoints
- `Api/CsrfCookieController` - CSRF cookie endpoint

**Routes**:
- `routes/web.php` - SPA fallback route: `Route::any('/{any}', [AppController::class, 'index'])`
- `routes/api.php` - API routes (authentication endpoints)

---

## Frontend Architecture

### Vue 3 SPA Structure

**Location**: `resources/js/`

**Components**:
- `app.js` - Vue app entry point
- `App.vue` - Root component
- `router/index.js` - Vue Router configuration
- `pages/` - Page components (Home, Login, Register, etc.)
- `stores/` - Pinia stores (state management)
- `services/` - API services

**Build System**:
- **Vite**: Modern build tool with HMR
- **Vue 3**: Composition API
- **Vue Router**: Client-side routing
- **Pinia**: State management

**SPA Routing Pattern**:
```
All routes → AppController → app.php view → Vue Router handles client-side
```

---

## Docker Setup

### Services (docker-compose.yml)

1. **app** - PHP-FPM application container
2. **nginx** - Web server (port 8000)
3. **mysql** - MySQL 8.0 database (port 3307)
4. **redis** - Redis cache/queue (port 6379)
5. **kafka** - Apache Kafka for realtime messaging (port 9092)
6. **rabbitmq** - RabbitMQ message broker (ports 5672, 15672)
7. **elasticsearch** - Elasticsearch for search (ports 9200, 9300)
8. **zookeeper** - ZooKeeper for Kafka (port 2181)

---

## Testing Structure

**Location**: `tests/`

**Test Suites**:
- `Unit/` - Unit tests (isolated components)
- `Feature/` - Feature tests (HTTP endpoints)
- `Integration/` - Integration tests (full stack)
- `Performance/` - Performance tests

**Test Infrastructure**:
- Uses in-memory SQLite for fast tests
- Transaction-based (rollback after each test)
- HTTP request/response helpers
- Container mocking
- Event mocking
- Queue faking

---

## Key Design Patterns

1. **Service Provider Pattern** - Modular service registration
2. **Repository Pattern** - Data access abstraction
3. **Factory Pattern** - Object creation (Model factories)
4. **Observer Pattern** - Model events, event system
5. **Strategy Pattern** - Multiple guards, drivers (Cache, Queue, Storage)
6. **Command Pattern** - Console commands, queue jobs
7. **Facade Pattern** - Static accessors (Route, Log, Auth, etc.)
8. **Dependency Injection** - Constructor and method injection
9. **Chain of Responsibility** - Middleware pipeline
10. **Active Record Pattern** - ORM models

---

## Performance Optimizations

1. **Container**: Reflection caching, singleton instance caching
2. **Database**: Eager loading (prevents N+1 queries), query caching, bulk operations
3. **Router**: Route caching, fast route matching algorithm
4. **Cache Layer**: Multiple drivers (File, Redis, Array), cache tags
5. **Queue System**: Async job processing, batch job processing

---

## Configuration Files

**Location**: `config/`

**24 Configuration Files**:
- `app.php` - Application configuration
- `auth.php` - Authentication configuration
- `cache.php` - Cache configuration
- `database.php` - Database connections
- `filesystems.php` - Storage configuration
- `hashing.php` - Password hashing
- `http.php` - HTTP configuration
- `kafka.php` - Kafka configuration
- `logging.php` - Logging configuration
- `mail.php` - Mail configuration
- `middleware.php` - Middleware groups and aliases
- `notification.php` - Notification configuration
- `queue.php` - Queue configuration
- `realtime.php` - Realtime messaging
- `search.php` - Search configuration
- `security.php` - Security settings
- `session.php` - Session configuration
- `translation.php` - Translation configuration
- `vite.php` - Vite configuration
- And more...

---

## Key Files Reference

### Bootstrap
- `public/index.php` - HTTP entry point
- `bootstrap/app.php` - Application bootstrap
- `console` - CLI entry point

### Framework Core
- `src/Framework/Foundation/Application.php` - Application container
- `src/Framework/Container/Container.php` - DI container
- `src/Framework/Routing/Router.php` - HTTP router
- `src/Framework/Database/ORM/Model.php` - ORM base class

### Application
- `src/App/Presentation/Http/Controllers/` - HTTP controllers
- `src/App/Application/Services/` - Application services
- `src/App/Domain/Entities/` - Domain entities
- `src/App/Infrastructure/Repository/` - Repository implementations

---

## Development Workflow

### Backend Development
1. Define domain entities in `src/App/Domain/`
2. Create application services in `src/App/Application/`
3. Implement repositories in `src/App/Infrastructure/`
4. Create controllers in `src/App/Presentation/`
5. Define routes in `routes/`

### Frontend Development
1. Create Vue components in `resources/js/pages/`
2. Define routes in `resources/js/router/`
3. Use Pinia stores for state management
4. Build with Vite: `npm run dev`

### Database Development
1. Create migrations in `database/migrations/`
2. Create seeders in `database/seeders/`
3. Create factories in `database/factories/`
4. Run migrations: `php console migrate`
5. Run seeders: `php console db:seed`

### Testing
1. Write tests in `tests/Unit/`, `tests/Feature/`, etc.
2. Run tests: `composer test`
3. Run specific suite: `composer test:unit`

---

## Code Quality & Standards

### PSR Compliance
- **PSR-3**: Logger interface (implemented)
- **PSR-7**: HTTP messages (inspired)
- **PSR-11**: Container interface (implemented)
- **PSR-12**: Coding style (followed)
- **PSR-14**: Event dispatcher (inspired)

### Code Organization
- **Namespaces**: Clear namespace hierarchy
- **Type Hints**: Full PHP 8.1+ type hints
- **Strict Types**: `declare(strict_types=1)` everywhere
- **PHPDoc**: Complete API documentation
- **SOLID**: All principles applied throughout

---

## Summary

The Toporia Framework codebase demonstrates:

✅ **Enterprise-Grade Architecture**: Clean Architecture with strict layer separation
✅ **SOLID Principles**: Applied consistently throughout
✅ **Performance Optimizations**: Reflection caching, eager loading, connection pooling
✅ **Developer Experience**: Laravel-compatible API, fluent interfaces, helpful errors
✅ **Testability**: Comprehensive testing infrastructure
✅ **Extensibility**: Service providers, interfaces, pluggable drivers
✅ **Security**: CSRF, XSS protection, rate limiting, secure headers
✅ **Modern PHP**: PHP 8.1+ features, type hints, strict types

This is a **production-ready framework** built with professional software engineering practices.

---

**Document Version**: 1.0.0
**Last Updated**: 2025-01-10
**Framework Version**: 1.0.0

