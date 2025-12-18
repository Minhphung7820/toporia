<template>
  <AdminLayout>
    <div class="dashboard">
      <div class="page-header">
        <h1>Dashboard</h1>
        <p>Welcome back! Here's what's happening with your blog.</p>
      </div>

      <!-- Statistics Cards -->
      <div class="stats-grid">
        <StatCard
          title="Total Posts"
          :value="statistics?.posts?.total || 0"
          :change="statistics?.posts?.change"
          icon="posts"
          color="blue"
        />
        <StatCard
          title="Total Views"
          :value="statistics?.views?.total || 0"
          :change="statistics?.views?.change"
          icon="views"
          color="green"
        />
        <StatCard
          title="Comments"
          :value="statistics?.comments?.total || 0"
          :change="statistics?.comments?.change"
          icon="comments"
          color="purple"
        />
        <StatCard
          title="Active Users"
          :value="statistics?.users?.total || 0"
          :change="statistics?.users?.change"
          icon="users"
          color="orange"
        />
      </div>

      <!-- Charts and Activity -->
      <div class="dashboard-grid">
        <div class="chart-section">
          <div class="section-header">
            <h2>Views Overview</h2>
            <div class="period-selector">
              <button
                v-for="period in periods"
                :key="period.value"
                :class="{ active: selectedPeriod === period.value }"
                @click="changePeriod(period.value)"
              >
                {{ period.label }}
              </button>
            </div>
          </div>
          <ViewsChart :data="charts" :loading="loading.charts" />
        </div>

        <div class="activity-section">
          <div class="section-header">
            <h2>Recent Activity</h2>
            <router-link to="/admin/comments" class="view-all">View all</router-link>
          </div>
          <ActivityFeed :items="activity" :loading="loading.activity" />
        </div>
      </div>

      <!-- Popular Posts and Recent Comments -->
      <div class="dashboard-grid">
        <div class="popular-posts-section">
          <div class="section-header">
            <h2>Popular Posts</h2>
            <router-link to="/admin/posts" class="view-all">View all</router-link>
          </div>
          <div class="posts-list">
            <div v-if="loading.popularPosts" class="loading-placeholder">
              <div v-for="i in 5" :key="i" class="skeleton-item"></div>
            </div>
            <div v-else-if="popularPosts.length === 0" class="empty-state">
              No posts yet
            </div>
            <div v-else v-for="post in popularPosts" :key="post.id" class="post-item">
              <div class="post-info">
                <router-link :to="`/admin/posts/${post.id}/edit`" class="post-title">
                  {{ post.title }}
                </router-link>
                <span class="post-meta">{{ formatDate(post.published_at) }}</span>
              </div>
              <div class="post-views">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                  <circle cx="12" cy="12" r="3" />
                </svg>
                {{ formatNumber(post.views_count) }}
              </div>
            </div>
          </div>
        </div>

        <div class="recent-comments-section">
          <div class="section-header">
            <h2>Recent Comments</h2>
            <router-link to="/admin/comments" class="view-all">View all</router-link>
          </div>
          <div class="comments-list">
            <div v-if="loading.recentComments" class="loading-placeholder">
              <div v-for="i in 5" :key="i" class="skeleton-item"></div>
            </div>
            <div v-else-if="recentComments.length === 0" class="empty-state">
              No comments yet
            </div>
            <div v-else v-for="comment in recentComments" :key="comment.id" class="comment-item">
              <div class="comment-avatar">
                {{ getInitials(comment.author_name || comment.user?.name || 'A') }}
              </div>
              <div class="comment-content">
                <div class="comment-header">
                  <span class="comment-author">{{ comment.author_name || comment.user?.name }}</span>
                  <span class="comment-date">{{ formatRelative(comment.created_at) }}</span>
                </div>
                <p class="comment-text">{{ truncate(comment.content, 100) }}</p>
                <span class="comment-post">on {{ comment.post?.title }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="quick-actions">
        <h2>Quick Actions</h2>
        <div class="actions-grid">
          <router-link to="/admin/posts/create" class="action-card">
            <div class="action-icon blue">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 5v14M5 12h14" />
              </svg>
            </div>
            <span>New Post</span>
          </router-link>
          <router-link to="/admin/categories/create" class="action-card">
            <div class="action-icon green">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z" />
              </svg>
            </div>
            <span>New Category</span>
          </router-link>
          <router-link to="/admin/comments/pending" class="action-card">
            <div class="action-icon purple">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
              </svg>
            </div>
            <span>Pending Comments</span>
          </router-link>
          <router-link to="/admin/settings" class="action-card">
            <div class="action-icon orange">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3" />
                <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z" />
              </svg>
            </div>
            <span>Settings</span>
          </router-link>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AdminLayout from '../components/layout/AdminLayout.vue';
import StatCard from '../components/dashboard/StatCard.vue';
import ViewsChart from '../components/dashboard/ViewsChart.vue';
import ActivityFeed from '../components/dashboard/ActivityFeed.vue';
import { useDashboardStore } from '../stores/dashboard';

const store = useDashboardStore();

const selectedPeriod = ref('week');
const periods = [
  { value: 'week', label: 'Week' },
  { value: 'month', label: 'Month' },
  { value: 'year', label: 'Year' },
];

const statistics = computed(() => store.statistics);
const activity = computed(() => store.activity);
const popularPosts = computed(() => store.popularPosts);
const recentComments = computed(() => store.recentComments);
const charts = computed(() => store.charts);
const loading = computed(() => store.loading);

const changePeriod = async (period) => {
  selectedPeriod.value = period;
  await store.fetchCharts(period);
};

const formatDate = (date) => {
  if (!date) return '';
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};

const formatRelative = (date) => {
  if (!date) return '';
  const now = new Date();
  const past = new Date(date);
  const diffMs = now - past;
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 1) return 'just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays < 7) return `${diffDays}d ago`;
  return formatDate(date);
};

