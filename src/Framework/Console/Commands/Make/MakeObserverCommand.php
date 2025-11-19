<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Make;

use Toporia\Framework\Console\Generator\GeneratorCommand;

final class MakeObserverCommand extends GeneratorCommand
{
    protected string $signature = 'make:observer {name : The name of the observer} {--model= : The model that the observer applies to}';

    protected string $description = 'Create a new observer class';

    protected string $type = 'Observer';

    protected function getStub(): string
    {
        return $this->resolveStubPath('observer.stub');
    }

    protected function getDefaultNamespace(): string
    {
        return 'App\\Application\\Observers';
    }

    public function handle(): int
    {
        // Validate model exists if provided
        $modelOption = $this->option('model');
        if (!empty($modelOption)) {
            $modelClass = $this->resolveModelClass($modelOption);

            if (!class_exists($modelClass)) {
                $this->error("Model [{$modelClass}] does not exist.");
                return 1;
            }
        }

        return parent::handle();
    }

    protected function buildClass(string $name): string
    {
        $stub = $this->getStubContent();

        $this->replaceNamespace($stub, $name);
        $stub = $this->replaceClass($stub, $name);

        // Handle model class
        $modelOption = $this->option('model');
        if (empty($modelOption)) {
            // Try to derive from observer name
            $className = $this->getClassName($name);
            $modelName = str_replace('Observer', '', $className);
            $modelClass = 'App\\Domain\\' . $modelName;
        } else {
            $modelClass = $this->resolveModelClass($modelOption);
        }

        $modelShortClass = $this->getClassBasename($modelClass);
        $modelVariable = lcfirst($modelShortClass);

        $stub = str_replace(['{{ modelClass }}', '{{modelClass}}'], $modelClass, $stub);
        $stub = str_replace(['{{ modelShortClass }}', '{{modelShortClass}}'], $modelShortClass, $stub);
        $stub = str_replace(['{{ modelVariable }}', '{{modelVariable}}'], $modelVariable, $stub);

        return $stub;
    }

    /**
     * Resolve full model class name from input.
     */
    private function resolveModelClass(string $model): string
    {
        if (str_contains($model, '\\')) {
            return $model;
        }

        return 'App\\Domain\\' . $model;
    }

    private function getClassBasename(string $class): string
    {
        return basename(str_replace('\\', '/', $class));
    }
}
