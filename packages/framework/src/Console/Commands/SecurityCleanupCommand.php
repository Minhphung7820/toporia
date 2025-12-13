<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands;

use Toporia\Framework\Console\Command;
use Toporia\Framework\Security\Contracts\ReplayAttackProtectionInterface;

/**
 * Class SecurityCleanupCommand
 *
 * Cleanup expired security tokens (nonces, etc.) from sessions.
 * Run this periodically via scheduler to prevent session bloat.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Console\Commands
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
final class SecurityCleanupCommand extends Command
{
    protected string $signature = 'security:cleanup';
    protected string $description = 'Cleanup expired security tokens (nonces) from sessions';

    public function __construct(
        private readonly ReplayAttackProtectionInterface $replayProtection
    ) {}

    public function handle(): int
    {
        $this->info('Cleaning up expired security tokens...');

        try {
            $cleaned = $this->replayProtection->cleanupExpired();

            if ($cleaned > 0) {
                $this->success("Cleaned up {$cleaned} expired nonce(s).");
            } else {
                $this->info('No expired nonces found.');
            }

            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to cleanup: {$e->getMessage()}");
            return 1;
        }
    }
}
