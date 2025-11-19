<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Make;

use Toporia\Framework\Console\Generator\GeneratorCommand;

final class MakeSubscriberCommand extends GeneratorCommand
{
    protected string $signature = 'make:subscriber {name : The name of the subscriber}';

    protected string $description = 'Create a new event subscriber class';

    protected string $type = 'Subscriber';

    protected function getStub(): string
    {
        return $this->resolveStubPath('subscriber.stub');
    }

    protected function getDefaultNamespace(): string
    {
        return 'App\\Application\\Subscribers';
    }
}
