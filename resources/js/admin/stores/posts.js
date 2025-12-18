import { defineStore } from 'pinia';
import { posts } from '../services/api';

export const usePostsStore = defineStore('admin-posts', {
  state: () => ({
    items: [],
    currentPost: null,
    // Support both offset and cursor pagination
    paginationType: 'cursor', // 'offset' or 'cursor'
    pagination: {
      currentPage: 1,
      lastPage: 1,
      perPage: 20,
      total: 0,
      from: 0,
      to: 0,
    },
    // Cursor pagination state
    cursor: {
      next: null,
      prev: null,
      hasMore: false,
      perPage: 20,
    },
    filters: {
      search: '',
      status: '',
      category_id: '',
      is_featured: '',
    },
    loading: false,
    loadingMore: false, // Separate state for "Load More" to prevent scroll reset
    saving: false,
    error: null,
  }),

  getters: {
    hasItems: (state) => state.items.length > 0,
    filteredCount: (state) => state.pagination.total,
    hasMorePages: (state) =>
      state.paginationType === 'cursor'
        ? state.cursor.hasMore
        : state.pagination.currentPage < state.pagination.lastPage,
  },

  actions: {
    // Offset-based pagination (traditional)
    async fetchPosts(page = 1) {
      this.paginationType = 'offset';
      this.loading = true;
      this.error = null;
      try {
        const params = {
          page,
          per_page: this.pagination.perPage,
          ...Object.fromEntries(
            Object.entries(this.filters).filter(([, v]) => v !== '')
          ),
        };
        const response = await posts.list(params);
        if (response.data.success) {
          const data = response.data.data;
          this.items = data.data || [];
          this.pagination = {
            currentPage: data.current_page || 1,
            lastPage: data.last_page || 1,
            perPage: data.per_page || 20,
            total: data.total || 0,
            from: data.from || 0,
            to: data.to || 0,
          };
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch posts';
      } finally {
        this.loading = false;
      }
    },

    // Cursor-based pagination (ultra-fast for large datasets)
    async fetchPostsCursor(cursorValue = null, reset = false) {
      this.paginationType = 'cursor';
      // Use loadingMore for appending, loading for initial/reset fetch
      const isAppending = cursorValue && !reset;
      if (isAppending) {
        this.loadingMore = true;
      } else {
        this.loading = true;
      }
      this.error = null;
      try {
        const params = {
          pagination: 'cursor',
          per_page: this.cursor.perPage,
          ...Object.fromEntries(
            Object.entries(this.filters).filter(([, v]) => v !== '')
          ),
        };
        if (cursorValue) {
          params.cursor = cursorValue;
        }

        const response = await posts.list(params);
        if (response.data.success) {
          const data = response.data.data;
          // CursorPaginator returns { data, per_page, next_cursor, prev_cursor, has_more }
          if (reset || !cursorValue) {
            this.items = data.data || [];
          } else {
            // Append for infinite scroll
            this.items = [...this.items, ...(data.data || [])];
          }
          this.cursor = {
            next: data.next_cursor || null,
            prev: data.prev_cursor || null,
            hasMore: data.has_more || false,
            perPage: data.per_page || 20,
          };
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch posts';
      } finally {
        this.loading = false;
        this.loadingMore = false;
      }
    },

    // Load more for infinite scroll
    async loadMore() {
      if (this.paginationType === 'cursor' && this.cursor.next && !this.loading && !this.loadingMore) {
        await this.fetchPostsCursor(this.cursor.next, false);
      }
    },

    setPerPage(perPage) {
      this.pagination.perPage = perPage;
      this.cursor.perPage = perPage;
    },

    async fetchPost(id) {
      this.loading = true;
      this.error = null;
      try {
        const response = await posts.get(id);
        if (response.data.success) {
          // API returns { data: { post: {...}, tags: [...] } }
          const data = response.data.data;
          this.currentPost = {
            ...data.post,
            tags: data.tags || [],
          };
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch post';
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async createPost(data) {
      this.saving = true;
      this.error = null;
      try {
        const response = await posts.create(data);
        if (response.data.success) {
          this.items.unshift(response.data.data);
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to create post';
        throw error;
      } finally {
        this.saving = false;
      }
    },

    async updatePost(id, data) {
      this.saving = true;
      this.error = null;
      try {
        const response = await posts.update(id, data);
        if (response.data.success) {
          const index = this.items.findIndex((p) => p.id === id);
          if (index !== -1) {
            this.items[index] = response.data.data;
          }
          this.currentPost = response.data.data;
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update post';
        throw error;
      } finally {
        this.saving = false;
      }
    },

    async deletePost(id) {
      this.error = null;
      try {
        const response = await posts.delete(id);
        if (response.data.success) {
          this.items = this.items.filter((p) => p.id !== id);
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to delete post';
        throw error;
      }
    },

    async publishPost(id) {
      try {
        const response = await posts.publish(id);
        if (response.data.success) {
          const index = this.items.findIndex((p) => p.id === id);
          if (index !== -1) {
            this.items[index] = response.data.data;
          }
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to publish post';
        throw error;
      }
    },

    async unpublishPost(id) {
      try {
        const response = await posts.unpublish(id);
        if (response.data.success) {
          const index = this.items.findIndex((p) => p.id === id);
          if (index !== -1) {
            this.items[index] = response.data.data;
          }
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to unpublish post';
        throw error;
      }
    },

    async schedulePost(id, scheduledAt) {
      try {
        const response = await posts.schedule(id, scheduledAt);
        if (response.data.success) {
          const index = this.items.findIndex((p) => p.id === id);
          if (index !== -1) {
            this.items[index] = response.data.data;
          }
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to schedule post';
        throw error;
      }
    },

    async toggleFeatured(id) {
      try {
        const response = await posts.toggleFeatured(id);
        if (response.data.success) {
          const index = this.items.findIndex((p) => p.id === id);
          if (index !== -1) {
            this.items[index] = response.data.data;
          }
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to toggle featured';
        throw error;
      }
    },

    setFilters(filters) {
      this.filters = { ...this.filters, ...filters };
    },

    clearFilters() {
      this.filters = {
        search: '',
        status: '',
        category_id: '',
        is_featured: '',
      };
    },

    clearCurrentPost() {
      this.currentPost = null;
    },
  },
});
