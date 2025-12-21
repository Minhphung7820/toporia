<template>
  <Teleport to="body">
    <div class="modal-overlay" @click.self="$emit('close')">
      <div class="modal-content">
        <div class="modal-header">
          <div class="header-title">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" />
              <polyline points="12 6 12 12 16 14" />
            </svg>
            <h3>Job History</h3>
          </div>
          <button class="close-btn" @click="$emit('close')">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 6L6 18M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Stats Summary -->
        <div class="stats-section">
          <div class="stats-grid">
            <div class="stat-card total">
              <div class="stat-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                  <polyline points="14 2 14 8 20 8" />
                </svg>
              </div>
              <div class="stat-info">
                <span class="stat-value">{{ stats?.total_jobs ?? 0 }}</span>
                <span class="stat-label">Total Jobs</span>
              </div>
            </div>
            <div class="stat-card imports">
              <div class="stat-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                  <polyline points="17 8 12 3 7 8" />
                  <line x1="12" y1="3" x2="12" y2="15" />
                </svg>
              </div>
              <div class="stat-info">
                <span class="stat-value">{{ stats?.total_imports ?? 0 }}</span>
                <span class="stat-label">Imports</span>
              </div>
            </div>
            <div class="stat-card exports">
              <div class="stat-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                  <polyline points="7 10 12 15 17 10" />
                  <line x1="12" y1="15" x2="12" y2="3" />
                </svg>
              </div>
              <div class="stat-info">
                <span class="stat-value">{{ stats?.total_exports ?? 0 }}</span>
                <span class="stat-label">Exports</span>
              </div>
            </div>
          </div>
          <div class="stats-summary">
            <div class="summary-item completed">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 11-5.93-9.14" />
                <polyline points="22 4 12 14.01 9 11.01" />
              </svg>
              <span>{{ stats?.completed ?? 0 }} completed</span>
            </div>
            <div class="summary-item failed">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" />
                <path d="M15 9l-6 6M9 9l6 6" />
              </svg>
              <span>{{ stats?.failed ?? 0 }} failed</span>
            </div>
            <div class="summary-item cancelled">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" />
                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
              </svg>
              <span>{{ stats?.cancelled ?? 0 }} cancelled</span>
            </div>
          </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
          <div class="filter-group">
            <label>Type</label>
            <select v-model="filters.type" @change="resetAndFetch">
              <option value="">All</option>
              <option value="import">Import</option>
              <option value="export">Export</option>
            </select>
          </div>
          <div class="filter-group">
            <label>Status</label>
            <select v-model="filters.status" @change="resetAndFetch">
              <option value="">All</option>
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
            <div class="empty-icon">
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                <polyline points="14 2 14 8 20 8" />
                <line x1="12" y1="18" x2="12" y2="12" />
                <line x1="9" y1="15" x2="15" y2="15" />
              </svg>
            </div>
            <p class="empty-title">No jobs found</p>
            <p class="empty-desc">Import or export jobs will appear here</p>
          </div>

          <!-- Jobs List -->
          <div v-else class="jobs-list">
            <div v-for="job in jobs" :key="job.id" class="job-card" :class="`status-${job.status}`">
              <div class="job-main">
                <div class="job-icon" :class="job.type">
                  <svg v-if="job.type === 'import'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                    <polyline points="17 8 12 3 7 8" />
                    <line x1="12" y1="3" x2="12" y2="15" />
                  </svg>
                  <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                    <polyline points="7 10 12 15 17 10" />
                    <line x1="12" y1="15" x2="12" y2="3" />
                  </svg>
                </div>
                <div class="job-content">
                  <div class="job-title-row">
                    <span class="job-title">{{ job.type === 'import' ? 'Import' : 'Export' }}</span>
                    <span class="job-status-badge" :class="`status-${job.status}`">
                      {{ formatStatus(job.status) }}
                    </span>
                  </div>
                  <div v-if="job.original_filename" class="job-filename">{{ job.original_filename }}</div>
                  <div class="job-meta">
                    <span class="meta-item">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                        <line x1="16" y1="2" x2="16" y2="6" />
                        <line x1="8" y1="2" x2="8" y2="6" />
                        <line x1="3" y1="10" x2="21" y2="10" />
                      </svg>
                      {{ formatDateTime(job.created_at) }}
                    </span>
                    <span v-if="job.duration !== null" class="meta-item">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10" />
                        <polyline points="12 6 12 12 16 14" />
                      </svg>
                      {{ formatDuration(job.duration) }}
                    </span>
                  </div>
                </div>
              </div>

              <div class="job-stats">
                <div class="stat-box">
                  <span class="stat-num success">{{ job.success_rows.toLocaleString() }}</span>
                  <span class="stat-txt">success</span>
                </div>
                <div v-if="job.failed_rows > 0" class="stat-box">
                  <span class="stat-num failed">{{ job.failed_rows.toLocaleString() }}</span>
                  <span class="stat-txt">failed</span>
                </div>
                <div class="stat-box">
                  <span class="stat-num">{{ job.total_rows.toLocaleString() }}</span>
                  <span class="stat-txt">total</span>
                </div>
                <div v-if="job.file_size" class="stat-box">
                  <span class="stat-num">{{ formatFileSize(job.file_size) }}</span>
                  <span class="stat-txt">size</span>
                </div>
              </div>

              <div v-if="job.error_message" class="job-error">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10" />
                  <line x1="12" y1="8" x2="12" y2="12" />
                  <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                {{ job.error_message }}
              </div>

              <div v-if="job.can_download || job.can_cancel" class="job-actions">
                <button
                  v-if="job.can_download"
                  class="btn btn-primary"
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
                  class="btn btn-danger-outline"
                  @click="cancelJob(job.id)"
                  :disabled="cancelling === job.id"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M15 9l-6 6M9 9l6 6" />
                  </svg>
                  {{ cancelling === job.id ? 'Cancelling...' : 'Cancel' }}
                </button>
              </div>
            </div>

            <!-- Load More -->
            <div v-if="hasMore" class="load-more">
              <button class="btn btn-secondary" @click="loadMore" :disabled="loading">
                <svg v-if="loading" class="spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 12a9 9 0 11-6.219-8.56" />
                </svg>
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
      const job = jobs.value.find(j => j.id === jobId);
      if (job) {
        job.status = 'cancelled';
        job.can_cancel = false;
      }
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
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
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
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.modal-content {
  background: #fff;
  border-radius: 16px;
  width: 90%;
  max-width: 720px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Header */
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.header-title {
  display: flex;
  align-items: center;
  gap: 12px;
  color: #fff;
}

.header-title svg {
  opacity: 0.9;
}

.header-title h3 {
  font-size: 18px;
  font-weight: 600;
  margin: 0;
}

.close-btn {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  padding: 8px;
  cursor: pointer;
  color: #fff;
  border-radius: 8px;
  transition: all 0.2s;
}

.close-btn:hover {
  background: rgba(255, 255, 255, 0.3);
}

/* Stats Section */
.stats-section {
  padding: 20px 24px;
  background: #f8fafc;
  border-bottom: 1px solid #e5e7eb;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin-bottom: 16px;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  transition: all 0.2s;
}

.stat-card:hover {
  border-color: #d1d5db;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.stat-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-card.total .stat-icon {
  background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%);
  color: #667eea;
}

