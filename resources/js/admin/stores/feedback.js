import { defineStore } from 'pinia';
import { feedback } from '../services/api';

export const useFeedbackStore = defineStore('admin-feedback', {
  state: () => ({
    items: [],
    pendingItems: [],
    myAssigned: [],
    currentFeedback: null,
    statistics: null,
    pagination: {
      currentPage: 1,
      lastPage: 1,
      perPage: 20,
      total: 0,
      from: 0,
      to: 0,
    },
    pendingPagination: {
      currentPage: 1,
      lastPage: 1,
      perPage: 20,
      total: 0,
      from: 0,
      to: 0,
    },
    filters: {
      search: '',
      status: '',
      priority: '',
      type: '',
    },
    loading: false,
    error: null,
    selectedIds: [],
  }),

  getters: {
    hasItems: (state) => state.items.length > 0,
    pendingCount: (state) => state.statistics?.pending || 0,
    selectedCount: (state) => state.selectedIds.length,
  },

  actions: {
    async fetchFeedback(page = 1) {
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
        const response = await feedback.list(params);
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
        this.error = error.response?.data?.message || 'Failed to fetch feedback';
      } finally {
        this.loading = false;
      }
    },

    setPerPage(perPage) {
      this.pagination.perPage = perPage;
    },

    async fetchPending(page = 1) {
      this.loading = true;
      try {
        const params = { page, per_page: this.pendingPagination.perPage };
        const response = await feedback.pending(params);
        if (response.data.success) {
          const data = response.data.data;
          // Paginator returns { data: [...], current_page, last_page, per_page, total, from, to }
          this.pendingItems = data.data || [];
          this.pendingPagination = {
            currentPage: data.current_page || 1,
            lastPage: data.last_page || 1,
            perPage: data.per_page || 20,
            total: data.total || 0,
            from: data.from || 0,
            to: data.to || 0,
          };
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch pending feedback';
      } finally {
        this.loading = false;
      }
    },

    async fetchMyAssigned(page = 1) {
      this.loading = true;
      try {
        const params = { page, per_page: 20 };
        const response = await feedback.myAssigned(params);
        if (response.data.success) {
          const data = response.data.data;
          // Paginator returns { data: [...], current_page, last_page, per_page, total, from, to }
          this.myAssigned = data.data || [];
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch assigned feedback';
      } finally {
        this.loading = false;
      }
    },

    async fetchStatistics() {
      try {
        const response = await feedback.statistics();
        if (response.data.success) {
          this.statistics = response.data.data;
        }
      } catch (error) {
        console.error('Failed to fetch statistics:', error);
      }
    },

    async fetchFeedbackItem(id) {
      this.loading = true;
      this.error = null;
      try {
        const response = await feedback.get(id);
        if (response.data.success) {
          this.currentFeedback = response.data.data;
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch feedback';
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async updateStatus(id, status) {
      try {
        const response = await feedback.updateStatus(id, status);
        if (response.data.success) {
          this.updateItemInLists(id, response.data.data);
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update status';
        throw error;
      }
    },

    async updatePriority(id, priority) {
      try {
        const response = await feedback.updatePriority(id, priority);
        if (response.data.success) {
          this.updateItemInLists(id, response.data.data);
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update priority';
        throw error;
      }
    },

    async assignFeedback(id, assigneeId) {
      try {
        const response = await feedback.assign(id, assigneeId);
        if (response.data.success) {
          this.updateItemInLists(id, response.data.data);
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to assign feedback';
        throw error;
      }
    },

    async addNotes(id, notes) {
      try {
        const response = await feedback.addNotes(id, notes);
        if (response.data.success) {
          this.updateItemInLists(id, response.data.data);
          if (this.currentFeedback?.id === id) {
            this.currentFeedback = response.data.data;
          }
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to add notes';
        throw error;
      }
    },

    async deleteFeedback(id) {
      try {
        const response = await feedback.delete(id);
        if (response.data.success) {
          this.removeFromAllLists(id);
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to delete feedback';
        throw error;
      }
    },

    async bulkDelete() {
      if (this.selectedIds.length === 0) return;
      try {
        const response = await feedback.bulkDelete(this.selectedIds);
        if (response.data.success) {
          this.selectedIds.forEach((id) => this.removeFromAllLists(id));
          this.selectedIds = [];
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to bulk delete';
        throw error;
      }
    },

    updateItemInLists(id, data) {
      const updateList = (list) => {
        const index = list.findIndex((item) => item.id === id);
        if (index !== -1) {
          list[index] = data;
        }
      };
      updateList(this.items);
      updateList(this.pendingItems);
      updateList(this.myAssigned);
    },

    removeFromAllLists(id) {
      this.items = this.items.filter((item) => item.id !== id);
      this.pendingItems = this.pendingItems.filter((item) => item.id !== id);
      this.myAssigned = this.myAssigned.filter((item) => item.id !== id);
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
        status: '',
        priority: '',
        type: '',
      };
    },

    clearCurrentFeedback() {
      this.currentFeedback = null;
    },
  },
});
