<?php

declare(strict_types=1);

namespace App\Presentation\Console;

/**
 * Console Kernel
 *
 * Registers all application console commands.
 *
 * Clean Architecture:
 * - Presentation layer (UI concerns)
 * - Console Commands are UI entry points
 * - Commands delegate to Application Services (use cases)
 *
 * SOLID Principles:
 * - Single Responsibility: Command registration only
 * - Open/Closed: Add new commands by extending array
 */
final class Kernel
{
    /**
     * Bootstrap console commands into application.
     *
     * @param mixed $application Application instance
     * @return void
     */
    public function bootstrap(mixed $application): void
    {
        $application->registerMany($this->commands());
    }

    /**
     * Get all application console commands.
     *
     * @return array<class-string> Command classes
     */
    public function commands(): array
    {
        return [
            // Add your custom application commands here...
            Commands\ExportExcelCommand::class,
            Commands\ImportExcelCommand::class,
            Commands\ImportPostsCommand::class,
            Commands\ExportPostsCommand::class,
        ];
    }
}
