<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Persistence\Models\SiteSettingModel;

/**
 * Site Settings Service - Centralized settings access with caching.
 *
 * Uses two-level caching:
 * 1. Persistent cache (Redis/File) - survives across requests (TTL: 1 hour)
 * 2. In-memory cache - avoids repeated cache reads within same request
 */
final class SiteSettingsService
{
    private const CACHE_KEY = 'site_settings:all';
    private const CACHE_TTL = 3600; // 1 hour

    private static ?array $memoryCache = null;
    private static ?self $instance = null;

    /**
     * Get singleton instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get all settings (cached).
     *
     * Two-level cache: memory -> persistent cache -> database
     */
    public function all(): array
    {
        // Level 1: In-memory cache (same request)
        if (self::$memoryCache !== null) {
            return self::$memoryCache;
        }

        // Level 2: Persistent cache (Redis/File)
        self::$memoryCache = cache()->remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn() => SiteSettingModel::getAllAsArray()
        );

        return self::$memoryCache;
    }

    /**
     * Get a setting value by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();
        return $settings[$key] ?? $default;
    }

    /**
     * Clear both memory and persistent cache.
     * Called by SiteSettingObserver when settings change.
     */
    public function clearCache(): void
    {
        // Clear persistent cache
        cache()->forget(self::CACHE_KEY);

        // Clear in-memory cache
        self::$memoryCache = null;
    }

    // =========================================================================
    // General Settings
    // =========================================================================

    public function siteName(): string
    {
        return (string) $this->get('site_name', 'Toporia Blog');
    }

    public function siteTagline(): string
    {
        return (string) $this->get('site_tagline', '');
    }

    public function siteLogo(): ?string
    {
        return $this->get('site_logo');
    }

    public function siteFavicon(): ?string
    {
        return $this->get('site_favicon');
    }

    // =========================================================================
    // Blog Settings
    // =========================================================================

    public function postsPerPage(): int
    {
        return (int) $this->get('posts_per_page', 10);
    }

    public function featuredPostsCount(): int
    {
        return (int) $this->get('featured_posts_count', 5);
    }

    public function popularPostsCount(): int
    {
        return (int) $this->get('popular_posts_count', 5);
    }

    public function relatedPostsCount(): int
    {
        return (int) $this->get('related_posts_count', 4);
    }

    public function commentsEnabled(): bool
    {
        return (bool) $this->get('comments_enabled', true);
    }

    public function commentsRequireApproval(): bool
    {
        return (bool) $this->get('comments_require_approval', true);
    }

    public function commentsMaxDepth(): int
    {
        return (int) $this->get('comments_max_depth', 3);
    }

    // =========================================================================
    // SEO Settings
    // =========================================================================

    public function metaTitle(): string
    {
        return (string) $this->get('meta_title', '');
    }

    public function metaDescription(): string
    {
        return (string) $this->get('meta_description', '');
    }

    public function metaKeywords(): array
    {
        $keywords = $this->get('meta_keywords', []);
        return is_array($keywords) ? $keywords : [];
    }

    // =========================================================================
    // Social Settings
    // =========================================================================

    public function socialGithub(): ?string
    {
        return $this->get('social_github');
    }

    public function socialTwitter(): ?string
    {
        return $this->get('social_twitter');
    }

    public function socialFacebook(): ?string
    {
        return $this->get('social_facebook');
    }

    public function socialLinks(): array
    {
        return [
            'github' => $this->socialGithub(),
            'twitter' => $this->socialTwitter(),
            'facebook' => $this->socialFacebook(),
        ];
    }
}
