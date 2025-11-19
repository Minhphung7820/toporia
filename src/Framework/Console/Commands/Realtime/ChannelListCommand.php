<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Realtime;

use Toporia\Framework\Console\Command;

final class ChannelListCommand extends Command
{
    protected string $signature = 'channel:list';

    protected string $description = 'List all registered broadcast channels';

    public function handle(): int
    {
        // Look for channel definitions in routes/channels.php
        $channelsPath = $this->getBasePath() . '/routes/channels.php';

        if (!file_exists($channelsPath)) {
            $this->info('No channels file found at routes/channels.php');
            return 0;
        }

        $content = file_get_contents($channelsPath);

        // Parse channel definitions
        $channels = $this->parseChannels($content);

        if (empty($channels)) {
            $this->info('No broadcast channels defined.');
            return 0;
        }

        $this->table(
            ['Channel', 'Type'],
            $channels
        );

        $this->newLine();
        $this->info('Total: ' . count($channels) . ' channel(s)');

        return 0;
    }

    private function parseChannels(string $content): array
    {
        $channels = [];

        // Match Broadcast::channel() calls
        preg_match_all(
            '/Broadcast::channel\s*\(\s*[\'"]([^\'"]+)[\'"]/m',
            $content,
            $matches
        );

        foreach ($matches[1] as $channel) {
            $type = $this->determineChannelType($channel);
            $channels[] = [$channel, $type];
        }

        return $channels;
    }

    private function determineChannelType(string $channel): string
    {
        if (str_starts_with($channel, 'private-')) {
            return 'Private';
        }

        if (str_starts_with($channel, 'presence-')) {
            return 'Presence';
        }

        if (str_contains($channel, '{')) {
            return 'Dynamic';
        }

        return 'Public';
    }

    private function getBasePath(): string
    {
        if (defined('APP_BASE_PATH')) {
            return constant('APP_BASE_PATH');
        }

        return getcwd() ?: dirname(__DIR__, 5);
    }
}
