<template>
  <AdminLayout>
    <div class="comments-page">
      <!-- Page Header -->
      <div class="page-header">
        <div class="header-content">
          <div class="header-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
            </svg>
          </div>
          <div>
            <h1>Comments</h1>
            <p>Manage all comments on your blog posts</p>
          </div>
        </div>
        <div class="header-actions">
          <router-link
            v-if="pendingCount > 0"
            to="/admin/comments/pending"
            class="btn btn-pending"
          >
            <span class="pending-badge">{{ pendingCount }}</span>
            Pending Review
          </router-link>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon total">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
            </svg>
          </div>
          <div class="stat-info">
            <span class="stat-value">{{ pagination.total }}</span>
            <span class="stat-label">Total Comments</span>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon approved">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 11-5.93-9.14" />
              <polyline points="22 4 12 14.01 9 11.01" />
            </svg>
          </div>
          <div class="stat-info">
            <span class="stat-value">{{ statistics?.approved || 0 }}</span>
            <span class="stat-label">Approved</span>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon pending">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" />
              <polyline points="12 6 12 12 16 14" />
            </svg>
          </div>
          <div class="stat-info">
            <span class="stat-value">{{ pendingCount }}</span>
            <span class="stat-label">Pending</span>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon rejected">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" />
              <line x1="15" y1="9" x2="9" y2="15" />
              <line x1="9" y1="9" x2="15" y2="15" />
            </svg>
          </div>
          <div class="stat-info">
            <span class="stat-value">{{ statistics?.rejected || 0 }}</span>
            <span class="stat-label">Rejected</span>
          </div>
        </div>
      </div>

      <!-- Filters & Actions Bar -->
      <div class="toolbar">
        <div class="toolbar-left">
          <div class="search-wrapper">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8" />
              <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
            <input
              type="text"
              v-model="filters.search"
              placeholder="Search comments..."
              @input="debouncedSearch"
            />
            <button v-if="filters.search" @click="clearSearch" class="clear-btn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>
          </div>

          <div class="filter-group">
            <select v-model="filters.status" @change="applyFilters" class="filter-select">
              <option value="">All Status</option>
              <option value="approved">Approved</option>
              <option value="pending">Pending</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
        </div>

        <div class="toolbar-right">
          <Transition name="slide-fade">
            <div v-if="selectedCount > 0" class="bulk-actions">
              <span class="selection-count">{{ selectedCount }} selected</span>
              <button @click="bulkApprove" class="btn btn-sm btn-success" :disabled="bulkLoading">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="20 6 9 17 4 12" />
                </svg>
                Approve
              </button>
              <button @click="bulkReject" class="btn btn-sm btn-warning" :disabled="bulkLoading">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="18" y1="6" x2="6" y2="18" />
                  <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
                Reject
              </button>
              <button @click="confirmBulkDelete" class="btn btn-sm btn-danger" :disabled="bulkLoading">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="3 6 5 6 21 6" />
                  <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
                </svg>
                Delete
              </button>
              <button @click="clearSelection" class="btn btn-sm btn-ghost">
                Clear
              </button>
            </div>
          </Transition>
        </div>
      </div>

      <!-- Realtime Connection Status -->
      <Transition name="fade">
        <div v-if="isConnected" class="realtime-status connected">
          <span class="status-dot"></span>
          Live updates enabled
        </div>
      </Transition>

      <!-- Comments Table -->
      <div class="table-card">
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr>
                <th class="col-checkbox">
                  <label class="checkbox-wrapper">
                    <input
                      type="checkbox"
                      @change="toggleSelectAll"
                      :checked="isAllSelected"
                      :indeterminate="isPartialSelected"
                    />
                    <span class="checkmark"></span>
                  </label>
                </th>
                <th class="col-author">Author</th>
                <th class="col-comment">Comment</th>
                <th class="col-post">Post</th>
                <th class="col-status">Status</th>
                <th class="col-date">Date</th>
                <th class="col-actions">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading && comments.length === 0">
                <td colspan="7">
                  <div class="loading-state">
                    <div class="spinner"></div>
                    <span>Loading comments...</span>
                  </div>
                </td>
              </tr>
              <tr v-else-if="!loading && comments.length === 0">
                <td colspan="7">
                  <div class="empty-state">
                    <div class="empty-icon">
                      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
                      </svg>
                    </div>
                    <h3>No comments found</h3>
                    <p>Comments will appear here once users start engaging with your posts</p>
                  </div>
                </td>
              </tr>
              <template v-else>
                <tr
                  v-for="comment in comments"
                  :key="comment.id"
                  :class="{ 'selected': selectedIds.includes(comment.id), 'highlight': highlightedId === comment.id }"
                >
                  <td class="col-checkbox">
                    <label class="checkbox-wrapper">
                      <input
                        type="checkbox"
                        :checked="selectedIds.includes(comment.id)"
                        @change="toggleSelection(comment.id)"
                      />
                      <span class="checkmark"></span>
                    </label>
                  </td>
                  <td class="col-author">
                    <div class="author-cell">
                      <div class="author-avatar" :style="{ background: getAvatarColor(comment.author_name || comment.user?.name || 'A') }">
                        {{ getInitials(comment.author_name || comment.user?.name || 'A') }}
                      </div>
                      <div class="author-info">
                        <span class="author-name">{{ comment.author_name || comment.user?.name || 'Anonymous' }}</span>
                        <span class="author-email">{{ comment.author_email || comment.user?.email || '-' }}</span>
                      </div>
                    </div>
                  </td>
                  <td class="col-comment">
                    <div class="comment-content">
                      <p>{{ truncate(comment.content, 120) }}</p>
                      <button
                        v-if="comment.content && comment.content.length > 120"
                        @click="showCommentDetail(comment)"
                        class="view-more"
                      >
                        View full comment
                      </button>
                    </div>
                  </td>
                  <td class="col-post">
                    <router-link
                      v-if="comment.post"
                      :to="`/admin/posts/${comment.post.id}/edit`"
                      class="post-link"
                    >
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                      </svg>
                      {{ truncate(comment.post.title, 35) }}
                    </router-link>
                    <span v-else class="no-post">No post</span>
                  </td>
                  <td class="col-status">
                    <span class="status-badge" :class="getStatusClass(comment.status)">
                      <span class="status-dot"></span>
                      {{ comment.status }}
                    </span>
                  </td>
                  <td class="col-date">
                    <div class="date-info">
                      <span class="date-relative">{{ formatRelativeDate(comment.created_at) }}</span>
                      <span class="date-full">{{ formatDate(comment.created_at) }}</span>
                    </div>
                  </td>
                  <td class="col-actions">
                    <div class="action-buttons">
                      <button
                        v-if="comment.status !== 'approved'"
                        @click="approveComment(comment.id)"
                        class="action-btn approve"
                        title="Approve"
                        :disabled="actionLoading === comment.id"
                      >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <polyline points="20 6 9 17 4 12" />
                        </svg>
                      </button>
                      <button
                        v-if="comment.status !== 'rejected'"
                        @click="rejectComment(comment.id)"
                        class="action-btn reject"
                        title="Reject"
                        :disabled="actionLoading === comment.id"
                      >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <line x1="18" y1="6" x2="6" y2="18" />
                          <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                      </button>
                      <button
                        @click="confirmDelete(comment)"
                        class="action-btn delete"
                        title="Delete"
                        :disabled="actionLoading === comment.id"
                      >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <polyline points="3 6 5 6 21 6" />
                          <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
                        </svg>
                      </button>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Loading Overlay -->
        <Transition name="fade">
          <div v-if="loading && comments.length > 0" class="table-loading-overlay">
            <div class="spinner"></div>
          </div>
        </Transition>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.lastPage > 1" class="pagination-wrapper">
        <div class="pagination-info">
          Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} comments
        </div>
        <Pagination
          :current-page="pagination.currentPage"
          :last-page="pagination.lastPage"
          :per-page="pagination.perPage"
          :total="pagination.total"
          :from="pagination.from"
          :to="pagination.to"
          @page-change="goToPage"
          @per-page-change="onPerPageChange"
        />
      </div>

      <!-- Delete Confirmation Modal -->
      <Teleport to="body">
        <Transition name="modal">
          <div v-if="showDeleteDialog" class="modal-overlay" @click.self="showDeleteDialog = false">
            <div class="modal-content">
              <div class="modal-icon danger">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10" />
                  <line x1="12" y1="8" x2="12" y2="12" />
                  <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
              </div>
              <h3>Delete Comment</h3>
              <p>Are you sure you want to delete this comment? This action cannot be undone.</p>
              <div class="modal-actions">
                <button @click="showDeleteDialog = false" class="btn btn-secondary">
                  Cancel
                </button>
                <button @click="deleteComment" class="btn btn-danger" :disabled="deleteLoading">
                  <span v-if="deleteLoading" class="btn-spinner"></span>
                  {{ deleteLoading ? 'Deleting...' : 'Delete Comment' }}
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </Teleport>

      <!-- Bulk Delete Confirmation Modal -->
      <Teleport to="body">
        <Transition name="modal">
          <div v-if="showBulkDeleteDialog" class="modal-overlay" @click.self="showBulkDeleteDialog = false">
            <div class="modal-content">
              <div class="modal-icon danger">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10" />
                  <line x1="12" y1="8" x2="12" y2="12" />
                  <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
              </div>
              <h3>Delete {{ selectedCount }} Comments</h3>
              <p>Are you sure you want to delete these comments? This action cannot be undone.</p>
              <div class="modal-actions">
                <button @click="showBulkDeleteDialog = false" class="btn btn-secondary">
                  Cancel
                </button>
                <button @click="bulkDelete" class="btn btn-danger" :disabled="bulkLoading">
                  <span v-if="bulkLoading" class="btn-spinner"></span>
                  {{ bulkLoading ? 'Deleting...' : 'Delete All' }}
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </Teleport>

      <!-- Comment Detail Modal -->
      <Teleport to="body">
        <Transition name="modal">
          <div v-if="showDetailModal" class="modal-overlay" @click.self="showDetailModal = false">
            <div class="modal-content modal-lg">
              <button @click="showDetailModal = false" class="modal-close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="18" y1="6" x2="6" y2="18" />
                  <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
              </button>
              <div v-if="selectedComment" class="comment-detail">
                <div class="detail-header">
                  <div class="author-avatar lg" :style="{ background: getAvatarColor(selectedComment.author_name || selectedComment.user?.name || 'A') }">
                    {{ getInitials(selectedComment.author_name || selectedComment.user?.name || 'A') }}
                  </div>
                  <div class="detail-author-info">
                    <h4>{{ selectedComment.author_name || selectedComment.user?.name || 'Anonymous' }}</h4>
                    <span>{{ selectedComment.author_email || selectedComment.user?.email || '-' }}</span>
                  </div>
                  <span class="status-badge" :class="getStatusClass(selectedComment.status)">
                    <span class="status-dot"></span>
                    {{ selectedComment.status }}
                  </span>
                </div>
                <div class="detail-content">
                  <p>{{ selectedComment.content }}</p>
                </div>
                <div class="detail-meta">
                  <div v-if="selectedComment.post" class="meta-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                      <polyline points="14 2 14 8 20 8" />
                    </svg>
                    <router-link :to="`/admin/posts/${selectedComment.post.id}/edit`">
                      {{ selectedComment.post.title }}
                    </router-link>
                  </div>
                  <div class="meta-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <circle cx="12" cy="12" r="10" />
                      <polyline points="12 6 12 12 16 14" />
                    </svg>
                    {{ formatDate(selectedComment.created_at) }}
                  </div>
                </div>
                <div class="detail-actions">
                  <button
                    v-if="selectedComment.status !== 'approved'"
                    @click="approveAndClose(selectedComment.id)"
                    class="btn btn-success"
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="20 6 9 17 4 12" />
                    </svg>
                    Approve
                  </button>
                  <button
                    v-if="selectedComment.status !== 'rejected'"
                    @click="rejectAndClose(selectedComment.id)"
                    class="btn btn-warning"
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <line x1="18" y1="6" x2="6" y2="18" />
                      <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                    Reject
                  </button>
                  <button @click="deleteAndClose(selectedComment)" class="btn btn-danger-outline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="3 6 5 6 21 6" />
                      <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
                    </svg>
                    Delete
                  </button>
                </div>
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
              <svg v-if="toast.type === 'success'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 11-5.93-9.14" />
                <polyline points="22 4 12 14.01 9 11.01" />
              </svg>
              <svg v-else-if="toast.type === 'error'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" />
                <line x1="15" y1="9" x2="9" y2="15" />
                <line x1="9" y1="9" x2="15" y2="15" />
              </svg>
              <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="16" x2="12" y2="12" />
                <line x1="12" y1="8" x2="12.01" y2="8" />
              </svg>
            </div>
            <span>{{ toast.message }}</span>
            <button @click="removeToast(toast.id)" class="toast-close">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
