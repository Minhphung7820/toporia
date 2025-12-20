/**
 * Blog API Service
 *
 * Handles all blog-related API calls for the public site.
 */

import http from './http';

/**
 * Post API endpoints
 */
export const posts = {
  /**
   * Get published posts with cursor pagination
   * @param {number} perPage - Items per page
   * @param {string|null} cursor - Cursor for pagination
   * @param {string} direction - 'next' or 'prev'
   */
  list(perPage = 10, cursor = null, direction = 'next') {
    const params = { per_page: perPage };
    if (cursor) {
      params.cursor = cursor;
      params.direction = direction;
    }
    return http.get('/blog/posts', { params });
  },

  /**
   * Get a single post by slug
   * @param {string} slug - Post slug
   */
  getBySlug(slug) {
    return http.get(`/blog/posts/${slug}`);
  },

  /**
   * Get featured posts
   * @param {number} limit - Number of posts
   */
  featured(limit = 5) {
    return http.get('/blog/posts/featured', { params: { limit } });
  },

  /**
   * Get popular/most viewed posts
   * @param {number} limit - Number of posts
   */
  popular(limit = 10) {
    return http.get('/blog/posts/popular', { params: { limit } });
  },

  /**
   * Get latest posts
   * @param {number} limit - Number of posts
   */
  latest(limit = 10) {
    return http.get('/blog/posts/latest', { params: { limit } });
  },

  /**
   * Get related posts
   * @param {number} postId - Post ID
   * @param {number} limit - Number of posts
   */
  related(postId, limit = 4) {
    return http.get(`/blog/posts/${postId}/related`, { params: { limit } });
  },

  /**
   * Search posts with cursor pagination
   * @param {string} query - Search query
   * @param {number} perPage - Items per page
   * @param {string|null} cursor - Cursor for pagination
   * @param {string} direction - 'next' or 'prev'
   */
  search(query, perPage = 10, cursor = null, direction = 'next') {
    const params = { q: query, per_page: perPage };
    if (cursor) {
      params.cursor = cursor;
      params.direction = direction;
    }
    return http.get('/blog/search', { params });
  },

  /**
   * Increment post views
   * @param {number} postId - Post ID
   */
  incrementViews(postId) {
    return http.post(`/blog/posts/${postId}/views`);
  },

  /**
   * Get posts by category with cursor pagination
   * @param {string} categorySlug - Category slug
   * @param {number} perPage - Items per page
   * @param {string|null} cursor - Cursor for pagination
   * @param {string} direction - 'next' or 'prev'
   */
  byCategory(categorySlug, perPage = 10, cursor = null, direction = 'next') {
    const params = { per_page: perPage };
    if (cursor) {
      params.cursor = cursor;
      params.direction = direction;
    }
    return http.get(`/blog/categories/${categorySlug}/posts`, { params });
  },

  /**
   * Get posts by tag with cursor pagination
   * @param {string} tagSlug - Tag slug
   * @param {number} perPage - Items per page
   * @param {string|null} cursor - Cursor for pagination
   * @param {string} direction - 'next' or 'prev'
   */
  byTag(tagSlug, perPage = 10, cursor = null, direction = 'next') {
    const params = { per_page: perPage };
    if (cursor) {
      params.cursor = cursor;
      params.direction = direction;
    }
    return http.get(`/blog/tags/${tagSlug}/posts`, { params });
  },
};

/**
 * Category API endpoints
 */
export const categories = {
  /**
   * Get all categories
   */
  list() {
    return http.get('/blog/categories');
  },

  /**
   * Get category by slug
   * @param {string} slug - Category slug
   */
  getBySlug(slug) {
    return http.get(`/blog/categories/${slug}`);
  },

  /**
   * Get categories tree structure
   */
  tree() {
    return http.get('/blog/categories/tree');
  },

  /**
   * Get categories with post counts
   */
  withCounts() {
    return http.get('/blog/categories/with-counts');
  },

  /**
   * Get categories with published posts only
   * @param {number|null} limit - Max categories to return
   */
  withPosts(limit = null) {
    const params = {};
    if (limit) params.limit = limit;
    return http.get('/blog/categories/with-posts', { params });
  },

  /**
   * Get categories tree with published post counts
   * Returns hierarchical tree with post_count and total_count
   */
  treeWithCounts() {
    return http.get('/blog/categories/tree-with-counts');
  },
};

/**
 * Tag API endpoints
 */
export const tags = {
  /**
   * Get all tags
   */
  list() {
    return http.get('/blog/tags');
  },

  /**
   * Get tag by slug
   * @param {string} slug - Tag slug
   */
  getBySlug(slug) {
    return http.get(`/blog/tags/${slug}`);
  },

  /**
   * Get popular tags
   * @param {number} limit - Number of tags
   */
  popular(limit = 20) {
    return http.get('/blog/tags/popular', { params: { limit } });
  },

  /**
   * Get tag cloud data
   * @param {number} limit - Number of tags
   */
  cloud(limit = 50) {
    return http.get('/blog/tags/cloud', { params: { limit } });
  },

  /**
   * Search tags
   * @param {string} query - Search query
   * @param {number} limit - Number of results
   */
  search(query, limit = 10) {
    return http.get('/blog/tags/search', { params: { q: query, limit } });
  },
};

/**
 * Comment API endpoints
 */
export const comments = {
  /**
   * Get comments for a post
   * @param {number} postId - Post ID
   */
  list(postId) {
    return http.get(`/blog/posts/${postId}/comments`);
  },

  /**
   * Create a new comment
   * @param {number} postId - Post ID
   * @param {Object} data - Comment data
   */
  create(postId, data) {
    return http.post(`/blog/posts/${postId}/comments`, data);
  },

  /**
   * Reply to a comment
   * @param {number} commentId - Parent comment ID
   * @param {Object} data - Reply data
   */
  reply(commentId, data) {
    return http.post(`/blog/comments/${commentId}/reply`, data);
  },

  /**
   * Like a comment
   * @param {number} commentId - Comment ID
   */
  like(commentId) {
    return http.post(`/blog/comments/${commentId}/like`);
  },
};

/**
 * Site Settings API endpoints
 */
export const settings = {
  /**
   * Get all public settings
   */
  getAll() {
    return http.get('/blog/settings');
  },

  /**
   * Get a single setting by key
   * @param {string} key - Setting key
   */
  get(key) {
    return http.get(`/blog/settings/${key}`);
  },
};

/**
 * Feedback API endpoints
 */
export const feedback = {
  /**
   * Submit feedback
   * @param {Object} data - Feedback data
   */
  submit(data) {
    return http.post('/feedback', data);
  },

  /**
   * Get user's own feedback
   * @param {number} page - Page number
   * @param {number} perPage - Items per page
   */
  myFeedback(page = 1, perPage = 10) {
    return http.get('/feedback/my', { params: { page, per_page: perPage } });
  },

  /**
   * Get feedback status
   * @param {number} feedbackId - Feedback ID
   */
  status(feedbackId) {
    return http.get(`/feedback/${feedbackId}/status`);
  },
};

export default {
  posts,
  categories,
  tags,
  comments,
  settings,
  feedback,
};
