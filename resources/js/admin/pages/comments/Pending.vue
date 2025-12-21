<template>
  <AdminLayout>
    <div class="pending-comments">
      <!-- Page Header -->
      <header class="page-header">
        <div class="header-content">
          <div class="header-left">
            <div class="header-icon">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
              </svg>
            </div>
            <div class="header-text">
              <h1>Pending Moderation</h1>
              <p class="header-subtitle">
                <span class="pending-count">{{ total }}</span> comments awaiting your review
              </p>
            </div>
          </div>
          <div class="header-actions">
            <!-- Realtime Status -->
            <div class="realtime-status" :class="{ connected: isConnected }">
              <span class="status-dot"></span>
              <span class="status-text">{{ isConnected ? 'Live' : 'Offline' }}</span>
            </div>
            <router-link to="/admin/comments" class="btn btn-secondary">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
              </svg>
              <span>All Comments</span>
            </router-link>
          </div>
        </div>
      </header>

      <!-- Quick Stats -->
      <div class="quick-stats">
        <div class="stat-item urgent">
          <div class="stat-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
          </div>
          <div class="stat-content">
            <span class="stat-value">{{ urgentCount }}</span>
            <span class="stat-label">Urgent (&gt;24h)</span>
          </div>
        </div>
        <div class="stat-item today">
          <div class="stat-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
          </div>
          <div class="stat-content">
            <span class="stat-value">{{ todayCount }}</span>
            <span class="stat-label">Today</span>
          </div>
        </div>
        <div class="stat-item week">
          <div class="stat-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
            </svg>
          </div>
          <div class="stat-content">
            <span class="stat-value">{{ weekCount }}</span>
            <span class="stat-label">This Week</span>
          </div>
        </div>
      </div>

      <!-- Bulk Actions Bar -->
      <Transition name="slide-down">
        <div v-if="selectedCount > 0" class="bulk-actions-bar">
          <div class="bulk-info">
            <div class="bulk-checkbox">
              <input
                type="checkbox"
                :checked="isAllSelected"
                :indeterminate="isPartialSelected"
                @change="toggleSelectAll"
                class="custom-checkbox"
              />
            </div>
            <span class="bulk-count">{{ selectedCount }} of {{ comments.length }} selected</span>
          </div>
          <div class="bulk-buttons">
            <button @click="handleBulkApprove" class="btn btn-success btn-sm" :disabled="processing">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12" />
              </svg>
              <span>Approve All</span>
            </button>
            <button @click="handleBulkReject" class="btn btn-warning btn-sm" :disabled="processing">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
              <span>Reject All</span>
            </button>
            <button @click="clearSelection" class="btn btn-ghost btn-sm">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
              <span>Clear</span>
            </button>
          </div>
        </div>
      </Transition>

      <!-- Loading State -->
      <div v-if="loading" class="loading-container">
        <div class="loading-spinner">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" stroke-dasharray="32" stroke-dashoffset="32">
              <animate attributeName="stroke-dashoffset" values="32;0;32" dur="1.5s" repeatCount="indefinite" />
            </circle>
          </svg>
        </div>
        <p>Loading pending comments...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="comments.length === 0" class="empty-state">
        <div class="empty-illustration">
          <svg width="120" height="120" viewBox="0 0 120 120" fill="none">
            <circle cx="60" cy="60" r="50" fill="#f0fdf4" />
            <circle cx="60" cy="60" r="35" fill="#dcfce7" />
            <path d="M45 60l10 10 20-20" stroke="#22c55e" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </div>
        <h3>All Caught Up!</h3>
        <p>No comments are waiting for moderation. Great job!</p>
        <router-link to="/admin/comments" class="btn btn-primary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
          </svg>
          <span>View All Comments</span>
        </router-link>
      </div>

      <!-- Comments List -->
      <TransitionGroup v-else name="comment-list" tag="div" class="comments-list">
        <article
          v-for="comment in comments"
          :key="comment.id"
          class="comment-card"
          :class="{
            selected: selectedIds.includes(comment.id),
            urgent: isUrgent(comment.created_at),
            processing: processingIds.includes(comment.id)
          }"
        >
          <!-- Card Header -->
          <div class="card-header">
            <div class="header-left">
              <label class="checkbox-wrapper">
                <input
                  type="checkbox"
                  :checked="selectedIds.includes(comment.id)"
                  @change="toggleSelection(comment.id)"
                  class="custom-checkbox"
                />
                <span class="checkmark"></span>
              </label>
              <div class="author-avatar" :style="{ background: getAvatarGradient(comment.author_name || comment.user?.name) }">
                {{ getInitials(comment.author_name || comment.user?.name || 'A') }}
              </div>
              <div class="author-details">
                <div class="author-name">{{ comment.author_name || comment.user?.name || 'Anonymous' }}</div>
                <div class="author-email">{{ comment.author_email || comment.user?.email }}</div>
              </div>
            </div>
            <div class="header-right">
              <div class="time-info">
                <span class="time-badge" :class="getTimeBadgeClass(comment.created_at)">
                  {{ formatTimeAgo(comment.created_at) }}
                </span>
              </div>
              <div v-if="comment.post" class="post-reference">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                  <polyline points="14 2 14 8 20 8" />
                </svg>
                <router-link :to="`/admin/posts/${comment.post.id}/edit`" class="post-link">
                  {{ truncate(comment.post.title, 30) }}
                </router-link>
              </div>
            </div>
          </div>

          <!-- Comment Content -->
          <div class="card-content">
            <div class="comment-text">
              <p>{{ comment.content }}</p>
            </div>

            <!-- Reply Preview if exists -->
            <div v-if="comment.parent" class="reply-context">
              <div class="reply-indicator">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="9 17 4 12 9 7" />
                  <path d="M20 18v-2a4 4 0 00-4-4H4" />
                </svg>
                <span>Reply to {{ comment.parent.author_name || 'someone' }}</span>
              </div>
              <p class="parent-preview">"{{ truncate(comment.parent.content, 80) }}"</p>
            </div>
          </div>

          <!-- Card Actions -->
          <div class="card-actions">
            <button
              @click="handleApprove(comment.id)"
              class="action-btn approve"
              :disabled="processingIds.includes(comment.id)"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12" />
              </svg>
              <span>Approve</span>
            </button>
            <button
              @click="handleReject(comment.id)"
              class="action-btn reject"
              :disabled="processingIds.includes(comment.id)"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
              <span>Reject</span>
            </button>
            <button
              @click="openDetailModal(comment)"
              class="action-btn view"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                <circle cx="12" cy="12" r="3" />
              </svg>
              <span>View</span>
            </button>
            <button
              @click="confirmDelete(comment)"
              class="action-btn delete"
              :disabled="processingIds.includes(comment.id)"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6" />
                <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
              </svg>
              <span>Delete</span>
            </button>
          </div>

          <!-- Processing Overlay -->
          <div v-if="processingIds.includes(comment.id)" class="processing-overlay">
            <div class="processing-spinner"></div>
          </div>
        </article>
      </TransitionGroup>

      <!-- Pagination -->
      <div v-if="pagination.lastPage > 1" class="pagination-wrapper">
        <Pagination
          :current-page="pagination.currentPage"
          :last-page="pagination.lastPage"
          :total="pagination.total"
          @page-change="goToPage"
        />
      </div>

      <!-- Comment Detail Modal -->
      <Teleport to="body">
        <Transition name="modal">
          <div v-if="showDetailModal" class="modal-overlay" @click.self="closeDetailModal">
            <div class="modal-container detail-modal">
              <div class="modal-header">
                <h2>Comment Details</h2>
                <button @click="closeDetailModal" class="modal-close">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                  </svg>
                </button>
              </div>
              <div v-if="selectedComment" class="modal-body">
                <div class="detail-author">
                  <div class="author-avatar large" :style="{ background: getAvatarGradient(selectedComment.author_name || selectedComment.user?.name) }">
                    {{ getInitials(selectedComment.author_name || selectedComment.user?.name || 'A') }}
                  </div>
                  <div class="author-info">
                    <div class="author-name">{{ selectedComment.author_name || selectedComment.user?.name || 'Anonymous' }}</div>
                    <div class="author-email">{{ selectedComment.author_email || selectedComment.user?.email }}</div>
                    <div class="author-meta">
                      <span class="meta-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <circle cx="12" cy="12" r="10" />
                          <polyline points="12 6 12 12 16 14" />
                        </svg>
                        {{ formatDate(selectedComment.created_at) }}
                      </span>
                      <span v-if="selectedComment.ip_address" class="meta-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <rect x="2" y="2" width="20" height="8" rx="2" ry="2" />
                          <rect x="2" y="14" width="20" height="8" rx="2" ry="2" />
                          <line x1="6" y1="6" x2="6.01" y2="6" />
                          <line x1="6" y1="18" x2="6.01" y2="18" />
                        </svg>
                        {{ selectedComment.ip_address }}
                      </span>
                    </div>
                  </div>
                </div>

                <div v-if="selectedComment.post" class="detail-post">
                  <div class="section-label">Commenting on</div>
                  <router-link :to="`/admin/posts/${selectedComment.post.id}/edit`" class="post-card">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                      <polyline points="14 2 14 8 20 8" />
                    </svg>
                    <span>{{ selectedComment.post.title }}</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <line x1="7" y1="17" x2="17" y2="7" />
                      <polyline points="7 7 17 7 17 17" />
                    </svg>
                  </router-link>
                </div>

                <div v-if="selectedComment.parent" class="detail-parent">
                  <div class="section-label">Replying to</div>
                  <div class="parent-card">
                    <div class="parent-author">
                      <div class="author-avatar small">
                        {{ getInitials(selectedComment.parent.author_name || 'A') }}
                      </div>
                      <span>{{ selectedComment.parent.author_name || 'Anonymous' }}</span>
                    </div>
                    <p>{{ selectedComment.parent.content }}</p>
                  </div>
                </div>

                <div class="detail-content">
                  <div class="section-label">Comment</div>
                  <div class="content-box">
                    <p>{{ selectedComment.content }}</p>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button @click="handleApprove(selectedComment.id); closeDetailModal()" class="btn btn-success">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12" />
                  </svg>
                  <span>Approve</span>
                </button>
                <button @click="handleReject(selectedComment.id); closeDetailModal()" class="btn btn-warning">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                  </svg>
                  <span>Reject</span>
                </button>
                <button @click="confirmDelete(selectedComment); closeDetailModal()" class="btn btn-danger">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6" />
                    <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
                  </svg>
                  <span>Delete</span>
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </Teleport>

      <!-- Delete Confirmation Modal -->
      <Teleport to="body">
        <Transition name="modal">
          <div v-if="showDeleteDialog" class="modal-overlay" @click.self="showDeleteDialog = false">
            <div class="modal-container confirm-modal">
              <div class="confirm-icon danger">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10" />
                  <line x1="12" y1="8" x2="12" y2="12" />
                  <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
              </div>
              <h3>Delete Comment</h3>
              <p>Are you sure you want to delete this comment? This action cannot be undone.</p>
              <div class="confirm-actions">
                <button @click="showDeleteDialog = false" class="btn btn-secondary">Cancel</button>
                <button @click="handleDelete" class="btn btn-danger" :disabled="processing">
                  <span v-if="processing">Deleting...</span>
                  <span v-else>Delete</span>
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </Teleport>

      <!-- Toast Notifications -->
      <Teleport to="body">
        <TransitionGroup name="toast" tag="div" class="toast-container">
          <div
            v-for="toast in toasts"
            :key="toast.id"
            class="toast"
            :class="toast.type"
          >
            <div class="toast-icon">
              <svg v-if="toast.type === 'success'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12" />
              </svg>
              <svg v-else-if="toast.type === 'error'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <circle cx="12" cy="12" r="10" />
                <line x1="15" y1="9" x2="9" y2="15" />
                <line x1="9" y1="9" x2="15" y2="15" />
              </svg>
              <svg v-else-if="toast.type === 'warning'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                <line x1="12" y1="9" x2="12" y2="13" />
                <line x1="12" y1="17" x2="12.01" y2="17" />
              </svg>
              <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="16" x2="12" y2="12" />
                <line x1="12" y1="8" x2="12.01" y2="8" />
              </svg>
            </div>
            <div class="toast-content">
              <div class="toast-title">{{ toast.title }}</div>
              <div v-if="toast.message" class="toast-message">{{ toast.message }}</div>
            </div>
            <button @click="removeToast(toast.id)" class="toast-close">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>
          </div>
        </TransitionGroup>
      </Teleport>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import AdminLayout from '../../components/layout/AdminLayout.vue';