import { useRoute, useRouter } from 'vue-router';
import AdminLayout from '../../components/layout/AdminLayout.vue';
import Pagination from '../../components/shared/Pagination.vue';
import { useCommentsStore } from '../../stores/comments';
import { useWebSocket } from '../../composables/useWebSocket';
import { debounce } from 'lodash-es';

const route = useRoute();
const router = useRouter();
const store = useCommentsStore();

// WebSocket for realtime updates
const { isConnected, connect, disconnect, subscribe, unsubscribe, on, off } = useWebSocket();

// Local state
const filters = ref({
  search: '',
  status: '',
});
const showDeleteDialog = ref(false);
const showBulkDeleteDialog = ref(false);
const showDetailModal = ref(false);
const commentToDelete = ref(null);
const selectedComment = ref(null);
const actionLoading = ref(null);
const deleteLoading = ref(false);
const bulkLoading = ref(false);
const highlightedId = ref(null);
const toasts = ref([]);
let toastId = 0;

// Computed from store
const comments = computed(() => store.items || []);
const pagination = computed(() => store.pagination);
const loading = computed(() => store.loading);
const selectedIds = computed(() => store.selectedIds || []);
const selectedCount = computed(() => store.selectedCount);
const pendingCount = computed(() => store.pendingCount);
const statistics = computed(() => store.statistics);

