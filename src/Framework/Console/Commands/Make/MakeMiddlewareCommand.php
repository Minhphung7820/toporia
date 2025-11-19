<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Make;

use Toporia\Framework\Console\Generator\GeneratorCommand;

final class MakeMiddlewareCommand extends GeneratorCommand
{
    protected string $signature = 'make:middleware {name : The name of the middleware}';

    protected string $description = 'Create a new middleware class';

    protected string $type = 'Middleware';

    protected function getStub(): string
    {
        return $this->resolveStubPath('middleware.stub');
    }

    protected function getDefaultNamespace(): string
    {
        return 'App\\Presentation\\Http\\Middleware';
    }
}