import Pagination from '../../../components/shared/Pagination.vue';
import { useCommentsStore } from '../../stores/comments';
import { useWebSocket } from '../../composables/useWebSocket';

// Store
const store = useCommentsStore();

// WebSocket
const { isConnected, connect, disconnect, subscribe, unsubscribe, on, off } = useWebSocket();

// State
const showDeleteDialog = ref(false);
const showDetailModal = ref(false);
const commentToDelete = ref(null);
const selectedComment = ref(null);
const processing = ref(false);
const processingIds = ref([]);
const toasts = ref([]);

// Computed
const comments = computed(() => store.pendingItems);
const pagination = computed(() => store.pendingPagination);
const loading = computed(() => store.loadingPending);
const selectedIds = computed(() => store.selectedIds);
const selectedCount = computed(() => store.selectedCount);
const total = computed(() => store.pendingPagination.total);

const isAllSelected = computed(() => {
  return comments.value.length > 0 && selectedIds.value.length === comments.value.length;
});

const isPartialSelected = computed(() => {
  return selectedIds.value.length > 0 && selectedIds.value.length < comments.value.length;
});

// Stats computed
const urgentCount = computed(() => {
  return comments.value.filter(c => isUrgent(c.created_at)).length;
});

const todayCount = computed(() => {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return comments.value.filter(c => new Date(c.created_at) >= today).length;
});

