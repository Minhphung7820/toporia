<template>
  <AdminLayout>
    <div class="comments-pending">
      <div class="page-header">
        <div>
          <h1>Pending Comments</h1>
          <p>{{ total }} comments awaiting moderation</p>
        </div>
        <router-link to="/admin/comments" class="btn btn-secondary">
          View All Comments
        </router-link>
      </div>

      <div v-if="selectedCount > 0" class="bulk-actions-bar">
        <span>{{ selectedCount }} selected</span>
        <button @click="bulkApprove" class="btn btn-small btn-success">
          Approve All
        </button>
        <button @click="bulkReject" class="btn btn-small btn-warning">
          Reject All
        </button>
        <button @click="clearSelection" class="btn btn-small btn-secondary">
          Clear
        </button>
      </div>

      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        Loading pending comments...
      </div>

      <div v-else-if="comments.length === 0" class="empty-state">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
        </svg>
        <h3>All caught up!</h3>
        <p>No comments awaiting moderation</p>
      </div>

      <div v-else class="comments-list">
        <div v-for="comment in comments" :key="comment.id" class="comment-card">
          <div class="comment-header">
            <input
              type="checkbox"
              :checked="selectedIds.includes(comment.id)"
              @change="toggleSelection(comment.id)"
            />
            <div class="author-info">
              <div class="author-avatar">
                {{ getInitials(comment.author_name || comment.user?.name || 'A') }}
              </div>
              <div>
                <div class="author-name">{{ comment.author_name || comment.user?.name }}</div>
                <div class="author-email">{{ comment.author_email || comment.user?.email }}</div>
              </div>
            </div>
            <div class="comment-meta">
              <span class="comment-date">{{ formatDate(comment.created_at) }}</span>
              <span v-if="comment.post" class="comment-post">
                on <router-link :to="`/admin/posts/${comment.post.id}/edit`">{{ comment.post.title }}</router-link>
              </span>
            </div>
          </div>
          <div class="comment-body">
            <p>{{ comment.content }}</p>
          </div>
          <div class="comment-actions">
            <button @click="approveComment(comment.id)" class="btn btn-success">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12" />
              </svg>
              Approve
            </button>
            <button @click="rejectComment(comment.id)" class="btn btn-warning">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
              Reject
            </button>
            <button @click="confirmDelete(comment)" class="btn btn-danger">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6" />
                <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
              </svg>
              Delete
            </button>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <Pagination
        v-if="pagination.lastPage > 1"
        :current-page="pagination.currentPage"
        :last-page="pagination.lastPage"
        :total="pagination.total"
        @page-change="goToPage"
      />

      <ConfirmDialog
        v-if="showDeleteDialog"
        title="Delete Comment"
        message="Are you sure you want to delete this comment?"
        confirm-text="Delete"
        confirm-class="danger"
        @confirm="deleteComment"
        @cancel="showDeleteDialog = false"
      />
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AdminLayout from '../../components/layout/AdminLayout.vue';
import Pagination from '../../../components/shared/Pagination.vue';
import ConfirmDialog from '../../components/shared/ConfirmDialog.vue';
import { useCommentsStore } from '../../stores/comments';

const store = useCommentsStore();

const showDeleteDialog = ref(false);
const commentToDelete = ref(null);

const comments = computed(() => store.pendingItems);
const pagination = computed(() => store.pendingPagination);
const loading = computed(() => store.loadingPending);
const selectedIds = computed(() => store.selectedIds);
const selectedCount = computed(() => store.selectedCount);
const total = computed(() => store.pendingPagination.total);

const goToPage = (page) => {
  store.fetchPending(page);
};

const toggleSelection = (id) => {
  store.toggleSelection(id);
};

const clearSelection = () => {
  store.clearSelection();
};

const getInitials = (name) => {
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
};

const formatDate = (date) => {
  if (!date) return '';
  const now = new Date();
  const past = new Date(date);
  const diffMs = now - past;
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays < 7) return `${diffDays}d ago`;
  return past.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

const approveComment = async (id) => {
  await store.approveComment(id);
};

const rejectComment = async (id) => {
  await store.rejectComment(id);
};

const bulkApprove = async () => {
  await store.bulkApprove();
};

const bulkReject = async () => {
  await store.bulkReject();
};

const confirmDelete = (comment) => {
  commentToDelete.value = comment;
  showDeleteDialog.value = true;
};

