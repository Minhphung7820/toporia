# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Toporia is a Clean Architecture PHP framework following SOLID principles. The project emphasizes strict separation of concerns between Framework and Application layers with interface-based design and dependency injection.

## Requirements

- **PHP**: >= 8.1
- **Composer**: For dependency management
- **Optional Extensions**: `ext-redis`, `ext-rdkafka`, `ext-pdo_mysql`, `ext-pdo_pgsql`, `ext-pdo_sqlite`, `ext-mongodb`, `ext-pcntl`, `ext-zip`

## Development Commands

```bash
# Docker Setup (Recommended)
make up                    # Start core services (app, nginx, mysql, redis)
make up-full               # Start all services (includes Kafka, RabbitMQ, Elasticsearch)
make setup                 # Complete setup (install, migrate, seed)
make setup-fresh           # Fresh setup (down, up, install, migrate, seed)
make shell                 # Access PHP container shell
make logs                  # Show all logs (follow mode)

# Local PHP Setup
composer install && cp .env.example .env && php console key:generate
php -S localhost:8000 -t public

# Frontend (Vue 3 SPA)
pnpm install && pnpm run dev     # Development with HMR
pnpm run build                   # Production build

# Testing
composer test                              # Run all tests
composer test:unit                         # Unit tests only
composer test:feature                      # Feature tests only
./vendor/bin/phpunit tests/Unit/SomeTest.php       # Single test file
./vendor/bin/phpunit --filter testMethodName       # Single test method

# Code Generation
php console make:controller Name
php console make:model Name
php console make:migration name
php console make:handler Name
php console make:job Name
php console make:repository Name
php console list                           # List ALL available commands

# Database
php console migrate
php console migrate:fresh
php console db:seed

# Queue & Cache
php console queue:work
php console schedule:run
php console cache:clear
php console optimize
```

**Test Environment**: Tests use `toporia_test` database (configured in phpunit.xml) with sync queue and array cache drivers for isolation.

## Architecture

### Layer Structure

```
app/                      # Application code (App\ namespace)
├── Domain/              # Pure business entities and repository interfaces (NO framework deps)
├── Application/         # Use cases (Commands/Handlers), Services, Rules, Observers
├── Infrastructure/      # Repository implementations, Providers, Jobs, Notifications
└── Presentation/        # Controllers, Middleware, Views, Console Commands

packages/framework/src/   # Framework code (Toporia\Framework\ namespace)
├── Container/           # DI container
├── Routing/             # Router
├── Http/                # Request/Response
├── Database/            # ORM, Query Builder, Migrations
└── ...                  # Events, Console, Bus, Queue, Cache, Auth, Validation, Log, Realtime, Search

resources/js/            # Vue 3 SPA (Composition API, Vue Router 4, Pinia, Vite)
```

### Key Files

- [public/index.php](public/index.php) → [bootstrap/app.php](bootstrap/app.php) - Entry point and bootstrap
- [bootstrap/helpers.php](bootstrap/helpers.php) - Global helper functions (50+ helpers)
- [routes/web.php](routes/web.php), [routes/api.php](routes/api.php) - Routes
- [routes/terminal.php](routes/terminal.php) - Closure-based console commands
- [config/middleware.php](config/middleware.php), [config/commands.php](config/commands.php), [config/observers.php](config/observers.php) - Configuration

## Key Patterns

### Service Provider Pattern

```php
class MyServiceProvider extends ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(MyService::class, fn() => new MyService());
    }

    public function boot(ContainerInterface $container): void
    {
        // Safe to resolve services here
    }
}
```

Register providers in [bootstrap/app.php](bootstrap/app.php).

### Routing

```php
$router->get('/products', [ProductsController::class, 'index']);
$router->get('/products/{id}', [ProductsController::class, 'show']);
$router->post('/products', CreateProductAction::class); // ADR style

$router->group(['prefix' => 'admin', 'middleware' => ['auth']], function ($router) {
    $router->get('/users', [AdminController::class, 'users']);
});
```

**Route Loading Order**: API routes → Webhook routes → Socialite routes → Web routes (SPA catch-all last).

### Command Bus Pattern

```php
// Command - Simple DTO
final class SendWelcomeEmail
{
    public function __construct(
        public readonly string $email,
        public readonly string $name
    ) {}
}

// Handler - Auto-wired dependencies (naming: CommandName => CommandNameHandler)
final class SendWelcomeEmailHandler
{
    public function __construct(private MailerInterface $mailer) {}

    public function __invoke(SendWelcomeEmail $command): void
    {
        $this->mailer->send(to: $command->email, subject: 'Welcome!', body: "Hello {$command->name}");
    }
}

// Dispatch
dispatch(new SendWelcomeEmail('user@example.com', 'John'));
```

### ORM Models

