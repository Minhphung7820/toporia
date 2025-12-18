<template>
  <AdminLayout>
    <div class="feedback-index">
      <div class="page-header">
        <div>
          <h1>Feedback</h1>
          <p>User feedback and suggestions</p>
        </div>
      </div>

      <!-- Stats -->
      <div class="stats-row" v-if="statistics">
        <div class="stat-item">
          <span class="stat-value">{{ statistics.total || 0 }}</span>
          <span class="stat-label">Total</span>
        </div>
        <div class="stat-item highlight">
          <span class="stat-value">{{ statistics.pending || 0 }}</span>
          <span class="stat-label">Pending</span>
        </div>
        <div class="stat-item">
          <span class="stat-value">{{ statistics.in_progress || 0 }}</span>
          <span class="stat-label">In Progress</span>
        </div>
        <div class="stat-item">
          <span class="stat-value">{{ statistics.resolved || 0 }}</span>
          <span class="stat-label">Resolved</span>
        </div>
      </div>

      <div class="filters-bar">
        <div class="search-input">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
          <input v-model="filters.search" placeholder="Search feedback..." @input="debouncedSearch" />
        </div>
        <select v-model="filters.status" @change="applyFilters">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="in_progress">In Progress</option>
          <option value="resolved">Resolved</option>
          <option value="closed">Closed</option>
        </select>
        <select v-model="filters.priority" @change="applyFilters">
          <option value="">All Priority</option>
          <option value="high">High</option>
          <option value="medium">Medium</option>
          <option value="low">Low</option>
        </select>
        <select v-model="filters.type" @change="applyFilters">
          <option value="">All Types</option>
          <option value="bug">Bug Report</option>
          <option value="feature">Feature Request</option>
          <option value="improvement">Improvement</option>
          <option value="other">Other</option>
        </select>
      </div>

      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>Subject</th>
              <th>Type</th>
              <th>Priority</th>
              <th>Status</th>
              <th>Submitted By</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="loading-cell">
                <div class="spinner"></div>
                Loading feedback...
              </td>
            </tr>
            <tr v-else-if="feedbackItems.length === 0">
              <td colspan="7" class="empty-cell">No feedback found</td>
            </tr>
            <tr v-else v-for="item in feedbackItems" :key="item.id">
              <td>
                <router-link :to="`/admin/feedback/${item.id}`" class="feedback-subject">
                  {{ item.subject }}
                </router-link>
              </td>
              <td>
                <span class="badge badge-type">{{ item.type }}</span>
              </td>
              <td>
                <span class="badge" :class="getPriorityClass(item.priority)">
                  {{ item.priority }}
                </span>
              </td>
              <td>
                <select
                  :value="item.status"
                  @change="updateStatus(item.id, $event.target.value)"
                  class="status-select"
                >
                  <option value="pending">Pending</option>
                  <option value="in_progress">In Progress</option>
                  <option value="resolved">Resolved</option>
                  <option value="closed">Closed</option>
                </select>
              </td>
              <td>
                <div class="user-cell">
                  {{ item.user?.name || item.name || 'Anonymous' }}
                  <span class="user-email">{{ item.user?.email || item.email }}</span>
                </div>
              </td>
              <td>{{ formatDate(item.created_at) }}</td>
              <td>
                <div class="actions">
                  <router-link :to="`/admin/feedback/${item.id}`" class="btn-icon" title="View">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                      <circle cx="12" cy="12" r="3" />
                    </svg>
                  </router-link>
                  <button @click="confirmDelete(item)" class="btn-icon text-red" title="Delete">
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

      <Pagination
        v-if="pagination.lastPage > 1"
        :current-page="pagination.currentPage"
        :last-page="pagination.lastPage"
        :per-page="pagination.perPage"
        :total="pagination.total"
        :from="pagination.from"
        :to="pagination.to"
        @page-change="goToPage"
        @per-page-change="onPerPageChange"
      />

      <ConfirmDialog
        v-if="showDeleteDialog"
        title="Delete Feedback"
        message="Delete this feedback item?"
        confirm-text="Delete"
        confirm-class="danger"
        @confirm="deleteFeedback"
        @cancel="showDeleteDialog = false"
      />
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminLayout from '../../components/layout/AdminLayout.vue';
import Pagination from '../../components/shared/Pagination.vue';
import ConfirmDialog from '../../components/shared/ConfirmDialog.vue';
import { useFeedbackStore } from '../../stores/feedback';
import { debounce } from 'lodash-es';

