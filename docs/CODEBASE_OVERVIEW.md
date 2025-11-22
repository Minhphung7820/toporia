# Toporia Framework - Codebase Overview

## 📋 Executive Summary

**Toporia** is a professional PHP framework (v1.0.0) built on **Clean Architecture** and **SOLID principles**. It provides a zero-dependency core with optional integrations, inspired by Laravel's elegance and Symfony's architecture.

- **Total Framework Files**: ~565 PHP files
- **Application Files**: ~94 PHP files
- **Architecture**: Clean Architecture (4 layers)
- **PHP Version**: >= 8.1
- **Frontend**: Vue 3 SPA with Vite

---

## 🏗️ Architecture Overview

### Clean Architecture Layers

```
┌─────────────────────────────────────────────────────────┐
│                  Presentation Layer                     │
│  (Controllers, Actions, Middleware, Views, API)         │
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
│  Location: src/App/Domain/                             │
└─────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────┐
│                 Infrastructure Layer                    │
│   (Repository Implementations, External Services)      │
│  Location: src/App/Infrastructure/                      │
└─────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────┐
│                   Framework Layer                       │
│  (HTTP, Routing, Container, Events, Database, etc.)    │
│  Location: src/Framework/                              │
└─────────────────────────────────────────────────────────┘
```

### Key Principles
- **Dependency Inversion**: High-level modules don't depend on low-level modules
- **Interface Segregation**: Small, focused interfaces
- **Single Responsibility**: Each class has one reason to change
- **Open/Closed**: Open for extension, closed for modification

---

## 📁 Directory Structure

### Root Level
```
toporia/
├── bootstrap/          # Application bootstrap files
├── config/             # Configuration files (24 config files)
├── configs/            # Additional configs (.npmrc, .nvmrc, etc.)
├── database/           # Migrations, seeders, factories
├── docker/             # Docker configuration
├── docs/               # Documentation
├── public/             # Public web root
├── resources/          # Frontend assets (JS, Vue, translations)
├── routes/             # Route definitions (web.php, api.php)
├── scripts/            # Utility scripts
├── src/                # Source code
│   ├── Framework/      # Framework core (~565 files)
│   └── App/            # Application code (~94 files)
├── storage/            # Logs, cache, sessions, temp files
├── tests/              # Test suites (Unit, Feature, Integration, Performance)
└── vendor/             # Composer dependencies
```

---

## 🔧 Framework Core (`src/Framework/`)

### Core Components

#### 1. **Foundation** (`Foundation/`)
- `Application.php` - Central application bootstrapper
- Bootstrap classes:
  - `LoadEnvironmentVariables` - Environment variable loading
  - `HandleExceptions` - Error/exception handling
  - `LoadConfiguration` - Configuration loading
  - `RegisterFacades` - Facade registration
  - `RegisterProviders` - Service provider registration
  - `BootProviders` - Service provider booting

#### 2. **Container** (`Container/`)
- **Dependency Injection Container** with:
  - Auto-wiring via reflection
  - Singleton pattern support
  - Contextual bindings
  - Tagged bindings
  - Extending bindings
  - Method injection
  - Circular dependency detection
  - Performance: O(1) singleton lookup, O(N) dependency resolution

#### 3. **Routing** (`Routing/`)
- Fully OOP router with fluent API
- RESTful HTTP verbs (GET, POST, PUT, PATCH, DELETE, ANY)
- Route parameters with regex support (`{id}`, `{slug}`)
- Named routes for URL generation
- Route groups with shared attributes (prefix, middleware, namespace)
- Middleware pipeline with before/after hooks
- Dependency injection for controllers

#### 4. **HTTP Layer** (`Http/`)
- PSR-7 inspired Request/Response abstraction
- JSON detection and response helpers
- File upload handling with validation
- Cookie management with encryption (Laravel-compatible)
- Security headers middleware (CSP, HSTS, X-Frame-Options)
- Middleware pipeline system