```php
class ProductModel extends Model
{
    protected static string $table = 'products';
    protected static array $fillable = ['title', 'price', 'sku'];
    protected static array $casts = ['price' => 'float', 'is_active' => 'bool'];
}

$product = ProductModel::create(['title' => 'Laptop', 'price' => 999.99]);
$products = ProductModel::where('price', '>', 500)->get();
$users = UserModel::with(['posts', 'profile'])->get(); // Eager loading
```

**Toporia ORM relationships**: `hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, `hasOneThrough`, `hasManyThrough`, `morphOne`, `morphMany`, `morphTo`, `morphToMany`, `morphedByMany`, `whereHas`, `whereDoesntHave`, `withCount`, `withSum`, `withAvg`, `withMin`, `withMax`, `withPivot`.

### Console Commands

**Class-based** (register in [config/commands.php](config/commands.php)):
```php
final class MyCommand extends Command
{
    protected string $signature = 'my:command';
    protected string $description = 'Description';
    public function handle(): int { $this->info('Processing...'); return 0; }
}
```

**Closure-based** (in [routes/terminal.php](routes/terminal.php)):
```php
Terminal::command('mail:send {user}', function (string $user) {
    $this->info("Sending email to: {$user}");
})->describe('Send marketing email');
```

## Code Conventions

- All files use `declare(strict_types=1)`
- Namespace structure mirrors directory structure
- One class per file
- Interfaces end with `Interface` suffix
- Abstract classes start with `Abstract` prefix
- Repository interfaces in Domain, implementations in Infrastructure

### CRITICAL: This is NOT Laravel

This is **Toporia**, a custom framework. Do NOT use Laravel components, helpers, facades, or import from `Illuminate\*` or `Laravel\*` namespaces.

**Toporia framework namespaces**:
- `Toporia\Framework\Container\Container`
- `Toporia\Framework\Routing\Router`
- `Toporia\Framework\Database\ORM\Model`
- `Toporia\Framework\Database\QueryBuilder`

### Namespace Import Convention

**CRITICAL**: Always import classes at the top of files. Never use full namespaces in executable code.

```php
// CORRECT
use App\Services\Sync\ProductSyncService;
ProductSyncService::handle();

// WRONG
\App\Services\Sync\ProductSyncService::handle();
```

### Framework-First Development

1. Inspect existing project structure for similar classes
2. Check existing base classes, abstract classes, interfaces, and traits
3. Follow the same patterns as existing implementations
4. Never create files in arbitrary locations
5. Use framework features (Queue, Event, Job, Repository, etc.) - don't reinvent them

**Rule**: Always adapt new code to the framework, never force the framework to adapt to new code.

### Variable Shadowing Prevention

**CRITICAL**: Do NOT shadow variables. Never reuse outer-scope variable names inside foreach loops, closures, or nested scopes.

```php
// If $constraints exists in outer scope, use different names inside:
$nestedConstraintList, $childItems, $relationConstraints, $mergedConstraints
```

## Optional Packages

Packages in `packages/` directory, installed via composer path repositories:

- **Socialite** (`toporia/socialite`) - OAuth authentication - [docs/SOCIALITE.md](docs/SOCIALITE.md)
- **Webhook** (`toporia/webhook`) - Webhook handling - [docs/WEBHOOK.md](docs/WEBHOOK.md)
- **Tabula** (`toporia/tabula`) - High-performance Excel/CSV import/export
- **MongoDB** (`toporia/mongodb`) - MongoDB ODM (requires `ext-mongodb` + `mongodb/mongodb` package)
- **Audit** (`toporia/audit`) - Audit logging for models
- **API Versioning** (`toporia/api-versioning`) - API versioning support
- **Tenancy** (`toporia/tenancy`) - Multi-tenancy support
- **Dominion** (`toporia/dominion`) - Utility helpers
- **Docura** (`toporia/docura`) - Documentation utilities

**Installation**: `composer require toporia/package-name` → `php console vendor:publish --tag=package-routes`

## Documentation

Extensive documentation in [docs/](docs/): BUS.md, orm-advanced-features.md, MIGRATION.md, QUEUE_GUIDE.md, SCHEDULE.md, FORM_REQUEST.md, TESTING.md, FACTORY_SEEDER_GUIDE.md, DOCKER.md, and more.

## Helper Functions

Common helpers: `app()`, `config()`, `env()`, `route()`, `response()`, `auth()`, `e()`, `hash_make()`, `dispatch()`, `event()`, `log_info()`, `cache()`.

## Important Reminders

- **No Documentation Files**: Do not create .md/README/CHANGELOG files unless explicitly requested
- Use Query Builder/ORM for SQL (automatic parameter binding prevents SQL injection)
- Escape output: `<?= e($userInput) ?>` and include `<?= csrf_field() ?>` in forms
