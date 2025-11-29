<?php

declare(strict_types=1);

namespace Toporia\Framework\Socialite;

use Toporia\Framework\Socialite\Contracts\ProviderInterface;
use Toporia\Framework\Http\Contracts\HttpClientInterface;
use Toporia\Framework\Container\Contracts\ContainerInterface;

/**
 * Socialite Manager
 *
 * Manages OAuth providers and creates provider instances.
 *
 * Performance:
 * - O(1) provider lookup (cached)
 * - Lazy provider instantiation
 * - Singleton providers
 */
final class SocialiteManager
{
    /**
     * @var array<string, ProviderInterface> Cached provider instances
     */
    private array $providers = [];

    /**
     * @param ContainerInterface $container Dependency injection container
     * @param HttpClientInterface $httpClient HTTP client
     * @param array<string, array<string, mixed>> $config Provider configurations
     */
    public function __construct(
        private ContainerInterface $container,
        private HttpClientInterface $httpClient,
        private array $config = []
    ) {}

    /**
     * Get OAuth provider instance.
     *
     * @param string $provider Provider name (google, facebook, github, etc.)
     * @return ProviderInterface Provider instance
     */
    public function driver(string $provider): ProviderInterface
    {
        $provider = strtolower($provider);

        // Return cached instance if available
        if (isset($this->providers[$provider])) {
            return $this->providers[$provider];
        }

        // Get provider config
        $config = $this->getProviderConfig($provider);

        // Create provider instance
        $instance = $this->createProvider($provider, $config);

        // Cache instance
        $this->providers[$provider] = $instance;

        return $instance;
    }

    /**
     * Create provider instance.
     *
     * @param string $provider Provider name
     * @param array<string, mixed> $config Provider configuration
     * @return ProviderInterface Provider instance
     */
    private function createProvider(string $provider, array $config): ProviderInterface
    {
        $clientId = $config['client_id'] ?? '';
        $clientSecret = $config['client_secret'] ?? '';
        $redirectUrl = $config['redirect'] ?? '';
        $scopes = $config['scopes'] ?? [];

        return match ($provider) {
            'google' => new Providers\GoogleProvider($clientId, $clientSecret, $redirectUrl, $this->httpClient, $scopes),
            'facebook' => new Providers\FacebookProvider($clientId, $clientSecret, $redirectUrl, $this->httpClient, $scopes),
            'github' => new Providers\GitHubProvider($clientId, $clientSecret, $redirectUrl, $this->httpClient, $scopes),
            default => throw new \InvalidArgumentException("Unsupported provider: {$provider}"),
        };
    }

    /**
     * Get provider configuration.
     *
     * @param string $provider Provider name
     * @return array<string, mixed> Provider configuration
     */
    private function getProviderConfig(string $provider): array
    {
        return $this->config[$provider] ?? [];
    }

    /**
     * Extend with custom provider.
     *
     * @param string $name Provider name
     * @param \Closure $callback Provider factory callback
     * @return void
     */
    public function extend(string $name, \Closure $callback): void
    {
        $this->providers[$name] = $callback($this->httpClient, $this->config[$name] ?? []);
    }
}

