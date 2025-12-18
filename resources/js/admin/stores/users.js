import { defineStore } from 'pinia';
import { users } from '../services/api';

export const useUsersStore = defineStore('admin-users', {
  state: () => ({
    items: [],
    currentUser: null,
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
      role: '',
      verified: '',
    },
    loading: false,
    saving: false,
    error: null,
  }),

  getters: {
    hasItems: (state) => state.items.length > 0,
    adminCount: (state) => state.statistics?.admin_count || 0,
    editorCount: (state) => state.statistics?.editor_count || 0,
    userCount: (state) => state.statistics?.user_count || 0,
  },

  actions: {
    async fetchUsers(page = 1) {
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
        const response = await users.list(params);
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
        this.error = error.response?.data?.message || 'Failed to fetch users';
      } finally {
        this.loading = false;
      }
    },

    setPerPage(perPage) {
      this.pagination.perPage = perPage;
    },

    async fetchUser(id) {
      this.loading = true;
      this.error = null;
      try {
        const response = await users.get(id);
        if (response.data.success) {
          this.currentUser = response.data.data;
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch user';
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async fetchStatistics() {
      try {
        const response = await users.statistics();
        if (response.data.success) {
          this.statistics = response.data.data;
        }
      } catch (error) {
        console.error('Failed to fetch statistics:', error);
      }
    },

    async createUser(data) {
      this.saving = true;
      this.error = null;
      try {
        const response = await users.create(data);
        if (response.data.success) {
          this.items.unshift(response.data.data);
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to create user';
        throw error;
      } finally {
        this.saving = false;
      }
    },

    async updateUser(id, data) {
      this.saving = true;
      this.error = null;
      try {
        const response = await users.update(id, data);
        if (response.data.success) {
          const index = this.items.findIndex((u) => u.id === id);
          if (index !== -1) {
            this.items[index] = response.data.data;
          }
          this.currentUser = response.data.data;
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update user';
        throw error;
      } finally {
        this.saving = false;
      }
    },

    async deleteUser(id) {
      this.error = null;
      try {
        const response = await users.delete(id);
        if (response.data.success) {
          this.items = this.items.filter((u) => u.id !== id);
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to delete user';
        throw error;
      }
    },

    async changeRole(id, role) {
      try {
        const response = await users.changeRole(id, role);
        if (response.data.success) {
          const index = this.items.findIndex((u) => u.id === id);
          if (index !== -1) {
            this.items[index].role = role;
          }
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to change role';
        throw error;
      }
    },

    setFilters(filters) {
      this.filters = { ...this.filters, ...filters };
    },

    clearFilters() {
      this.filters = {
        search: '',
        role: '',
        verified: '',
      };
    },

    clearCurrentUser() {
      this.currentUser = null;
    },
  },
});
