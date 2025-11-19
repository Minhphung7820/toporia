<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Make;

use Toporia\Framework\Console\Generator\GeneratorCommand;

final class MakeEntityCommand extends GeneratorCommand
{
    protected string $signature = 'make:entity {name : The name of the entity}';

    protected string $description = 'Create a new domain entity class';

    protected string $type = 'Entity';

    protected function getStub(): string
    {
        return $this->resolveStubPath('entity.stub');
    }

    protected function getDefaultNamespace(): string
    {
        return 'App\\Domain';
    }
}
