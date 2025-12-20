# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Toporia is a Clean Architecture PHP framework following SOLID principles. The project emphasizes strict separation of concerns between Framework and Application layers with interface-based design and dependency injection.

## Requirements

- **PHP**: >= 8.1
- **Composer**: For dependency management
- **Optional Extensions**:
  - `ext-redis` - Redis cache/queue/realtime support
  - `ext-rdkafka` - High-performance Kafka broker
  - `ext-pdo_mysql` - MySQL database support
  - `ext-pdo_pgsql` - PostgreSQL database support
  - `ext-pdo_sqlite` - SQLite database support
  - `ext-mongodb` - MongoDB support (requires `mongodb/mongodb` package)
  - `ext-pcntl` - Multi-process execution (Linux/macOS)
  - `ext-zip` - Excel import/export

## Development Commands

```bash
# Setup (Docker - Recommended)
make up                                    # Start core services (app, nginx, mysql, redis)
make up-full                               # Start all services (includes Kafka, RabbitMQ, Elasticsearch)
docker-compose exec app composer install   # Install dependencies
docker-compose exec app php console migrate # Run migrations
make setup                                 # Complete setup (install, migrate, seed)
make setup-fresh                           # Fresh setup (down, up, install, migrate, seed)

# Setup (Local PHP)
composer install && cp .env.example .env && php console key:generate
php -S localhost:8000 -t public            # Start dev server

# Frontend (Vue 3 SPA)
pnpm install                               # Install Node dependencies
pnpm run dev                               # Start Vite dev server (HMR)
pnpm run build                             # Build for production
pnpm run preview                           # Preview production build

# Docker Utilities
make shell                                 # Access PHP container shell
make logs                                  # Show all logs (follow mode)
make ps                                    # Show running services
make health                                # Check health of all services
make down                                  # Stop all services
make restart                               # Restart all services

# Testing
composer test                              # Run all tests
composer test:unit                         # Unit tests only
composer test:feature                      # Feature tests only
composer test:integration                  # Integration tests only
composer test:performance                  # Performance tests only
composer test:coverage                     # Generate HTML coverage in coverage/
./vendor/bin/phpunit tests/Unit/SomeTest.php       # Single test file
./vendor/bin/phpunit --filter testMethodName       # Single test method
make test                                  # Run tests in Docker

# Static Analysis (if phpstan.neon exists)
./vendor/bin/phpstan analyse                       # Run static analysis

# Code Generation (make:*)
php console make:controller Name           # Create controller
php console make:model Name                # Create ORM model
php console make:migration name            # Create migration
php console make:handler Name              # Create command/query handler
php console make:job Name                  # Create queue job
php console make:repository Name           # Create repository interface + implementation
php console list                           # List ALL available commands

# Database
php console migrate                        # Run migrations
php console migrate:fresh                  # Drop all + re-run migrations
php console db:seed                        # Run seeders
make migrate                               # Run migrations in Docker
make mysql-cli                             # Access MySQL CLI
make redis-cli                             # Access Redis CLI
make db-backup                             # Backup database to backup.sql
make db-restore                            # Restore database from backup.sql

# Queue & Scheduling
php console queue:work                     # Process queue jobs
php console schedule:run                   # Run due scheduled tasks
make queue-work                            # Start queue worker in Docker
make schedule-run                          # Run scheduled tasks in Docker

# Caching
php console cache:clear                    # Clear cache
php console config:cache                   # Cache config
php console route:cache                    # Cache routes
php console optimize                       # Optimize application (cache config, routes)
php console optimize:clear                 # Clear all optimizations
make cache-clear                           # Clear cache in Docker
```

**Test Environment**: Tests use `toporia_test` database (configured in [phpunit.xml](phpunit.xml)) with sync queue and array cache drivers for isolation. Test suites: Unit (framework components), Feature (HTTP/application layer), Integration (database/external services), Performance (benchmarks).

## Architecture

### Layer Structure

