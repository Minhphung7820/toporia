<template>
  <AdminLayout>
    <div class="comments-index">
      <div class="page-header">
        <div>
          <h1>Comments</h1>
          <p>Manage all comments on your blog posts</p>
        </div>
        <router-link to="/admin/comments/pending" class="btn btn-warning" v-if="pendingCount > 0">
          {{ pendingCount }} Pending
        </router-link>
      </div>

      <!-- Filters -->
      <div class="filters-bar">
        <div class="search-input">
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
        </div>
        <select v-model="filters.status" @change="applyFilters">
          <option value="">All Status</option>
          <option value="approved">Approved</option>
          <option value="pending">Pending</option>
          <option value="rejected">Rejected</option>
        </select>
        <div v-if="selectedCount > 0" class="bulk-actions">
          <button @click="bulkApprove" class="btn btn-small btn-success">
            Approve ({{ selectedCount }})
          </button>
          <button @click="bulkReject" class="btn btn-small btn-warning">
            Reject ({{ selectedCount }})
          </button>
          <button @click="bulkDelete" class="btn btn-small btn-danger">
            Delete ({{ selectedCount }})
          </button>
        </div>
      </div>

      <!-- Comments Table -->
      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th class="checkbox-col">
                <input
                  type="checkbox"
                  @change="toggleSelectAll"
                  :checked="isAllSelected"
                />
              </th>
              <th>Author</th>
              <th>Comment</th>
              <th>Post</th>
              <th>Status</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="loading-cell">
                <div class="spinner"></div>
                Loading comments...
              </td>
            </tr>
            <tr v-else-if="comments.length === 0">
              <td colspan="7" class="empty-cell">
                No comments found
              </td>
            </tr>
            <tr v-else v-for="comment in comments" :key="comment.id">
              <td class="checkbox-col">
                <input
                  type="checkbox"
                  :checked="selectedIds.includes(comment.id)"
                  @change="toggleSelection(comment.id)"
                />
              </td>
              <td>
                <div class="author-cell">
                  <div class="author-avatar">
                    {{ getInitials(comment.author_name || comment.user?.name || 'A') }}
                  </div>
                  <div>
                    <div class="author-name">{{ comment.author_name || comment.user?.name }}</div>
                    <div class="author-email">{{ comment.author_email || comment.user?.email }}</div>
                  </div>
                </div>
              </td>
              <td>
                <div class="comment-text">{{ truncate(comment.content, 100) }}</div>
              </td>
              <td>
                <router-link
                  v-if="comment.post"
                  :to="`/admin/posts/${comment.post.id}/edit`"
                  class="post-link"
                >
                  {{ truncate(comment.post.title, 40) }}
                </router-link>
                <span v-else class="text-muted">-</span>
              </td>
              <td>
                <span class="badge" :class="getStatusClass(comment.status)">
                  {{ comment.status }}
                </span>
              </td>
              <td>{{ formatDate(comment.created_at) }}</td>
              <td>
                <div class="actions">
                  <button
                    v-if="comment.status !== 'approved'"
                    @click="approveComment(comment.id)"
                    class="btn-icon text-green"
                    title="Approve"
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="20 6 9 17 4 12" />
                    </svg>
                  </button>
                  <button
                    v-if="comment.status !== 'rejected'"
                    @click="rejectComment(comment.id)"
                    class="btn-icon text-orange"
                    title="Reject"
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <line x1="18" y1="6" x2="6" y2="18" />
                      <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                  </button>
                  <button @click="confirmDelete(comment)" class="btn-icon text-red" title="Delete">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="3 6 5 6 21 6" />
                      <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <Pagination
        v-if="pagination.lastPage > 1"
        :current-page="pagination.currentPage"
        :last-page="pagination.lastPage"
        :total="pagination.total"
        @page-change="goToPage"
      />

      <!-- Delete Confirmation Modal -->
      <ConfirmDialog
        v-if="showDeleteDialog"
        title="Delete Comment"
        message="Are you sure you want to delete this comment? This action cannot be undone."
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
import { debounce } from 'lodash-es';