const weekCount = computed(() => {
  const weekAgo = new Date();
  weekAgo.setDate(weekAgo.getDate() - 7);
  return comments.value.filter(c => new Date(c.created_at) >= weekAgo).length;
});

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
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays < 7) return `${diffDays}d ago`;
  return past.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

const formatDate = (date) => {
  if (!date) return '';
  return new Date(date).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

const truncate = (text, length) => {
  if (!text) return '';
  return text.length > length ? text.substring(0, length) + '...' : text;
};

const isUrgent = (date) => {
  if (!date) return false;
  const now = new Date();
  const past = new Date(date);
  const diffMs = now - past;
  const diffHours = diffMs / 3600000;
  return diffHours > 24;
};

const getTimeBadgeClass = (date) => {
  if (!date) return '';
  const diffMs = new Date() - new Date(date);
  const diffHours = diffMs / 3600000;

  if (diffHours > 48) return 'urgent';
  if (diffHours > 24) return 'warning';
  return 'normal';
};

// Toast System
let toastId = 0;
const showToast = (type, title, message = '') => {
  const id = ++toastId;
  toasts.value.push({ id, type, title, message });
  setTimeout(() => removeToast(id), 4000);
};

const removeToast = (id) => {
  const index = toasts.value.findIndex(t => t.id === id);
  if (index > -1) toasts.value.splice(index, 1);
};

// Selection
const toggleSelection = (id) => {
  store.toggleSelection(id);
};

const toggleSelectAll = () => {
  if (isAllSelected.value) {
    store.clearSelection();
  } else {
    comments.value.forEach(c => {
      if (!selectedIds.value.includes(c.id)) {
        store.toggleSelection(c.id);
      }
    });
  }
};

const clearSelection = () => {
  store.clearSelection();
};

// Pagination
const goToPage = (page) => {
  store.fetchPending(page);
};

// Actions
const handleApprove = async (id) => {
  processingIds.value.push(id);
  try {
    await store.approveComment(id);
    showToast('success', 'Comment Approved', 'The comment is now visible to everyone.');
  } catch (error) {
    showToast('error', 'Failed to Approve', error.message || 'Please try again.');
  } finally {
    processingIds.value = processingIds.value.filter(i => i !== id);
  }
};

const handleReject = async (id) => {
  processingIds.value.push(id);
  try {
    await store.rejectComment(id);
    showToast('warning', 'Comment Rejected', 'The author has been notified.');
  } catch (error) {
    showToast('error', 'Failed to Reject', error.message || 'Please try again.');
  } finally {
    processingIds.value = processingIds.value.filter(i => i !== id);
  }
};

const handleBulkApprove = async () => {
  processing.value = true;
  try {
    await store.bulkApprove();
    showToast('success', 'Comments Approved', `${selectedCount.value} comments have been approved.`);
    store.clearSelection();
  } catch (error) {
    showToast('error', 'Bulk Approve Failed', error.message || 'Please try again.');
  } finally {
    processing.value = false;
  }
};

const handleBulkReject = async () => {
  processing.value = true;
  try {
    await store.bulkReject();
    showToast('warning', 'Comments Rejected', `${selectedCount.value} comments have been rejected.`);
    store.clearSelection();
  } catch (error) {
    showToast('error', 'Bulk Reject Failed', error.message || 'Please try again.');
  } finally {
    processing.value = false;
  }
};

const confirmDelete = (comment) => {
  commentToDelete.value = comment;
  showDeleteDialog.value = true;
};

const handleDelete = async () => {
  if (!commentToDelete.value) return;

  processing.value = true;
  try {
    await store.deleteComment(commentToDelete.value.id);
    showToast('success', 'Comment Deleted', 'The comment has been permanently removed.');
    showDeleteDialog.value = false;
    commentToDelete.value = null;
  } catch (error) {
    showToast('error', 'Delete Failed', error.message || 'Please try again.');
  } finally {
    processing.value = false;
  }
};

// Detail Modal
const openDetailModal = (comment) => {
  selectedComment.value = comment;
  showDetailModal.value = true;
};

const closeDetailModal = () => {
  showDetailModal.value = false;
  setTimeout(() => {
    selectedComment.value = null;
  }, 200);
};

// WebSocket Handlers
const handleNewComment = (data) => {
  if (data.status === 'pending') {
    store.fetchPending(pagination.value.currentPage);
    showToast('info', 'New Comment', 'A new comment is awaiting moderation.');
  }
};

const handleCommentUpdated = (data) => {
  store.fetchPending(pagination.value.currentPage);
};

// Lifecycle
onMounted(() => {
  store.fetchPending(1);

  // Connect WebSocket
  connect();
  subscribe('admin.comments');
  on('comment.created', handleNewComment, 'admin.comments');
  on('comment.updated', handleCommentUpdated, 'admin.comments');
});

onUnmounted(() => {
  off('comment.created', handleNewComment, 'admin.comments');
  off('comment.updated', handleCommentUpdated, 'admin.comments');
  unsubscribe('admin.comments');
});
</script>

<style scoped>
/* Base Layout */
.pending-comments {
  max-width: 1000px;
  margin: 0 auto;
  padding: 0 16px;
}

/* Page Header */
.page-header {
  margin-bottom: 24px;
}

.header-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.header-icon {
  width: 56px;
  height: 56px;
  border-radius: 16px;
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  flex-shrink: 0;
}

.header-text h1 {
  font-size: 24px;
  font-weight: 700;
  color: #111827;
  margin: 0 0 4px 0;
}

.header-subtitle {
  color: #6b7280;
  font-size: 14px;
  margin: 0;
}

.pending-count {
  font-weight: 600;
  color: #f59e0b;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

/* Realtime Status */
.realtime-status {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 20px;
  background: #fef2f2;
  font-size: 12px;
  font-weight: 500;
  color: #dc2626;
}

.realtime-status.connected {
  background: #f0fdf4;
  color: #16a34a;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: currentColor;
  animation: pulse 2s infinite;
}

.realtime-status.connected .status-dot {
  animation: none;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

/* Quick Stats */
.quick-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

.stat-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.stat-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-item.urgent .stat-icon {
  background: #fef2f2;
  color: #dc2626;
}

.stat-item.today .stat-icon {
  background: #eff6ff;
  color: #2563eb;
}

.stat-item.week .stat-icon {
  background: #f0fdf4;
  color: #16a34a;
}

.stat-content {
  display: flex;
  flex-direction: column;
}

.stat-value {
  font-size: 24px;
  font-weight: 700;
  color: #111827;
  line-height: 1.2;
}

.stat-label {
  font-size: 13px;
  color: #6b7280;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
  border: none;
  transition: all 0.2s ease;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-sm {
  padding: 8px 14px;
  font-size: 13px;
}

.btn-primary {
  background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4);
}

.btn-secondary {
  background: #f3f4f6;
  color: #374151;
}

.btn-secondary:hover:not(:disabled) {
  background: #e5e7eb;
}

.btn-success {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
}

.btn-success:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.btn-warning {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: white;
}

.btn-warning:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
}

.btn-danger {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
}

.btn-danger:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

.btn-ghost {
  background: transparent;
  color: #6b7280;
}

.btn-ghost:hover:not(:disabled) {
  background: #f3f4f6;
  color: #374151;
}

/* Bulk Actions Bar */
.bulk-actions-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 20px;
  background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
  border-radius: 12px;
  margin-bottom: 24px;
  box-shadow: 0 4px 14px rgba(79, 70, 229, 0.3);
}

.bulk-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.bulk-count {
  font-weight: 500;
  color: white;
}

.bulk-buttons {
  display: flex;
  gap: 8px;
}

.bulk-actions-bar .btn {
  background: rgba(255, 255, 255, 0.2);
  color: white;
  border: 1px solid rgba(255, 255, 255, 0.3);
}

.bulk-actions-bar .btn:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.3);
}

