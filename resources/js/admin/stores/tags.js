import { defineStore } from 'pinia';
import { tags } from '../services/api';

export const useTagsStore = defineStore('admin-tags', {
  state: () => ({
    items: [],
    statistics: null,
    pagination: {
      currentPage: 1,
      lastPage: 1,
      perPage: 20,
      total: 0,
    },
    filters: {
      search: '',
    },
    loading: false,
    saving: false,
    error: null,
    selectedIds: [],
  }),

  getters: {
    hasItems: (state) => state.items.length > 0,
    selectedCount: (state) => state.selectedIds.length,
    unusedTagsCount: (state) => state.statistics?.unused_count || 0,
  },

  actions: {
    async fetchTags(page = 1) {
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
        const response = await tags.list(params);
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
        this.error = error.response?.data?.message || 'Failed to fetch tags';
      } finally {
        this.loading = false;
      }
    },

    async fetchStatistics() {
      try {
        const response = await tags.statistics();
        if (response.data.success) {
          this.statistics = response.data.data;
        }
      } catch (error) {
        console.error('Failed to fetch statistics:', error);
      }
    },

    async createTag(data) {
      this.saving = true;
      this.error = null;
      try {
        const response = await tags.create(data);
        if (response.data.success) {
          this.items.unshift(response.data.data);
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to create tag';
        throw error;
      } finally {
        this.saving = false;
      }
    },

    async updateTag(id, data) {
      this.saving = true;
      this.error = null;
      try {
        const response = await tags.update(id, data);
        if (response.data.success) {
          const index = this.items.findIndex((t) => t.id === id);
          if (index !== -1) {
            this.items[index] = response.data.data;
          }
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update tag';
        throw error;
      } finally {
        this.saving = false;
      }
    },

    async deleteTag(id) {
      this.error = null;
      try {
        const response = await tags.delete(id);
        if (response.data.success) {
          this.items = this.items.filter((t) => t.id !== id);
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to delete tag';
        throw error;
      }
    },

    async bulkDelete() {
      if (this.selectedIds.length === 0) return;
      try {
        const response = await tags.bulkDelete(this.selectedIds);
        if (response.data.success) {
          this.items = this.items.filter((t) => !this.selectedIds.includes(t.id));
          this.selectedIds = [];
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to bulk delete';
        throw error;
      }
    },

    async mergeTags(targetId, sourceIds) {
      try {
        const response = await tags.merge(targetId, sourceIds);
        if (response.data.success) {
          // Remove merged tags from list
          this.items = this.items.filter((t) => !sourceIds.includes(t.id));
          // Update target tag if in list
          const targetIndex = this.items.findIndex((t) => t.id === targetId);
          if (targetIndex !== -1 && response.data.data) {
            this.items[targetIndex] = response.data.data;
          }
          this.selectedIds = [];
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to merge tags';
        throw error;
      }
    },

    async cleanupUnusedTags() {
      try {
        const response = await tags.cleanup();
        if (response.data.success) {
          // Refresh the list after cleanup
          await this.fetchTags(this.pagination.currentPage);
          await this.fetchStatistics();
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to cleanup tags';
        throw error;
      }
    },

    toggleSelection(id) {
      const index = this.selectedIds.indexOf(id);
      if (index === -1) {
        this.selectedIds.push(id);
      } else {
        this.selectedIds.splice(index, 1);
      }
    },

    selectAll() {
      this.selectedIds = this.items.map((item) => item.id);
    },

    clearSelection() {
      this.selectedIds = [];
    },

    setFilters(filters) {
      this.filters = { ...this.filters, ...filters };
    },

    clearFilters() {
      this.filters = {
        search: '',
      };
    },
  },
});
