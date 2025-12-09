# Console::call() - Guide

## 📚 Overview

Toporia Framework supports programmatic command execution from PHP code.

Use `Console::call()` to run any console command programmatically.

---

## 🚀 Basic Usage

```php
use Toporia\Framework\Support\Facades\Console;

// Simple command
$exitCode = Console::call('migrate');

// With options
$exitCode = Console::call('cache:clear', ['--force' => true]);

// With arguments and options
$exitCode = Console::call('user:create', [
    'name' => 'John Doe',
    '--admin' => true,
    '--email' => 'john@example.com'
]);

// Check exit code
if ($exitCode === 0) {
    echo "Command succeeded!";
} else {
    echo "Command failed with code: $exitCode";
}
```

---

## 📝 Silent Execution (Capture Output)

```php
use Toporia\Framework\Support\Facades\Console;

// Call command and capture output as string
$output = Console::callSilent('route:list');
echo $output; // Shows route list
```

---

## 🎯 Real-World Examples

### Example 1: Clear Cache After Deployment

```php
use Toporia\Framework\Support\Facades\Console;

public function deploy()
{
    // Clear all caches
    Console::call('cache:clear');
    Console::call('config:cache');
    Console::call('route:cache');
    Console::call('view:cache');

    return response()->json(['message' => 'Deployed successfully']);
}
```

---

### Example 2: Run Migration in Controller

```php
use Toporia\Framework\Support\Facades\Console;

public function runMigration()
{
    $exitCode = Console::call('migrate', ['--force' => true]);

    if ($exitCode === 0) {
        return response()->json(['message' => 'Migration completed']);
    }

    return response()->json(['error' => 'Migration failed'], 500);
}
```

---

### Example 3: Queue Job Processing

```php
use Toporia\Framework\Support\Facades\Console;

public function processQueue()
{
    // Process 100 jobs then stop
    Console::call('queue:work', [
        '--max-jobs' => 100,
        '--stop-when-empty' => true
    ]);
}
```

---

### Example 4: Schedule Task Execution

```php
use Toporia\Framework\Support\Facades\Console;

public function runScheduledTasks()
{
    // Run all due scheduled tasks
    $exitCode = Console::call('schedule:run');

    return response()->json([
        'executed' => $exitCode === 0,
        'exit_code' => $exitCode
    ]);
}
```

---

### Example 5: Generate Report

```php
use Toporia\Framework\Support\Facades\Console;

public function generateReport()
{
    // Call custom command
    Console::call('report:generate', [
        'type' => 'sales',
        '--month' => date('Y-m'),
        '--format' => 'pdf'
    ]);

    return response()->download(storage_path('reports/sales.pdf'));
}
```

---

### Example 6: Capture Command Output

```php
use Toporia\Framework\Support\Facades\Console;

public function getSystemInfo()
{
    // Get output as string
    $about = Console::callSilent('about');
    $routes = Console::callSilent('route:list');

    return response()->json([
        'about' => $about,
        'routes' => $routes
    ]);
}
```

---

## 🔧 Advanced Usage

### Custom Output Handler

```php
use Toporia\Framework\Support\Facades\Console;
use Toporia\Framework\Console\Output;

// Create custom output handler
$output = new class extends Output {
    public array $messages = [];

    public function write(string $message, bool $newline = false): void
    {
        $this->messages[] = $message;
        parent::write($message, $newline);
    }
};

// Call command with custom output
Console::call('migrate', [], $output);

// Access captured messages
foreach ($output->messages as $message) {
    echo $message . "\n";
}
```

---

### Error Handling

```php
use Toporia\Framework\Support\Facades\Console;

try {
    $exitCode = Console::call('migrate');

    if ($exitCode !== 0) {
        throw new \RuntimeException("Migration failed with code: $exitCode");
    }

    echo "Migration successful!";
} catch (\InvalidArgumentException $e) {
    echo "Command not found: " . $e->getMessage();
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}
```

---

## 📊 Parameter Formats

### Arguments (Positional)

```php
// Positional arguments
Console::call('user:create', [
    'John Doe',           // First argument
    'john@example.com'    // Second argument
]);
```

