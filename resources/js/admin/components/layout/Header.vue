<template>
  <header class="admin-header" :class="{ 'sidebar-collapsed': sidebarCollapsed }">
    <div class="header-left">
      <button class="menu-toggle" @click="$emit('toggle-sidebar')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="3" y1="12" x2="21" y2="12" />
          <line x1="3" y1="6" x2="21" y2="6" />
          <line x1="3" y1="18" x2="21" y2="18" />
        </svg>
      </button>

      <div class="search-box">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        <input
          type="text"
          v-model="searchQuery"
          placeholder="Search..."
          @keyup.enter="handleSearch"
        />
      </div>
    </div>

    <div class="header-right">
      <!-- Notification Bell with Dropdown -->
      <div class="notification-menu" ref="notificationMenuRef">
        <button class="header-btn notification-btn" @click="toggleNotificationMenu" title="Notifications">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.73 21a2 2 0 01-3.46 0" />
          </svg>
          <span v-if="notificationCount > 0" class="badge" :class="{ 'animate': hasNewNotification }">
            {{ notificationCount > 99 ? '99+' : notificationCount }}
          </span>
          <!-- Realtime indicator -->
          <span v-if="isConnected" class="realtime-dot"></span>
        </button>

        <Transition name="dropdown">
          <div v-if="showNotificationMenu" class="notification-dropdown">
            <div class="dropdown-header">
              <h3>Notifications</h3>
              <div class="header-actions">
                <span class="connection-status" :class="{ connected: isConnected }">
                  <span class="status-dot"></span>
                  {{ isConnected ? 'Live' : 'Offline' }}
                </span>
                <button v-if="pendingComments.length > 0" @click="markAllAsRead" class="mark-read-btn">
                  Mark all read
                </button>
              </div>
            </div>

            <div class="dropdown-tabs">
              <button
                class="tab-btn"
                :class="{ active: activeTab === 'comments' }"
                @click="activeTab = 'comments'"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
                </svg>
                Comments
                <span v-if="pendingCommentsCount > 0" class="tab-badge">{{ pendingCommentsCount }}</span>
              </button>
              <button
                class="tab-btn"
                :class="{ active: activeTab === 'feedback' }"
                @click="activeTab = 'feedback'"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z" />
                </svg>
                Feedback
                <span v-if="pendingFeedbackCount > 0" class="tab-badge">{{ pendingFeedbackCount }}</span>
              </button>
            </div>

            <div class="dropdown-content">
              <!-- Comments Tab -->
              <div v-if="activeTab === 'comments'" class="notification-list">
                <div v-if="loadingNotifications" class="loading-state">
                  <div class="spinner"></div>
                  <span>Loading...</span>
                </div>
                <div v-else-if="pendingComments.length === 0" class="empty-state">
                  <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
                    <polyline points="9 10 12 13 15 10" />
                  </svg>
                  <p>No pending comments</p>
                </div>
                <TransitionGroup v-else name="notification-item" tag="div">
                  <div
                    v-for="comment in pendingComments.slice(0, 5)"
                    :key="comment.id"
                    class="notification-item"
                    :class="{ new: isNewComment(comment.id) }"
                    @click="goToComment(comment)"
                  >
                    <div class="item-avatar" :style="{ background: getAvatarGradient(comment.author_name) }">
                      {{ getInitials(comment.author_name || 'A') }}
                    </div>
                    <div class="item-content">
                      <div class="item-header">
                        <span class="item-author">{{ comment.author_name || 'Anonymous' }}</span>
                        <span class="item-time">{{ formatTimeAgo(comment.created_at) }}</span>
                      </div>
                      <p class="item-text">{{ truncate(comment.content, 60) }}</p>
                      <div v-if="comment.post" class="item-meta">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                        </svg>
                        <span>{{ truncate(comment.post.title, 25) }}</span>
                      </div>
                    </div>
                    <div class="item-actions">
                      <button @click.stop="quickApprove(comment.id)" class="quick-action approve" title="Approve">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                          <polyline points="20 6 9 17 4 12" />
                        </svg>
                      </button>
                      <button @click.stop="quickReject(comment.id)" class="quick-action reject" title="Reject">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                          <line x1="18" y1="6" x2="6" y2="18" />
                          <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                      </button>
                    </div>
                  </div>
                </TransitionGroup>
              </div>

              <!-- Feedback Tab -->
              <div v-if="activeTab === 'feedback'" class="notification-list">
                <div v-if="loadingNotifications" class="loading-state">
                  <div class="spinner"></div>
                  <span>Loading...</span>
                </div>
                <div v-else-if="pendingFeedback.length === 0" class="empty-state">
                  <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z" />
                  </svg>
                  <p>No pending feedback</p>
                </div>
                <TransitionGroup v-else name="notification-item" tag="div">
                  <div
                    v-for="feedback in pendingFeedback.slice(0, 5)"
                    :key="feedback.id"
                    class="notification-item"
                    @click="goToFeedback(feedback)"
                  >
                    <div class="item-avatar" :style="{ background: getAvatarGradient(feedback.name) }">
                      {{ getInitials(feedback.name || 'A') }}
                    </div>
                    <div class="item-content">
                      <div class="item-header">
                        <span class="item-author">{{ feedback.name || 'Anonymous' }}</span>
                        <span class="item-time">{{ formatTimeAgo(feedback.created_at) }}</span>
                      </div>
                      <p class="item-text">{{ truncate(feedback.message, 60) }}</p>
                      <div class="item-meta">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <circle cx="12" cy="12" r="10" />
                          <line x1="12" y1="16" x2="12" y2="12" />
                          <line x1="12" y1="8" x2="12.01" y2="8" />
                        </svg>
                        <span>{{ feedback.type || 'General' }}</span>
                      </div>
                    </div>
                  </div>
                </TransitionGroup>
              </div>
            </div>

            <div class="dropdown-footer">
              <router-link
                v-if="activeTab === 'comments'"
                to="/admin/comments/pending"
                class="view-all-btn"
                @click="showNotificationMenu = false"
              >
                View all pending comments
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="5" y1="12" x2="19" y2="12" />
                  <polyline points="12 5 19 12 12 19" />
                </svg>
              </router-link>
              <router-link
                v-else
                to="/admin/feedback"
                class="view-all-btn"
                @click="showNotificationMenu = false"
              >
                View all feedback
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="5" y1="12" x2="19" y2="12" />
                  <polyline points="12 5 19 12 12 19" />
                </svg>
              </router-link>
            </div>
          </div>
        </Transition>
      </div>

      <a href="/" target="_blank" class="header-btn" title="View Site">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
          <path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6" />
          <polyline points="15 3 21 3 21 9" />
          <line x1="10" y1="14" x2="21" y2="3" />
        </svg>
      </a>

      <div class="user-menu" ref="userMenuRef">
        <button class="user-btn" @click="toggleUserMenu">
          <div class="user-avatar">
            <img v-if="user?.avatar" :src="user.avatar" :alt="user.name" />
            <span v-else>{{ userInitials }}</span>
          </div>
          <span class="user-name">{{ user?.name || 'Admin' }}</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 12 15 18 9" />
          </svg>
        </button>

        <Transition name="dropdown">
          <div v-if="showUserMenu" class="user-dropdown">
            <div class="dropdown-header">
              <strong>{{ user?.name }}</strong>
              <span>{{ user?.email }}</span>
            </div>
            <div class="dropdown-divider"></div>
            <router-link to="/admin/profile" class="dropdown-item" @click="showUserMenu = false">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" />
                <circle cx="12" cy="7" r="4" />
              </svg>
              Profile
            </router-link>
            <router-link to="/admin/settings" class="dropdown-item" @click="showUserMenu = false">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                <circle cx="12" cy="12" r="3" />
                <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z" />
              </svg>
              Settings
            </router-link>
            <div class="dropdown-divider"></div>
            <button class="dropdown-item logout" @click="handleLogout">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" />
                <polyline points="16 17 21 12 16 7" />
                <line x1="21" y1="12" x2="9" y2="12" />
              </svg>
              Logout
            </button>
          </div>
        </Transition>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../../stores/auth';