/* Loading State */
.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  background: white;
  border-radius: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.loading-spinner {
  margin-bottom: 16px;
  color: #4f46e5;
}

.loading-spinner svg {
  animation: rotate 1.5s linear infinite;
}

@keyframes rotate {
  to { transform: rotate(360deg); }
}

.loading-container p {
  color: #6b7280;
  margin: 0;
}

/* Empty State */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  background: white;
  border-radius: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  text-align: center;
}

.empty-illustration {
  margin-bottom: 24px;
}

.empty-state h3 {
  font-size: 20px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 8px 0;
}

.empty-state p {
  color: #6b7280;
  margin: 0 0 24px 0;
}

/* Comments List */
.comments-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Comment Card */
.comment-card {
  position: relative;
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: all 0.2s ease;
}

.comment-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.comment-card.selected {
  box-shadow: 0 0 0 2px #4f46e5, 0 4px 12px rgba(79, 70, 229, 0.2);
}

.comment-card.urgent {
  border-left: 4px solid #dc2626;
}

.comment-card.processing {
  pointer-events: none;
}

/* Card Header */
.card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 20px;
  border-bottom: 1px solid #f3f4f6;
  gap: 16px;
}

.card-header .header-left {
  display: flex;
  align-items: center;
  gap: 14px;
}

