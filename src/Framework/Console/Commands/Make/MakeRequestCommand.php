<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Make;

use Toporia\Framework\Console\Generator\GeneratorCommand;

final class MakeRequestCommand extends GeneratorCommand
{
    protected string $signature = 'make:request {name : The name of the form request}';

    protected string $description = 'Create a new form request class';

    protected string $type = 'Request';

    protected function getStub(): string
    {
        return $this->resolveStubPath('request.stub');
    }

    protected function getDefaultNamespace(): string
    {
        return 'App\\Presentation\\Http\\Requests';
    }
}
