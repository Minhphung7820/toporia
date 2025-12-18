import { defineStore } from 'pinia';
import { posts } from '../services/api';

export const usePostsStore = defineStore('admin-posts', {
  state: () => ({
    items: [],
    currentPost: null,
    pagination: {
      currentPage: 1,
      lastPage: 1,
      perPage: 20,
      total: 0,
    },
    filters: {
      search: '',
      status: '',
      category_id: '',
      is_featured: '',
    },
    loading: false,
    saving: false,
    error: null,
  }),

  getters: {
    hasItems: (state) => state.items.length > 0,
    filteredCount: (state) => state.pagination.total,
  },

  actions: {
    async fetchPosts(page = 1) {
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
          this.items = response.data.data.items;
          this.pagination = {
            currentPage: response.data.data.current_page,
            lastPage: response.data.data.last_page,
            perPage: response.data.data.per_page,
            total: response.data.data.total,
          };
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch posts';
      } finally {
        this.loading = false;
      }
    },

    async fetchPost(id) {
      this.loading = true;
      this.error = null;
      try {
        const response = await posts.get(id);
        if (response.data.success) {
          this.currentPost = response.data.data;
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