const isAllSelected = computed(() => {
  const items = comments.value;
  const selected = selectedIds.value;
  return items.length > 0 && selected.length === items.length;
});

const isPartialSelected = computed(() => {
  const items = comments.value;
  const selected = selectedIds.value;
  return selected.length > 0 && selected.length < items.length;
});

// Toast notifications
const addToast = (message, type = 'success') => {
  const id = ++toastId;
  toasts.value.push({ id, message, type });
  setTimeout(() => removeToast(id), 4000);
};

const removeToast = (id) => {
  const index = toasts.value.findIndex(t => t.id === id);
  if (index > -1) toasts.value.splice(index, 1);
};

// URL sync
const updateUrl = (params) => {
  const query = { ...route.query };
  Object.keys(params).forEach((key) => {
    if (params[key] !== '' && params[key] !== null && params[key] !== undefined) {
      query[key] = String(params[key]);
    } else {
      delete query[key];
    }
  });
  router.replace({ query });
};

// Debounced search
const debouncedSearch = debounce(() => {
  applyFilters();
}, 300);

const applyFilters = () => {
  store.setFilters(filters.value);
  updateUrl({ ...filters.value, page: 1 });
  store.fetchComments(1);
};

const clearSearch = () => {
  filters.value.search = '';
  applyFilters();
};

