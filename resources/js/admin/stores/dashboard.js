import { defineStore } from 'pinia';
import { dashboard } from '../services/api';

export const useDashboardStore = defineStore('admin-dashboard', {
  state: () => ({
    statistics: null,
    activity: [],
    popularPosts: [],
    recentComments: [],
    charts: null,
    quickStats: null,
    loading: {
      statistics: false,
      activity: false,
      popularPosts: false,
      recentComments: false,
      charts: false,
      quickStats: false,
    },
    error: null,
  }),

  getters: {
    isLoading: (state) => Object.values(state.loading).some(Boolean),
  },

  actions: {
    async fetchStatistics() {
      this.loading.statistics = true;
      this.error = null;
      try {
        const response = await dashboard.statistics();
        if (response.data.success) {
          this.statistics = response.data.data;
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch statistics';
      } finally {
        this.loading.statistics = false;
      }
    },

    async fetchActivity(limit = 10) {
      this.loading.activity = true;
      try {
        const response = await dashboard.activity(limit);
        if (response.data.success) {
          // API returns { activities: [...] }
          this.activity = response.data.data?.activities || [];
        }
      } catch (error) {
        console.error('Failed to fetch activity:', error);
        this.activity = [];
      } finally {
        this.loading.activity = false;
      }
    },

    async fetchPopularPosts(limit = 5) {
      this.loading.popularPosts = true;
      try {
        const response = await dashboard.popularPosts(limit);
        if (response.data.success) {
          // API returns { posts: [...] }
          this.popularPosts = response.data.data?.posts || [];
        }
      } catch (error) {
        console.error('Failed to fetch popular posts:', error);
        this.popularPosts = [];
      } finally {
        this.loading.popularPosts = false;
      }
    },

    async fetchRecentComments(limit = 5) {
      this.loading.recentComments = true;
      try {
        const response = await dashboard.recentComments(limit);
        if (response.data.success) {
          // API returns { comments: [...] }
          this.recentComments = response.data.data?.comments || [];
        }
      } catch (error) {
        console.error('Failed to fetch recent comments:', error);
        this.recentComments = [];
      } finally {
        this.loading.recentComments = false;
      }
    },

    async fetchCharts(period = 'week') {
      this.loading.charts = true;
      try {
        const response = await dashboard.charts(period);
        if (response.data.success) {
          this.charts = response.data.data;
        }
      } catch (error) {
        console.error('Failed to fetch charts:', error);
      } finally {
        this.loading.charts = false;
      }
    },

    async fetchQuickStats() {
      this.loading.quickStats = true;
      try {
        const response = await dashboard.quickStats();
        if (response.data.success) {
          this.quickStats = response.data.data;
        }
      } catch (error) {
        console.error('Failed to fetch quick stats:', error);
      } finally {
        this.loading.quickStats = false;
      }
    },

    async fetchAll() {
      await Promise.all([
        this.fetchStatistics(),
        this.fetchActivity(),
        this.fetchPopularPosts(),
        this.fetchRecentComments(),
        this.fetchCharts(),
        this.fetchQuickStats(),
      ]);
    },
  },
});