```
app/                      # Application code (App\ namespace)
├── Domain/              # Pure business entities and repository interfaces
├── Application/         # Use cases (Commands/Handlers)
├── Infrastructure/      # Repository implementations, external services
└── Presentation/        # Controllers, Actions, Middleware, Views

packages/framework/src/   # Framework code (Toporia\Framework\ namespace)
├── Container/           # DI container
├── Routing/             # Router
├── Http/                # Request/Response
├── Database/            # ORM, Query Builder, Migrations
└── ...                  # Other framework components

resources/                # Frontend assets
├── js/
│   ├── pages/           # Vue 3 pages (Home.vue, Login.vue, Dashboard.vue)
│   ├── App.vue          # Root Vue component
│   ├── router/          # Vue Router configuration
│   └── main.js          # Vue app entry point
└── views/               # PHP templates (used as SPA shell)
```

1. **Framework Layer** (`packages/framework/src/`) - Zero-dependency mini-framework with Container, Routing, Http, Events, Console, Bus, Database/ORM, Queue, Cache, Auth, Validation, Log, Realtime, Search components. Related packages: `toporia/dominion` (utilities) and `toporia/docura` (documentation).

2. **Domain Layer** (`app/Domain/`) - Pure business logic:
   - Entities in `Domain/Entities/` (plain PHP classes with readonly properties)
   - Repository interfaces in `Domain/Contracts/Repository/`
   - Value Objects in `Domain/ValueObjects/`
   - **IMPORTANT**: Domain layer has NO framework dependencies

3. **Application Layer** (`app/Application/`) - Use cases:
   - Use cases organized in `Application/UseCases/` by feature
   - Services in `Application/Services/`
   - Custom validation rules in `Application/Rules/`
   - Model observers in `Application/Observers/` (registered in [config/observers.php](config/observers.php))

4. **Infrastructure Layer** (`app/Infrastructure/`) - External concerns:
   - Repository implementations in `Infrastructure/Repository/` and `Infrastructure/Persistence/`
   - Service providers in `Infrastructure/Providers/`
   - Authentication implementations in `Infrastructure/Auth/`
   - Jobs in `Infrastructure/Jobs/`
   - Notifications in `Infrastructure/Notifications/`
   - External service integrations (Import/Export, Transformers, etc.)

5. **Presentation Layer** (`app/Presentation/`) - HTTP interface:
   - Controllers (MVC style) in `Presentation/Http/Controllers/`
   - Middleware in `Presentation/Http/Middleware/`
   - Views in `Presentation/Views/` (plain PHP templates)
   - Console commands in `Presentation/Console/Commands/`

6. **Frontend Layer** (`resources/js/`) - Vue 3 SPA:
   - **Stack**: Vue 3 (Composition API), Vue Router 4, Pinia, Vite 7, Axios
   - **Architecture**: Single Page Application (SPA) with client-side routing
   - **Integration**: Backend serves API routes ([routes/api.php](routes/api.php)), frontend consumes them
   - **Build**: Vite bundles JS/CSS to [public/build/](public/build/)
   - **Entry Point**: [resources/js/main.js](resources/js/main.js) mounted in [resources/views/app.php](resources/views/app.php)
   - **Pages**: Vue components in [resources/js/pages/](resources/js/pages/) (Home, Login, Dashboard, etc.)
   - **Admin Panel**: Full admin system in [resources/js/admin/](resources/js/admin/) with layout, pages, stores, and shared components
   - **Routing**: Vue Router handles client-side navigation, web routes have catch-all for SPA fallback

### Key Files

- [public/index.php](public/index.php) → [bootstrap/app.php](bootstrap/app.php) - Entry point and bootstrap
- [bootstrap/helpers.php](bootstrap/helpers.php) - Global helper functions (50+ helpers)
- [routes/web.php](routes/web.php), [routes/api.php](routes/api.php) - Web and API routes
- [routes/terminal.php](routes/terminal.php) - Closure-based console commands
- [routes/webhook.php](routes/webhook.php), [routes/socialite.php](routes/socialite.php) - Optional package routes
- [app/Infrastructure/Providers/RouteServiceProvider.php](app/Infrastructure/Providers/RouteServiceProvider.php) - Route loading logic
- [config/middleware.php](config/middleware.php) - Middleware groups and aliases
- [config/commands.php](config/commands.php) - Register console commands
- [config/observers.php](config/observers.php) - Register model observers
- [config/search.php](config/search.php) - Elasticsearch index configuration
- [vite.config.js](vite.config.js) - Vite configuration for frontend build
- [resources/js/main.js](resources/js/main.js) - Vue app entry point
- [resources/js/router/index.js](resources/js/router/index.js) - Vue Router configuration

