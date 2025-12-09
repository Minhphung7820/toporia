# Command Loading - Quick Guide

## 📚 Overview

Toporia Framework uses **lazy loading** for console commands, similar to Laravel. Commands are instantiated only when executed, not at boot time.

---

## 🚀 Adding New Commands

### Method 1: Config File (Recommended)

Edit `config/commands.php`:

```php
return [
    'my:command' => App\Presentation\Console\Commands\MyCommandClass::class,
    'user:create' => App\Presentation\Console\Commands\CreateUserCommand::class,
];
```

**Benefits:**
- ✅ Explicit command names
- ✅ Better performance
- ✅ Centralized configuration

---

### Method 2: Auto-Discovery (Development Only)

Enable in `config/commands.php`:

```php
'auto_discovery' => [
    'enabled' => true,
    'paths' => [base_path('src/App/Presentation/Console/Commands')],
    'namespaces' => ['App\\Presentation\\Console\\Commands'],
],
```

**Warning:** Slower than explicit registration. Use only in development.

---

## 📦 Command Structure

### Core Commands (Framework)
- Registered in `ConsoleServiceProvider`
- Examples: `migrate`, `cache:clear`, `queue:work`
- **Location:** `src/Framework/Console/Commands/`

### Application Commands (Business Logic)
- Registered in `config/commands.php`
- Examples: `export:excel`, `kafka:topics`
- **Location:** `src/App/Presentation/Console/Commands/`

---

## 🎯 Best Practices

1. **Use explicit registration** in production (faster)
2. **Use auto-discovery** only in development
3. **Keep commands thin** - delegate to services
4. **Follow naming convention:** `namespace:action` (e.g., `user:create`)

---

## 📊 Performance

| Method | Boot Time | Memory | When to Use |
|--------|-----------|--------|-------------|
| **Explicit Config** | Fast (150ms) | Low (12 MB) | ✅ Production |
| **Auto-Discovery** | Slow (250ms) | Medium (15 MB) | 🔧 Development |

---

## 🔧 Command Template

```php
<?php

namespace App\Presentation\Console\Commands;

use Toporia\Framework\Console\Command;

final class MyCommand extends Command
{
    protected string $signature = 'my:command {arg} {--option}';
    protected string $description = 'My command description';

    public function handle(): int
    {
        $arg = $this->argument('arg');
        $option = $this->option('option');

        $this->info('Command executed!');

        return 0; // Success
    }
}
```

---

## 🧪 Testing

```bash
# List all commands
php console list

# Run specific command
php console my:command

# Check performance
time php console list
```

---

## 📚 Related Files

- **Application Commands:** `config/commands.php`
- **Framework Commands:** `src/Framework/Providers/ConsoleServiceProvider.php`
- **Command Loader:** `src/Framework/Console/LazyCommandLoader.php`
- **Full Documentation:** `docs/COMMAND_LOADING_OPTIMIZATION.md`

---

**Quick Tip:** Always register commands in `config/commands.php` for best performance!

