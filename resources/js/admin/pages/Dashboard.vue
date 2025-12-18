<template>
  <AdminLayout>
    <div class="dashboard">
      <!-- Page Header -->
      <div class="page-header">
        <div class="header-content">
          <h1>Dashboard</h1>
          <p>Welcome back! Here's an overview of your blog performance.</p>
        </div>
        <div class="header-actions">
          <router-link to="/admin/posts/create" class="btn-primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 5v14M5 12h14" />
            </svg>
            New Post
          </router-link>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="stats-grid">
        <StatCard
          title="Total Posts"
          :value="statistics?.posts?.total || 0"
          :change="statistics?.posts?.change"
          icon="posts"
          color="blue"
          :loading="loading.statistics"
        />
        <StatCard
          title="Total Views"
          :value="statistics?.views?.total || 0"
          :change="statistics?.views?.change"
          icon="views"
          color="green"
          :loading="loading.statistics"
        />
        <StatCard
          title="Comments"
          :value="statistics?.comments?.total || 0"
          :change="statistics?.comments?.change"
          icon="comments"
          color="purple"
          :loading="loading.statistics"
        />
        <StatCard
          title="Active Users"
          :value="statistics?.users?.total || 0"
          :change="statistics?.users?.change"
          icon="users"
          color="orange"
          :loading="loading.statistics"
        />
      </div>

      <!-- Main Content Grid -->
      <div class="main-grid">
        <!-- Left Column -->
        <div class="main-column">
          <!-- Chart Section -->
          <div class="card">
            <div class="card-header">
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
            <div class="card-body">
              <ViewsChart :data="charts" :loading="loading.charts" />
            </div>
          </div>

          <!-- Popular Posts Section -->
          <div class="card">
            <div class="card-header">
              <h2>Popular Posts</h2>
              <router-link to="/admin/posts" class="view-all">View all</router-link>
            </div>
            <div class="card-body">
              <div v-if="loading.popularPosts" class="loading-placeholder">
                <div v-for="i in 5" :key="i" class="skeleton-item"></div>
              </div>
              <div v-else-if="popularPosts.length === 0" class="empty-state">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                  <path d="M14 2v6h6"/>
                </svg>
                <p>No posts yet</p>
              </div>
              <div v-else class="posts-list">
                <div v-for="(post, index) in popularPosts" :key="post.id" class="post-item">
                  <div class="post-rank">{{ index + 1 }}</div>
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
                    {{ formatNumber(post.views_count || post.views) }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column -->
        <div class="side-column">
          <!-- Quick Stats -->
          <div class="card quick-stats-card">
            <div class="card-header">
              <h2>Quick Stats</h2>
            </div>
            <div class="card-body">
              <div class="quick-stat-item">
                <span class="stat-label">Today's Views</span>
                <span class="stat-value">{{ formatNumber(quickStats?.today_views || 0) }}</span>
              </div>
              <div class="quick-stat-item">
                <span class="stat-label">Pending Comments</span>
                <span class="stat-value warning">{{ quickStats?.new_comments || statistics?.comments?.pending || 0 }}</span>
              </div>
              <div class="quick-stat-item">
                <span class="stat-label">New Feedback</span>
                <span class="stat-value">{{ quickStats?.new_feedback || statistics?.feedback?.pending || 0 }}</span>
              </div>
              <div class="quick-stat-item">
                <span class="stat-label">Scheduled Posts</span>
                <span class="stat-value">{{ quickStats?.scheduled_posts || statistics?.posts?.scheduled || 0 }}</span>
              </div>
            </div>
          </div>

          <!-- Recent Activity -->
          <div class="card">
            <div class="card-header">
              <h2>Recent Activity</h2>
              <router-link to="/admin/comments" class="view-all">View all</router-link>
            </div>
            <div class="card-body">
              <ActivityFeed :items="activity" :loading="loading.activity" />
            </div>
          </div>

          <!-- Recent Comments -->
          <div class="card">
            <div class="card-header">
              <h2>Recent Comments</h2>
              <router-link to="/admin/comments" class="view-all">View all</router-link>
            </div>
            <div class="card-body">
              <div v-if="loading.recentComments" class="loading-placeholder">
                <div v-for="i in 3" :key="i" class="skeleton-item small"></div>
              </div>
              <div v-else-if="recentComments.length === 0" class="empty-state small">
                <p>No comments yet</p>
              </div>
              <div v-else class="comments-list">
                <div v-for="comment in recentComments.slice(0, 4)" :key="comment.id" class="comment-item">
                  <div class="comment-avatar">
                    {{ getInitials(comment.author_name || comment.user?.name || 'A') }}
                  </div>
                  <div class="comment-content">
                    <div class="comment-header">
                      <span class="comment-author">{{ comment.author_name || comment.user?.name }}</span>
                      <span class="comment-date">{{ formatRelative(comment.created_at) }}</span>
                    </div>
                    <p class="comment-text">{{ truncate(comment.content, 80) }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="card">
        <div class="card-header">
          <h2>Quick Actions</h2>
        </div>
        <div class="card-body">
          <div class="actions-grid">
            <router-link to="/admin/posts/create" class="action-card">
              <div class="action-icon blue">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 5v14M5 12h14" />
                </svg>
              </div>
              <div class="action-info">
                <span class="action-title">New Post</span>
                <span class="action-desc">Create a new blog post</span>
              </div>
            </router-link>
            <router-link to="/admin/categories/create" class="action-card">
              <div class="action-icon green">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z" />
                </svg>
              </div>
              <div class="action-info">
                <span class="action-title">New Category</span>
                <span class="action-desc">Organize your content</span>
              </div>
            </router-link>
            <router-link to="/admin/comments/pending" class="action-card">
              <div class="action-icon purple">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
                </svg>
              </div>
              <div class="action-info">
                <span class="action-title">Pending Comments</span>
                <span class="action-desc">Review and moderate</span>
              </div>
            </router-link>
            <router-link to="/admin/settings" class="action-card">
              <div class="action-icon orange">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="3" />
                  <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z" />
                </svg>
              </div>
              <div class="action-info">
                <span class="action-title">Settings</span>
                <span class="action-desc">Configure your site</span>
              </div>
            </router-link>
          </div>
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
const activity = computed(() => store.activity || []);
const popularPosts = computed(() => store.popularPosts || []);
const recentComments = computed(() => store.recentComments || []);
const charts = computed(() => store.charts);
const quickStats = computed(() => store.quickStats);
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
  if (num >= 1000000000) return (num / 1000000000).toFixed(1) + 'B';
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
  width: 100%;
}

/* Page Header */
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 28px;
  gap: 16px;
}