## Key Patterns

### Service Provider Pattern

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

Register providers in [bootstrap/app.php](bootstrap/app.php).

### Dependency Injection

```php
// Auto-wiring via type hints
$container->get(ProductsController::class); // Resolves dependencies automatically

// Method invocation with DI
$container->call([Controller::class, 'method'], ['param' => 'value']);
```

### Routing

Routes in [routes/web.php](routes/web.php) and [routes/api.php](routes/api.php):

```php
$router->get('/products', [ProductsController::class, 'index']);
$router->get('/products/{id}', [ProductsController::class, 'show']);
$router->post('/products', CreateProductAction::class); // ADR style

// With middleware
$router->get('/dashboard', [HomeController::class, 'dashboard'])
    ->name('dashboard')
    ->middleware(['auth']);

// Route groups
$router->group(['prefix' => 'admin', 'middleware' => ['auth']], function ($router) {
    $router->get('/users', [AdminController::class, 'users']);
});
```

**Route Loading Order** (in [RouteServiceProvider](app/Infrastructure/Providers/RouteServiceProvider.php)):
1. API routes (`/api/*`) - Loaded FIRST to prevent catch-all conflicts
2. Webhook routes (`/webhook/*`) - Optional package routes (loaded if file exists)
3. Socialite routes (`/auth/socialite/*`) - Optional package routes (loaded if file exists)
4. Web routes - Loaded LAST (includes SPA catch-all route)

Middleware aliases configured in [config/middleware.php](config/middleware.php).

### Command Bus Pattern

The Bus system dispatches commands/queries to handlers with auto-wired dependencies:

```php
// Command - Simple DTO
final class SendWelcomeEmail
{
    public function __construct(
        public readonly string $email,
        public readonly string $name
    ) {}
}

// Handler - Auto-wired dependencies via constructor
final class SendWelcomeEmailHandler
{
    public function __construct(private MailerInterface $mailer) {}

    public function __invoke(SendWelcomeEmail $command): void
    {
        $this->mailer->send(
            to: $command->email,
            subject: 'Welcome!',
            body: "Hello {$command->name}"
        );
    }
}

// Dispatch
dispatch(new SendWelcomeEmail('user@example.com', 'John'));

// Synchronous dispatch (wait for result)
$result = dispatch_sync(new SendWelcomeEmail('user@example.com', 'John'));
```

**Handler naming convention**: `CommandName` => `CommandNameHandler`

See [docs/BUS.md](docs/BUS.md) for queue support, batching, and middleware.

### ORM Models

```php
class ProductModel extends Model
{
    protected static string $table = 'products';
    protected static array $fillable = ['title', 'price', 'sku'];
    protected static array $casts = ['price' => 'float', 'is_active' => 'bool'];
}

// Usage
$product = ProductModel::create(['title' => 'Laptop', 'price' => 999.99]);
$products = ProductModel::where('price', '>', 500)->get();
$users = UserModel::with(['posts', 'profile'])->get(); // Eager loading
```

### Console Commands

**Class-based Commands** (for complex commands):

```php
final class MyCommand extends Command
{
    protected string $signature = 'my:command';
    protected string $description = 'Description';

    public function handle(): int
    {
        $this->info('Processing...');
        return 0;
    }
}
```

Register in [config/commands.php](config/commands.php).

**Closure-based Commands** (for simple commands in [routes/terminal.php](routes/terminal.php)):

```php
use Toporia\Framework\Support\Accessors\Terminal;

// Simple command with argument
Terminal::command('mail:send {user}', function (string $user) {
    $this->info("Sending email to: {$user}");
})->describe('Send marketing email');

// With dependency injection
Terminal::command('db:stats', function (DatabaseManager $db) {
    $stats = $db->getStats();
    $this->table(['Metric', 'Value'], $stats);
})->describe('Display database statistics');

// With options
Terminal::command('cache:clear {--tags=* : Tags to clear}', function () {
    $tags = $this->option('tags');
    // Clear cache logic
})->describe('Clear cache with optional tags');

// Command chaining (orchestration)
Terminal::command('deploy:prepare', function () {
    $this->call('config:cache');
    $this->call('route:cache');
    $this->call('migrate', ['--force' => true]);
    $this->info('Deployment ready!');
})->describe('Prepare deployment');
```

