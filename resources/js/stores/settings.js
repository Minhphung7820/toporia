/**
 * Site Settings Store (Pinia)
 *
 * Centralized state management for public site settings.
 * Fetches and caches settings from the API for use across the site.
 */

import { defineStore } from 'pinia';
import { settings } from '../services/blog';

export const useSettingsStore = defineStore('settings', {
  state: () => ({
    // All settings as key-value pairs
    data: {},
    loading: false,
    initialized: false,
    error: null,
  }),

  getters: {
    /**
     * Get a setting value by key with optional default
     */
    get: (state) => (key, defaultValue = null) => {
      return state.data[key] ?? defaultValue;
    },

    /**
     * Site name
     */
    siteName: (state) => state.data.site_name ?? 'Toporia Blog',

    /**
     * Site tagline
     */
    siteTagline: (state) => state.data.site_tagline ?? '',

    /**
     * Site logo URL
     */
    siteLogo: (state) => state.data.site_logo ?? null,

    /**
     * Site favicon URL
     */
    siteFavicon: (state) => state.data.site_favicon ?? null,

    /**
     * Posts per page
     */
    postsPerPage: (state) => state.data.posts_per_page ?? 10,

    /**
     * Featured posts count
     */
    featuredPostsCount: (state) => state.data.featured_posts_count ?? 5,

    /**
     * Popular posts count
     */
    popularPostsCount: (state) => state.data.popular_posts_count ?? 5,

    /**
     * Related posts count
     */
    relatedPostsCount: (state) => state.data.related_posts_count ?? 4,

    /**
     * Comments enabled
     */
    commentsEnabled: (state) => state.data.comments_enabled ?? true,

    /**
     * Max comment depth
     */
    commentsMaxDepth: (state) => state.data.comments_max_depth ?? 3,

    /**
     * Meta title for SEO
     */
    metaTitle: (state) => state.data.meta_title ?? '',

    /**
     * Meta description for SEO
     */
    metaDescription: (state) => state.data.meta_description ?? '',

    /**
     * Meta keywords for SEO
     */
    metaKeywords: (state) => state.data.meta_keywords ?? [],

    /**
     * Social links
     */
    socialLinks: (state) => ({
      github: state.data.social_github ?? null,
      twitter: state.data.social_twitter ?? null,
      facebook: state.data.social_facebook ?? null,
    }),
  },

  actions: {
    /**
     * Initialize settings (fetch from API)
     * Only runs once on app load
     */
    async initialize() {
      if (this.initialized) {
        return;
      }

      this.loading = true;
      this.error = null;

      try {
        const response = await settings.getAll();
        if (response.data.success) {
          this.data = response.data.data;
        }
      } catch (error) {
        console.error('Failed to load settings:', error);
        this.error = 'Failed to load settings';
      } finally {
        this.loading = false;
        this.initialized = true;
      }
    },

    /**
     * Refresh settings from API
     */
    async refresh() {
      this.loading = true;
      this.error = null;

      try {
        const response = await settings.getAll();
        if (response.data.success) {
          this.data = response.data.data;
        }
      } catch (error) {
        console.error('Failed to refresh settings:', error);
        this.error = 'Failed to refresh settings';
      } finally {
        this.loading = false;
      }
    },
  },
});
