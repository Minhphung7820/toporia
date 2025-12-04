<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Process\{ProcessManager, ProcessPool};

/**
 * Class ProcessServiceProvider
 *
 * Registers multi-process services.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Providers
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
final class ProcessServiceProvider extends ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        // Register ProcessManager
        $container->bind(ProcessManager::class, fn() => new ProcessManager());

        // Register ProcessPool with default worker count (CPU cores)
        $container->bind(ProcessPool::class, function () {
            $cores = $this->getCpuCoreCount();
            return new ProcessPool(workerCount: $cores);
        });

        // Convenience bindings
        $container->bind('process.manager', fn($c) => $c->get(ProcessManager::class));
        $container->bind('process.pool', fn($c) => $c->get(ProcessPool::class));
    }

    /**
     * Get CPU core count.
     *
     * @return int
     */
    private function getCpuCoreCount(): int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return (int) ($_ENV['NUMBER_OF_PROCESSORS'] ?? 4);
        }

        // Linux/macOS
        $output = shell_exec('nproc 2>/dev/null || sysctl -n hw.ncpu 2>/dev/null || echo 4');
        return max(1, (int) trim($output));
    }
}