#### 5. **Database & ORM** (`Database/`)
- **DatabaseManager**: Multi-connection support (MySQL, PostgreSQL, SQLite)
- **QueryBuilder**: Fluent query builder with automatic parameter binding
- **ORM Model**: Eloquent-style Active Record pattern
  - Relationships: HasOne, HasMany, BelongsTo, BelongsToMany
  - Eager loading to prevent N+1 queries
  - Relationship aggregates (withCount, withSum, withAvg, etc.)
  - Bulk upsert (100x faster than separate queries)
  - Model hooks (creating, created, updating, updated, deleting, deleted)
  - Mass assignment protection
  - Attribute casting
  - Global scopes
  - Model collections
- **Factory System**: Model factories with Faker integration
- **Seeder System**: Database seeders with transaction support and dependency management
- **Migrations**: Schema builder for database migrations

#### 6. **Authentication & Authorization** (`Auth/`)
- Session-based authentication
- Token-based authentication (API)
- Gate system for closure-based authorization
- Policy classes for resource-based authorization
- Password hashing (Bcrypt, Argon2id)
- Guards: Session, Token, Personal Access Token

#### 7. **Security** (`Security/`)
- CSRF protection with token management
- XSS protection service
- Replay attack protection (nonce-based)
- Rate limiting (cache-based)
- Security headers middleware
- Cookie encryption/decryption

#### 8. **Events** (`Events/`)
- Priority-based event dispatchers
- Event propagation control
- Event subscriber pattern
- PSR-14 inspired interface design

#### 9. **Queue System** (`Queue/`)
- Multiple drivers: Sync, Database, Redis, RabbitMQ
- Job interface with dependency injection
- Queue workers
- Failed job handling

#### 10. **Cache** (`Cache/`)
- Multiple drivers: File, Redis, Array
- Cache tags support
- TTL management

#### 11. **Logging** (`Log/`) - PSR-3
- Multi-channel logger (Daily, Single, Stack, Syslog, Stderr)
- Daily file rotation with YYYY-MM-DD.log format
- Auto-cleanup of old logs
- Placeholder interpolation (`{user_id}` syntax)
- Context data as structured JSON
- Thread-safe file locking

#### 12. **Mail** (`Mail/`)
- Mailable classes
- Multiple drivers: SMTP, Sendmail, Log
- Queue support for emails

#### 13. **Notification** (`Notification/`)
- Multi-channel notifications (Mail, Database, SMS)
- Notification drivers
- Queue support

#### 14. **Storage** (`Storage/`)
- File system abstraction
- Multiple drivers: Local, S3, FTP
- File upload handling

#### 15. **Validation** (`Validation/`)
- Form request validation
- Rule-based validation
- Custom validation rules
- Array validation

#### 16. **Translation** (`Translation/`)
- Multi-language support
- File-based translation loaders
- Cache support
- Placeholder replacement

#### 17. **Realtime** (`Realtime/`)
- WebSocket, SSE, Long-polling transports
- Brokers: Redis Pub/Sub, Kafka, RabbitMQ, NATS, PostgreSQL
- Channel-based messaging
- Multi-server support

#### 18. **Console** (`Console/`)
- Professional CLI with Command pattern
- Input parsing (arguments, options, flags)
- Colored output and formatted tables
- Interactive prompts and confirmations
- Built-in commands (cache, queue, schedule, migrate, etc.)

#### 19. **Search** (`Search/`)
- Elasticsearch integration
- Search query builder

#### 20. **Testing** (`Testing/`)
- Test case base classes
- Database testing helpers
- Mock utilities

---

## 🎯 Application Layer (`src/App/`)

### Structure

#### **Domain Layer** (`Domain/`)
- **Entities**: Business entities (e.g., `Product.php`)
- **Value Objects**: Immutable value objects
- **Contracts**: Repository interfaces, domain contracts

#### **Application Layer** (`Application/`)
- **Services**: Application services
- **UseCases**: Use case implementations
- **Rules**: Validation rules
- **Observers**: Domain observers

#### **Infrastructure Layer** (`Infrastructure/`)
- **Persistence**: Database implementations
- **Repository**: Repository implementations
- **Auth**: Authentication implementations
- **Mails**: Email templates and mailables
- **Notifications**: Notification implementations
- **Jobs**: Queue jobs
- **Services**: External service integrations
- **Export/Import**: Data export/import services
- **Transformer**: Data transformers