const deleteComment = async () => {
  if (commentToDelete.value) {
    await store.deleteComment(commentToDelete.value.id);
    showDeleteDialog.value = false;
    commentToDelete.value = null;
  }
};

onMounted(() => {
  store.fetchPending(1);
});
</script>

<style scoped>
.comments-pending {
  max-width: 900px;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 24px;
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
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 16px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
  border: none;
  transition: all 0.2s ease;
}

.btn-secondary {
  background: #f3f4f6;
  color: #374151;
}

.btn-success {
  background: #10b981;
  color: #fff;
}

.btn-warning {
  background: #f59e0b;
  color: #fff;
}

.btn-danger {
  background: #ef4444;
  color: #fff;
}

.btn-small {
  padding: 6px 12px;
  font-size: 13px;
}

.bulk-actions-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: #f3f4f6;
  border-radius: 8px;
  margin-bottom: 24px;
}

.bulk-actions-bar span {
  font-weight: 500;
  color: #374151;
}

.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 64px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e5e7eb;
  border-top-color: #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 16px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-state svg {
  color: #d1d5db;
  margin-bottom: 16px;
}

.empty-state h3 {
  font-size: 18px;
  color: #1f2937;
  margin: 0 0 8px 0;
}

.empty-state p {
  color: #6b7280;
  margin: 0;
}

.comments-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.comment-card {
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.comment-header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 16px;
}

.comment-header input[type="checkbox"] {
  width: 18px;
  height: 18px;
}

.author-info {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
}

.author-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 600;
}

.author-name {
  font-weight: 500;
  color: #1f2937;
}

.author-email {
  font-size: 13px;
  color: #9ca3af;
}

.comment-meta {
  text-align: right;
  font-size: 13px;
  color: #6b7280;
}

.comment-date {
  display: block;
}

.comment-post a {
  color: #4f46e5;
  text-decoration: none;
}

.comment-post a:hover {
  text-decoration: underline;
}

.comment-body {
  padding: 16px;
  background: #f9fafb;
  border-radius: 8px;
  margin-bottom: 16px;
}

.comment-body p {
  margin: 0;
  color: #374151;
  line-height: 1.6;
}

.comment-actions {
  display: flex;
  gap: 8px;
}

@media (max-width: 768px) {
  .comments-pending {
    max-width: 100%;
  }

  .page-header {
    flex-direction: column;
    align-items: stretch;
    gap: 16px;
  }

  .page-header h1 {
    font-size: 20px;
  }

  .page-header .btn {
    text-align: center;
    justify-content: center;
  }

  .bulk-actions-bar {
    flex-wrap: wrap;
    gap: 8px;
  }

  .bulk-actions-bar span {
    width: 100%;
    margin-bottom: 4px;
  }

  .comment-card {
    padding: 16px;
  }

  .comment-header {
    flex-wrap: wrap;
    gap: 12px;
  }

  .author-info {
    width: calc(100% - 30px);
  }

  .comment-meta {
    width: 100%;
    text-align: left;
    display: flex;
    gap: 8px;
  }

  .comment-date {
    display: inline;
  }

  .comment-body {
    padding: 12px;
  }

  .comment-actions {
    flex-wrap: wrap;
  }

  .comment-actions .btn {
    flex: 1;
    min-width: 80px;
    justify-content: center;
    padding: 8px 12px;
    font-size: 13px;
  }
}

@media (max-width: 480px) {
  .page-header {
    gap: 12px;
    margin-bottom: 16px;
  }

  .page-header h1 {
    font-size: 18px;
  }

  .bulk-actions-bar {
    padding: 10px 12px;
  }

  .btn-small {
    padding: 5px 10px;
    font-size: 12px;
  }

  .comment-card {
    padding: 14px;
  }

  .author-avatar {
    width: 36px;
    height: 36px;
    font-size: 12px;
  }

  .author-name {
    font-size: 14px;
  }

  .author-email {
    font-size: 12px;
  }

  .comment-meta {
    font-size: 12px;
  }

  .comment-body {
    padding: 10px;
  }

  .comment-body p {
    font-size: 14px;
  }

  .comment-actions {
    gap: 6px;
  }

  .comment-actions .btn {
    padding: 8px 10px;
    font-size: 12px;
    gap: 4px;
  }

  .comment-actions .btn svg {
    width: 14px;
    height: 14px;
  }
}
</style>
