/**
 * Blog Store
 *
 * Pinia store for managing blog state (posts, categories, tags).
 */

import { defineStore } from 'pinia';
import { posts, categories, tags } from '../services/blog';

export const useBlogStore = defineStore('blog', {
  state: () => ({
    // Posts
    posts: [],
    currentPost: null,
    featuredPosts: [],
    popularPosts: [],
    latestPosts: [],
    relatedPosts: [],
    searchResults: [],

    // Cursor Pagination
    pagination: {
      nextCursor: null,
      prevCursor: null,
      hasMore: false,
      perPage: 10,
    },

    // Categories
    categories: [],
    categoriesTree: [],
    currentCategory: null,

    // Tags
    tags: [],
    popularTags: [],
    tagCloud: [],
    currentTag: null,

    // Loading states
    loading: {
      posts: false,
      post: false,
      featured: false,
      popular: false,
      latest: false,
      related: false,
      search: false,
      categories: false,
      tags: false,
    },

    // Error state
    error: null,

    // Search
    searchQuery: '',
    searchPagination: {
      nextCursor: null,
      prevCursor: null,
      hasMore: false,
      total: 0,
    },
  }),

  getters: {
    isLoading: (state) => Object.values(state.loading).some(Boolean),

    hasMorePages: (state) => state.pagination.hasMore,

    hasPrevPage: (state) => !!state.pagination.prevCursor,

    formattedCategories: (state) => {
      return state.categories.map(cat => ({
        ...cat,
        url: `/blog/category/${cat.slug}`,
      }));
    },

    formattedTags: (state) => {
      return state.tags.map(tag => ({
        ...tag,
        url: `/blog/tag/${tag.slug}`,
      }));
    },
  },

  actions: {
    /**
     * Fetch published posts with cursor pagination
     */
    async fetchPosts(cursor = null, direction = 'next', perPage = 10) {
      this.loading.posts = true;
      this.error = null;

      try {
        const response = await posts.list(perPage, cursor, direction);
        const data = response.data;

        if (data.success) {
          this.posts = data.data.posts;
          this.pagination = {
            nextCursor: data.data.pagination.next_cursor,
            prevCursor: data.data.pagination.prev_cursor,
            hasMore: data.data.pagination.has_more,
            perPage: data.data.pagination.per_page,
          };
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch posts';
        console.error('Failed to fetch posts:', error);
      } finally {
        this.loading.posts = false;
      }
    },

    /**
     * Fetch single post by slug
     */
    async fetchPost(slug) {
      this.loading.post = true;
      this.error = null;
      this.currentPost = null;

      try {
        const response = await posts.getBySlug(slug);
        const data = response.data;

        if (data.success) {
          this.currentPost = {
            ...data.data.post,
            tags: data.data.tags || [],
            category: data.data.category || null,
          };

          // Increment views (fire and forget)
          if (this.currentPost.id) {
            posts.incrementViews(this.currentPost.id).catch(() => { });
          }
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Post not found';
        console.error('Failed to fetch post:', error);
      } finally {
        this.loading.post = false;
      }
    },

    /**
     * Fetch featured posts
     */
    async fetchFeaturedPosts(limit = 5) {
      this.loading.featured = true;

      try {
        const response = await posts.featured(limit);
        const data = response.data;

        if (data.success) {
          this.featuredPosts = data.data.posts;
        }
      } catch (error) {
        console.error('Failed to fetch featured posts:', error);
      } finally {
        this.loading.featured = false;
      }
    },

    /**
     * Fetch popular posts
     */
    async fetchPopularPosts(limit = 10) {
      this.loading.popular = true;

      try {
        const response = await posts.popular(limit);
        const data = response.data;

        if (data.success) {
          this.popularPosts = data.data.posts;
        }
      } catch (error) {
        console.error('Failed to fetch popular posts:', error);
      } finally {
        this.loading.popular = false;
      }
    },

    /**
     * Fetch latest posts
     */
    async fetchLatestPosts(limit = 10) {
      this.loading.latest = true;

      try {
        const response = await posts.latest(limit);
        const data = response.data;

        if (data.success) {
          this.latestPosts = data.data.posts;
        }
      } catch (error) {
        console.error('Failed to fetch latest posts:', error);
      } finally {
        this.loading.latest = false;
      }
    },

    /**
     * Fetch related posts
     */
    async fetchRelatedPosts(postId, limit = 4) {
      this.loading.related = true;
      this.relatedPosts = [];

      try {
        const response = await posts.related(postId, limit);
        const data = response.data;

        if (data.success) {
          this.relatedPosts = data.data.posts;
        }
      } catch (error) {
        console.error('Failed to fetch related posts:', error);
      } finally {
        this.loading.related = false;
      }
    },

    /**
     * Search posts with cursor pagination
     */
    async searchPosts(query, perPage = 10, cursor = null, direction = 'next') {
      if (!query || query.trim().length < 2) {
        this.searchResults = [];
        this.searchPagination = {
          nextCursor: null,
          prevCursor: null,
          hasMore: false,
          total: 0,
        };
        return;
      }

      this.loading.search = true;
      this.searchQuery = query;
      this.error = null;

      try {
        const response = await posts.search(query, perPage, cursor, direction);
        const data = response.data;

        if (data.success) {
          this.searchResults = data.data.posts;
          this.searchPagination = {
            nextCursor: data.data.pagination.next_cursor,
            prevCursor: data.data.pagination.prev_cursor,
            hasMore: data.data.pagination.has_more,
            total: data.data.pagination.total,
          };
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Search failed';
        console.error('Search failed:', error);
      } finally {
        this.loading.search = false;
      }
    },

    /**
     * Fetch posts by category with cursor pagination
     */
    async fetchPostsByCategory(categorySlug, cursor = null, direction = 'next', perPage = 10) {
      this.loading.posts = true;
      this.error = null;

      try {
        const response = await posts.byCategory(categorySlug, perPage, cursor, direction);
        const data = response.data;

        if (data.success) {
          this.posts = data.data.posts;
          this.currentCategory = data.data.category;
          this.pagination = {
            nextCursor: data.data.pagination.next_cursor,
            prevCursor: data.data.pagination.prev_cursor,
            hasMore: data.data.pagination.has_more,
            perPage: data.data.pagination.per_page,
          };
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Category not found';
        console.error('Failed to fetch posts by category:', error);
      } finally {
        this.loading.posts = false;
      }
    },

    /**
     * Fetch posts by tag with cursor pagination
     */
    async fetchPostsByTag(tagSlug, cursor = null, direction = 'next', perPage = 10) {
      this.loading.posts = true;
      this.error = null;

      try {
        const response = await posts.byTag(tagSlug, perPage, cursor, direction);
        const data = response.data;

        if (data.success) {
          this.posts = data.data.posts;
          this.currentTag = data.data.tag;
          this.pagination = {
            nextCursor: data.data.pagination.next_cursor,
            prevCursor: data.data.pagination.prev_cursor,
            hasMore: data.data.pagination.has_more,
            perPage: data.data.pagination.per_page,
          };
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Tag not found';
        console.error('Failed to fetch posts by tag:', error);
      } finally {
        this.loading.posts = false;
      }
    },

    /**
     * Fetch categories
     */
    async fetchCategories() {
      this.loading.categories = true;

      try {
        const response = await categories.list();
        const data = response.data;

        if (data.success) {
          this.categories = data.data.categories;
        }
      } catch (error) {
        console.error('Failed to fetch categories:', error);
      } finally {
        this.loading.categories = false;
      }
    },

    /**
     * Fetch categories tree
     */
    async fetchCategoriesTree() {
      this.loading.categories = true;

      try {
        const response = await categories.tree();
        const data = response.data;

        if (data.success) {
          this.categoriesTree = data.data.tree;
        }
      } catch (error) {
        console.error('Failed to fetch categories tree:', error);
      } finally {
        this.loading.categories = false;
      }
    },

    /**
     * Fetch all tags
     */
    async fetchTags() {
      this.loading.tags = true;

      try {
        const response = await tags.list();
        const data = response.data;

        if (data.success) {
          this.tags = data.data.tags;
        }
      } catch (error) {
        console.error('Failed to fetch tags:', error);
      } finally {
        this.loading.tags = false;
      }
    },

    /**
     * Fetch popular tags
     */
    async fetchPopularTags(limit = 20) {
      this.loading.tags = true;

      try {
        const response = await tags.popular(limit);
        const data = response.data;

        if (data.success) {
          this.popularTags = data.data.tags;
        }
      } catch (error) {
        console.error('Failed to fetch popular tags:', error);
      } finally {
        this.loading.tags = false;
      }
    },

    /**
     * Fetch tag cloud
     */
    async fetchTagCloud(limit = 50) {
      this.loading.tags = true;

      try {
        const response = await tags.cloud(limit);
        const data = response.data;

        if (data.success) {
          this.tagCloud = data.data.tags;
        }
      } catch (error) {
        console.error('Failed to fetch tag cloud:', error);
      } finally {
        this.loading.tags = false;
      }
    },

    /**
     * Clear current post
     */
    clearCurrentPost() {
      this.currentPost = null;
      this.relatedPosts = [];
    },

    /**
     * Clear search results
     */
    clearSearch() {
      this.searchResults = [];
      this.searchQuery = '';
    },

    /**
     * Reset store state
     */
    reset() {
      this.posts = [];
      this.currentPost = null;
      this.featuredPosts = [];
      this.popularPosts = [];
      this.latestPosts = [];
      this.relatedPosts = [];
      this.searchResults = [];
      this.categories = [];
      this.categoriesTree = [];
      this.currentCategory = null;
      this.tags = [];
      this.popularTags = [];
      this.tagCloud = [];
      this.currentTag = null;
      this.error = null;
      this.searchQuery = '';
      this.pagination = {
        nextCursor: null,
        prevCursor: null,
        hasMore: false,
        perPage: 10,
      };
    },
  },
});
