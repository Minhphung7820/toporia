<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Make;

use Toporia\Framework\Console\Generator\GeneratorCommand;

final class MakeModelCommand extends GeneratorCommand
{
    protected string $signature = 'make:model {name : The name of the model} {--m|migration : Create a new migration file for the model} {--f|factory : Create a new factory for the model} {--s|seed : Create a new seeder file for the model} {--a|all : Generate migration, factory, and seeder}';

    protected string $description = 'Create a new ORM model class';

    protected string $type = 'Model';

    protected function getStub(): string
    {
        return $this->resolveStubPath('model.stub');
    }

    protected function getDefaultNamespace(): string
    {
        return 'App\\Domain';
    }

    protected function buildClass(string $name): string
    {
        $stub = $this->getStubContent();

        $this->replaceNamespace($stub, $name);
        $stub = $this->replaceClass($stub, $name);

        // Generate table name from class name
        $className = $this->getClassName($name);
        $tableName = $this->tableize($className);

        $searches = ['{{ table }}', '{{table}}'];
        $stub = str_replace($searches, $tableName, $stub);

        return $stub;
    }

    protected function afterCreate(string $className, string $path): void
    {
        $baseName = $this->getClassName($className);

        if ($this->option('all') || $this->option('migration')) {
            $this->createMigration($baseName);
        }

        if ($this->option('all') || $this->option('factory')) {
            $this->createFactory($baseName);
        }

        if ($this->option('all') || $this->option('seed')) {
            $this->createSeeder($baseName);
        }
    }

    private function createMigration(string $modelName): void
    {
        $tableName = $this->tableize($modelName);
        $migrationName = "create_{$tableName}_table";

        // Output info - actual creation would need MakeMigrationCommand
        $this->info("Migration [{$migrationName}] should be created.");
    }

    private function createFactory(string $modelName): void
    {
        $factoryName = $modelName . 'Factory';
        $this->info("Factory [{$factoryName}] should be created.");
    }

    private function createSeeder(string $modelName): void
    {
        $seederName = $modelName . 'Seeder';
        $this->info("Seeder [{$seederName}] should be created.");
    }

    private function tableize(string $className): string
    {
        // Remove Model suffix if present
        $name = preg_replace('/Model$/', '', $className);

        // Convert CamelCase to snake_case and pluralize
        $snake = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));

        // Simple pluralization
        if (str_ends_with($snake, 'y')) {
            return substr($snake, 0, -1) . 'ies';
        }

        if (str_ends_with($snake, 's') || str_ends_with($snake, 'x') || str_ends_with($snake, 'ch') || str_ends_with($snake, 'sh')) {
            return $snake . 'es';
        }

        return $snake . 's';
    }
}
