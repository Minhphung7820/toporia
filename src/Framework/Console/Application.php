<?php

declare(strict_types=1);

namespace Toporia\Framework\Console;

use Toporia\Framework\Console\Contracts\{InputInterface, OutputInterface};
use Toporia\Framework\Console\Input;
use Toporia\Framework\Console\Output;
use Toporia\Framework\Container\Contracts\ContainerInterface;

/**
 * Class Application
 *
 * Minimal command dispatcher that registers console commands by signature,
 * resolves command instances via the container, routes argv to appropriate
 * commands, and injects Input/Output into commands.
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
  /** @var array<string, class-string<Command>> */
  private array $registry = [];

  private InputInterface $input;
  private OutputInterface $output;

  public function __construct(
    private readonly ContainerInterface $container
  ) {
    $this->output = new Output();
  }

  /**
   * Register a command class by its signature.
   *
   * @param class-string<Command> $commandClass
   */
  public function register(string $commandClass): void
  {
    /** @var Command $instance */
    $instance = $this->container->get($commandClass);
    $name = $instance->getName();
    $this->registry[$name] = $commandClass;
  }

  /**
   * Register multiple command classes.
   *
   * @param array<class-string<Command>> $commandClasses
   * @return void
   */
  public function registerMany(array $commandClasses): void
  {
    foreach ($commandClasses as $commandClass) {
      $this->register($commandClass);
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

    // Find and execute command
    if (!isset($this->registry[$commandName])) {
      $this->output->error("Command not found: {$commandName}");
      $this->output->writeln("Run 'list' to see available commands.");
      return 1;
    }

    return $this->executeCommand($commandName);
  }

  /**
   * Execute a registered command
   *
   * @param string $commandName
   * @return int
   */
  private function executeCommand(string $commandName): int
  {
    try {
      /** @var Command $command */
      $command = $this->container->get($this->registry[$commandName]);

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
   * List all registered commands
   *
   * @return int
   */
  private function listCommands(): int
  {
    $this->output->writeln("Available commands:");
    $this->output->newLine();

    if (empty($this->registry)) {
      $this->output->warning("No commands registered.");
      return 0;
    }

    // Prepare table data
    $headers = ['Command', 'Description'];
    $rows = [];

    foreach ($this->registry as $name => $class) {
      /** @var Command $command */
      $command = $this->container->get($class);
      $rows[] = [$name, $command->getDescription()];
    }

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