import { useCommentsStore } from '../../stores/comments';
import { useFeedbackStore } from '../../stores/feedback';
import { useWebSocket } from '../../composables/useWebSocket';

defineProps({
  sidebarCollapsed: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['toggle-sidebar']);

const router = useRouter();
const authStore = useAuthStore();
const commentsStore = useCommentsStore();
const feedbackStore = useFeedbackStore();
const { isConnected, connect, subscribe, unsubscribe, on, off } = useWebSocket();

// Refs
const searchQuery = ref('');
const showUserMenu = ref(false);
const showNotificationMenu = ref(false);
const userMenuRef = ref(null);
const notificationMenuRef = ref(null);
const activeTab = ref('comments');
const loadingNotifications = ref(false);
const hasNewNotification = ref(false);
const newCommentIds = ref([]);

// Computed
const user = computed(() => authStore.user);
const userInitials = computed(() => {
  if (!user.value?.name) return 'A';
  return user.value.name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
});

const pendingComments = computed(() => commentsStore.pendingItems || []);
const pendingFeedback = computed(() => feedbackStore.pendingItems || []);
const pendingCommentsCount = computed(() => commentsStore.pendingCount || 0);
const pendingFeedbackCount = computed(() => feedbackStore.pendingCount || 0);
const notificationCount = computed(() => pendingCommentsCount.value + pendingFeedbackCount.value);

// Avatar gradient colors
const avatarGradients = [
  'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
  'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
  'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
  'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
  'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
  'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)',
  'linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)',
  'linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%)',
];

// Helper Functions
const getInitials = (name) => {
  if (!name) return 'A';
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
};

const getAvatarGradient = (name) => {
  if (!name) return avatarGradients[0];
  const charCode = name.charCodeAt(0);
  return avatarGradients[charCode % avatarGradients.length];
};

const formatTimeAgo = (date) => {
  if (!date) return '';
  const now = new Date();
  const past = new Date(date);
  const diffMs = now - past;
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins}m`;
  if (diffHours < 24) return `${diffHours}h`;
  if (diffDays < 7) return `${diffDays}d`;
  return past.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

const truncate = (text, length) => {
  if (!text) return '';
  return text.length > length ? text.substring(0, length) + '...' : text;
};

const isNewComment = (id) => {
  return newCommentIds.value.includes(id);
};

// Toggle functions
const toggleUserMenu = () => {
  showUserMenu.value = !showUserMenu.value;
  if (showUserMenu.value) {
    showNotificationMenu.value = false;
  }
};

const toggleNotificationMenu = async () => {
  showNotificationMenu.value = !showNotificationMenu.value;
  if (showNotificationMenu.value) {
    showUserMenu.value = false;
    hasNewNotification.value = false;
    await loadNotifications();
  }
};

// Load notifications
const loadNotifications = async () => {
  loadingNotifications.value = true;
  try {
    await Promise.all([
      commentsStore.fetchPending(1),
      commentsStore.fetchStatistics(),
      feedbackStore.fetchPending?.(1) || Promise.resolve(),
      feedbackStore.fetchStatistics?.() || Promise.resolve()
    ]);
  } catch (error) {
    console.error('Failed to load notifications:', error);
  } finally {
    loadingNotifications.value = false;
  }
};

// Actions
const handleSearch = () => {
  if (searchQuery.value.trim()) {
    console.log('Search:', searchQuery.value);
  }
};

const handleLogout = async () => {
  await authStore.logout();
  window.location.href = '/login';
};

const goToComment = (comment) => {
  showNotificationMenu.value = false;
  router.push('/admin/comments/pending');
};

const goToFeedback = (feedback) => {
  showNotificationMenu.value = false;
  router.push('/admin/feedback');
};

const quickApprove = async (id) => {
  try {
    await commentsStore.approveComment(id);
  } catch (error) {
    console.error('Failed to approve:', error);
  }
};

const quickReject = async (id) => {
  try {
    await commentsStore.rejectComment(id);
  } catch (error) {
    console.error('Failed to reject:', error);
  }
};

const markAllAsRead = () => {
  newCommentIds.value = [];
};

// Click outside handler
const handleClickOutside = (event) => {
  if (userMenuRef.value && !userMenuRef.value.contains(event.target)) {
    showUserMenu.value = false;
  }
  if (notificationMenuRef.value && !notificationMenuRef.value.contains(event.target)) {
    showNotificationMenu.value = false;
  }
};

// WebSocket handlers
const handleNewComment = (data) => {
  if (data.status === 'pending') {
    newCommentIds.value.push(data.id);
    hasNewNotification.value = true;
    // Fetch both pending list and statistics to update badge count
    commentsStore.fetchPending(1);
    commentsStore.fetchStatistics();
  }
};

const handleCommentUpdated = () => {
  // Fetch both pending list and statistics to update badge count
  commentsStore.fetchPending(1);
  commentsStore.fetchStatistics();
};

// Lifecycle
onMounted(() => {
  document.addEventListener('click', handleClickOutside);

  // Connect WebSocket
  connect();
  subscribe('admin.notifications');
  on('comment.created', handleNewComment, 'admin.notifications');
  on('comment.updated', handleCommentUpdated, 'admin.notifications');

  // Load initial data
  loadNotifications();
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  off('comment.created', handleNewComment, 'admin.notifications');
  off('comment.updated', handleCommentUpdated, 'admin.notifications');
  unsubscribe('admin.notifications');
});
</script>

<style scoped>
.admin-header {
  height: 72px;
  background: #fff;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 28px;
  position: fixed;
  top: 0;
  right: 0;
  left: 260px;
  z-index: 100;
  transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.admin-header.sidebar-collapsed {
  left: 72px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.menu-toggle {
  display: none;
  padding: 10px;
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
  border-radius: 10px;
  transition: all 0.2s ease;
}

.menu-toggle:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.search-box {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  min-width: 280px;
  transition: all 0.2s ease;
}

.search-box:focus-within {
  border-color: #2563eb;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.search-box svg {
  color: #94a3b8;
  flex-shrink: 0;
}

.search-box input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font-size: 14px;
  color: #0f172a;
}

.search-box input::placeholder {
  color: #94a3b8;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 6px;
}

.header-btn {
  position: relative;
  padding: 10px;
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
  border-radius: 10px;
  text-decoration: none;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.header-btn:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.header-btn .badge {
  position: absolute;
  top: 4px;
  right: 4px;
  background: #dc2626;
  color: #fff;
  font-size: 10px;
  font-weight: 600;
  padding: 2px 5px;
  border-radius: 10px;
  min-width: 18px;
  text-align: center;
  line-height: 1.2;
}

.header-btn .badge.animate {
  animation: badgePulse 0.5s ease;
}

@keyframes badgePulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.2); }
}

.realtime-dot {
  position: absolute;
  bottom: 6px;
  right: 6px;
  width: 8px;
  height: 8px;
  background: #22c55e;
  border-radius: 50%;
  border: 2px solid white;
}

/* Notification Menu */
.notification-menu {
  position: relative;
}

.notification-dropdown {
  position: absolute;
  top: calc(100% + 12px);
  right: 0;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
  width: 400px;
  max-height: 520px;
  overflow: hidden;
  z-index: 1000;
  display: flex;
  flex-direction: column;
}

.notification-dropdown .dropdown-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.notification-dropdown .dropdown-header h3 {
  font-size: 16px;
  font-weight: 600;
  color: #0f172a;
  margin: 0;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.connection-status {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 500;
  color: #dc2626;
  padding: 4px 8px;
  border-radius: 12px;
  background: #fef2f2;
}

.connection-status.connected {
  color: #16a34a;
  background: #f0fdf4;
}

.connection-status .status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.mark-read-btn {
  font-size: 12px;
  color: #4f46e5;
  background: none;
  border: none;
  cursor: pointer;
  font-weight: 500;
}

.mark-read-btn:hover {
  text-decoration: underline;
}

/* Tabs */
.dropdown-tabs {
  display: flex;
  border-bottom: 1px solid #e2e8f0;
}

.tab-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 16px;
  background: none;
  border: none;
  font-size: 13px;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
  transition: all 0.2s ease;
  border-bottom: 2px solid transparent;
}

.tab-btn:hover {
  color: #0f172a;
  background: #f8fafc;
}

.tab-btn.active {
  color: #4f46e5;
  border-bottom-color: #4f46e5;
}

.tab-badge {
  background: #dc2626;
  color: white;
  font-size: 10px;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 10px;
  min-width: 18px;
  text-align: center;
}

/* Dropdown Content */
.dropdown-content {
  flex: 1;
  overflow-y: auto;
  max-height: 340px;
}

.notification-list {
  padding: 8px;
}

.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  color: #94a3b8;
}

.loading-state .spinner {
  width: 24px;
  height: 24px;
  border: 3px solid #e2e8f0;
  border-top-color: #4f46e5;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin-bottom: 8px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-state svg {
  margin-bottom: 8px;
  opacity: 0.5;
}

.empty-state p {
  font-size: 13px;
  margin: 0;
}

/* Notification Item */
.notification-item {
  display: flex;
  gap: 12px;
  padding: 12px;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
}

.notification-item:hover {
  background: #f8fafc;
}

.notification-item.new {
  background: #eff6ff;
}

.notification-item.new::before {
  content: '';
  position: absolute;
  left: 4px;
  top: 50%;
  transform: translateY(-50%);
  width: 6px;
  height: 6px;
  background: #4f46e5;
  border-radius: 50%;
}

.item-avatar {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 600;
  color: white;
  flex-shrink: 0;
}

.item-content {
  flex: 1;
  min-width: 0;
}

.item-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 4px;
}

.item-author {
  font-size: 13px;
  font-weight: 600;
  color: #0f172a;
}

.item-time {
  font-size: 11px;
  color: #94a3b8;
}

.item-text {
  font-size: 13px;
  color: #475569;
  margin: 0 0 6px 0;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.item-meta {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: #94a3b8;
}

/* Quick Actions */
.item-actions {
  display: flex;
  flex-direction: column;
  gap: 4px;
  opacity: 0;
  transition: opacity 0.2s ease;
}

.notification-item:hover .item-actions {
  opacity: 1;
}

.quick-action {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.quick-action.approve {
  background: #dcfce7;
  color: #16a34a;
}

.quick-action.approve:hover {
  background: #bbf7d0;
}

.quick-action.reject {
  background: #fee2e2;
  color: #dc2626;
}

.quick-action.reject:hover {
  background: #fecaca;
}

/* Dropdown Footer */
.dropdown-footer {
  padding: 12px 16px;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
}

.view-all-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 10px;
  background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
  color: white;
  text-decoration: none;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 500;
  transition: all 0.2s ease;
}

.view-all-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4);
}

/* User Menu */
.user-menu {
  position: relative;
  margin-left: 8px;
}

.user-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 12px 6px 6px;
  background: transparent;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.user-btn:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
}

.user-avatar {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 12px;
  font-weight: 600;
  overflow: hidden;
}

.user-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.user-name {
  font-size: 14px;
  font-weight: 500;
  color: #0f172a;
}

.user-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
  min-width: 220px;
  overflow: hidden;
  z-index: 1000;
}

.user-dropdown .dropdown-header {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  background: #f8fafc;
}

.user-dropdown .dropdown-header strong {
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
}

.user-dropdown .dropdown-header span {
  font-size: 12px;
  color: #64748b;
}

.dropdown-divider {
  height: 1px;
  background: #e2e8f0;
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  color: #334155;
  text-decoration: none;
  font-size: 14px;
  background: transparent;
  border: none;
  width: 100%;
  cursor: pointer;
  transition: all 0.2s ease;
}

.dropdown-item:hover {
  background: #f8fafc;
}

.dropdown-item.logout {
  color: #dc2626;
}

.dropdown-item.logout:hover {
  background: #fef2f2;
}

/* Transitions */
.dropdown-enter-active,
.dropdown-leave-active {
  transition: all 0.2s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

.notification-item-enter-active {
  transition: all 0.3s ease;
}

.notification-item-leave-active {
  transition: all 0.2s ease;
}

.notification-item-enter-from {
  opacity: 0;
  transform: translateX(-10px);
}

.notification-item-leave-to {
  opacity: 0;
  transform: translateX(10px);
}

/* Responsive */
@media (max-width: 1024px) {
  .admin-header {
    left: 0;
    padding: 0 16px;
    width: 100%;
    box-sizing: border-box;
  }

  .admin-header.sidebar-collapsed {
    left: 0;
  }

  .menu-toggle {
    display: flex;
  }

  .search-box {
    display: none;
  }

  .notification-dropdown {
    width: 380px;
    right: -20px;
  }
}

@media (max-width: 768px) {
  .admin-header {
    height: 56px;
    padding: 0 12px;
  }

  .header-left {
    gap: 8px;
  }

  .menu-toggle {
    padding: 8px;
  }

  .header-right {
    gap: 2px;
  }

  .header-btn {
    padding: 8px;
  }

  .user-name {
    display: none;
  }

  .user-btn {
    padding: 4px;
    border: none;
    gap: 0;
  }

  .user-btn svg {
    display: none;
  }

  .user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 8px;
  }

  .user-dropdown {
    right: -8px;
    min-width: 200px;
  }

  /* Full-screen notification dropdown on mobile */
  .notification-dropdown {
    position: fixed;
    top: 56px;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100%;
    max-width: 100%;
    max-height: none;
    height: calc(100vh - 56px);
    border-radius: 0;
    border: none;
    border-top: 1px solid #e2e8f0;
    box-shadow: none;
    z-index: 1001;
  }

  .notification-dropdown .dropdown-header {
    padding: 14px 16px;
    position: sticky;
    top: 0;
    z-index: 1;
  }

  .notification-dropdown .dropdown-header h3 {
    font-size: 15px;
  }

  .header-actions {
    gap: 8px;
  }

  .connection-status {
    font-size: 10px;
    padding: 3px 6px;
  }

  .mark-read-btn {
    font-size: 11px;
  }

  .dropdown-tabs {
    position: sticky;
    top: 52px;
    background: #fff;
    z-index: 1;
  }

  .tab-btn {
    padding: 12px 8px;
    font-size: 13px;
    gap: 6px;
  }

  .tab-btn svg {
    width: 14px;
    height: 14px;
  }

  .dropdown-content {
    flex: 1;
    max-height: none;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
  }

  .notification-list {
    padding: 8px 12px;
  }

  .notification-item {
    padding: 12px;
    gap: 10px;
  }

  .item-avatar {
    width: 40px;
    height: 40px;
    font-size: 13px;
    border-radius: 10px;
  }

  .item-header {
    margin-bottom: 3px;
  }

  .item-author {
    font-size: 13px;
  }

  .item-time {
    font-size: 11px;
  }

  .item-text {
    font-size: 13px;
    -webkit-line-clamp: 2;
    line-clamp: 2;
  }

  .item-meta {
    font-size: 11px;
  }

  .item-actions {
    opacity: 1;
    flex-direction: row;
    gap: 6px;
  }

  .quick-action {
    width: 32px;
    height: 32px;
  }

  .dropdown-footer {
    position: sticky;
    bottom: 0;
    padding: 12px 16px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
  }

  .view-all-btn {
    padding: 12px;
    font-size: 14px;
    border-radius: 12px;
  }

  .empty-state,
  .loading-state {
    padding: 60px 20px;
  }

  .empty-state svg {
    width: 48px;
    height: 48px;
  }

  .empty-state p {
    font-size: 14px;
  }
}

@media (max-width: 480px) {
  .admin-header {
    padding: 0 8px;
  }

  .header-btn {
    padding: 6px;
  }

  .menu-toggle {
    padding: 6px;
  }

  .menu-toggle svg {
    width: 20px;
    height: 20px;
  }

  .notification-dropdown .dropdown-header {
    padding: 12px;
  }

  .notification-dropdown .dropdown-header h3 {
    font-size: 14px;
  }

  .dropdown-tabs {
    top: 46px;
  }

  .tab-btn {
    padding: 10px 6px;
    font-size: 12px;
    gap: 4px;
  }

  .tab-badge {
    font-size: 9px;
    padding: 1px 5px;
    min-width: 16px;
  }

  .notification-list {
    padding: 6px 8px;
  }

  .notification-item {
    padding: 10px;
    gap: 8px;
  }

  .item-avatar {
    width: 36px;
    height: 36px;
    font-size: 12px;
    border-radius: 8px;
  }

  .item-author {
    font-size: 12px;
  }

  .item-text {
    font-size: 12px;
  }

  .quick-action {
    width: 28px;
    height: 28px;
  }

  .quick-action svg {
    width: 12px;
    height: 12px;
  }

  .dropdown-footer {
    padding: 10px 12px;
  }

  .view-all-btn {
    padding: 10px;
    font-size: 13px;
  }
}
</style>
