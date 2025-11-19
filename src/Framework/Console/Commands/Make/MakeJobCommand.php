<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Make;

use Toporia\Framework\Console\Generator\GeneratorCommand;

final class MakeJobCommand extends GeneratorCommand
{
    protected string $signature = 'make:job {name : The name of the job} {--sync : Indicates that job should be synchronous}';

    protected string $description = 'Create a new queue job class';

    protected string $type = 'Job';

    protected function getStub(): string
    {
        return $this->resolveStubPath('job.stub');
    }

    protected function getDefaultNamespace(): string
    {
        return 'App\\Application\\Jobs';
    }
}