/* Custom Checkbox */
.checkbox-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  cursor: pointer;
}

.custom-checkbox {
  position: absolute;
  opacity: 0;
  cursor: pointer;
}

.checkmark {
  width: 20px;
  height: 20px;
  border: 2px solid #d1d5db;
  border-radius: 6px;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.custom-checkbox:checked + .checkmark {
  background: #4f46e5;
  border-color: #4f46e5;
}

.custom-checkbox:checked + .checkmark::after {
  content: '';
  width: 6px;
  height: 10px;
  border: solid white;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
  margin-bottom: 2px;
}

/* Author Avatar */
.author-avatar {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 600;
  color: white;
  flex-shrink: 0;
}

.author-avatar.large {
  width: 56px;
  height: 56px;
  font-size: 18px;
}

.author-avatar.small {
  width: 28px;
  height: 28px;
  font-size: 11px;
  border-radius: 8px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.author-details .author-name {
  font-weight: 600;
  color: #111827;
  margin-bottom: 2px;
}

.author-details .author-email {
  font-size: 13px;
  color: #6b7280;
}

.header-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
}

/* Time Badge */
.time-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
}

.time-badge.normal {
  background: #f3f4f6;
  color: #6b7280;
}

.time-badge.warning {
  background: #fef3c7;
  color: #d97706;
}