#### **Presentation Layer** (`Presentation/`)
- **Http/Controllers**: HTTP controllers
  - `AppController` - Vue SPA controller
  - `Api/AuthController` - Authentication API
  - `Api/CsrfCookieController` - CSRF cookie endpoint
- **Views**: PHP views (app.php, emails/)
- **Console**: Console commands

---

## 🌐 Frontend Architecture

### Vue 3 SPA Structure (`resources/js/`)

```
resources/js/
├── app.js              # Vue app entry point
├── App.vue             # Root Vue component
├── router/
│   └── index.js        # Vue Router configuration
├── pages/              # Page components
│   ├── Home.vue        # Welcome page (Laravel style)
│   ├── About.vue
│   ├── Login.vue
│   ├── Register.vue
│   ├── ForgotPassword.vue
│   ├── ResetPassword.vue
│   ├── ChangePassword.vue
│   └── errors/         # Error pages (403, 404, 500)
├── services/           # API services
├── stores/             # Pinia stores (state management)
└── ...
```

### Build System
- **Vite**: Modern build tool
- **Vue 3**: Composition API
- **Vue Router**: Client-side routing
- **Pinia**: State management

---

## 🔄 Request Flow

### HTTP Request Flow

```
1. public/index.php
   ↓
2. bootstrap/app.php
   - Load environment variables
   - Handle exceptions
   - Create Application instance
   - Load helpers
   - Load configuration
   - Register facades
   - Register service providers
   - Boot service providers (loads routes)
   ↓
3. Router::dispatch()
   - Match route
   - Build middleware pipeline
   - Execute middleware (before)
   - Resolve controller with DI
   - Execute controller method
   - Execute middleware (after)
   - Return response
```

### Route Registration

Routes are loaded in service providers:
- `routes/web.php` - Web routes (loaded by RoutingServiceProvider)
- `routes/api.php` - API routes (loaded by RoutingServiceProvider)

Current routes:
- **Web**: `Route::any('/{any}', [AppController::class, 'index'])` - Vue SPA fallback
- **API**: Authentication routes (`/api/auth/*`)

---

## 🗄️ Database Architecture

### ORM Features
- **Active Record Pattern**: Models extend `Model` base class
- **Relationships**: HasOne, HasMany, BelongsTo, BelongsToMany
- **Eager Loading**: `with()` method to prevent N+1 queries
- **Query Builder**: Fluent interface for complex queries
- **Migrations**: Schema builder for database changes
- **Factories**: Model factories with Faker for testing/seeding
- **Seeders**: Database seeders with transaction support

### Factory/Seeder System
- **Factory**: Base factory class with Faker integration
- **Seeder**: Base seeder class with dependency management
- **SeederManager**: Manages seeder execution
- **Custom Faker Providers**: Vietnamese provider for locale-specific data

---

## 🔐 Security Features

1. **CSRF Protection**: Token-based validation with Laravel-compatible cookies
2. **XSS Protection**: HTML escaping service
3. **Replay Attack Protection**: Nonce-based protection
4. **Rate Limiting**: Cache-based rate limiting for API
5. **Security Headers**: CSP, HSTS, X-Frame-Options
6. **Cookie Encryption**: Encrypted cookies with Laravel compatibility
7. **Password Hashing**: Bcrypt and Argon2id support
8. **Mass Assignment Protection**: Fillable/guarded attributes

---

## 🧪 Testing Structure

```
tests/
├── Unit/              # Unit tests
├── Feature/           # Feature tests
├── Integration/       # Integration tests
└── Performance/       # Performance tests
```

Test suites configured in `phpunit.xml`.

---

## 🐳 Docker Setup

### Services (docker-compose.yml)
- **app**: PHP-FPM application container
- **nginx**: Web server (port 8000)
- **mysql**: MySQL 8.0 database
- **redis**: Redis cache/queue
- **kafka**: Apache Kafka for realtime messaging
- **rabbitmq**: RabbitMQ message broker
- **elasticsearch**: Elasticsearch for search
- **zookeeper**: ZooKeeper for Kafka

---

## 📦 Service Providers