.stat-card.imports .stat-icon {
  background: #dbeafe;
  color: #2563eb;
}

.stat-card.exports .stat-icon {
  background: #f3e8ff;
  color: #7c3aed;
}

.stat-info {
  display: flex;
  flex-direction: column;
}

.stat-info .stat-value {
  font-size: 22px;
  font-weight: 700;
  color: #1e293b;
  line-height: 1.2;
}

.stat-info .stat-label {
  font-size: 12px;
  color: #64748b;
  font-weight: 500;
}

.stats-summary {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.summary-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 500;
}

.summary-item.completed {
  color: #059669;
}

.summary-item.failed {
  color: #dc2626;
}

.summary-item.cancelled {
  color: #6b7280;
}

/* Filters */
.filters-bar {
  display: flex;
  gap: 16px;
  padding: 16px 24px;
  border-bottom: 1px solid #e5e7eb;
  background: #fff;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.filter-group label {
  font-size: 11px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.filter-group select {
  padding: 8px 32px 8px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  background: #fff url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e") right 8px center no-repeat;
  background-size: 16px;
  cursor: pointer;
  appearance: none;
  min-width: 120px;
}

.filter-group select:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Modal Body */
.modal-body {
  padding: 20px 24px;
  overflow-y: auto;
  flex: 1;
  background: #f8fafc;
}

/* Loading & Empty States */
.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 24px;
  text-align: center;
}

.spinner {
  width: 36px;
  height: 36px;
  border: 3px solid #e5e7eb;
  border-top-color: #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 16px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-icon {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
  color: #94a3b8;
}

.empty-title {
  font-size: 16px;
  font-weight: 600;
  color: #334155;
  margin: 0 0 4px 0;
}

.empty-desc {
  font-size: 14px;
  color: #64748b;
  margin: 0;
}

/* Jobs List */
.jobs-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.job-card {
  background: #fff;
  border-radius: 12px;
  padding: 16px;
  border: 1px solid #e5e7eb;
  transition: all 0.2s;
}

.job-card:hover {
  border-color: #d1d5db;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.job-card.status-completed {
  border-left: 4px solid #10b981;
}

.job-card.status-failed {
  border-left: 4px solid #ef4444;
}

.job-card.status-processing {
  border-left: 4px solid #3b82f6;
}

.job-card.status-pending {
  border-left: 4px solid #f59e0b;
}

.job-card.status-cancelled {
  border-left: 4px solid #6b7280;
}

.job-main {
  display: flex;
  gap: 14px;
  margin-bottom: 14px;
}

.job-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.job-icon.import {
  background: #dbeafe;
  color: #2563eb;
}

.job-icon.export {
  background: #f3e8ff;
  color: #7c3aed;
}

.job-content {
  flex: 1;
  min-width: 0;
}

.job-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 4px;
}

.job-title {
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
}

