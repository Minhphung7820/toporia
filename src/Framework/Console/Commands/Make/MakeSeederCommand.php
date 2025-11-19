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
        if (!str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }

        $stubPath = $this->resolveStubPath('seeder.stub');

        if (!file_exists($stubPath)) {
            $this->error("Stub file not found: {$stubPath}");
            return 1;
        }

        $stubContent = file_get_contents($stubPath);

        // Replace placeholders
        $namespace = 'Database\\Seeders';
        $stubContent = str_replace(['{{ namespace }}', '{{namespace}}'], $namespace, $stubContent);
        $stubContent = str_replace(['{{ class }}', '{{class}}'], $name, $stubContent);

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

    private function getBasePath(): string
    {
        if (defined('APP_BASE_PATH')) {
            return constant('APP_BASE_PATH');
        }

        return getcwd() ?: dirname(__DIR__, 5);
    }
}
