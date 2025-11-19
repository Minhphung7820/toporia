<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Make;

use Toporia\Framework\Console\Generator\GeneratorCommand;

final class MakeCommandCommand extends GeneratorCommand
{
    protected string $signature = 'make:command {name : The name of the command class} {--command= : The terminal command that will be used to invoke the class}';

    protected string $description = 'Create a new console command';

    protected string $type = 'Command';

    protected function getStub(): string
    {
        return dirname(__DIR__, 4) . '/Console/stubs/command.stub';
    }

    protected function getDefaultNamespace(): string
    {
        return 'App\\Presentation\\Console\\Commands';
    }

    protected function buildClass(string $name): string
    {
        $stub = $this->getStubContent();

        $this->replaceNamespace($stub, $name);
        $stub = $this->replaceClass($stub, $name);

        // Replace signature
        $commandName = $this->option('command');
        if (empty($commandName)) {
            $commandName = $this->generateCommandName($name);
        }
        $stub = str_replace(['{{ signature }}', '{{signature}}'], $commandName, $stub);

        // Replace description
        $stub = str_replace(['{{ description }}', '{{description}}'], 'Command description', $stub);

        return $stub;
    }

    private function generateCommandName(string $name): string
    {
        $className = $this->getClassName($name);

        // Remove 'Command' suffix
        $baseName = preg_replace('/Command$/', '', $className);

        // Convert to kebab-case
        $kebab = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $baseName));

        return 'app:' . $kebab;
    }

    protected function getStubContent(): string
    {
        $stubPath = $this->resolveStubPath('command.stub');

        if (!file_exists($stubPath)) {
            throw new \RuntimeException("Stub file not found: {$stubPath}");
        }

        return file_get_contents($stubPath);
    }
}
