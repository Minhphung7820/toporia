<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Make;

use Toporia\Framework\Console\Generator\GeneratorCommand;

final class MakeRuleCommand extends GeneratorCommand
{
    protected string $signature = 'make:rule {name : The name of the validation rule}';

    protected string $description = 'Create a new validation rule class';

    protected string $type = 'Rule';

    protected function getStub(): string
    {
        return $this->resolveStubPath('rule.stub');
    }

    protected function getDefaultNamespace(): string
    {
        return 'App\\Application\\Rules';
    }
}
