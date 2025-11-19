<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Make;

use Toporia\Framework\Console\Generator\GeneratorCommand;

final class MakeExceptionCommand extends GeneratorCommand
{
    protected string $signature = 'make:exception {name : The name of the exception}';

    protected string $description = 'Create a new custom exception class';

    protected string $type = 'Exception';

    protected function getStub(): string
    {
        return $this->resolveStubPath('exception.stub');
    }

    protected function getDefaultNamespace(): string
    {
        return 'App\\Domain\\Exceptions';
    }
}
