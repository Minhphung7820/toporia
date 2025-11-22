# Toporia Framework - Comprehensive Codebase Understanding

## 📋 Executive Summary

**Toporia Framework** is a professional-grade PHP framework (v1.0.0) built on **Clean Architecture** principles with strict adherence to **SOLID** design patterns. The codebase demonstrates enterprise-level engineering with ~565 framework files and ~94 application files, organized into clear architectural layers.

This document provides a deep understanding of the codebase structure, implementation patterns, and architectural decisions beyond the basic overview.

---

## 🏗️ Architecture Deep Dive

### Clean Architecture Layers - Detailed

#### 1. **Framework Layer** (`src/Framework/`)
The foundation layer providing reusable components with zero dependencies on application code.

**Key Characteristics:**
- **Zero Dependencies**: Core framework has no external dependencies (except PHP extensions)
- **Interface-Based**: All components depend on interfaces, not concrete implementations
- **Lazy Loading**: Services are loaded on-demand for performance
- **Service Provider Pattern**: Modular service registration and bootstrapping

#### 2. **Infrastructure Layer** (`src/App/Infrastructure/`)
Concrete implementations of domain contracts and external service integrations.

**Key Components:**
- **Persistence**: Database repository implementations
- **External Services**: Email, notifications, storage integrations
- **Jobs**: Queue job implementations
- **Observers**: Model observer implementations

#### 3. **Domain Layer** (`src/App/Domain/`)
Business entities and value objects representing core business logic.

**Key Components:**
- **Entities**: Business models (e.g., `Product.php`)
- **Value Objects**: Immutable value objects
- **Contracts**: Repository interfaces and domain contracts

#### 4. **Application Layer** (`src/App/Application/`)
Use cases and application services coordinating domain objects.

**Key Components:**
- **Services**: Application service layer
- **UseCases**: Business use case implementations
- **Rules**: Validation rules
- **Observers**: Domain event observers

#### 5. **Presentation Layer** (`src/App/Presentation/`)
HTTP controllers, console commands, and views handling external interfaces.

**Key Components:**
- **Http/Controllers**: HTTP request handlers
- **Console**: CLI command handlers
- **Views**: Presentation templates

---

## 🔄 Request Lifecycle - Detailed Flow

### HTTP Request Flow (Step-by-Step)

```
1. public/index.php (Entry Point)
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
   │   └─ src/Framework/Support/helpers.php
   ├─ LoadConfiguration::bootstrap($app)
   │   └─ Loads all config files from config/
   │   └─ Stores in container under 'config' key
   ├─ RegisterFacades::bootstrap($app)
   │   └─ Sets container reference for ServiceAccessor
   │   └─ Enables static facade access (Route::get(), etc.)
   ├─ RegisterProviders::bootstrap($app)
   │   └─ Registers all service providers from config/app.php
   │   └─ Calls register() on each provider
   └─ BootProviders::bootstrap($app)
       └─ Calls boot() on each provider
       └─ Routes are loaded here (RoutingServiceProvider)

3. Router::dispatch()
   ├─ Request created from $_SERVER superglobal
   ├─ Response object initialized
   ├─ Router matches route from RouteCollection
   │   └─ Extracts route parameters ({id} → actual values)
   ├─ MiddlewarePipeline builds middleware stack
   │   └─ Wraps each middleware around next layer (Onion pattern)
   ├─ Executes middleware (before hooks)
   ├─ Resolves controller via Container
   │   └─ Auto-wires constructor dependencies
   ├─ Calls controller method with route parameters
   ├─ Executes middleware (after hooks)
   └─ Sends response to client
```

### Key Design Patterns in Request Flow

1. **Service Provider Pattern**: Modular service registration
2. **Dependency Injection**: Auto-wiring via Container
3. **Middleware Pipeline**: Chain of Responsibility pattern
4. **Facade Pattern**: Static accessors (Route, Log, etc.)
5. **Strategy Pattern**: Multiple guards, drivers, etc.

---

## 🧩 Core Components - Detailed Analysis

### 1. Container (Dependency Injection)

**Location**: `src/Framework/Container/Container.php`

