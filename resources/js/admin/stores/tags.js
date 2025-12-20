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
      from: 0,
      to: 0,
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
          const data = response.data.data;
          // Paginator returns { data: [...], current_page, last_page, per_page, total, from, to }
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
        this.error = error.response?.data?.message || 'Failed to fetch tags';
      } finally {
        this.loading = false;
      }
    },

    setPerPage(perPage) {
      this.pagination.perPage = perPage;
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

    async mergeTags(targetTagId, sourceTagIds) {
      if (!targetTagId || sourceTagIds.length === 0) return;
      try {
        const response = await tags.merge(targetTagId, sourceTagIds);
        if (response.data.success) {
          // Refresh the list after merge
          await this.fetchTags(this.pagination.currentPage);
          await this.fetchStatistics();
          this.selectedIds = [];
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to merge tags';
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
