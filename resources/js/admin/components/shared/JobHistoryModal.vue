<template>
  <Teleport to="body">
    <div class="modal-overlay" @click.self="$emit('close')">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Import / Export History</h3>
          <button class="close-btn" @click="$emit('close')">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 6L6 18M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Stats Summary -->
        <div class="stats-bar">
          <div class="stat-item">
            <span class="stat-value">{{ stats?.total_jobs ?? 0 }}</span>
            <span class="stat-label">Total</span>
          </div>
          <div class="stat-item">
            <span class="stat-value text-blue">{{ stats?.total_imports ?? 0 }}</span>
            <span class="stat-label">Imports</span>
          </div>
          <div class="stat-item">
            <span class="stat-value text-purple">{{ stats?.total_exports ?? 0 }}</span>
            <span class="stat-label">Exports</span>
          </div>
          <div class="stat-item">
            <span class="stat-value text-green">{{ stats?.completed ?? 0 }}</span>
            <span class="stat-label">Completed</span>
          </div>
          <div class="stat-item">
            <span class="stat-value text-red">{{ stats?.failed ?? 0 }}</span>
            <span class="stat-label">Failed</span>
          </div>
          <div class="stat-item">
            <span class="stat-value text-orange">{{ stats?.cancelled ?? 0 }}</span>
            <span class="stat-label">Cancelled</span>
          </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
          <div class="filter-group">
            <select v-model="filters.type" @change="resetAndFetch">
              <option value="">All Types</option>
              <option value="import">Import</option>
              <option value="export">Export</option>
            </select>
          </div>
          <div class="filter-group">
            <select v-model="filters.status" @change="resetAndFetch">
              <option value="">All Status</option>
              <option value="completed">Completed</option>
              <option value="failed">Failed</option>
              <option value="cancelled">Cancelled</option>
              <option value="processing">Processing</option>
              <option value="pending">Pending</option>
            </select>
          </div>
        </div>

        <div class="modal-body">
          <!-- Loading State -->
          <div v-if="loading && jobs.length === 0" class="loading-state">
            <div class="spinner"></div>
            <span>Loading history...</span>
          </div>

          <!-- Empty State -->
          <div v-else-if="jobs.length === 0" class="empty-state">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
              <polyline points="14 2 14 8 20 8" />
              <line x1="12" y1="18" x2="12" y2="12" />
              <line x1="9" y1="15" x2="15" y2="15" />
            </svg>
            <p>No jobs found</p>
          </div>

          <!-- Jobs List -->
          <div v-else class="jobs-list">
            <div v-for="job in jobs" :key="job.id" class="job-card" :class="`status-${job.status}`">
              <div class="job-header">
                <div class="job-type" :class="job.type">
                  <svg v-if="job.type === 'import'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                    <polyline points="17 8 12 3 7 8" />
                    <line x1="12" y1="3" x2="12" y2="15" />
                  </svg>
                  <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                    <polyline points="7 10 12 15 17 10" />
                    <line x1="12" y1="15" x2="12" y2="3" />
                  </svg>
                  {{ job.type === 'import' ? 'Import' : 'Export' }}
                </div>
                <span class="job-status" :class="`status-${job.status}`">
                  {{ formatStatus(job.status) }}
                </span>
              </div>

              <div class="job-details">
                <div v-if="job.original_filename" class="detail-row">
                  <span class="detail-label">File:</span>
                  <span class="detail-value">{{ job.original_filename }}</span>
                </div>

                <div class="detail-row">
                  <span class="detail-label">Rows:</span>
                  <span class="detail-value">
                    <span class="text-green">{{ job.success_rows.toLocaleString() }}</span>
                    <span v-if="job.failed_rows > 0" class="text-red"> / {{ job.failed_rows.toLocaleString() }} failed</span>
                    <span class="text-muted"> of {{ job.total_rows.toLocaleString() }}</span>
                  </span>
                </div>

                <div v-if="job.file_size" class="detail-row">
                  <span class="detail-label">Size:</span>
                  <span class="detail-value">{{ formatFileSize(job.file_size) }}</span>
                </div>

                <div v-if="job.duration !== null" class="detail-row">
                  <span class="detail-label">Duration:</span>
                  <span class="detail-value">{{ formatDuration(job.duration) }}</span>
                </div>

                <div class="detail-row">
                  <span class="detail-label">Created:</span>
                  <span class="detail-value">{{ formatDateTime(job.created_at) }}</span>
                </div>

                <div v-if="job.completed_at" class="detail-row">
                  <span class="detail-label">Completed:</span>
                  <span class="detail-value">{{ formatDateTime(job.completed_at) }}</span>
                </div>

                <div v-if="job.error_message" class="error-message">
                  {{ job.error_message }}
                </div>
              </div>

              <div class="job-actions">
                <button
                  v-if="job.can_download"
                  class="btn btn-sm btn-success"
                  @click="downloadJob(job.id)"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                    <polyline points="7 10 12 15 17 10" />
                    <line x1="12" y1="15" x2="12" y2="3" />
                  </svg>
                  Download
                </button>
                <button
                  v-if="job.can_cancel"
                  class="btn btn-sm btn-warning"
                  @click="cancelJob(job.id)"
                  :disabled="cancelling === job.id"
                >
                  {{ cancelling === job.id ? 'Cancelling...' : 'Cancel' }}
                </button>
              </div>
            </div>

            <!-- Load More -->
            <div v-if="hasMore" class="load-more">
              <button class="btn btn-secondary" @click="loadMore" :disabled="loading">
                {{ loading ? 'Loading...' : 'Load More' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const emit = defineEmits(['close']);

// State
const jobs = ref([]);
const stats = ref(null);
const loading = ref(false);
const hasMore = ref(false);
const nextCursor = ref(null);
const cancelling = ref(null);
const filters = ref({
  type: '',
  status: '',
});

// Methods
const fetchStats = async () => {
  try {
    const response = await axios.get('/api/admin/posts/jobs/stats');
    if (response.data.success) {
      stats.value = response.data.data;
    }
  } catch (error) {
    console.error('Failed to fetch stats:', error);
  }
};

const fetchJobs = async (cursor = null) => {
  loading.value = true;
  try {
    const params = { limit: 15 };
    if (cursor) params.cursor = cursor;
    if (filters.value.type) params.type = filters.value.type;
    if (filters.value.status) params.status = filters.value.status;

    const response = await axios.get('/api/admin/posts/jobs/history', { params });

    if (response.data.success) {
      if (cursor) {
        jobs.value = [...jobs.value, ...response.data.data];
      } else {
        jobs.value = response.data.data;
      }
      hasMore.value = response.data.pagination.has_more;
      nextCursor.value = response.data.pagination.next_cursor;
    }
  } catch (error) {
    console.error('Failed to fetch jobs:', error);
  } finally {
    loading.value = false;
  }
};

const resetAndFetch = () => {
  jobs.value = [];
  nextCursor.value = null;
  fetchJobs();
};

const loadMore = () => {
  if (nextCursor.value && !loading.value) {
    fetchJobs(nextCursor.value);
  }
};

const downloadJob = (jobId) => {
  window.location.href = `/api/admin/posts/jobs/${jobId}/download`;
};

const cancelJob = async (jobId) => {
  cancelling.value = jobId;
  try {
    const response = await axios.post(`/api/admin/posts/jobs/${jobId}/cancel`);
    if (response.data.success) {
      // Update job status locally
      const job = jobs.value.find(j => j.id === jobId);
      if (job) {
        job.status = 'cancelled';
        job.can_cancel = false;
      }
      // Refresh stats
      fetchStats();
    }
  } catch (error) {
    alert(error.response?.data?.message || 'Failed to cancel job');
  } finally {
    cancelling.value = null;
  }
};

const formatStatus = (status) => {
  const labels = {
    pending: 'Pending',
    processing: 'Processing',
    completed: 'Completed',
    failed: 'Failed',
    cancelled: 'Cancelled',
  };
  return labels[status] || status;
};

const formatDateTime = (dateString) => {
  if (!dateString) return '-';
  const date = new Date(dateString);
  return date.toLocaleString('vi-VN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const formatFileSize = (bytes) => {
  if (!bytes) return '-';
  const units = ['B', 'KB', 'MB', 'GB'];
  let unitIndex = 0;
  let size = bytes;
  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024;
    unitIndex++;
  }
  return `${size.toFixed(1)} ${units[unitIndex]}`;
};

const formatDuration = (seconds) => {
  if (seconds === null || seconds === undefined) return '-';
  if (seconds < 60) return `${seconds}s`;
  if (seconds < 3600) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}m ${secs}s`;
  }
  const hours = Math.floor(seconds / 3600);
  const mins = Math.floor((seconds % 3600) / 60);
  return `${hours}h ${mins}m`;
};

// Lifecycle
onMounted(() => {
  fetchStats();
  fetchJobs();
});
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.modal-content {
  background: #fff;
  border-radius: 12px;
  width: 90%;
  max-width: 700px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.close-btn {
  background: none;
  border: none;
  padding: 4px;
  cursor: pointer;
  color: #6b7280;
  border-radius: 6px;
  transition: all 0.2s;
}

.close-btn:hover {
  background: #f3f4f6;
  color: #1f2937;
}

/* Stats Bar */
.stats-bar {
  display: flex;
  justify-content: space-around;
  padding: 16px 24px;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.stat-value {
  font-size: 20px;
  font-weight: 700;
  color: #1f2937;
}

.stat-label {
  font-size: 12px;
  color: #6b7280;
}

.text-blue { color: #3b82f6; }
.text-purple { color: #8b5cf6; }
.text-green { color: #10b981; }
.text-red { color: #ef4444; }
.text-orange { color: #f59e0b; }
.text-muted { color: #9ca3af; }

/* Filters */
.filters-bar {
  display: flex;
  gap: 12px;
  padding: 12px 24px;
  border-bottom: 1px solid #e5e7eb;
}

.filter-group select {
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  background: #fff;
  cursor: pointer;
}

.filter-group select:focus {
  outline: none;
  border-color: #667eea;
}

/* Modal Body */
.modal-body {
  padding: 16px 24px;
  overflow-y: auto;
  flex: 1;
}

/* Loading & Empty States */
.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  color: #6b7280;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e5e7eb;
  border-top-color: #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 12px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-state svg {
  margin-bottom: 12px;
  color: #9ca3af;
}

/* Jobs List */
.jobs-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.job-card {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 16px;
  background: #fff;
  transition: all 0.2s;
}

.job-card:hover {
  border-color: #d1d5db;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.job-card.status-failed {
  border-left: 3px solid #ef4444;
}

.job-card.status-completed {
  border-left: 3px solid #10b981;
}

.job-card.status-processing {
  border-left: 3px solid #3b82f6;
}

.job-card.status-cancelled {
  border-left: 3px solid #6b7280;
}

.job-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.job-type {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 600;
}

.job-type.import {
  color: #3b82f6;
}

.job-type.export {
  color: #8b5cf6;
}

.job-status {
  font-size: 12px;
  font-weight: 500;
  padding: 4px 8px;
  border-radius: 4px;
}

.job-status.status-pending {
  background: #fef3c7;
  color: #92400e;
}

.job-status.status-processing {
  background: #dbeafe;
  color: #1e40af;
}

.job-status.status-completed {
  background: #d1fae5;
  color: #065f46;
}

.job-status.status-failed {
  background: #fee2e2;
  color: #991b1b;
}

.job-status.status-cancelled {
  background: #f3f4f6;
  color: #6b7280;
}

/* Job Details */
.job-details {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 12px;
}

.detail-row {
  display: flex;
  gap: 8px;
  font-size: 13px;
}

.detail-label {
  color: #6b7280;
  min-width: 70px;
}

.detail-value {
  color: #374151;
}

.error-message {
  margin-top: 8px;
  padding: 8px 12px;
  background: #fee2e2;
  border-radius: 6px;
  font-size: 13px;
  color: #991b1b;
}

/* Job Actions */
.job-actions {
  display: flex;
  gap: 8px;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
}

.btn-sm {
  padding: 6px 12px;
  font-size: 13px;
}

.btn-success {
  background: #10b981;
  color: #fff;
}

.btn-success:hover {
  background: #059669;
}

.btn-warning {
  background: #f59e0b;
  color: #fff;
}

.btn-warning:hover {
  background: #d97706;
}

.btn-secondary {
  background: #f3f4f6;
  color: #374151;
}

.btn-secondary:hover {
  background: #e5e7eb;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Load More */
.load-more {
  display: flex;
  justify-content: center;
  padding: 16px 0;
}

/* Responsive - Tablet */
@media (max-width: 768px) {
  .modal-content {
    width: 95%;
    max-height: 90vh;
  }

  .modal-header {
    padding: 16px 20px;
  }

  .modal-header h3 {
    font-size: 16px;
  }

  .stats-bar {
    padding: 12px 16px;
    gap: 8px;
  }

  .stat-value {
    font-size: 18px;
  }

  .stat-label {
    font-size: 11px;
  }

  .filters-bar {
    padding: 10px 16px;
  }

  .modal-body {
    padding: 12px 16px;
  }

  .job-card {
    padding: 12px;
  }

  .detail-row {
    font-size: 12px;
  }

  .detail-label {
    min-width: 60px;
  }
}

/* Responsive - Mobile */
@media (max-width: 480px) {
  .modal-content {
    width: 100%;
    height: 100%;
    max-height: 100vh;
    border-radius: 0;
  }

  .modal-header {
    padding: 14px 16px;
  }

  .modal-header h3 {
    font-size: 15px;
  }

  .stats-bar {
    flex-wrap: wrap;
    gap: 4px 0;
    padding: 10px 12px;
  }

  .stat-item {
    flex: 1 1 33.33%;
    min-width: 60px;
    padding: 4px 0;
  }

  .stat-value {
    font-size: 16px;
  }

  .stat-label {
    font-size: 10px;
  }

  .filters-bar {
    flex-direction: column;
    gap: 8px;
    padding: 10px 12px;
  }

  .filter-group {
    width: 100%;
  }

  .filter-group select {
    width: 100%;
    padding: 10px 12px;
  }

  .modal-body {
    padding: 10px 12px;
  }

  .loading-state,
  .empty-state {
    padding: 32px 16px;
  }

  .jobs-list {
    gap: 10px;
  }

  .job-card {
    padding: 10px;
  }

  .job-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
    margin-bottom: 10px;
  }

  .job-type {
    font-size: 13px;
  }

  .job-status {
    font-size: 11px;
    padding: 3px 6px;
  }

  .job-details {
    gap: 4px;
    margin-bottom: 10px;
  }

  .detail-row {
    font-size: 11px;
    flex-wrap: wrap;
  }

  .detail-label {
    min-width: 55px;
  }

  .error-message {
    font-size: 12px;
    padding: 6px 10px;
  }

  .job-actions {
    flex-wrap: wrap;
  }

  .btn-sm {
    padding: 6px 10px;
    font-size: 12px;
  }

  .load-more {
    padding: 12px 0;
  }

  .load-more .btn {
    width: 100%;
    justify-content: center;
  }
}
</style>
