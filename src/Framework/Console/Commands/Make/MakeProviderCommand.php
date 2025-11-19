<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Make;

use Toporia\Framework\Console\Generator\GeneratorCommand;

final class MakeProviderCommand extends GeneratorCommand
{
    protected string $signature = 'make:provider {name : The name of the service provider}';

    protected string $description = 'Create a new service provider class';

    protected string $type = 'Provider';

    protected function getStub(): string
    {
        return $this->resolveStubPath('provider.stub');
    }

    protected function getDefaultNamespace(): string
    {
        return 'App\\Infrastructure\\Providers';
    }
}