**Key Features:**
- **Auto-wiring**: Uses Reflection to resolve constructor dependencies
- **Singleton Support**: Cached instances for shared services
- **Circular Dependency Detection**: Prevents infinite loops
- **Contextual Bindings**: Different implementations for same interface
- **Tagged Bindings**: Group related services
- **Extending Bindings**: Modify resolved instances
- **Method Injection**: Inject dependencies into method calls via `call()`

**Performance Optimizations:**
- Reflection caching (`$reflectionClassCache`, `$reflectionMethodCache`)
- Singleton lookup: O(1) via `$instances` array
- Dependency resolution: O(N) where N = dependency depth

**Example Usage:**
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

**Key Features:**
- **Fluent API**: Chainable route definition
- **Route Parameters**: Extract `{id}`, `{slug}` with regex validation
- **Named Routes**: Generate URLs via route names
- **Route Groups**: Shared prefix, middleware, namespace
- **Middleware Pipeline**: Onion pattern execution
- **Controller Resolution**: Auto-wire via Container

**Route Matching Algorithm:**
1. Normalize path (remove leading/trailing slashes)
2. Extract route parameters using regex
3. Match HTTP method
4. Validate parameter constraints (if any)
5. Return route + extracted parameters

**Middleware Execution:**
- Built in reverse order (wraps from inside out)
- Executes before hooks first, then controller, then after hooks
- Each middleware wraps the next layer (Onion pattern)

### 3. Database & ORM

#### Query Builder (`src/Framework/Database/Query/QueryBuilder.php`)

**Key Features:**
- **Fluent Interface**: Chainable query building
- **Automatic Parameter Binding**: Prevents SQL injection
- **Join Support**: INNER, LEFT, RIGHT, FULL OUTER joins
- **Subqueries**: Nested queries with proper scoping
- **Unions**: Combine multiple queries
- **Lock Support**: SELECT FOR UPDATE/SHARE

**Query Building Process:**
1. Build WHERE clauses (supports nested conditions)
2. Build JOIN clauses
3. Build ORDER BY, GROUP BY, HAVING
4. Build LIMIT/OFFSET
5. Compile SQL with parameter binding
6. Execute via PDO connection

#### ORM Model (`src/Framework/Database/ORM/Model.php`)

**Key Features:**
- **Active Record Pattern**: Models represent database rows
- **Relationships**: HasOne, HasMany, BelongsTo, BelongsToMany, etc.
- **Eager Loading**: Prevents N+1 queries via `with()`
- **Mass Assignment Protection**: Fillable/guarded attributes
- **Attribute Casting**: Automatic type conversion
- **Global Scopes**: Automatic query filters
- **Model Events**: creating, created, updating, updated, deleting, deleted
- **Collections**: Rich collection methods (map, filter, groupBy, etc.)

**Model Lifecycle:**
```
1. new Model() → creating event
2. $model->save() → saving event → saving event
3. Database INSERT → created event → saved event
4. $model->update() → updating event → updating event
5. Database UPDATE → updated event → saved event
6. $model->delete() → deleting event → Database DELETE → deleted event
```

#### Relationships (`src/Framework/Database/ORM/Relations/`)

**Base Relation Class** (`Relation.php`):
- Provides common functionality for all relationships
- Handles eager loading constraints
- Manages foreign/local key mapping

**Relationship Types:**
1. **HasOne**: One-to-one (parent → child)
2. **HasMany**: One-to-many (parent → children)
3. **BelongsTo**: Many-to-one (child → parent)
4. **BelongsToMany**: Many-to-many (via pivot table)
5. **HasOneThrough**: One-to-one through intermediate
6. **HasManyThrough**: One-to-many through intermediate
7. **MorphOne/MorphMany**: Polymorphic relationships
8. **MorphTo**: Inverse polymorphic

**Eager Loading Mechanism:**
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

### 4. Event System

**Location**: `src/Framework/Events/`

**Key Features:**
- **Priority-Based**: Listeners can have priorities
- **Event Propagation**: Can stop event propagation
- **PSR-14 Inspired**: Event dispatcher interface
- **Subscriber Pattern**: Register multiple listeners at once