### Framework Service Providers (`src/Framework/Providers/`)
1. **AuthServiceProvider** - Authentication services
2. **BusServiceProvider** - Command bus
3. **CacheServiceProvider** - Cache services
4. **ConfigServiceProvider** - Configuration
5. **ConsoleServiceProvider** - CLI commands
6. **DatabaseServiceProvider** - Database connections
7. **DateTimeServiceProvider** - Date/time utilities
8. **EventServiceProvider** - Event dispatcher
9. **HashServiceProvider** - Password hashing
10. **HttpServiceProvider** - HTTP services
11. **LogServiceProvider** - Logging
12. **MailServiceProvider** - Mail services
13. **NotificationServiceProvider** - Notifications
14. **ObserverServiceProvider** - Model observers
15. **QueueServiceProvider** - Queue system
16. **RealtimeServiceProvider** - Realtime messaging
17. **RoutingServiceProvider** - Route loading
18. **ScheduleServiceProvider** - Task scheduling
19. **SearchServiceProvider** - Search services
20. **SecurityServiceProvider** - Security services
21. **SessionServiceProvider** - Session management
22. **StorageServiceProvider** - File storage
23. **TranslationServiceProvider** - Translations
24. **UrlServiceProvider** - URL generation
25. **ViteServiceProvider** - Vite integration

---

## 🔑 Key Design Patterns

1. **Service Provider Pattern**: Modular service registration
2. **Repository Pattern**: Data access abstraction
3. **Factory Pattern**: Object creation
4. **Observer Pattern**: Model events
5. **Strategy Pattern**: Multiple drivers (cache, queue, etc.)
6. **Command Pattern**: Console commands
7. **Facade Pattern**: Static accessors (Route, Log, etc.)
8. **Dependency Injection**: Constructor and method injection

---

## 📝 Configuration Files

Located in `config/`:
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

---

## 🚀 Key Features Summary

### Framework Core
✅ Clean Architecture with 4-layer separation
✅ SOLID principles throughout
✅ Zero-dependency core
✅ PSR standards (PSR-3, PSR-7, PSR-11, PSR-14)
✅ Auto-wiring dependency injection
✅ Service provider pattern
✅ Facade pattern for static access

### Database & ORM
✅ Eloquent-style ORM
✅ Relationships (HasOne, HasMany, BelongsTo, BelongsToMany)
✅ Eager loading
✅ Query builder
✅ Migrations
✅ Factories & Seeders
✅ Bulk operations

### Security
✅ CSRF protection
✅ XSS protection
✅ Replay attack protection
✅ Rate limiting
✅ Security headers
✅ Cookie encryption

### Frontend
✅ Vue 3 SPA
✅ Vite build system
✅ Vue Router
✅ Pinia state management

### Infrastructure
✅ Docker setup
✅ Multiple queue drivers
✅ Multiple cache drivers
✅ Multiple storage drivers
✅ Realtime messaging (WebSocket, SSE, Kafka, etc.)
✅ Search (Elasticsearch)

---

## 📚 Documentation

Comprehensive documentation available in `/docs`:
- Architecture guides
- ORM documentation
- Security features
- Testing guides
- And more...

---

## 🎯 Current State

- **Version**: 1.0.0
- **Status**: Production-ready framework
- **Welcome Page**: Laravel-style welcome page in Vue SPA (`Home.vue`)
- **Routes**: All routes handled by Vue SPA (SPA fallback pattern)
- **API**: Authentication API endpoints available
- **Database**: Factory/Seeder system implemented
- **Frontend**: Vue 3 SPA with modern UI

---

## 🔄 Development Workflow

1. **Backend**: PHP code in `src/App/` following Clean Architecture
2. **Frontend**: Vue components in `resources/js/`
3. **Routes**: Defined in `routes/web.php` and `routes/api.php`
4. **Database**: Migrations in `database/migrations/`, seeders in `database/seeders/`
5. **Build**: `npm run dev` for development, `npm run build` for production
6. **Testing**: `composer test` for PHPUnit tests

---

This codebase represents a professional, enterprise-grade PHP framework built with modern best practices and Clean Architecture principles.