.time-badge.urgent {
  background: #fef2f2;
  color: #dc2626;
}

/* Post Reference */
.post-reference {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #6b7280;
}

.post-link {
  color: #4f46e5;
  text-decoration: none;
}

.post-link:hover {
  text-decoration: underline;
}

/* Card Content */
.card-content {
  padding: 20px;
}

.comment-text {
  background: #f9fafb;
  border-radius: 12px;
  padding: 16px;
}

.comment-text p {
  margin: 0;
  color: #374151;
  line-height: 1.7;
  white-space: pre-wrap;
}

/* Reply Context */
.reply-context {
  margin-top: 16px;
  padding: 12px 16px;
  background: #fef3c7;
  border-radius: 10px;
  border-left: 3px solid #f59e0b;
}

.reply-indicator {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 500;
  color: #92400e;
  margin-bottom: 6px;
}

.parent-preview {
  margin: 0;
  font-size: 13px;
  color: #78350f;
  font-style: italic;
}

/* Card Actions */
.card-actions {
  display: flex;
  gap: 8px;
  padding: 16px 20px;
  background: #f9fafb;
  border-top: 1px solid #f3f4f6;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 16px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  transition: all 0.2s ease;
  flex: 1;
  justify-content: center;
}

.action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.action-btn.approve {
  background: #dcfce7;
  color: #16a34a;
}

