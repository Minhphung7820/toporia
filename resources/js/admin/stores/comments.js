import { defineStore } from 'pinia';
import { comments } from '../services/api';

export const useCommentsStore = defineStore('admin-comments', {
  state: () => ({
    items: [],
    pendingItems: [],
    statistics: null,
    pagination: {
      currentPage: 1,
      lastPage: 1,
      perPage: 20,
      total: 0,
    },
    pendingPagination: {
      currentPage: 1,
      lastPage: 1,
      perPage: 20,
      total: 0,
    },
    filters: {
      search: '',
      status: '',
      post_id: '',
    },
    loading: false,
    loadingPending: false,
    error: null,
    selectedIds: [],
  }),

  getters: {
    hasItems: (state) => state.items.length > 0,
    hasPendingItems: (state) => state.pendingItems.length > 0,
    pendingCount: (state) => state.statistics?.pending || 0,
    selectedCount: (state) => state.selectedIds.length,
  },

  actions: {
    async fetchComments(page = 1) {
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
        const response = await comments.list(params);
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
        this.error = error.response?.data?.message || 'Failed to fetch comments';
      } finally {
        this.loading = false;
      }
    },

    async fetchPending(page = 1) {
      this.loadingPending = true;
      try {
        const params = { page, per_page: this.pendingPagination.perPage };
        const response = await comments.pending(params);
        if (response.data.success) {
          this.pendingItems = response.data.data.items;
          this.pendingPagination = {
            currentPage: response.data.data.current_page,
            lastPage: response.data.data.last_page,
            perPage: response.data.data.per_page,
            total: response.data.data.total,
          };
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch pending comments';
      } finally {
        this.loadingPending = false;
      }
    },

    async fetchStatistics() {
      try {
        const response = await comments.statistics();
        if (response.data.success) {
          this.statistics = response.data.data;
        }
      } catch (error) {
        console.error('Failed to fetch statistics:', error);
      }
    },

    async approveComment(id) {
      try {
        const response = await comments.approve(id);
        if (response.data.success) {
          this.updateCommentStatus(id, 'approved');
          this.removePendingComment(id);
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to approve comment';
        throw error;
      }
    },

    async rejectComment(id) {
      try {
        const response = await comments.reject(id);
        if (response.data.success) {
          this.updateCommentStatus(id, 'rejected');
          this.removePendingComment(id);
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to reject comment';
        throw error;
      }
    },

    async deleteComment(id) {
      try {
        const response = await comments.delete(id);
        if (response.data.success) {
          this.items = this.items.filter((c) => c.id !== id);
          this.removePendingComment(id);
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to delete comment';
        throw error;
      }
    },

    async bulkApprove() {
      if (this.selectedIds.length === 0) return;
      try {
        const response = await comments.bulkApprove(this.selectedIds);
        if (response.data.success) {
          this.selectedIds.forEach((id) => {
            this.updateCommentStatus(id, 'approved');
            this.removePendingComment(id);
          });
          this.selectedIds = [];
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to bulk approve';
        throw error;
      }
    },

    async bulkReject() {
      if (this.selectedIds.length === 0) return;
      try {
        const response = await comments.bulkReject(this.selectedIds);
        if (response.data.success) {
          this.selectedIds.forEach((id) => {
            this.updateCommentStatus(id, 'rejected');
            this.removePendingComment(id);
          });
          this.selectedIds = [];
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to bulk reject';
        throw error;
      }
    },

    async bulkDelete() {
      if (this.selectedIds.length === 0) return;
      try {
        const response = await comments.bulkDelete(this.selectedIds);
        if (response.data.success) {
          this.items = this.items.filter((c) => !this.selectedIds.includes(c.id));
          this.pendingItems = this.pendingItems.filter((c) => !this.selectedIds.includes(c.id));
          this.selectedIds = [];
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to bulk delete';
        throw error;
      }
    },

    updateCommentStatus(id, status) {
      const index = this.items.findIndex((c) => c.id === id);
      if (index !== -1) {
        this.items[index].status = status;
      }
    },

    removePendingComment(id) {
      this.pendingItems = this.pendingItems.filter((c) => c.id !== id);
      if (this.pendingPagination.total > 0) {
        this.pendingPagination.total--;
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

    selectAll(items) {
      this.selectedIds = items.map((item) => item.id);
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
        post_id: '',
      };
    },
  },
});