const formatNumber = (num) => {
  if (!num) return '0';
  if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
  if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
  return num.toString();
};

const getInitials = (name) => {
  return name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
};

const truncate = (text, length) => {
  if (!text) return '';
  if (text.length <= length) return text;
  return text.slice(0, length) + '...';
};

onMounted(() => {
  store.fetchAll();
});
</script>

<style scoped>
.dashboard {
  max-width: 1400px;
}

.page-header {
  margin-bottom: 32px;
}

.page-header h1 {
  font-size: 28px;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 8px 0;
}

.page-header p {
  color: #6b7280;
  margin: 0;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 24px;
  margin-bottom: 32px;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 24px;
  margin-bottom: 32px;
}

.chart-section,
.activity-section,
.popular-posts-section,
.recent-comments-section {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}

.section-header h2 {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.period-selector {
  display: flex;
  gap: 4px;
  background: #f3f4f6;
  padding: 4px;
  border-radius: 8px;
}

.period-selector button {
  padding: 6px 12px;
  border: none;
  background: transparent;
  color: #6b7280;
  font-size: 13px;
  font-weight: 500;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.period-selector button.active {
  background: #fff;
  color: #1f2937;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.view-all {
  color: #4f46e5;
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
}

.view-all:hover {
  text-decoration: underline;
}

.posts-list,
.comments-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.post-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px;
  background: #f9fafb;
  border-radius: 8px;
}

.post-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.post-title {
  color: #1f2937;
  text-decoration: none;
  font-weight: 500;
  font-size: 14px;
}

.post-title:hover {
  color: #4f46e5;
}

.post-meta {
  color: #9ca3af;
  font-size: 12px;
}

.post-views {
  display: flex;
  align-items: center;
  gap: 4px;
  color: #6b7280;
  font-size: 13px;
}

.comment-item {
  display: flex;
  gap: 12px;
  padding: 12px;
  background: #f9fafb;
  border-radius: 8px;
}

.comment-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
  flex-shrink: 0;
}

.comment-content {
  flex: 1;
  min-width: 0;
}

.comment-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.comment-author {
  font-weight: 500;
  font-size: 14px;
  color: #1f2937;
}

.comment-date {
  color: #9ca3af;
  font-size: 12px;
}

.comment-text {
  color: #6b7280;
  font-size: 13px;
  margin: 0 0 4px 0;
  line-height: 1.4;
}

.comment-post {
  color: #9ca3af;
  font-size: 12px;
}

.loading-placeholder {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.skeleton-item {
  height: 60px;
  background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 8px;
}

@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

.empty-state {
  text-align: center;
  padding: 32px;
  color: #9ca3af;
}

.quick-actions {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.quick-actions h2 {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 20px 0;
}

.actions-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.action-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 24px;
  background: #f9fafb;
  border-radius: 12px;
  text-decoration: none;
  transition: all 0.2s ease;
}

.action-card:hover {
  background: #f3f4f6;
  transform: translateY(-2px);
}

.action-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
}

.action-icon.blue {
  background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
}

.action-icon.green {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.action-icon.purple {
  background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
}

.action-icon.orange {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.action-card span {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}

@media (max-width: 1200px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .dashboard-grid {
    grid-template-columns: 1fr;
  }

  .actions-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }

  .actions-grid {
    grid-template-columns: 1fr;
  }
}
</style>
