<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Make;

use Toporia\Framework\Console\Command;

final class MakeSeederCommand extends Command
{
    protected string $signature = 'make:seeder {name : The name of the seeder}';

    protected string $description = 'Create a new database seeder class';

    public function handle(): int
    {
        $name = $this->argument('name');

        if (empty($name)) {
            $this->error('Seeder name is required.');
            return 1;
        }

        // Ensure it ends with Seeder
        $baseName = $name;
        if (!str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }

        $stubPath = $this->resolveStubPath('seeder.stub');

        if (!file_exists($stubPath)) {
            $this->error("Stub file not found: {$stubPath}");
            return 1;
        }

        $stubContent = file_get_contents($stubPath);

        // Guess factory and table name from seeder name
        $factoryName = $this->guessFactoryName($baseName);
        $tableName = $this->guessTableName($baseName);
        $factoryUse = $this->generateFactoryUse($factoryName);

        // Replace placeholders
        $namespace = 'Database\\Seeders';

        $replacements = [
            '{{ namespace }}' => $namespace,
            '{{namespace}}' => $namespace,
            '{{ class }}' => $name,
            '{{class}}' => $name,
            '{{ factoryName }}' => $factoryName,
            '{{factoryName}}' => $factoryName,
            '{{ tableName }}' => $tableName,
            '{{tableName}}' => $tableName,
            '{{ factoryUse }}' => $factoryUse,
            '{{factoryUse}}' => $factoryUse,
        ];

        foreach ($replacements as $placeholder => $replacement) {
            $stubContent = str_replace($placeholder, $replacement, $stubContent);
        }

        // Generate path
        $path = $this->getBasePath() . '/database/seeders';

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $filePath = $path . '/' . $name . '.php';

        if (file_exists($filePath)) {
            $this->error("Seeder [{$name}] already exists!");
            return 1;
        }

        if (file_put_contents($filePath, $stubContent) === false) {
            $this->error("Failed to write seeder file: {$filePath}");
            return 1;
        }

        $relativePath = str_replace($this->getBasePath() . '/', '', $filePath);
        $this->success("Seeder [{$relativePath}] created successfully.");

        return 0;
    }

    /**
     * Guess factory name from seeder name.
     *
     * @param string $seederName
     * @return string
     */
    private function guessFactoryName(string $seederName): string
    {
        // Remove 'Seeder' suffix if present
        $baseName = preg_replace('/Seeder$/', '', $seederName);

        // Try to find factory
        $factoryClass = "Database\\Factories\\{$baseName}Factory";
        if (class_exists($factoryClass)) {
            return $factoryClass;
        }

        // Return pattern for user to fill
        return "{$baseName}Factory::new()";
    }

    /**
     * Guess table name from seeder name.
     *
     * @param string $seederName
     * @return string
     */
    private function guessTableName(string $seederName): string
    {
        // Remove 'Seeder' suffix if present
        $baseName = preg_replace('/Seeder$/', '', $seederName);

        // Convert PascalCase to snake_case and pluralize
        $tableName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $baseName));

        // Pluralize (simple version)
        if (!str_ends_with($tableName, 's')) {
            $tableName .= 's';
        }

        return $tableName;
    }

    /**
     * Generate factory use statement.
     *
     * @param string $factoryName
     * @return string
     */
    private function generateFactoryUse(string $factoryName): string
    {
        // If it's a full class name, generate use statement
        if (str_contains($factoryName, '\\')) {
            $factoryClass = preg_replace('/::new\(\)$/', '', $factoryName);
            if (class_exists($factoryClass)) {
                return "use {$factoryClass};";
            }
        }

        return '';
    }

    private function getBasePath(): string
    {
        if (defined('APP_BASE_PATH')) {
            return constant('APP_BASE_PATH');
        }

        return getcwd() ?: dirname(__DIR__, 5);
    }
}
