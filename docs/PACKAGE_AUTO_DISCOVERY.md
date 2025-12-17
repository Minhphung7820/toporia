# Package Auto-Discovery System

Toporia Framework features a comprehensive **Package Auto-Discovery System** that automatically discovers and registers package resources without manual configuration - inspired by Laravel's package auto-discovery but built from scratch with performance and flexibility in mind.

## Table of Contents

- [Overview](#overview)
- [What Gets Auto-Discovered](#what-gets-auto-discovered)
- [Package Configuration](#package-configuration)
- [How It Works](#how-it-works)
- [Creating a Package](#creating-a-package)
- [Route Auto-Discovery](#route-auto-discovery)
- [Middleware Auto-Discovery](#middleware-auto-discovery)
- [Command Auto-Discovery](#command-auto-discovery)
- [Performance](#performance)
- [Advanced Features](#advanced-features)

## Overview

The auto-discovery system eliminates the need for manual package setup. When you install a Toporia package via Composer, the framework automatically:

✅ **Discovers and registers** service providers
✅ **Loads and merges** configuration files
✅ **Registers** database migration paths
✅ **Loads** package routes with middleware and prefixes
✅ **Merges** middleware groups and aliases
✅ **Registers** console commands
✅ **Registers** view paths and namespaces
✅ **Creates** facade aliases

**Zero configuration required from the end user!**

## What Gets Auto-Discovered

### 1. Service Providers
```json
{
    "extra": {
        "toporia": {
            "providers": [
                "Vendor\\Package\\PackageServiceProvider"
            ]
        }
    }
}
```

Providers are automatically registered in the application container.

### 2. Configuration Files
```json
{
    "extra": {
        "toporia": {
            "config": {
                "package": "config/package.php"
            }
        }
    }
}
```

Config files are merged into the application config and accessible via `config('package')`.

### 3. Database Migrations
```json
{
    "extra": {
        "toporia": {
            "migrations": [
                "database/migrations"
            ]
        }
    }
}
```

Migration directories are auto-detected. The `php console migrate` command automatically runs migrations from all packages.

### 4. Routes
```json
{
    "extra": {
        "toporia": {
            "routes": [
                {
                    "path": "routes/web.php",
                    "middleware": ["web"],
                    "prefix": "admin",
                    "namespace": "Vendor\\Package\\Controllers"
                }
            ]
        }
    }
}
```

Routes are loaded with specified middleware, prefix, and namespace.

### 5. Middleware
```json
{
    "extra": {
        "toporia": {
            "middleware": {
                "groups": {
                    "web": ["Vendor\\Package\\Middleware\\WebMiddleware"]
                },
                "aliases": {
                    "package.auth": "Vendor\\Package\\Middleware\\AuthMiddleware"
                }
            }
        }
    }
}
```

Middleware is merged into application middleware groups and aliases.

### 6. Console Commands
```json
{
    "extra": {
        "toporia": {
            "commands": [
                "Vendor\\Package\\Console\\PackageCommand"
            ]
        }
    }
}
```

Commands are registered and available immediately via `php console`.

### 7. Views
```json
{
    "extra": {
        "toporia": {
            "views": {
                "paths": ["resources/views"],
                "namespaces": {
                    "package": "resources/views"
                }
            }
        }
    }
}
```

View paths are added to the view finder, and namespaces enable `view('package::component')` syntax.

**Auto-discovery**: If `resources/views` directory exists and views not configured, framework automatically registers it with package namespace.

### 8. Facade Aliases
```json
{
    "extra": {
        "toporia": {
            "aliases": {
                "PackageService": "Vendor\\Package\\PackageService"
            }
        }
    }
}
```

Aliases allow convenient access to services: `app('PackageService')`.

## Package Configuration

### Basic Structure

```json
{
    "name": "vendor/package-name",
    "description": "Package description",
    "type": "library",
    "require": {
        "php": ">=8.1"
    },
    "autoload": {
        "psr-4": {
            "Vendor\\Package\\": "src/"
        }
    },
    "extra": {
        "toporia": {
            "providers": ["Vendor\\Package\\PackageServiceProvider"],
            "config": {
                "package": "config/package.php"
            },
            "routes": [
                {
                    "path": "routes/web.php",
                    "middleware": ["web"],
                    "prefix": "package"
                }
            ],
            "middleware": {
                "groups": {
                    "web": ["Vendor\\Package\\Middleware\\PackageMiddleware"]
                },
                "aliases": {
                    "package.check": "Vendor\\Package\\Middleware\\CheckMiddleware"
                }
            },
            "commands": ["Vendor\\Package\\Console\\PackageCommand"],
            "migrations": ["database/migrations"],
            "views": {
                "paths": ["resources/views"],
                "namespaces": {
                    "package": "resources/views"
                }
            },
            "aliases": {
                "Package": "Vendor\\Package\\PackageFacade"
            }
        }
    }
}
```

## How It Works

### 1. Package Discovery

When `composer install` or `composer update` runs:

```
composer install/update
    ↓
PackageDiscovery scans:
    - vendor/composer/installed.json (vendor packages)
    - packages/*/composer.json (local packages)
    ↓
Extracts extra.toporia config from each package
    ↓
Builds PackageManifest
    ↓
Cached to bootstrap/cache/packages.php
```

### 2. Manifest Caching

The discovered package metadata is cached for **O(1) lookup**:

```php
// bootstrap/cache/packages.php
return [
    'providers' => [...],
    'config' => [...],
    'routes' => [...],
    'middleware' => [...],
    'commands' => [...],
    'migrations' => [...],
    'aliases' => [...],
];
```

**Rebuild triggers:**
- Manifest file doesn't exist
- `composer.lock` modified time > manifest modified time

### 3. Auto-Loading

During application bootstrap:

```
Application Bootstrap
    ↓
[1] LoadConfiguration
    ├─ Merge package configs
    └─ Merge package middleware
    ↓
[2] RegisterProviders
    └─ Load package providers from manifest
    ↓
[3] BootProviders
    ├─ RouteServiceProvider loads package routes
    └─ ConsoleServiceProvider loads package commands
```

## Creating a Package

### Step 1: Package Structure

```
vendor/package-name/
├── composer.json
├── src/
│   ├── PackageServiceProvider.php
│   ├── PackageService.php
│   ├── Console/
│   │   └── PackageCommand.php
│   ├── Middleware/
│   │   └── PackageMiddleware.php
│   └── Controllers/
│       └── PackageController.php
├── config/
│   └── package.php
├── routes/
│   ├── web.php
│   └── api.php
├── database/
│   └── migrations/
│       └── 2025_01_01_000000_create_package_table.php
└── README.md
```

### Step 2: composer.json

```json
{
    "name": "vendor/package-name",
    "description": "An awesome Toporia package",
    "type": "library",
    "license": "MIT",
    "authors": [
        {
            "name": "Your Name",
            "email": "you@example.com"
        }
    ],
    "require": {
        "php": ">=8.1"
    },
    "autoload": {
        "psr-4": {
            "Vendor\\Package\\": "src/"
        }
    },
    "extra": {
        "toporia": {
            "providers": [
                "Vendor\\Package\\PackageServiceProvider"
            ],
            "config": {
                "package": "config/package.php"
            },
            "routes": [
                {
                    "path": "routes/web.php",
                    "middleware": ["web"],
                    "prefix": "package",
                    "namespace": "Vendor\\Package\\Controllers"
                },
                {
                    "path": "routes/api.php",
                    "middleware": ["api"],
                    "prefix": "api/package",
                    "namespace": "Vendor\\Package\\Controllers"
                }
            ],
            "middleware": {
                "groups": {
                    "web": ["Vendor\\Package\\Middleware\\PackageMiddleware"]
                },
                "aliases": {
                    "package.check": "Vendor\\Package\\Middleware\\CheckMiddleware"
                }
            },
            "commands": [
                "Vendor\\Package\\Console\\PackageCommand"
            ],
            "aliases": {
                "Package": "Vendor\\Package\\PackageService"
            }
        }
    }
}
```

### Step 3: Service Provider

```php
<?php

declare(strict_types=1);

namespace Vendor\Package;

use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;

class PackageServiceProvider extends ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        // Register services
        $container->singleton(PackageService::class, function (ContainerInterface $c) {
            $config = $c->has('config')
                ? $c->get('config')->get('package', [])
                : [];

            return new PackageService($config);
        });

        // Register facade alias
        $container->bind('Package', fn($c) => $c->get(PackageService::class));
    }

    public function boot(ContainerInterface $container): void
    {
        // Package is ready
        // Routes, middleware, commands are auto-loaded by framework
    }
}
```

### Step 4: Rebuild Manifest

After creating your package:

```bash
composer dump-autoload
php console package:discover
```

## Route Auto-Discovery

### Simple Route File

```json
{
    "routes": ["routes/web.php"]
}
```

Routes are loaded as-is without any grouping.

### Route with Middleware and Prefix

```json
{
    "routes": [
        {
            "path": "routes/web.php",
            "middleware": ["web", "auth"],
            "prefix": "admin",
            "namespace": "Vendor\\Package\\Controllers",
            "name": "admin."
        }
    ]
}
```

Results in:
```php
$router->group([
    'middleware' => ['web', 'auth'],
    'prefix' => 'admin',
    'namespace' => 'Vendor\\Package\\Controllers',
    'name' => 'admin.',
], function ($router) {
    require 'routes/web.php';
});
```

### Multiple Route Files

```json
{
    "routes": [
        {
            "path": "routes/web.php",
            "middleware": ["web"],
            "prefix": "package"
        },
        {
            "path": "routes/api.php",
            "middleware": ["api"],
            "prefix": "api/package"
        }
    ]
}
```

### Auto-Discovery from `routes/` Directory

If you don't specify routes in `composer.json`, the framework auto-detects:
- `routes/web.php` → loaded with `web` middleware
- `routes/api.php` → loaded with `api` middleware, `api` prefix

## Middleware Auto-Discovery

### Middleware Groups

Add middleware to existing groups:

```json
{
    "middleware": {
        "groups": {
            "web": [
                "Vendor\\Package\\Middleware\\TrackVisits",
                "Vendor\\Package\\Middleware\\CheckSubscription"
            ],
            "api": [
                "Vendor\\Package\\Middleware\\ApiRateLimiter"
            ]
        }
    }
}
```

These are appended to application middleware groups.

### Middleware Aliases

Create named middleware shortcuts:

```json
{
    "middleware": {
        "aliases": {
            "package.auth": "Vendor\\Package\\Middleware\\Authenticate",
            "package.admin": "Vendor\\Package\\Middleware\\RequireAdmin"
        }
    }
}
```

Usage in routes:
```php
$router->get('/admin', [Controller::class, 'index'])
    ->middleware(['package.auth', 'package.admin']);
```

### Merge Behavior

- **Groups**: Package middleware is **appended** to existing groups
- **Aliases**: Package aliases **don't override** application aliases

## Command Auto-Discovery

### Registering Commands

```json
{
    "commands": [
        "Vendor\\Package\\Console\\SyncCommand",
        "Vendor\\Package\\Console\\ReportCommand"
    ]
}
```

### Command Class

```php
<?php

namespace Vendor\Package\Console;

use Toporia\Framework\Console\Command;

class SyncCommand extends Command
{
    protected string $signature = 'package:sync {--force}';
    protected string $description = 'Sync package data';

    public function handle(): int
    {
        $this->info('Syncing package data...');

        // Command logic here

        return self::SUCCESS;
    }
}
```

### How It Works

1. Framework extracts command `$signature` via Reflection
2. Command name is parsed from signature (e.g., `package:sync`)
3. Command is registered with `LazyCommandLoader` for lazy instantiation
4. Available immediately via `php console package:sync`

## View Auto-Discovery

### Simple View Paths

Register view directories to be searched globally:

```json
{
    "views": {
        "paths": ["resources/views"]
    }
}
```

All views in these paths are available via `view('template')`.

### View Namespaces

Create namespaced views for package isolation:

```json
{
    "views": {
        "namespaces": {
            "admin": "resources/views/admin",
            "package": "resources/views"
        }
    }
}
```

Usage:
```php
// Render view with namespace
return view('package::dashboard');
return view('admin::users.index');
```

### Combined Configuration

```json
{
    "views": {
        "paths": ["resources/views"],
        "namespaces": {
            "pkg": "resources/views/components"
        }
    }
}
```

This registers views both globally AND with a namespace.

### Auto-Discovery from `resources/views` Directory

If you don't specify views in `composer.json`, the framework auto-detects:
- `resources/views` → registered with package namespace (from `composer.json` name)

Example: Package `vendor/my-package` automatically gets namespace `my-package`:
```php
view('my-package::component')
```

### ViewFactory Integration

Package views are integrated into ViewFactory during boot:

```php
// In ViewServiceProvider::boot()
$viewFactory->addLocation($path);          // Add global path
$viewFactory->addNamespace($ns, $path);    // Add namespaced path
```

Performance: O(N) where N = number of view paths across all packages.

## Performance

### Manifest Caching

- **First Request**: O(N) discovery (scans all packages)
- **Subsequent Requests**: O(1) lookup (from cached manifest)
- **Cache Invalidation**: Automatic when `composer.lock` changes

### Lazy Loading

- **Routes**: Loaded only during HTTP requests
- **Commands**: Instantiated only when executed
- **Configs**: Loaded on first access
- **Providers**: Booted only once per request

### Benchmarks

| Operation | Time | Memory |
|-----------|------|--------|
| Manifest Build | ~3-5ms | ~1MB |
| Manifest Load | ~0.1ms | Negligible |
| Route Loading | ~0.5ms per file | ~100KB |
| Command Registration | ~0.01ms per command | Negligible |

## Advanced Features

### Deferred Providers

Defer provider loading until service is actually needed:

```php
class PackageServiceProvider extends ServiceProvider
{
    protected bool $defer = true;

    public function provides(): array
    {
        return [
            PackageService::class,
            'Package',
        ];
    }
}
```

### Publishing Assets

Allow users to publish package resources:

```php
public function boot(ContainerInterface $container): void
{
    $this->publishes([
        __DIR__ . '/../config/package.php' => 'config/package.php',
        __DIR__ . '/../resources/views' => 'resources/views/vendor/package',
    ], 'package-config');
}
```

Users run: `php console vendor:publish --tag=package-config`

### Conditional Registration

Register resources conditionally:

```php
public function boot(ContainerInterface $container): void
{
    if ($this->app->environment('production')) {
        // Production-only registration
    }
}
```

## Troubleshooting

### Package Not Discovered

**Problem**: Package resources not loaded after `composer install`.

**Solution**:
```bash
# Rebuild package manifest
php console package:discover

# Or clear cache
rm bootstrap/cache/packages.php
php console package:discover
```

### Route Conflicts

**Problem**: Package routes conflict with application routes.

**Solution**: Use specific prefixes in package route configuration:
```json
{
    "routes": [
        {
            "path": "routes/web.php",
            "prefix": "my-package"
        }
    ]
}
```

### Middleware Not Applied

**Problem**: Package middleware doesn't seem to run.

**Solution**:
1. Verify middleware is in `extra.toporia.middleware`
2. Clear config cache: `php console config:clear`
3. Check middleware class exists and is autoloaded

### Command Not Found

**Problem**: `php console package:command` says "Command not found".

**Solution**:
1. Ensure command is in `extra.toporia.commands`
2. Verify command has `protected string $signature` property
3. Rebuild manifest: `php console package:discover`

## Best Practices

### 1. Use Specific Prefixes

Always use package-specific route prefixes to avoid conflicts:
```json
{
    "routes": [{"path": "routes/web.php", "prefix": "my-package"}]
}
```

### 2. Namespace Middleware Aliases

Prefix middleware aliases with package name:
```json
{
    "middleware": {
        "aliases": {
            "mypackage.auth": "Vendor\\Package\\Middleware\\Auth"
        }
    }
}
```

### 3. Document Required Configuration

If your package needs configuration, document it clearly:
```php
// config/package.php
return [
    'api_key' => env('PACKAGE_API_KEY'), // Required
    'cache_ttl' => env('PACKAGE_CACHE_TTL', 3600),
];
```

### 4. Version Your Migrations

Use semantic versioning in migration filenames:
```
2025_01_01_000000_create_package_v1_tables.php
2025_02_01_000000_add_fields_to_package_v2.php
```

### 5. Test Package in Isolation

Test your package in a fresh Toporia installation to ensure auto-discovery works correctly.

## Example: Complete Package

See `/packages/example-package/` for a complete reference implementation demonstrating all auto-discovery features:

- Service Provider
- Routes (Web & API)
- Middleware (Groups & Aliases)
- Console Commands
- Configuration
- Migrations
- Facade Aliases

## Related Documentation

- [Creating Packages](CREATING_PACKAGES.md)
- [Service Providers](SERVICE_PROVIDERS.md)
- [Routing Guide](ROUTING.md)
- [Middleware](MIDDLEWARE.md)
- [Console Commands](CONSOLE.md)