### Named Arguments

```php
// Named arguments
Console::call('user:create', [
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);
```

### Boolean Options

```php
// Boolean flags
Console::call('migrate', [
    '--force' => true,      // Adds --force
    '--pretend' => false    // Omits --pretend
]);
```

### Options with Values

```php
// Options with values
Console::call('queue:work', [
    '--queue' => 'default',
    '--max-jobs' => 100,
    '--sleep' => 3
]);
```

### Mixed Parameters

```php
// Mix of arguments and options
Console::call('db:seed', [
    '--class' => 'UserSeeder',
    '--force' => true
]);
```

---

## 🎓 Features

| Feature | Status | Description |
|---------|--------|-------------|
| `Console::call()` | ✅ | Execute command and get exit code |
| `Console::callSilent()` | ✅ | Capture command output as string |
| Boolean options | ✅ | `['--force' => true]` |
| Named arguments | ✅ | `['name' => 'value']` |
| Custom output | ✅ | Pass OutputInterface |
| Exit codes | ✅ | 0 = success, non-zero = error |

---

## 🧪 Testing

```php
use Toporia\Framework\Support\Facades\Console;

// In your test
public function testCommandExecution()
{
    $exitCode = Console::call('migrate');

    $this->assertEquals(0, $exitCode);
}

public function testCommandOutput()
{
    $output = Console::callSilent('about');

    $this->assertStringContains('Toporia', $output);
}
```

---

## ⚡ Performance

- **Lazy Loading:** Commands instantiated only when called
- **No Overhead:** Direct execution, no subprocess spawning
- **Fast:** ~10-50ms per command call
- **Memory Efficient:** Shares same PHP process

---

## 🔒 Security Considerations

### ⚠️ Never Call Commands with User Input Directly

**Bad:**
```php
// DANGEROUS! User can execute any command
$command = $_GET['command'];
Console::call($command);
```

**Good:**
```php
// Safe: Whitelist allowed commands
$allowedCommands = ['cache:clear', 'route:cache'];

if (in_array($command, $allowedCommands)) {
    Console::call($command);
} else {
    throw new \InvalidArgumentException('Command not allowed');
}
```

---

## 📚 Available Methods

### Console::call()

```php
/**
 * Call a console command programmatically
 *
 * @param string $commandName Command name
 * @param array $parameters Arguments and options
 * @param OutputInterface|null $output Custom output
 * @return int Exit code (0 = success)
 */
Console::call(string $commandName, array $parameters = [], ?OutputInterface $output = null): int
```

### Console::callSilent()

```php
/**
 * Call command and capture output as string
 *
 * @param string $commandName Command name
 * @param array $parameters Arguments and options
 * @return string Command output
 */
Console::callSilent(string $commandName, array $parameters = []): string
```

---

## 🎯 Use Cases

| Use Case | Method | Example |
|----------|--------|---------|
| Clear cache after deploy | `Console::call()` | `Console::call('cache:clear')` |
| Run migrations | `Artisan::call()` | `Artisan::call('migrate')` |
| Process queue | `Console::call()` | `Console::call('queue:work')` |
| Generate reports | `Console::call()` | `Console::call('report:generate')` |
| Get system info | `Console::callSilent()` | `Console::callSilent('about')` |
| Seed database | `Artisan::call()` | `Artisan::call('db:seed')` |

---

## 📁 Files

- **Application:** `src/Framework/Console/Application.php`
- **Console Facade:** `src/Framework/Support/Facades/Console.php`
- **Documentation:** `docs/CONSOLE_CALL_GUIDE.md`

---

## ✅ Summary

Toporia Framework provides powerful programmatic command execution:

- ✅ `Console::call()` - Execute any command
- ✅ `Console::callSilent()` - Capture output
- ✅ Arguments & options support
- ✅ Custom output handlers
- ✅ Error handling
- ✅ Fast and efficient

**You can now call any console command from PHP code!** 🎉

---

**Last Updated:** 2025-12-09
**Framework:** Toporia v1.0

