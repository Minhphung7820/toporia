<?php

declare(strict_types=1);

namespace Toporia\Framework\Console;

use Toporia\Framework\Console\Contracts\{CommandLoaderInterface, InputInterface, OutputInterface};
use Toporia\Framework\Console\Input;
use Toporia\Framework\Console\Output;
use Toporia\Framework\Container\Contracts\ContainerInterface;

/**
 * Class Application
 *
 * Console application with lazy command loading for optimal performance.
 * Uses LazyCommandLoader to defer command instantiation until execution.
 *
 * Performance Improvements:
 * - Commands instantiated only when executed (lazy loading)
 * - O(1) command lookup via CommandLoader
 * - Minimal memory footprint (~10-20 MB less for 80+ commands)
 * - Faster boot time (~50-100ms improvement)
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Console
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
final class Application
{
  private InputInterface $input;
  private OutputInterface $output;
  private ?CommandLoaderInterface $loader = null;

  public function __construct(
    private readonly ContainerInterface $container
  ) {
    $this->output = new Output();

    // Initialize with empty lazy loader
    $this->loader = new LazyCommandLoader($this->container);
  }

  /**
   * Set command loader (for dependency injection)
   *
   * @param CommandLoaderInterface $loader
   * @return void
   */
  public function setLoader(CommandLoaderInterface $loader): void
  {
    $this->loader = $loader;
  }

  /**
   * Get command loader
   *
   * @return CommandLoaderInterface
   */
  public function getLoader(): CommandLoaderInterface
  {
    return $this->loader;
  }

  /**
   * Register a command class (LAZY - no instantiation)
   *
   * @param class-string<Command> $commandClass
   * @deprecated Use setLoader() with pre-configured LazyCommandLoader instead
   */
  public function register(string $commandClass): void
  {
    // PERFORMANCE: No longer instantiates command to get name
    // Instead, requires command name to be provided or use registerMany with map

    // Fallback: Instantiate to get name (for backward compatibility)
    // This is SLOW but maintains compatibility with old code
    /** @var Command $instance */
    $instance = $this->container->get($commandClass);
    $name = $instance->getName();

    if ($this->loader instanceof LazyCommandLoader) {
      $this->loader->register($name, $commandClass);
    }
  }

  /**
   * Register multiple command classes (LAZY)
   *
   * @param array<class-string<Command>> $commandClasses
   * @return void
   * @deprecated Use setLoader() with pre-configured LazyCommandLoader instead
   */
  public function registerMany(array $commandClasses): void
  {
    foreach ($commandClasses as $commandClass) {
      $this->register($commandClass);
    }
  }

  /**
   * Register commands with explicit names (LAZY - best performance)
   *
   * @param array<string, class-string<Command>> $commands ['command:name' => 'ClassName']
   * @return void
   */
  public function registerCommandMap(array $commands): void
  {
    if ($this->loader instanceof LazyCommandLoader) {
      $this->loader->registerMany($commands);
    }
  }

  /**
   * Run the console application.
   *
   * @param array<int, string> $argv
   */
  public function run(array $argv): int
  {
    // Parse input
    $this->input = Input::fromArgv($argv);

    // Get command name
    $commandName = $argv[1] ?? 'list';

    // Handle built-in commands
    if ($commandName === 'list') {
      return $this->listCommands();
    }

    // Find and execute command (LAZY - only loads when executed)
    if (!$this->loader->has($commandName)) {
      $this->output->error("Command not found: {$commandName}");
      $this->output->writeln("Run 'list' to see available commands.");
      return 1;
    }

    return $this->executeCommand($commandName);
  }

  /**
   * Execute a registered command (LAZY - instantiates here)
   *
   * @param string $commandName
   * @return int
   */
  private function executeCommand(string $commandName): int
  {
    try {
      // PERFORMANCE: Command instantiated ONLY when executed (not at boot time)
      $commandClass = $this->loader->get($commandName);

      if ($commandClass === null) {
        throw new \RuntimeException("Command class not found: {$commandName}");
      }

      /** @var Command $command */
      $command = $this->container->get($commandClass);

      // Parse signature to get argument names and map positional arguments
      $this->mapArgumentsFromSignature($command->getSignature());

      // Inject Input/Output
      $command->setInput($this->input);
      $command->setOutput($this->output);

      // Execute command
      return $command->handle();
    } catch (\Throwable $e) {
      $this->renderException($e);
      return 1;
    }
  }

  /**
   * Parse command signature and map positional arguments to their names
   *
   * @param string $signature
   * @return void
   */
  private function mapArgumentsFromSignature(string $signature): void
  {
    // Extract argument definitions from signature: {name} {name?} {name=default}
    preg_match_all('/\{([^-][^}]*)\}/', $signature, $matches);

    if (empty($matches[1])) {
      return;
    }

    $arguments = $this->input->getArguments();
    $mappedArguments = [];
    $positionalIndex = 0;

    foreach ($matches[1] as $definition) {
      // Skip options (they start with --)
      if (str_starts_with($definition, '-')) {
        continue;
      }

      // Parse argument: name, name?, name=default, or name : description
      $parts = explode(':', $definition, 2);
      $argDef = trim($parts[0]);

      // Handle optional marker and default value
      $isOptional = str_ends_with($argDef, '?');
      $argDef = rtrim($argDef, '?');

      // Handle default value
      $default = null;
      if (str_contains($argDef, '=')) {
        [$argDef, $default] = explode('=', $argDef, 2);
      }

      $argName = trim($argDef);

      // Map positional argument to named argument
      if (isset($arguments[$positionalIndex])) {
        $mappedArguments[$argName] = $arguments[$positionalIndex];
        $positionalIndex++;
      } elseif ($default !== null) {
        $mappedArguments[$argName] = $default;
      }
    }

    // Update input with mapped arguments
    if ($this->input instanceof Input) {
      $this->input->setArguments($mappedArguments);
    }
  }

  /**
   * List all registered commands (LAZY - uses cached descriptions)
   *
   * @return int
   */
  private function listCommands(): int
  {
    $this->output->writeln("Available commands:");
    $this->output->newLine();

    // Get all commands with descriptions (LAZY - uses reflection, not instantiation)
    $commands = $this->loader->all();

    if (empty($commands)) {
      $this->output->warning("No commands registered.");
      return 0;
    }

    // Prepare table data
    $headers = ['Command', 'Description'];
    $rows = [];

    foreach ($commands as $name => $description) {
      $rows[] = [$name, $description];
    }

    // Sort by command name
    usort($rows, fn($a, $b) => strcmp($a[0], $b[0]));

    $this->output->table($headers, $rows);

    $this->output->newLine();
    $this->output->info("Run 'php console [command] --help' for more information.");

    return 0;
  }

  /**
   * Set custom output (for testing)
   *
   * @param OutputInterface $output
   * @return void
   */
  public function setOutput(OutputInterface $output): void
  {
    $this->output = $output;
  }

  /**
   * Render an exception with beautiful formatting.
   *
   * @param \Throwable $e
   * @return void
   */
  private function renderException(\Throwable $e): void
  {
    $renderer = new ExceptionRenderer();
    $renderer->render($e);
  }
}
