<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Make;

use Toporia\Framework\Console\Generator\GeneratorCommand;

/**
 * Make Rule Command
 *
 * Creates validation rule classes with support for:
 * - Regular rules (default)
 * - Implicit rules (--implicit) - run even when field is empty
 * - Data-aware rules (--data-aware) - access to all validation data
 * - Combined (--implicit --data-aware)
 *
 * Clean Architecture:
 * - Rules are placed in App\Application\Rules (Application layer)
 * - Rules are framework-agnostic (depend only on RuleInterface)
 *
 * Performance:
 * - Rules are stateless by default (can be cached/reused)
 * - Implicit rules run first (fail-fast optimization)
 */
final class MakeRuleCommand extends GeneratorCommand
{
    protected string $signature = 'make:rule {name : The name of the validation rule}
                            {--i|implicit : Create an implicit rule (runs even when field is empty)}
                            {--d|data-aware : Create a data-aware rule (access to all validation data)}
                            {--force : Overwrite existing rule}';

    protected string $description = 'Create a new validation rule class';

    protected string $type = 'Rule';

    protected function getStub(): string
    {
        $isImplicit = $this->option('implicit');
        $isDataAware = $this->option('data-aware');

        if ($isImplicit && $isDataAware) {
            return $this->resolveStubPath('rule.implicit-data-aware.stub');
        }

        if ($isImplicit) {
            return $this->resolveStubPath('rule.implicit.stub');
        }

        if ($isDataAware) {
            return $this->resolveStubPath('rule.data-aware.stub');
        }

        return $this->resolveStubPath('rule.stub');
    }

    protected function getDefaultNamespace(): string
    {
        return 'App\\Application\\Rules';
    }

    /**
     * Check if file already exists (unless --force is used).
     */
    protected function alreadyExists(string $path): bool
    {
        if ($this->option('force')) {
            return false;
        }

        return parent::alreadyExists($path);
    }
}