**Event Flow:**
1. Event dispatched via `$dispatcher->dispatch($event)`
2. Listeners sorted by priority (higher first)
3. Each listener called with event object
4. Listener can stop propagation
5. Returns modified event

### 5. Queue System

**Location**: `src/Framework/Queue/`

**Key Features:**
- **Multiple Drivers**: Sync, Database, Redis, RabbitMQ
- **Job Interface**: All jobs implement `JobInterface`
- **Dependency Injection**: Jobs resolved via Container
- **Failed Jobs**: Automatic retry and failure handling
- **Queue Workers**: Long-lived processes consuming jobs

**Queue Architecture:**
```
Producer (anywhere) → Queue Driver → Queue Storage
                                      ↓
Consumer (CLI worker) ← Queue Driver ← Queue Storage
```

**Job Processing:**
1. Worker polls queue for next job
2. Resolves job class via Container
3. Calls `handle()` method with DI
4. On success: Removes from queue
5. On failure: Retries or moves to failed queue

### 6. Authentication & Authorization

**Location**: `src/Framework/Auth/`

**Key Components:**
- **AuthManager**: Manages multiple authentication guards
- **Guards**: Session, Token, Personal Access Token
- **Gate System**: Closure-based authorization
- **Policies**: Resource-based authorization classes

**Authentication Flow:**
1. User submits credentials
2. Guard attempts authentication
3. Validates credentials (password hash, etc.)
4. Creates session/token
5. Returns authenticated user

**Authorization Flow:**
1. Check if user has permission via Gate/Policy
2. Gate: Closure-based check
3. Policy: Method-based check (`update()`, `delete()`, etc.)
4. Returns boolean allow/deny

### 7. Security Features

**CSRF Protection**:
- Token generation and validation
- Laravel-compatible cookie encryption
- SPA-friendly token endpoint

**Rate Limiting**:
- Cache-based rate limiter
- Configurable limits per route
- Automatic blocking on exceed

**XSS Protection**:
- HTML escaping service
- Automatic escaping in views

**Security Headers**:
- CSP (Content Security Policy)
- HSTS (HTTP Strict Transport Security)
- X-Frame-Options

---

## 🧪 Testing Infrastructure

### Test Structure

```
tests/
├── Unit/              # Unit tests (isolated components)
├── Feature/           # Feature tests (HTTP endpoints)
├── Integration/       # Integration tests (full stack)
└── Performance/       # Performance tests
```

### TestCase Base Class

**Location**: `src/Framework/Testing/TestCase.php`

**Key Features:**
- **InteractsWithDatabase**: In-memory SQLite for fast tests
- **InteractsWithHttp**: HTTP request/response helpers
- **InteractsWithContainer**: Container mocking
- **InteractsWithEvents**: Event mocking
- **InteractsWithQueue**: Queue faking
- **InteractsWithCache**: Cache mocking
- **PerformanceAssertions**: Performance testing helpers

**Test Database Setup:**
- Uses in-memory SQLite (`sqlite::memory:`)
- Transaction-based (rollback after each test)
- Fast execution (no disk I/O)

---

## 🎨 Frontend Architecture

### Vue 3 SPA Structure

**Build System**: Vite (modern, fast build tool)

**Components:**
- **Vue 3**: Composition API
- **Vue Router**: Client-side routing
- **Pinia**: State management
- **Vite**: Build tool and dev server

**SPA Routing Pattern:**
```
All routes → AppController → app.php view → Vue Router handles client-side
```

**Key Files:**
- `resources/js/app.js`: Vue app entry point
- `resources/js/App.vue`: Root component
- `resources/js/router/index.js`: Router configuration
- `resources/js/pages/Home.vue`: Welcome page (Laravel-style)

---

## 📦 Service Providers - Complete List

### Framework Service Providers (27 total)