const goToPage = (page) => {
  updateUrl({ page });
  store.fetchComments(page);
};

const onPerPageChange = (perPage) => {
  store.setPerPage(perPage);
  updateUrl({ per_page: perPage, page: 1 });
  store.fetchComments(1);
};

// Selection
const toggleSelection = (id) => {
  store.toggleSelection(id);
};

const toggleSelectAll = () => {
  if (isAllSelected.value) {
    store.clearSelection();
  } else {
    store.selectAll(comments.value);
  }
};

const clearSelection = () => {
  store.clearSelection();
};

// Helpers
const getInitials = (name) => {
  if (!name) return 'A';
  return name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
};

const getAvatarColor = (name) => {
  const colors = [
    'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
    'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
    'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
    'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
    'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
    'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)',
  ];
  const index = name ? name.charCodeAt(0) % colors.length : 0;
  return colors[index];
};

const truncate = (text, length) => {
  if (!text) return '';
  if (text.length <= length) return text;
  return text.slice(0, length) + '...';
};

const getStatusClass = (status) => {
  return {
    approved: 'status-approved',
    pending: 'status-pending',
    rejected: 'status-rejected',
  }[status] || 'status-default';
};

const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const formatRelativeDate = (date) => {
  if (!date) return '-';
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

// Actions
const approveComment = async (id) => {
  actionLoading.value = id;
  try {
    await store.approveComment(id);
    addToast('Comment approved successfully');
  } catch (error) {
    addToast('Failed to approve comment', 'error');
  } finally {
    actionLoading.value = null;
  }
};

const rejectComment = async (id) => {
  actionLoading.value = id;
  try {
    await store.rejectComment(id);
    addToast('Comment rejected');
  } catch (error) {
    addToast('Failed to reject comment', 'error');
  } finally {
    actionLoading.value = null;
  }
};

const confirmDelete = (comment) => {
  commentToDelete.value = comment;
  showDeleteDialog.value = true;
};

const deleteComment = async () => {
  if (!commentToDelete.value) return;
  deleteLoading.value = true;
  try {
    await store.deleteComment(commentToDelete.value.id);
    addToast('Comment deleted successfully');
    showDeleteDialog.value = false;
    commentToDelete.value = null;
  } catch (error) {
    addToast('Failed to delete comment', 'error');
  } finally {
    deleteLoading.value = false;
  }
};

const confirmBulkDelete = () => {
  showBulkDeleteDialog.value = true;
};

const bulkApprove = async () => {
  bulkLoading.value = true;
  try {
    await store.bulkApprove();
    addToast(`${selectedCount.value} comments approved`);
  } catch (error) {
    addToast('Failed to approve comments', 'error');
  } finally {
    bulkLoading.value = false;
  }
};

const bulkReject = async () => {
  bulkLoading.value = true;
  try {
    await store.bulkReject();
    addToast(`${selectedCount.value} comments rejected`);
  } catch (error) {
    addToast('Failed to reject comments', 'error');
  } finally {
    bulkLoading.value = false;
  }
};

const bulkDelete = async () => {
  bulkLoading.value = true;
  try {
    await store.bulkDelete();
    addToast('Comments deleted successfully');
    showBulkDeleteDialog.value = false;
  } catch (error) {
    addToast('Failed to delete comments', 'error');
  } finally {
    bulkLoading.value = false;
  }
};

// Detail modal
const showCommentDetail = (comment) => {
  selectedComment.value = comment;
  showDetailModal.value = true;
};

const approveAndClose = async (id) => {
  await approveComment(id);
  showDetailModal.value = false;
};

const rejectAndClose = async (id) => {
  await rejectComment(id);
  showDetailModal.value = false;
};

const deleteAndClose = (comment) => {
  showDetailModal.value = false;
  confirmDelete(comment);
};

// WebSocket realtime handlers
const handleNewComment = (data) => {
  addToast('New comment received', 'info');
  highlightedId.value = data.id;
  store.fetchComments(pagination.value.currentPage);
  store.fetchStatistics();
  setTimeout(() => {
    highlightedId.value = null;
  }, 3000);
};

const handleCommentUpdated = (data) => {
  store.fetchComments(pagination.value.currentPage);
  store.fetchStatistics();
};

// Initialize
onMounted(() => {
  const page = parseInt(route.query.page) || 1;
  const perPage = parseInt(route.query.per_page) || 20;
  const search = route.query.search || '';
  const status = route.query.status || '';

  filters.value = { search, status };
  store.setFilters(filters.value);
  store.setPerPage(perPage);
  store.fetchComments(page);
  store.fetchStatistics();

  // Connect to WebSocket for realtime updates
  connect();
  subscribe('admin.comments');
  on('comment.created', handleNewComment, 'admin.comments');
  on('comment.updated', handleCommentUpdated, 'admin.comments');
});

onUnmounted(() => {
  off('comment.created', handleNewComment, 'admin.comments');
  off('comment.updated', handleCommentUpdated, 'admin.comments');
  unsubscribe('admin.comments');
  disconnect();
});
</script>

<style scoped>
.comments-page {
  max-width: 1400px;
  width: 100%;
  overflow-x: hidden;
  box-sizing: border-box;
}

/* Page Header */
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
  gap: 16px;
}