.header-content h1 {
  font-size: 26px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 6px 0;
  letter-spacing: -0.025em;
}

.header-content p {
  color: #64748b;
  margin: 0;
  font-size: 15px;
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  background: #2563eb;
  color: #fff;
  border-radius: 10px;
  text-decoration: none;
  font-weight: 500;
  font-size: 14px;
  transition: all 0.2s ease;
  border: none;
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
}

.btn-primary:hover {
  background: #1d4ed8;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
  margin-bottom: 28px;
}

/* Main Grid Layout */
.main-grid {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 24px;
  margin-bottom: 24px;
}

.main-column {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.side-column {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Card Styles */
.card {
  background: #fff;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 22px;
  border-bottom: 1px solid #f1f5f9;
}

.card-header h2 {
  font-size: 16px;
  font-weight: 600;
  color: #0f172a;
  margin: 0;
}

.card-body {
  padding: 18px 22px;
}

.view-all {
  color: #2563eb;
  text-decoration: none;
  font-size: 13px;
  font-weight: 500;
  transition: color 0.2s;
}

.view-all:hover {
  color: #1d4ed8;
}

/* Period Selector */
.period-selector {
  display: flex;
  gap: 2px;
  background: #f1f5f9;
  padding: 3px;
  border-radius: 8px;
}

.period-selector button {
  padding: 6px 14px;
  border: none;
  background: transparent;
  color: #64748b;
  font-size: 13px;
  font-weight: 500;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.period-selector button.active {
  background: #fff;
  color: #0f172a;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.period-selector button:hover:not(.active) {
  color: #334155;
}

/* Quick Stats Card */
.quick-stats-card .card-body {
  padding: 12px 22px 18px;
}

.quick-stat-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 0;
  border-bottom: 1px solid #f1f5f9;
}

.quick-stat-item:last-child {
  border-bottom: none;
}

.stat-label {
  font-size: 14px;
  color: #64748b;
}

.stat-value {
  font-size: 15px;
  font-weight: 600;
  color: #0f172a;
}

.stat-value.warning {
  color: #ea580c;
}

/* Posts List */
.posts-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.post-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  background: #f8fafc;
  border-radius: 12px;
  transition: background 0.2s;
}

.post-item:hover {
  background: #f1f5f9;
}

.post-rank {
  width: 28px;
  height: 28px;
  background: #e2e8f0;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 600;
  color: #475569;
  flex-shrink: 0;
}

.post-item:first-child .post-rank {
  background: #fef3c7;
  color: #b45309;
}

.post-item:nth-child(2) .post-rank {
  background: #e2e8f0;
  color: #475569;
}

.post-item:nth-child(3) .post-rank {
  background: #fed7aa;
  color: #c2410c;
}

.post-info {
  flex: 1;
  min-width: 0;
}

.post-title {
  display: block;
  color: #0f172a;
  text-decoration: none;
  font-weight: 500;
  font-size: 14px;
  margin-bottom: 3px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.post-title:hover {
  color: #2563eb;
}

.post-meta {
  color: #94a3b8;
  font-size: 12px;
}

.post-views {
  display: flex;
  align-items: center;
  gap: 5px;
  color: #64748b;
  font-size: 13px;
  font-weight: 500;
  flex-shrink: 0;
}

.post-views svg {
  opacity: 0.7;
}

/* Comments List */
.comments-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.comment-item {
  display: flex;
  gap: 12px;
}

.comment-avatar {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
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
  font-size: 13px;
  color: #0f172a;
}

.comment-date {
  color: #94a3b8;
  font-size: 12px;
}

.comment-text {
  color: #64748b;
  font-size: 13px;
  margin: 0;
  line-height: 1.5;
}

/* Loading & Empty States */
.loading-placeholder {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.skeleton-item {
  height: 56px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 12px;
}

.skeleton-item.small {
  height: 48px;
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
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  color: #94a3b8;
}

.empty-state svg {
  margin-bottom: 12px;
  opacity: 0.5;
}

.empty-state p {
  margin: 0;
  font-size: 14px;
}

.empty-state.small {
  padding: 24px 16px;
}

/* Actions Grid */
.actions-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.action-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 18px 20px;
  background: #f8fafc;
  border-radius: 14px;
  text-decoration: none;
  transition: all 0.2s ease;
  border: 1px solid transparent;
}

.action-card:hover {
  background: #fff;
  border-color: #e2e8f0;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  transform: translateY(-2px);
}

.action-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.action-icon.blue {
  background: #eff6ff;
  color: #2563eb;
}

.action-icon.green {
  background: #ecfdf5;
  color: #059669;
}

.action-icon.purple {
  background: #f5f3ff;
  color: #7c3aed;
}

.action-icon.orange {
  background: #fff7ed;
  color: #ea580c;
}

.action-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.action-title {
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
}

.action-desc {
  font-size: 12px;
  color: #64748b;
}

/* Responsive */
@media (max-width: 1400px) {
  .main-grid {
    grid-template-columns: 1fr 340px;
  }
}

@media (max-width: 1200px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .main-grid {
    grid-template-columns: 1fr;
  }

  .side-column {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
  }

  .actions-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: row;
    align-items: center;
  }

  .header-content h1 {
    font-size: 20px;
  }

  .header-content p {
    display: none;
  }

  .btn-primary {
    padding: 8px 14px;
    font-size: 13px;
    gap: 6px;
  }

  .btn-primary svg {
    width: 16px;
    height: 16px;
  }

  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
  }

  .side-column {
    grid-template-columns: 1fr;
  }

  .actions-grid {
    grid-template-columns: 1fr;
  }

  .card-header,
  .card-body {
    padding: 14px 16px;
  }

  .action-card {
    padding: 14px 16px;
  }
}

@media (max-width: 480px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
}
</style>