**Key Features**:
- Automatic dependency injection via type hints
- Access to all Command methods: `$this->info()`, `$this->ask()`, `$this->table()`, `$this->call()`
- Support for arguments and options with Laravel-like syntax
- Fluent API with `->describe()` for help text
- Perfect for quick utilities, maintenance tasks, and command orchestration

## Code Conventions

- All files use `declare(strict_types=1)`
- Namespace structure mirrors directory structure
- One class per file
- Interfaces end with `Interface` suffix
- Abstract classes start with `Abstract` prefix
- Repository interfaces in Domain, implementations in Infrastructure
- Prefer composition over inheritance
- Program to interfaces, not implementations

### This is NOT Laravel

**CRITICAL**: This is a custom framework called "Toporia", NOT Laravel. Do NOT assume Laravel components, helpers, facades, or Eloquent conventions.

- Never generate code using Laravel syntax like: `Route::`, `Artisan::`, Laravel facades
- Never import from `Illuminate\*` or `Laravel\*` namespaces
- If unsure between Laravel and Toporia behavior, ALWAYS choose Toporia
- If a method does not exist, do NOT create a Laravel-like one - use or propose the equivalent Toporia-style method

**Toporia ORM supported methods** (similar method names but Toporia's own implementation - NOT Laravel/Eloquent):
- `with()` - Eager loading relationships
- `hasOne()`, `hasMany()`, `belongsTo()`, `belongsToMany()` - Basic relationship definitions
- `hasOneThrough()`, `hasManyThrough()` - Through relationships
- `morphOne()`, `morphMany()`, `morphTo()`, `morphToMany()`, `morphedByMany()` - Polymorphic relationships
- `whereHas()`, `whereDoesntHave()` - Filter models by related records
- `withCount()`, `withSum()`, `withAvg()`, `withMin()`, `withMax()` - Relationship aggregates
- `belongsToMany()->withPivot()` - Include pivot columns in results

**Toporia framework classes** (in `packages/framework/src/`):
- `Toporia\Framework\Container\Container` - DI Container
- `Toporia\Framework\Routing\Router` - Router
- `Toporia\Framework\Database\ORM\Model` - ORM Model
- `Toporia\Framework\Database\QueryBuilder` - Query Builder

### Namespace Import Convention

**CRITICAL**: Always import classes at the top of files using `use` statements. Use only short class names in code.

```php
// ✅ CORRECT
use App\Services\Sync\ProductSyncService;
new Request();
ProductSyncService::handle();

// ❌ WRONG - Never use full namespaces in executable code
new \App\Http\Request();
\App\Services\Sync\ProductSyncService::handle();
```

### Framework-First Development

When implementing features:
1. Inspect existing project structure for similar classes
2. Check existing base classes, abstract classes, interfaces, and traits
3. Follow the same patterns as existing implementations
4. Never create files in arbitrary locations - follow framework conventions
5. Never invent your own architecture - reuse framework patterns
6. Use framework features (Queue, Event, Job, Repository, etc.) - don't reinvent them

**Rule**: Always adapt new code to the framework, never force the framework to adapt to new code.

### Variable Shadowing Prevention

**CRITICAL**: Do NOT shadow variables. Never reuse the name of an outer-scope variable inside foreach loops, closures, callbacks, or nested scopes.

- If a variable named `$constraints`, `$items`, `$data`, `$list`, or similar exists in the outer scope, inner variables MUST use DIFFERENT names
- Inner-scope variables must be renamed clearly:
  - `$nestedConstraintList`
  - `$childItems`
  - `$relationConstraints`
  - `$mergedConstraints`
- Variable names in nested loops must be descriptive and indicate their purpose
- Prefer clarity over brevity
- **ORM-specific**: When working with eager-loading, constraint merging, relation grouping, or nested relations:
  - Direct relation constraints must never be overwritten by accident
  - Nested relation lists must use clearly distinct names to avoid conflicts

## Optional Packages

Toporia supports optional packages for extended functionality (source in `packages/` directory, installed via composer path repositories):

- **Socialite** (`toporia/socialite`) - OAuth authentication (Google, Facebook, GitHub, etc.) - See [docs/SOCIALITE.md](docs/SOCIALITE.md)
  - Install: `composer require toporia/socialite` + `php console vendor:publish --tag=socialite-routes`
  - Routes: [routes/socialite.php](routes/socialite.php) (OAuth redirect/callback endpoints)

- **Webhook** (`toporia/webhook`) - Webhook handling and verification - See [docs/WEBHOOK.md](docs/WEBHOOK.md)
  - Install: `composer require toporia/webhook` + `php console vendor:publish --tag=webhook-routes`
  - Routes: [routes/webhook.php](routes/webhook.php) (Inbound webhook endpoints)

- **Tabula** (`toporia/tabula`) - High-performance Excel/CSV import & export (handles millions of rows) - See [docs/TABULA.md](docs/TABULA.md) if available
  - Uses streaming for memory efficiency with large datasets
  - Supports parallel chunk processing for imports
- **MongoDB** (`toporia/mongodb`) - MongoDB ODM with embedded documents, references, and aggregation pipelines
- **Audit** (`toporia/audit`) - Audit logging for models
- **API Versioning** (`toporia/api-versioning`) - API versioning support
- **Tenancy** (`toporia/tenancy`) - Multi-tenancy support
- **Dominion** (`toporia/dominion`) - Utility helpers and support classes
- **Docura** (`toporia/docura`) - Documentation utilities

**Package Installation Workflow**:
1. Install via composer: `composer require toporia/package-name`
2. Publish routes (if package has routes): `php console vendor:publish --tag=package-routes`
3. Publish config (optional): `php console vendor:publish --tag=package-config`
4. Run migrations (if needed): `php console migrate`
5. Routes are loaded conditionally by [RouteServiceProvider](app/Infrastructure/Providers/RouteServiceProvider.php) using `file_exists()` check

**Publishing System**: Packages register publishable assets (routes, configs) via `ServiceProvider::publishes()`. The `vendor:publish` command copies files from `vendor/toporia/package/` to application directory for customization.

## Documentation

Extensive documentation available in [docs/](docs/) (100+ guides covering core features, architecture decisions, and optimization strategies):

**Core Features**:
- [BUS.md](docs/BUS.md) - Command Bus pattern, handlers, queuing
- [orm-advanced-features.md](docs/orm-advanced-features.md) - Advanced ORM features (eager loading, aggregates, etc.)
- [MIGRATION.md](docs/MIGRATION.md) - Database migrations
- [QUEUE_GUIDE.md](docs/QUEUE_GUIDE.md) - Queue system and jobs
- [SCHEDULE.md](docs/SCHEDULE.md) - Task scheduling
- [FORM_REQUEST.md](docs/FORM_REQUEST.md) - Form validation
- [TESTING.md](docs/TESTING.md) - Testing guide
- [FACTORY_SEEDER_GUIDE.md](docs/FACTORY_SEEDER_GUIDE.md) - Factories and seeders

**Optional Packages**:
- [SOCIALITE.md](docs/SOCIALITE.md) - OAuth authentication
- [WEBHOOK.md](docs/WEBHOOK.md) - Webhook handling

**DevOps**:
- [DOCKER.md](docs/DOCKER.md) - Docker setup and usage

Use `ls docs/` to discover additional documentation on specific features and architectural decisions.

## Helper Functions

Common helpers ([bootstrap/helpers.php](bootstrap/helpers.php)): `app()`, `config()`, `env()`, `route()`, `response()`, `auth()`, `e()`, `hash_make()`, `dispatch()`, `event()`, `log_info()`, `cache()`.

## Important Reminders

- **No Documentation Files**: Do not create .md/README/CHANGELOG files unless explicitly requested
- Use Query Builder/ORM for SQL (automatic parameter binding prevents SQL injection)
- Escape output: `<?= e($userInput) ?>` and include `<?= csrf_field() ?>` in forms