.job-status-badge {
  font-size: 11px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.job-status-badge.status-completed {
  background: #dcfce7;
  color: #166534;
}

.job-status-badge.status-failed {
  background: #fee2e2;
  color: #991b1b;
}

.job-status-badge.status-processing {
  background: #dbeafe;
  color: #1e40af;
}

.job-status-badge.status-pending {
  background: #fef3c7;
  color: #92400e;
}

.job-status-badge.status-cancelled {
  background: #f1f5f9;
  color: #475569;
}

.job-filename {
  font-size: 13px;
  color: #64748b;
  margin-bottom: 6px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.job-meta {
  display: flex;
  gap: 14px;
  flex-wrap: wrap;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: #64748b;
}

.meta-item svg {
  opacity: 0.7;
}

/* Job Stats */
.job-stats {
  display: flex;
  gap: 8px;
  padding: 12px;
  background: #f8fafc;
  border-radius: 8px;
  margin-bottom: 12px;
}

.stat-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0 12px;
  border-right: 1px solid #e5e7eb;
}

.stat-box:last-child {
  border-right: none;
}

.stat-num {
  font-size: 16px;
  font-weight: 700;
  color: #1e293b;
}

.stat-num.success {
  color: #059669;
}

.stat-num.failed {
  color: #dc2626;
}

.stat-txt {
  font-size: 10px;
  color: #94a3b8;
  text-transform: uppercase;
  font-weight: 500;
  letter-spacing: 0.3px;
}

/* Job Error */
.job-error {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 10px 12px;
  background: #fef2f2;
  border-radius: 8px;
  font-size: 13px;
  color: #b91c1c;
  margin-bottom: 12px;
}

.job-error svg {
  flex-shrink: 0;
  margin-top: 2px;
}

/* Job Actions */
.job-actions {
  display: flex;
  gap: 8px;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
}

.btn-primary:hover {
  opacity: 0.9;
  transform: translateY(-1px);
}

.btn-danger-outline {
  background: #fff;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.btn-danger-outline:hover {
  background: #fef2f2;
}

.btn-secondary {
  background: #fff;
  color: #374151;
  border: 1px solid #e5e7eb;
}

.btn-secondary:hover {
  background: #f9fafb;
  border-color: #d1d5db;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.spin {
  animation: spin 1s linear infinite;
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

  .header-title h3 {
    font-size: 16px;
  }

  .stats-section {
    padding: 16px 20px;
  }

  .stats-grid {
    gap: 8px;
  }

  .stat-card {
    padding: 12px;
  }

  .stat-icon {
    width: 36px;
    height: 36px;
  }

  .stat-info .stat-value {
    font-size: 18px;
  }

  .filters-bar {
    padding: 12px 20px;
  }

  .modal-body {
    padding: 16px 20px;
  }

  .job-stats {
    flex-wrap: wrap;
  }

  .stat-box {
    flex: 1;
    min-width: 60px;
    padding: 0 8px;
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

  .header-title {
    gap: 8px;
  }

  .header-title svg {
    width: 20px;
    height: 20px;
  }

  .header-title h3 {
    font-size: 15px;
  }

  .stats-section {
    padding: 14px 16px;
  }

  .stats-grid {
    grid-template-columns: 1fr;
    gap: 8px;
  }

  .stat-card {
    padding: 10px 12px;
  }

  .stat-icon {
    width: 32px;
    height: 32px;
  }

  .stat-icon svg {
    width: 16px;
    height: 16px;
  }

  .stat-info .stat-value {
    font-size: 16px;
  }

  .stat-info .stat-label {
    font-size: 11px;
  }

  .stats-summary {
    gap: 12px;
  }

  .summary-item {
    font-size: 12px;
  }

  .filters-bar {
    flex-direction: column;
    gap: 12px;
    padding: 12px 16px;
  }

  .filter-group {
    width: 100%;
  }

  .filter-group select {
    width: 100%;
    padding: 10px 32px 10px 12px;
  }

  .modal-body {
    padding: 14px 16px;
  }

  .loading-state,
  .empty-state {
    padding: 40px 16px;
  }

  .jobs-list {
    gap: 10px;
  }

  .job-card {
    padding: 14px;
  }

  .job-main {
    gap: 10px;
    margin-bottom: 12px;
  }

  .job-icon {
    width: 38px;
    height: 38px;
  }

  .job-icon svg {
    width: 18px;
    height: 18px;
  }

  .job-title {
    font-size: 14px;
  }

  .job-status-badge {
    font-size: 10px;
    padding: 2px 6px;
  }

  .job-filename {
    font-size: 12px;
  }

  .meta-item {
    font-size: 11px;
  }

  .job-stats {
    padding: 10px;
    gap: 4px;
  }

  .stat-box {
    padding: 0 6px;
  }

  .stat-num {
    font-size: 14px;
  }

  .stat-txt {
    font-size: 9px;
  }

  .job-error {
    font-size: 12px;
    padding: 8px 10px;
  }

  .job-actions {
    flex-wrap: wrap;
  }

  .btn {
    padding: 8px 12px;
    font-size: 12px;
    flex: 1;
  }

  .load-more .btn {
    width: 100%;
  }
}
</style>
