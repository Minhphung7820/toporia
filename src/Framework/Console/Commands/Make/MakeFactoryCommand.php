<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Make;

use Toporia\Framework\Console\Command;

final class MakeFactoryCommand extends Command
{
    protected string $signature = 'make:factory {name : The name of the factory} {--model= : The name of the model}';

    protected string $description = 'Create a new model factory class';

    public function handle(): int
    {
        $name = $this->argument('name');

        if (empty($name)) {
            $this->error('Factory name is required.');
            return 1;
        }

        // Ensure it ends with Factory
        if (!str_ends_with($name, 'Factory')) {
            $name .= 'Factory';
        }

        $stubPath = $this->resolveStubPath('factory.stub');

        if (!file_exists($stubPath)) {
            $this->error("Stub file not found: {$stubPath}");
            return 1;
        }

        $stubContent = file_get_contents($stubPath);

        // Replace placeholders
        $namespace = 'Database\\Factories';
        $stubContent = str_replace(['{{ namespace }}', '{{namespace}}'], $namespace, $stubContent);
        $stubContent = str_replace(['{{ class }}', '{{class}}'], $name, $stubContent);

        // Generate path
        $path = $this->getBasePath() . '/database/factories';

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $filePath = $path . '/' . $name . '.php';

        if (file_exists($filePath)) {
            $this->error("Factory [{$name}] already exists!");
            return 1;
        }

        if (file_put_contents($filePath, $stubContent) === false) {
            $this->error("Failed to write factory file: {$filePath}");
            return 1;
        }

        $relativePath = str_replace($this->getBasePath() . '/', '', $filePath);
        $this->success("Factory [{$relativePath}] created successfully.");

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