.action-btn.approve:hover:not(:disabled) {
  background: #bbf7d0;
}

.action-btn.reject {
  background: #fef3c7;
  color: #d97706;
}

.action-btn.reject:hover:not(:disabled) {
  background: #fde68a;
}

.action-btn.view {
  background: #e0e7ff;
  color: #4f46e5;
}

.action-btn.view:hover:not(:disabled) {
  background: #c7d2fe;
}

.action-btn.delete {
  background: #fee2e2;
  color: #dc2626;
}

.action-btn.delete:hover:not(:disabled) {
  background: #fecaca;
}

/* Processing Overlay */
.processing-overlay {
  position: absolute;
  inset: 0;
  background: rgba(255, 255, 255, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10;
}

.processing-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e5e7eb;
  border-top-color: #4f46e5;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Pagination */
.pagination-wrapper {
  margin-top: 32px;
  display: flex;
  justify-content: center;
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  z-index: 1000;
  backdrop-filter: blur(4px);
}

.modal-container {
  background: white;
  border-radius: 20px;
  width: 100%;
  max-width: 600px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

/* Detail Modal */
.detail-modal .modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
}

.detail-modal .modal-header h2 {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

.modal-close {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  border: none;
  background: #f3f4f6;
  color: #6b7280;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.modal-close:hover {
  background: #e5e7eb;
  color: #374151;
}

.detail-modal .modal-body {
  padding: 24px;
  overflow-y: auto;
}

.detail-author {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 24px;
}

.detail-author .author-info {
  flex: 1;
}

.detail-author .author-name {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 4px;
}

.detail-author .author-email {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 8px;
}

.detail-author .author-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
  color: #9ca3af;
}

.section-label {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 8px;
}

.detail-post,
.detail-parent {
  margin-bottom: 20px;
}

.post-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: #f3f4f6;
  border-radius: 10px;
  text-decoration: none;
  color: #374151;
  transition: all 0.2s ease;
}

.post-card:hover {
  background: #e5e7eb;
}

.post-card span {
  flex: 1;
  font-weight: 500;
}

.parent-card {
  padding: 16px;
  background: #fef3c7;
  border-radius: 10px;
  border-left: 3px solid #f59e0b;
}

.parent-author {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
  font-weight: 500;
  color: #92400e;
  font-size: 14px;
}

.parent-card p {
  margin: 0;
  color: #78350f;
  font-size: 14px;
  line-height: 1.6;
}

.detail-content .content-box {
  padding: 20px;
  background: #f9fafb;
  border-radius: 12px;
}

.detail-content .content-box p {
  margin: 0;
  color: #374151;
  line-height: 1.7;
  white-space: pre-wrap;
}

.detail-modal .modal-footer {
  display: flex;
  gap: 12px;
  padding: 20px 24px;
  border-top: 1px solid #e5e7eb;
  background: #f9fafb;
}

.detail-modal .modal-footer .btn {
  flex: 1;
}

/* Confirm Modal */
.confirm-modal {
  max-width: 420px;
  text-align: center;
  padding: 32px;
}

.confirm-icon {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 20px;
}

.confirm-icon.danger {
  background: #fef2f2;
  color: #dc2626;
}

.confirm-modal h3 {
  font-size: 20px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 12px 0;
}

.confirm-modal p {
  color: #6b7280;
  margin: 0 0 24px 0;
  line-height: 1.6;
}

.confirm-actions {
  display: flex;
  gap: 12px;
}

.confirm-actions .btn {
  flex: 1;
}

/* Toast Notifications */
.toast-container {
  position: fixed;
  top: 20px;
  right: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  z-index: 2000;
  max-width: 400px;
}