.header-content {
  display: flex;
  align-items: center;
  gap: 16px;
}

.header-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
}

.page-header h1 {
  font-size: 24px;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.page-header p {
  color: #6b7280;
  margin: 0;
  font-size: 14px;
}

.btn-pending {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: white;
  border-radius: 10px;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.2s ease;
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.btn-pending:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(245, 158, 11, 0.4);
}

.pending-badge {
  background: rgba(255, 255, 255, 0.2);
  padding: 2px 8px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
  width: 100%;
  box-sizing: border-box;
}

.stat-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: all 0.2s ease;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-icon.total {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.stat-icon.approved {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
}

.stat-icon.pending {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: white;
}

.stat-icon.rejected {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
}

.stat-info {
  display: flex;
  flex-direction: column;
}

.stat-value {
  font-size: 24px;
  font-weight: 700;
  color: #1f2937;
  line-height: 1.2;
}

.stat-label {
  font-size: 13px;
  color: #6b7280;
}

/* Toolbar */
.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.toolbar-left {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.search-wrapper {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  min-width: 280px;
  transition: all 0.2s ease;
}

.search-wrapper:focus-within {
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.search-wrapper svg {
  color: #9ca3af;
  flex-shrink: 0;
}

.search-wrapper input {
  flex: 1;
  border: none;
  outline: none;
  font-size: 14px;
  background: transparent;
}

.clear-btn {
  padding: 4px;
  background: #f3f4f6;
  border: none;
  border-radius: 6px;
  color: #6b7280;
  cursor: pointer;
  display: flex;
  transition: all 0.2s ease;
}

.clear-btn:hover {
  background: #e5e7eb;
  color: #374151;
}

.filter-select {
  padding: 10px 32px 10px 14px;
  background: white url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E") no-repeat right 12px center;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  font-size: 14px;
  color: #374151;
  cursor: pointer;
  appearance: none;
  transition: all 0.2s ease;
}

.filter-select:focus {
  border-color: #667eea;
  outline: none;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.bulk-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: #f8fafc;
  border-radius: 10px;
}

.selection-count {
  font-size: 13px;
  font-weight: 500;
  color: #374151;
  padding-right: 8px;
  border-right: 1px solid #e5e7eb;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px 16px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-sm {
  padding: 6px 12px;
  font-size: 13px;
}

.btn-success {
  background: #10b981;
  color: white;
}

.btn-success:hover:not(:disabled) {
  background: #059669;
}

.btn-warning {
  background: #f59e0b;
  color: white;
}

.btn-warning:hover:not(:disabled) {
  background: #d97706;
}

.btn-danger {
  background: #ef4444;
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background: #dc2626;
}

.btn-danger-outline {
  background: transparent;
  color: #ef4444;
  border: 1px solid #ef4444;
}

.btn-danger-outline:hover:not(:disabled) {
  background: #fef2f2;
}

.btn-secondary {
  background: #f3f4f6;
  color: #374151;
}

.btn-secondary:hover:not(:disabled) {
  background: #e5e7eb;
}

.btn-ghost {
  background: transparent;
  color: #6b7280;
}

.btn-ghost:hover:not(:disabled) {
  background: #f3f4f6;
}

.btn-spinner {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

/* Realtime Status */
.realtime-status {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  background: #ecfdf5;
  color: #065f46;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
  margin-bottom: 16px;
}

.realtime-status .status-dot {
  width: 8px;
  height: 8px;
  background: #10b981;
  border-radius: 50%;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

/* Table */
.table-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  margin-bottom: 24px;
  position: relative;
}

.table-wrapper {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
}

.data-table th,
.data-table td {
  padding: 14px 16px;
  text-align: left;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: middle;
  overflow: hidden;
  text-overflow: ellipsis;
}

.data-table th {
  background: #f9fafb;
  font-weight: 600;
  font-size: 12px;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  white-space: nowrap;
}

.data-table tbody tr {
  transition: background 0.2s ease;
}

.data-table tbody tr:hover {
  background: #f9fafb;
}

.data-table tbody tr.selected {
  background: #eff6ff;
}

.data-table tbody tr.highlight {
  animation: highlight 3s ease;
}

@keyframes highlight {
  0%, 100% { background: transparent; }
  10%, 50% { background: #fef3c7; }
}

.col-checkbox {
  width: 50px;
}

.col-author {
  width: 220px;
}

.col-comment {
  width: auto;
}

.col-post {
  width: 200px;
}

.col-status {
  width: 110px;
}

.col-date {
  width: 120px;
}

.col-actions {
  width: 110px;
}

/* Checkbox */
.checkbox-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.checkbox-wrapper input {
  position: absolute;
  opacity: 0;
  cursor: pointer;
}

.checkmark {
  width: 18px;
  height: 18px;
  background: white;
  border: 2px solid #d1d5db;
  border-radius: 4px;
  transition: all 0.2s ease;
  position: relative;
}

.checkbox-wrapper:hover .checkmark {
  border-color: #667eea;
}

.checkbox-wrapper input:checked ~ .checkmark {
  background: #667eea;
  border-color: #667eea;
}

.checkbox-wrapper input:checked ~ .checkmark::after {
  content: '';
  position: absolute;
  left: 5px;
  top: 2px;
  width: 4px;
  height: 8px;
  border: solid white;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
}

.checkbox-wrapper input:indeterminate ~ .checkmark {
  background: #667eea;
  border-color: #667eea;
}

.checkbox-wrapper input:indeterminate ~ .checkmark::after {
  content: '';
  position: absolute;
  left: 3px;
  top: 6px;
  width: 8px;
  height: 2px;
  background: white;
}

/* Author Cell */
.author-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.author-avatar {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 13px;
  font-weight: 600;
  flex-shrink: 0;
}

.author-avatar.lg {
  width: 48px;
  height: 48px;
  font-size: 16px;
  border-radius: 12px;
}

.author-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.author-name {
  font-weight: 500;
  color: #1f2937;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.author-email {
  font-size: 12px;
  color: #9ca3af;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Comment Content */
.comment-content {
  max-width: 100%;
  overflow: hidden;
}

.comment-content p {
  margin: 0;
  color: #4b5563;
  font-size: 14px;
  line-height: 1.5;
  word-wrap: break-word;
  overflow-wrap: break-word;
}

.view-more {
  margin-top: 6px;
  padding: 0;
  background: none;
  border: none;
  color: #667eea;
  font-size: 12px;
  cursor: pointer;
  transition: color 0.2s ease;
}

.view-more:hover {
  color: #5b21b6;
  text-decoration: underline;
}

/* Post Link */
.post-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #667eea;
  text-decoration: none;
  font-size: 13px;
  transition: color 0.2s ease;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.post-link:hover {
  color: #5b21b6;
  text-decoration: underline;
}

.no-post {
  color: #9ca3af;
  font-size: 13px;
}

/* Status Badge */
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
  text-transform: capitalize;
}

.status-badge .status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.status-approved {
  background: #d1fae5;
  color: #065f46;
}

.status-approved .status-dot {
  background: #10b981;
}

.status-pending {
  background: #fef3c7;
  color: #92400e;
}

.status-pending .status-dot {
  background: #f59e0b;
}

.status-rejected {
  background: #fee2e2;
  color: #991b1b;
}

.status-rejected .status-dot {
  background: #ef4444;
}

/* Date Info */
.date-info {
  display: flex;
  flex-direction: column;
}

.date-relative {
  font-size: 13px;
  color: #374151;
}

.date-full {
  font-size: 11px;
  color: #9ca3af;
}

/* Action Buttons */
.action-buttons {
  display: flex;
  gap: 4px;
}

.action-btn {
  padding: 8px;
  background: transparent;
  border: none;
  border-radius: 8px;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.action-btn.approve:hover:not(:disabled) {
  background: #d1fae5;
  color: #10b981;
}

.action-btn.reject:hover:not(:disabled) {
  background: #fef3c7;
  color: #f59e0b;
}

.action-btn.delete:hover:not(:disabled) {
  background: #fee2e2;
  color: #ef4444;
}

/* Loading States */
.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 64px 32px;
  text-align: center;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e5e7eb;
  border-top-color: #667eea;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.loading-state span {
  margin-top: 12px;
  color: #6b7280;
  font-size: 14px;
}

.empty-icon {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
  color: #9ca3af;
}

.empty-state h3 {
  margin: 0 0 8px 0;
  font-size: 18px;
  color: #374151;
}

.empty-state p {
  margin: 0;
  color: #6b7280;
  font-size: 14px;
  max-width: 320px;
}

.table-loading-overlay {
  position: absolute;
  inset: 0;
  background: rgba(255, 255, 255, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10;
}

/* Pagination */
.pagination-wrapper {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
}

.pagination-info {
  font-size: 14px;
  color: #6b7280;
}

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 16px;
  padding: 24px;
  max-width: 420px;
  width: 100%;
  text-align: center;
  position: relative;
}

.modal-content.modal-lg {
  max-width: 600px;
  text-align: left;
}

.modal-close {
  position: absolute;
  top: 16px;
  right: 16px;
  padding: 8px;
  background: #f3f4f6;
  border: none;
  border-radius: 8px;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s ease;
}

.modal-close:hover {
  background: #e5e7eb;
  color: #374151;
}

.modal-icon {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 16px;
}

.modal-icon.danger {
  background: #fee2e2;
  color: #ef4444;
}

.modal-content h3 {
  margin: 0 0 8px 0;
  font-size: 18px;
  color: #1f2937;
}

.modal-content > p {
  margin: 0 0 24px 0;
  color: #6b7280;
  font-size: 14px;
  line-height: 1.5;
}

.modal-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
}

.modal-actions .btn {
  flex: 1;
  max-width: 150px;
}

/* Comment Detail Modal */
.comment-detail {
  padding: 8px 0;
}

.detail-header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 20px;
}

.detail-author-info {
  flex: 1;
}

.detail-author-info h4 {
  margin: 0 0 4px 0;
  font-size: 16px;
  color: #1f2937;
}

.detail-author-info span {
  font-size: 13px;
  color: #6b7280;
}

.detail-content {
  background: #f9fafb;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 16px;
}

.detail-content p {
  margin: 0;
  color: #374151;
  font-size: 15px;
  line-height: 1.6;
  white-space: pre-wrap;
}

.detail-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 20px;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #6b7280;
}

.meta-item svg {
  flex-shrink: 0;
}

.meta-item a {
  color: #667eea;
  text-decoration: none;
}

.meta-item a:hover {
  text-decoration: underline;
}

.detail-actions {
  display: flex;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid #e5e7eb;
}

/* Toast Notifications */
.toast-container {
  position: fixed;
  bottom: 24px;
  right: 24px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  z-index: 2000;
}

.toast {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
  min-width: 300px;
  max-width: 400px;
}

.toast.success {
  border-left: 4px solid #10b981;
}

.toast.error {
  border-left: 4px solid #ef4444;
}

.toast.info {
  border-left: 4px solid #3b82f6;
}

.toast-icon {
  flex-shrink: 0;
}

.toast.success .toast-icon {
  color: #10b981;
}

.toast.error .toast-icon {
  color: #ef4444;
}

.toast.info .toast-icon {
  color: #3b82f6;
}

.toast span {
  flex: 1;
  font-size: 14px;
  color: #374151;
}

.toast-close {
  padding: 4px;
  background: transparent;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  border-radius: 6px;
  transition: all 0.2s ease;
}

.toast-close:hover {
  background: #f3f4f6;
  color: #374151;
}

/* Transitions */
.slide-fade-enter-active,
.slide-fade-leave-active {
  transition: all 0.3s ease;
}

.slide-fade-enter-from,
.slide-fade-leave-to {
  opacity: 0;
  transform: translateX(20px);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.modal-enter-active,
.modal-leave-active {
  transition: all 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from .modal-content,
.modal-leave-to .modal-content {
  transform: scale(0.95) translateY(20px);
}

.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(100%);
}

/* Responsive */
@media (max-width: 1200px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 1024px) {
  .page-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .header-actions {
    width: 100%;
  }

  .btn-pending {
    width: 100%;
    justify-content: center;
  }

  .toolbar {
    flex-direction: column;
    align-items: stretch;
  }

  .toolbar-left {
    width: 100%;
    flex-direction: column;
  }

  .search-wrapper {
    min-width: 0;
    width: 100%;
  }

  .filter-group {
    width: 100%;
  }

  .filter-select {
    width: 100%;
  }

  .toolbar-right {
    width: 100%;
  }

  .bulk-actions {
    width: 100%;
    justify-content: flex-start;
    flex-wrap: wrap;
  }

  .col-post {
    display: none;
  }
}

@media (max-width: 768px) {
  .comments-page {
    padding: 0;
  }

  .page-header {
    margin-bottom: 16px;
  }

  .header-content {
    gap: 12px;
  }

  .header-icon {
    width: 40px;
    height: 40px;
  }

  .page-header h1 {
    font-size: 20px;
  }

  .page-header p {
    font-size: 13px;
  }

  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 16px;
  }

  .stat-card {
    padding: 14px;
  }

  .stat-icon {
    width: 40px;
    height: 40px;
  }

  .stat-value {
    font-size: 20px;
  }

  .stat-label {
    font-size: 12px;
  }

  .search-wrapper {
    min-width: 100%;
  }

  .filter-select {
    width: 100%;
  }

  .table-card {
    border-radius: 8px;
  }

  .data-table th,
  .data-table td {
    padding: 12px;
  }

  .col-comment {
    min-width: 180px;
  }

  .col-date,
  .col-status {
    display: none;
  }

  .author-avatar {
    width: 32px;
    height: 32px;
    font-size: 11px;
  }

  .pagination-wrapper {
    flex-direction: column;
    align-items: center;
  }

  .toast-container {
    left: 16px;
    right: 16px;
    bottom: 16px;
  }

  .toast {
    min-width: auto;
    width: 100%;
  }

  .modal-overlay {
    padding: 16px;
  }

  .modal-content {
    padding: 20px;
  }

  .modal-actions {
    flex-direction: column;
  }

  .modal-actions .btn {
    max-width: none;
  }
}

@media (max-width: 480px) {
  .header-icon {
    display: none;
  }

  .page-header h1 {
    font-size: 18px;
  }

  .stats-grid {
    grid-template-columns: 1fr 1fr;
  }

  .stat-card {
    padding: 12px;
    gap: 10px;
  }

  .stat-icon {
    width: 36px;
    height: 36px;
  }

  .stat-icon svg {
    width: 16px;
    height: 16px;
  }

  .stat-value {
    font-size: 18px;
  }

  .bulk-actions {
    gap: 6px;
  }

  .selection-count {
    display: none;
  }

  .btn-sm {
    padding: 6px 10px;
    font-size: 12px;
  }

  .data-table {
    min-width: 500px;
  }

  .author-info {
    display: none;
  }

  .action-btn {
    padding: 6px;
  }
}
</style>
