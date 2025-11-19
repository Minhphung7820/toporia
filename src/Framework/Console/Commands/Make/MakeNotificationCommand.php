<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Make;

use Toporia\Framework\Console\Generator\GeneratorCommand;

final class MakeNotificationCommand extends GeneratorCommand
{
    protected string $signature = 'make:notification {name : The name of the notification}';

    protected string $description = 'Create a new notification class';

    protected string $type = 'Notification';

    protected function getStub(): string
    {
        return $this->resolveStubPath('notification.stub');
    }

    protected function getDefaultNamespace(): string
    {
        return 'App\\Application\\Notifications';
    }
}
