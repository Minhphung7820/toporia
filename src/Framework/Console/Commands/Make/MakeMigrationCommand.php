<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Make;

use Toporia\Framework\Console\Command;

final class MakeMigrationCommand extends Command
{
    protected string $signature = 'make:migration {name : The name of the migration} {--create= : The table to be created} {--table= : The table to be modified} {--path= : The location where the migration file should be created}';

    protected string $description = 'Create a new migration file';

    public function handle(): int
    {
        $name = $this->argument('name');

        if (empty($name)) {
            $this->error('Migration name is required.');
            return 1;
        }

        $table = $this->option('create') ?: $this->option('table');
        $create = (bool) $this->option('create');

        // Determine table name from migration name if not provided
        if (empty($table)) {
            if (preg_match('/^create_(\w+)_table$/', $name, $matches)) {
                $table = $matches[1];
                $create = true;
            } elseif (preg_match('/^add_\w+_to_(\w+)_table$/', $name, $matches)) {
                $table = $matches[1];
            } elseif (preg_match('/^remove_\w+_from_(\w+)_table$/', $name, $matches)) {
                $table = $matches[1];
            }
        }

        $stub = $create ? 'migration.stub' : 'migration.update.stub';
        $stubPath = $this->resolveStubPath($stub);

        if (!file_exists($stubPath)) {
            $this->error("Stub file not found: {$stubPath}");
            return 1;
        }

        $stubContent = file_get_contents($stubPath);

        // Generate class name from migration name (e.g., create_users_table -> CreateUsersTable)
        $className = $this->generateClassName($name);

        // Generate description
        $description = $this->generateDescription($name);

        // Replace placeholders
        $stubContent = str_replace(['{{ table }}', '{{table}}'], $table ?: 'table_name', $stubContent);
        $stubContent = str_replace(['{{ class }}', '{{class}}'], $className, $stubContent);
        $stubContent = str_replace(['{{ description }}', '{{description}}'], $description, $stubContent);

        // Generate filename (class name based, not timestamp)
        $filename = "{$className}.php";

        // Determine path
        $path = $this->option('path') ?: $this->getBasePath() . '/database/migrations';

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $filePath = $path . '/' . $filename;

        // Check if file already exists
        if (file_exists($filePath)) {
            $this->error("Migration [{$className}] already exists!");
            return 1;
        }

        if (file_put_contents($filePath, $stubContent) === false) {
            $this->error("Failed to write migration file: {$filePath}");
            return 1;
        }

        $relativePath = str_replace($this->getBasePath() . '/', '', $filePath);
        $this->success("Migration [{$relativePath}] created successfully.");

        return 0;
    }

    /**
     * Generate class name from migration name.
     * E.g., create_users_table -> CreateUsersTable
     */
    private function generateClassName(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }

    /**
     * Generate description from migration name.
     */
    private function generateDescription(string $name): string
    {
        return ucfirst(str_replace('_', ' ', $name)) . '.';
    }

    private function getBasePath(): string
    {
        if (defined('APP_BASE_PATH')) {
            return constant('APP_BASE_PATH');
        }

        return getcwd() ?: dirname(__DIR__, 5);
    }
}
