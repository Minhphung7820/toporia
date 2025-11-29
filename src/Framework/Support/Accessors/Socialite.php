<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Foundation\Application;
use Toporia\Framework\Socialite\{SocialiteManager, Contracts\ProviderInterface};

/**
 * Socialite Facade
 *
 * Provides static access to socialite manager.
 *
 * @method static ProviderInterface driver(string $provider)
 */
final class Socialite
{
    /**
     * Get socialite manager instance.
     *
     * @return SocialiteManager
     */
    public static function getInstance(): SocialiteManager
    {
        return Application::getInstance()->get('socialite');
    }

    /**
     * Get OAuth provider driver.
     *
     * @param string $provider Provider name
     * @return ProviderInterface
     */
    public static function driver(string $provider): ProviderInterface
    {
        return static::getInstance()->driver($provider);
    }
}

