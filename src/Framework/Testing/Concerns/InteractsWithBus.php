<?php

declare(strict_types=1);

namespace Toporia\Framework\Testing\Concerns;

/**
 * Bus Testing Trait
 *
 * Provides utilities for command/query bus testing.
 *
 * Performance:
 * - O(1) bus faking
 * - Fast command assertions
 */
trait InteractsWithBus
{
    /**
     * Fake bus (disable real dispatching).
     */
    protected bool $fakeBus = false;

    /**
     * Dispatched commands.
     *
     * @var array
     */
    protected array $dispatchedCommands = [];

    /**
     * Fake bus.
     *
     * Performance: O(1)
     */
    protected function fakeBus(): void
    {
        $this->fakeBus = true;
        $this->dispatchedCommands = [];
    }

    /**
     * Assert that a command was dispatched.
     *
     * Performance: O(N) where N = number of commands
     */
    protected function assertCommandDispatched(string $commandClass, array $data = null): void
    {
        $found = false;

        foreach ($this->dispatchedCommands as $command) {
            if ($command['class'] === $commandClass) {
                if ($data === null || $command['data'] === $data) {
                    $found = true;
                    break;
                }
            }
        }

        $this->assertTrue($found, "Command {$commandClass} was not dispatched");
    }

    /**
     * Assert that a command was not dispatched.
     *
     * Performance: O(N) where N = number of commands
     */
    protected function assertCommandNotDispatched(string $commandClass): void
    {
        $found = false;

        foreach ($this->dispatchedCommands as $command) {
            if ($command['class'] === $commandClass) {
                $found = true;
                break;
            }
        }

        $this->assertFalse($found, "Command {$commandClass} was unexpectedly dispatched");
    }

    /**
     * Record a dispatched command.
     *
     * Performance: O(1)
     */
    protected function recordCommand(string $commandClass, array $data = []): void
    {
        $this->dispatchedCommands[] = [
            'class' => $commandClass,
            'data' => $data,
        ];
    }

    /**
     * Cleanup bus after test.
     */
    protected function tearDownBus(): void
    {
        $this->dispatchedCommands = [];
        $this->fakeBus = false;
    }
}

