<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Make;

use Toporia\Framework\Console\Generator\GeneratorCommand;

final class MakeActionCommand extends GeneratorCommand
{
    protected string $signature = 'make:action {name : The name of the action}';

    protected string $description = 'Create a new ADR-style action class';

    protected string $type = 'Action';

    protected function getStub(): string
    {
        return $this->resolveStubPath('action.stub');
    }

    protected function getDefaultNamespace(): string
    {
        return 'App\\Presentation\\Http\\Actions';
    }
}