const store = useCommentsStore();

const filters = ref({
  search: '',
  status: '',
});

const showDeleteDialog = ref(false);
const commentToDelete = ref(null);

const comments = computed(() => store.items);
const pagination = computed(() => store.pagination);
const loading = computed(() => store.loading);
const selectedIds = computed(() => store.selectedIds);
const selectedCount = computed(() => store.selectedCount);
const pendingCount = computed(() => store.pendingCount);

const isAllSelected = computed(() => {
  return comments.value.length > 0 && selectedIds.value.length === comments.value.length;
});

const debouncedSearch = debounce(() => {
  applyFilters();
}, 300);

const applyFilters = () => {
  store.setFilters(filters.value);
  store.fetchComments(1);
};

const goToPage = (page) => {
  store.fetchComments(page);
};

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

const getStatusClass = (status) => {
  const classes = {
    approved: 'badge-success',
    pending: 'badge-warning',
    rejected: 'badge-danger',
  };
  return classes[status] || 'badge-default';
};

const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
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

const bulkDelete = async () => {
  await store.bulkDelete();
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
  store.fetchComments(1);
  store.fetchStatistics();
});
</script>

<style scoped>
.comments-index {
  max-width: 1400px;
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
  gap: 8px;
  padding: 10px 16px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
  border: none;
  transition: all 0.2s ease;
}

.btn-warning {
  background: #f59e0b;
  color: #fff;
}

.btn-small {
  padding: 6px 12px;
  font-size: 13px;
}

.btn-success {
  background: #10b981;
  color: #fff;
}

.btn-danger {
  background: #ef4444;
  color: #fff;
}

.filters-bar {
  display: flex;
  gap: 12px;
  margin-bottom: 24px;
  flex-wrap: wrap;
  align-items: center;
}

.search-input {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  flex: 1;
  max-width: 300px;
}

.search-input svg {
  color: #9ca3af;
  flex-shrink: 0;
}

.search-input input {
  border: none;
  outline: none;
  width: 100%;
  font-size: 14px;
}

.filters-bar select {
  padding: 8px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  background: #fff;
  cursor: pointer;
}

.bulk-actions {
  display: flex;
  gap: 8px;
  margin-left: auto;
}

.table-container {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  margin-bottom: 24px;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th,
.data-table td {
  padding: 12px 16px;
  text-align: left;
  border-bottom: 1px solid #e5e7eb;
}

.data-table th {
  background: #f9fafb;
  font-weight: 600;
  font-size: 13px;
  color: #6b7280;
  text-transform: uppercase;
}

.checkbox-col {
  width: 40px;
}

.loading-cell,
.empty-cell {
  text-align: center;
  padding: 48px !important;
  color: #9ca3af;
}

.spinner {
  width: 24px;
  height: 24px;
  border: 3px solid #e5e7eb;
  border-top-color: #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 12px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.author-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.author-avatar {
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
}

.author-name {
  font-weight: 500;
  color: #1f2937;
}

.author-email {
  font-size: 12px;
  color: #9ca3af;
}

.comment-text {
  color: #6b7280;
  font-size: 14px;
  max-width: 300px;
}

.post-link {
  color: #4f46e5;
  text-decoration: none;
}

.post-link:hover {
  text-decoration: underline;
}

.text-muted {
  color: #9ca3af;
}

.badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 500;
  text-transform: capitalize;
}

.badge-success {
  background: #d1fae5;
  color: #065f46;
}

.badge-warning {
  background: #fef3c7;
  color: #92400e;
}

.badge-danger {
  background: #fee2e2;
  color: #dc2626;
}

.actions {
  display: flex;
  gap: 4px;
}

.btn-icon {
  padding: 6px;
  background: transparent;
  border: none;
  border-radius: 6px;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-icon:hover {
  background: #f3f4f6;
}

.btn-icon.text-green:hover {
  color: #10b981;
}

.btn-icon.text-orange:hover {
  color: #f59e0b;
}

.btn-icon.text-red:hover {
  color: #ef4444;
}
</style>