1. **RoutingServiceProvider**: Loads routes from `routes/` directory
2. **EventServiceProvider**: Registers event listeners
3. **HttpServiceProvider**: HTTP request/response services
4. **ConfigServiceProvider**: Configuration loading
5. **AuthServiceProvider**: Authentication services
6. **DatabaseServiceProvider**: Database connections
7. **ViteServiceProvider**: Vite integration
8. **UrlServiceProvider**: URL generation
9. **TranslationServiceProvider**: Translation services
10. **SecurityServiceProvider**: Security services
11. **StorageServiceProvider**: File storage
12. **SessionServiceProvider**: Session management
13. **SearchServiceProvider**: Search services (Elasticsearch)
14. **ScheduleServiceProvider**: Task scheduling
15. **RealtimeServiceProvider**: Realtime messaging
16. **QueueServiceProvider**: Queue system
17. **ProcessServiceProvider**: Process management
18. **ObserverServiceProvider**: Model observers
19. **NotificationServiceProvider**: Notifications
20. **MailServiceProvider**: Mail services
21. **HttpClientServiceProvider**: HTTP client
22. **LogServiceProvider**: Logging (PSR-3)
23. **HashServiceProvider**: Password hashing
24. **DateTimeServiceProvider**: Date/time utilities
25. **ConsoleServiceProvider**: CLI commands
26. **CacheServiceProvider**: Cache services
27. **BusServiceProvider**: Command bus

### Service Provider Lifecycle

```
1. register() → Bind services to container
2. boot() → Initialize services, load routes, register listeners
```

---

## 🔑 Design Patterns Used

### 1. Service Provider Pattern
- Modular service registration
- Two-phase lifecycle (register → boot)
- Lazy loading of services

### 2. Repository Pattern
- Data access abstraction
- Domain contracts in Domain layer
- Implementations in Infrastructure layer

### 3. Factory Pattern
- Model factories for testing/seeding
- Service factories in Container

### 4. Observer Pattern
- Model events (creating, created, etc.)
- Event system (listeners/subscribers)

### 5. Strategy Pattern
- Multiple guards (Session, Token)
- Multiple drivers (Cache, Queue, Storage)
- Pluggable implementations

### 6. Command Pattern
- Console commands
- Queue jobs

### 7. Facade Pattern
- Static accessors (Route, Log, Auth, etc.)
- ServiceAccessor implementation

### 8. Dependency Injection
- Constructor injection
- Method injection
- Auto-wiring via Container

### 9. Chain of Responsibility
- Middleware pipeline
- Request processing chain

### 10. Active Record Pattern
- ORM models represent database rows
- Models know how to save/delete themselves

---

## 📊 Performance Optimizations

### 1. Container Optimizations
- Reflection caching
- Singleton instance caching
- Lazy service resolution

### 2. Database Optimizations
- Eager loading (prevents N+1 queries)
- Query caching (configurable)
- Batch operations (bulk insert/update)
- Connection pooling

### 3. Route Optimizations
- Route caching (compiled routes)
- Fast route matching algorithm
- Parameter extraction optimization

### 4. Cache Layer
- Multiple drivers (File, Redis, Array)
- Cache tags for invalidation
- TTL management

### 5. Queue System
- Async job processing
- Batch job processing
- Priority queues

---

## 🚀 Key Architectural Decisions

### 1. Clean Architecture
**Decision**: Strict layer separation
**Rationale**: Maintainability, testability, independence of frameworks
**Implementation**: 4 clear layers with dependency rules

### 2. Zero-Dependency Core
**Decision**: Core framework has no external dependencies
**Rationale**: Flexibility, portability, version control
**Implementation**: Only PHP extensions, optional packages for features

### 3. Service Provider Pattern
**Decision**: Modular service registration
**Rationale**: Extensibility, lazy loading, organized bootstrap
**Implementation**: Register → Boot lifecycle

### 4. Dependency Injection Container
**Decision**: Auto-wiring DI container
**Rationale**: Testability, flexibility, loose coupling
**Implementation**: Reflection-based resolution with caching

### 5. Interface-Based Design
**Decision**: Program to interfaces
**Rationale**: Flexibility, testability, SOLID principles
**Implementation**: All services depend on interfaces

### 6. Eloquent-Style ORM
**Decision**: Active Record pattern
**Rationale**: Developer productivity, Laravel compatibility
**Implementation**: Model class with relationships and scopes

