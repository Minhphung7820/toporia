<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Make;

use Toporia\Framework\Console\Generator\GeneratorCommand;

final class MakePolicyCommand extends GeneratorCommand
{
    protected string $signature = 'make:policy {name : The name of the policy} {--model= : The model that the policy applies to}';

    protected string $description = 'Create a new policy class';

    protected string $type = 'Policy';

    protected function getStub(): string
    {
        return $this->resolveStubPath('policy.stub');
    }

    protected function getDefaultNamespace(): string
    {
        return 'App\\Application\\Policies';
    }
}