const route = useRoute();
const router = useRouter();
const store = useFeedbackStore();

const filters = ref({ search: '', status: '', priority: '', type: '' });
const showDeleteDialog = ref(false);
const itemToDelete = ref(null);

const feedbackItems = computed(() => store.items || []);
const pagination = computed(() => store.pagination);
const loading = computed(() => store.loading);
const statistics = computed(() => store.statistics);

// Sync filters to URL
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

const debouncedSearch = debounce(() => applyFilters(), 300);

const applyFilters = () => {
  store.setFilters(filters.value);
  updateUrl({ ...filters.value, page: 1 });
  store.fetchFeedback(1);
};

const goToPage = (page) => {
  updateUrl({ page });
  store.fetchFeedback(page);
};

const onPerPageChange = (perPage) => {
  store.setPerPage(perPage);
  updateUrl({ per_page: perPage, page: 1 });
  store.fetchFeedback(1);
};

const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const getPriorityClass = (priority) => {
  const classes = { high: 'badge-danger', medium: 'badge-warning', low: 'badge-success' };
  return classes[priority] || 'badge-default';
};

const updateStatus = async (id, status) => {
  await store.updateStatus(id, status);
};

const confirmDelete = (item) => {
  itemToDelete.value = item;
  showDeleteDialog.value = true;
};

const deleteFeedback = async () => {
  if (itemToDelete.value) {
    await store.deleteFeedback(itemToDelete.value.id);
    showDeleteDialog.value = false;
    itemToDelete.value = null;
  }
};

// Initialize from URL params
onMounted(() => {
  const page = parseInt(route.query.page) || 1;
  const perPage = parseInt(route.query.per_page) || 20;
  const search = route.query.search || '';
  const status = route.query.status || '';
  const priority = route.query.priority || '';
  const type = route.query.type || '';

  filters.value = { search, status, priority, type };
  store.setFilters(filters.value);
  store.setPerPage(perPage);
  store.fetchFeedback(page);
  store.fetchStatistics();
});
</script>

<style scoped>
.feedback-index { max-width: 1400px; }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 24px; }
.page-header h1 { font-size: 24px; font-weight: 700; color: #1f2937; margin: 0 0 4px 0; }
.page-header p { color: #6b7280; margin: 0; }
.stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-item { background: #fff; padding: 16px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); text-align: center; }
.stat-item.highlight { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; }
.stat-value { display: block; font-size: 24px; font-weight: 700; }
.stat-item:not(.highlight) .stat-value { color: #1f2937; }
.stat-label { font-size: 13px; }
.stat-item:not(.highlight) .stat-label { color: #6b7280; }
.filters-bar { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
.search-input { display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; flex: 1; max-width: 300px; }
.search-input svg { color: #9ca3af; }
.search-input input { border: none; outline: none; width: 100%; font-size: 14px; }
.filters-bar select { padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; background: #fff; }
.table-container { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); overflow: hidden; margin-bottom: 24px; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #e5e7eb; }
.data-table th { background: #f9fafb; font-weight: 600; font-size: 13px; color: #6b7280; text-transform: uppercase; }
.loading-cell, .empty-cell { text-align: center; padding: 48px !important; color: #9ca3af; }
.spinner { width: 24px; height: 24px; border: 3px solid #e5e7eb; border-top-color: #667eea; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 12px; }
@keyframes spin { to { transform: rotate(360deg); } }
.feedback-subject { color: #1f2937; font-weight: 500; text-decoration: none; }
.feedback-subject:hover { color: #4f46e5; }
.user-cell { display: flex; flex-direction: column; }
.user-email { font-size: 12px; color: #9ca3af; }
.badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; text-transform: capitalize; }
.badge-type { background: #dbeafe; color: #1e40af; }
.badge-success { background: #d1fae5; color: #065f46; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-danger { background: #fee2e2; color: #dc2626; }
.status-select { padding: 4px 8px; border: 1px solid #e5e7eb; border-radius: 4px; font-size: 13px; background: #fff; }
.actions { display: flex; gap: 4px; }
.btn-icon { padding: 6px; background: transparent; border: none; border-radius: 6px; color: #6b7280; cursor: pointer; text-decoration: none; display: inline-flex; }
.btn-icon:hover { background: #f3f4f6; color: #1f2937; }
.btn-icon.text-red:hover { color: #ef4444; }
</style>