---

## 🔍 Code Quality & Standards

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

### Testing
- **Unit Tests**: Component isolation
- **Feature Tests**: Full HTTP stack
- **Integration Tests**: Real database/cache
- **Performance Tests**: Benchmarking

---

## 📚 Key Files Reference

### Bootstrap
- `public/index.php`: HTTP entry point
- `bootstrap/app.php`: Application bootstrap
- `console`: CLI entry point

### Configuration
- `config/app.php`: Core application config
- `config/database.php`: Database connections
- `config/middleware.php`: Middleware groups
- All config files in `config/` directory

### Routes
- `routes/web.php`: Web routes (Vue SPA fallback)
- `routes/api.php`: API routes (authentication, etc.)

### Framework Core
- `src/Framework/Foundation/Application.php`: Application container
- `src/Framework/Container/Container.php`: DI container
- `src/Framework/Routing/Router.php`: HTTP router
- `src/Framework/Database/ORM/Model.php`: ORM base class

### Application
- `src/App/Presentation/Http/Controllers/`: HTTP controllers
- `src/App/Application/Services/`: Application services
- `src/App/Domain/Entities/`: Domain entities
- `src/App/Infrastructure/Repository/`: Repository implementations

---

## 🎯 Development Workflow

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

## 🔄 Data Flow Examples

### Example 1: HTTP Request to Database

```
1. HTTP Request → public/index.php
2. Bootstrap → bootstrap/app.php
3. Router::dispatch() → Match route
4. Middleware → Auth middleware checks session
5. Controller → Resolved via Container with DI
6. Service → Application service called
7. Repository → Database repository queries via ORM
8. Model → ORM Model executes query
9. Query Builder → Builds SQL with parameter binding
10. Connection → PDO executes query
11. Response → Data returned to controller
12. View → Rendered or JSON response
```

### Example 2: Queue Job Processing

```
1. Controller → Dispatches job to queue
2. Queue Manager → Routes to appropriate driver (Redis/Database)
3. Queue Driver → Stores job in queue storage
4. Queue Worker (CLI) → Polls queue for jobs
5. Container → Resolves job class with dependencies
6. Job::handle() → Executes job logic
7. On Success → Removes from queue
8. On Failure → Retries or moves to failed queue
```

### Example 3: Event System

```
1. Model Event → User::creating() triggered
2. Event Dispatcher → Dispatches event object
3. Listeners → All registered listeners called (sorted by priority)
4. Listener Actions → Each listener performs action
5. Event Propagation → Can stop propagation if needed
6. Return → Modified event returned
```

---

## 🎓 Learning Resources

### Codebase Documentation
- `CODEBASE_OVERVIEW.md`: High-level overview
- `README.md`: Quick start guide
- `docs/`: Comprehensive guides for each feature

### Key Areas to Study
1. **Container**: Understand DI resolution (`src/Framework/Container/`)
2. **Router**: Understand route matching (`src/Framework/Routing/`)
3. **ORM**: Understand relationships (`src/Framework/Database/ORM/Relations/`)
4. **Service Providers**: Understand bootstrap (`src/Framework/Providers/`)
5. **Testing**: Understand test infrastructure (`src/Framework/Testing/`)

---

## 🏁 Conclusion

The Toporia Framework codebase demonstrates:

- ✅ **Enterprise-Grade Architecture**: Clean Architecture with strict layer separation
- ✅ **SOLID Principles**: Applied consistently throughout
- ✅ **Performance Optimizations**: Reflection caching, eager loading, connection pooling
- ✅ **Developer Experience**: Laravel-compatible API, fluent interfaces, helpful errors
- ✅ **Testability**: Comprehensive testing infrastructure
- ✅ **Extensibility**: Service providers, interfaces, pluggable drivers
- ✅ **Security**: CSRF, XSS protection, rate limiting, secure headers
- ✅ **Modern PHP**: PHP 8.1+ features, type hints, strict types

This is a production-ready framework built with professional software engineering practices.

---

**Document Version**: 1.0.0
**Last Updated**: 2025-01-10
**Framework Version**: 1.0.0