.toast {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 16px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
  border-left: 4px solid;
}

.toast.success {
  border-color: #10b981;
}

.toast.error {
  border-color: #ef4444;
}

.toast.warning {
  border-color: #f59e0b;
}

.toast.info {
  border-color: #3b82f6;
}

.toast-icon {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.toast.success .toast-icon { color: #10b981; }
.toast.error .toast-icon { color: #ef4444; }
.toast.warning .toast-icon { color: #f59e0b; }
.toast.info .toast-icon { color: #3b82f6; }

.toast-content {
  flex: 1;
  min-width: 0;
}

.toast-title {
  font-weight: 600;
  color: #111827;
  margin-bottom: 2px;
}

.toast-message {
  font-size: 14px;
  color: #6b7280;
}

.toast-close {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  border: none;
  background: none;
  color: #9ca3af;
  cursor: pointer;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  transition: all 0.2s ease;
}

.toast-close:hover {
  background: #f3f4f6;
  color: #6b7280;
}

/* Transitions */
.slide-down-enter-active,
.slide-down-leave-active {
  transition: all 0.3s ease;
}

.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

.comment-list-enter-active {
  transition: all 0.3s ease;
}

.comment-list-leave-active {
  transition: all 0.2s ease;
}

.comment-list-enter-from {
  opacity: 0;
  transform: translateX(-20px);
}

.comment-list-leave-to {
  opacity: 0;
  transform: translateX(20px);
}

.comment-list-move {
  transition: transform 0.3s ease;
}

.modal-enter-active,
.modal-leave-active {
  transition: all 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from .modal-container,
.modal-leave-to .modal-container {
  transform: scale(0.95) translateY(20px);
}

.toast-enter-active {
  transition: all 0.3s ease;
}

.toast-leave-active {
  transition: all 0.2s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100px);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(100px);
}

/* Responsive Design */
@media (max-width: 768px) {
  .pending-comments {
    padding: 0 12px;
  }

  .header-content {
    flex-direction: column;
    align-items: stretch;
  }

  .header-actions {
    justify-content: space-between;
  }

  .quick-stats {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .stat-item {
    padding: 14px;
  }

  .bulk-actions-bar {
    flex-direction: column;
    gap: 12px;
    padding: 16px;
  }

  .bulk-info {
    width: 100%;
    justify-content: center;
  }

  .bulk-buttons {
    width: 100%;
    justify-content: center;
    flex-wrap: wrap;
  }

  .card-header {
    flex-direction: column;
    gap: 12px;
    padding: 16px;
  }

  .card-header .header-left {
    width: 100%;
  }

  .header-right {
    width: 100%;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
  }

  .card-content {
    padding: 16px;
  }

  .card-actions {
    flex-wrap: wrap;
    padding: 12px 16px;
  }

  .action-btn {
    flex: 1 1 calc(50% - 4px);
    min-width: 0;
    padding: 10px 12px;
    font-size: 13px;
  }

  .action-btn span {
    display: none;
  }

  .action-btn svg {
    margin: 0;
  }

  .modal-container {
    margin: 10px;
    max-height: calc(100vh - 40px);
  }

  .detail-modal .modal-footer {
    flex-wrap: wrap;
  }

  .detail-modal .modal-footer .btn {
    flex: 1 1 calc(50% - 6px);
  }

  .toast-container {
    left: 12px;
    right: 12px;
    max-width: none;
  }
}

@media (max-width: 480px) {
  .header-icon {
    width: 48px;
    height: 48px;
  }

  .header-icon svg {
    width: 24px;
    height: 24px;
  }

  .header-text h1 {
    font-size: 20px;
  }

  .realtime-status .status-text {
    display: none;
  }

  .btn span {
    display: none;
  }

  .stat-value {
    font-size: 20px;
  }

  .author-avatar {
    width: 36px;
    height: 36px;
    font-size: 12px;
  }

  .author-details .author-name {
    font-size: 14px;
  }

  .author-details .author-email {
    font-size: 12px;
  }

  .comment-text {
    padding: 12px;
  }

  .comment-text p {
    font-size: 14px;
  }

  .action-btn {
    padding: 10px;
  }

  .confirm-modal {
    padding: 24px;
  }

  .confirm-actions {
    flex-direction: column;
  }
}
</style>
