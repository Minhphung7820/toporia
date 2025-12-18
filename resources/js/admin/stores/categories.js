import { defineStore } from 'pinia';
import { categories } from '../services/api';

export const useCategoriesStore = defineStore('admin-categories', {
  state: () => ({
    items: [],
    selectOptions: [],
    currentCategory: null,
    pagination: {
      currentPage: 1,
      lastPage: 1,
      perPage: 20,
      total: 0,
    },
    filters: {
      search: '',
      is_active: '',
    },
    loading: false,
    saving: false,
    error: null,
  }),

  getters: {
    hasItems: (state) => state.items.length > 0,
    activeCategories: (state) => state.items.filter((c) => c.is_active),
  },

  actions: {
    async fetchCategories(page = 1) {
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
        const response = await categories.list(params);
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
        this.error = error.response?.data?.message || 'Failed to fetch categories';
      } finally {
        this.loading = false;
      }
    },

    async fetchSelectOptions(excludeId = null) {
      try {
        const response = await categories.selectOptions(excludeId);
        if (response.data.success) {
          this.selectOptions = response.data.data;
        }
      } catch (error) {
        console.error('Failed to fetch select options:', error);
      }
    },

    async fetchCategory(id) {
      this.loading = true;
      this.error = null;
      try {
        const response = await categories.get(id);
        if (response.data.success) {
          this.currentCategory = response.data.data;
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch category';
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async createCategory(data) {
      this.saving = true;
      this.error = null;
      try {
        const response = await categories.create(data);
        if (response.data.success) {
          this.items.unshift(response.data.data);
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to create category';
        throw error;
      } finally {
        this.saving = false;
      }
    },

    async updateCategory(id, data) {
      this.saving = true;
      this.error = null;
      try {
        const response = await categories.update(id, data);
        if (response.data.success) {
          const index = this.items.findIndex((c) => c.id === id);
          if (index !== -1) {
            this.items[index] = response.data.data;
          }
          this.currentCategory = response.data.data;
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update category';
        throw error;
      } finally {
        this.saving = false;
      }
    },

    async deleteCategory(id) {
      this.error = null;
      try {
        const response = await categories.delete(id);
        if (response.data.success) {
          this.items = this.items.filter((c) => c.id !== id);
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to delete category';
        throw error;
      }
    },

    async toggleActive(id) {
      try {
        const response = await categories.toggleActive(id);
        if (response.data.success) {
          const index = this.items.findIndex((c) => c.id === id);
          if (index !== -1) {
            this.items[index] = response.data.data;
          }
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to toggle active';
        throw error;
      }
    },

    async reorderCategories(order) {
      try {
        const response = await categories.reorder(order);
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to reorder categories';
        throw error;
      }
    },

    setFilters(filters) {
      this.filters = { ...this.filters, ...filters };
    },

    clearFilters() {
      this.filters = {
        search: '',
        is_active: '',
      };
    },

    clearCurrentCategory() {
      this.currentCategory = null;
    },
  },
});
