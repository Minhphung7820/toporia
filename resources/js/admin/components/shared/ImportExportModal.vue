<template>
  <Teleport to="body">
    <div class="modal-overlay" @click.self="handleClose">
      <div class="modal-content">
        <div class="modal-header">
          <h3>{{ title }}</h3>
          <button class="close-btn" @click="handleClose" :disabled="isProcessing">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 6L6 18M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="modal-body">
          <!-- Import Mode -->
          <template v-if="mode === 'import'">
            <!-- No active job: Show upload form -->
            <div v-if="!activeJob" class="upload-section">
              <div
                class="drop-zone"
                :class="{ 'drag-over': isDragging }"
                @dragover.prevent="isDragging = true"
                @dragleave="isDragging = false"
                @drop.prevent="handleDrop"
                @click="triggerFileInput"
              >
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                  <polyline points="17 8 12 3 7 8" />
                  <line x1="12" y1="3" x2="12" y2="15" />
                </svg>
                <p>Drag & drop CSV file here</p>
                <span>or click to browse</span>
              </div>
              <input
                ref="fileInput"
                type="file"
                accept=".csv"
                @change="handleFileSelect"
                style="display: none"
              />
              <div v-if="selectedFile" class="selected-file">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                  <polyline points="14 2 14 8 20 8" />
                </svg>
                <span>{{ selectedFile.name }}</span>
                <button class="remove-file" @click.stop="selectedFile = null">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Active job: Show progress -->
            <div v-else class="progress-section">
              <div class="job-info">
                <div class="job-file">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                  </svg>
                  <span>{{ activeJob.original_filename || 'Import file' }}</span>
                </div>
                <span class="job-status" :class="`status-${activeJob.status}`">
                  {{ getStatusLabel(activeJob.status) }}
                </span>
              </div>

              <div class="progress-bar-container">
                <div class="progress-bar" :style="{ width: `${activeJob.progress}%` }"></div>
              </div>
              <div class="progress-info">
                <span>{{ activeJob.progress }}%</span>
                <span v-if="activeJob.total_rows > 0">
                  {{ activeJob.processed_rows.toLocaleString() }} / {{ activeJob.total_rows.toLocaleString() }} rows
                </span>
              </div>

              <p v-if="activeJob.message" class="job-message">{{ activeJob.message }}</p>
              <p v-if="activeJob.error_message" class="job-error">{{ activeJob.error_message }}</p>

              <div v-if="activeJob.status === 'completed'" class="result-summary">
                <div class="result-item success">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 11-5.93-9.14" />
                    <polyline points="22 4 12 14.01 9 11.01" />
                  </svg>
                  {{ activeJob.success_rows.toLocaleString() }} imported
                </div>
                <div v-if="activeJob.failed_rows > 0" class="result-item failed">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M15 9l-6 6M9 9l6 6" />
                  </svg>
                  {{ activeJob.failed_rows.toLocaleString() }} failed
                </div>
              </div>
            </div>
          </template>

          <!-- Export Mode -->
          <template v-else>
            <!-- No active job: Show export options -->
            <div v-if="!activeJob" class="export-options">
              <p class="export-description">Export posts to CSV file. You can filter the export:</p>

              <div class="filter-group">
                <label>Status</label>
                <select v-model="exportFilters.is_published">
                  <option value="">All Status</option>
                  <option :value="true">Published</option>
                  <option :value="false">Draft</option>
                </select>
              </div>

              <div class="filter-group">
                <label>Category</label>
                <select v-model="exportFilters.category_id">
                  <option value="">All Categories</option>
                  <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                    {{ cat.name }}
                  </option>
                </select>
              </div>
            </div>

            <!-- Active job: Show progress -->
            <div v-else class="progress-section">
              <div class="job-info">
                <span class="job-title">Exporting posts...</span>
                <span class="job-status" :class="`status-${activeJob.status}`">
                  {{ getStatusLabel(activeJob.status) }}
                </span>
              </div>

              <div class="progress-bar-container">
                <div class="progress-bar" :style="{ width: `${activeJob.progress}%` }"></div>
              </div>
              <div class="progress-info">
                <span>{{ activeJob.progress }}%</span>
                <span v-if="activeJob.total_rows > 0">
                  {{ activeJob.processed_rows.toLocaleString() }} / {{ activeJob.total_rows.toLocaleString() }} rows
                </span>
              </div>

              <p v-if="activeJob.message" class="job-message">{{ activeJob.message }}</p>
              <p v-if="activeJob.error_message" class="job-error">{{ activeJob.error_message }}</p>
            </div>
          </template>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" @click="handleClose" :disabled="isProcessing">
            {{ activeJob && !isFinished ? 'Close' : 'Cancel' }}
          </button>

          <!-- Import buttons -->
          <template v-if="mode === 'import'">
            <button
              v-if="activeJob && activeJob.can_cancel"
              class="btn btn-warning"
              @click="cancelJob"
              :disabled="cancelling"
            >
              {{ cancelling ? 'Cancelling...' : 'Cancel Import' }}
            </button>
            <button
              v-if="!activeJob && selectedFile"
              class="btn btn-primary"
              @click="startImport"
              :disabled="uploading"
            >
              {{ uploading ? 'Uploading...' : 'Start Import' }}
            </button>
            <button
              v-if="isFinished"
              class="btn btn-primary"
              @click="resetAndClose"
            >
              Done
            </button>
          </template>

          <!-- Export buttons -->
          <template v-else>
            <button
              v-if="activeJob && activeJob.can_cancel"
              class="btn btn-warning"
              @click="cancelJob"
              :disabled="cancelling"
            >
              {{ cancelling ? 'Cancelling...' : 'Cancel Export' }}
            </button>
            <button
              v-if="!activeJob"
              class="btn btn-primary"
              @click="startExport"
              :disabled="exporting"
            >
              {{ exporting ? 'Starting...' : 'Start Export' }}
            </button>
            <button
              v-if="activeJob && activeJob.can_download"
              class="btn btn-success"
              @click="downloadFile"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
              </svg>
              Download
            </button>
            <button
              v-if="isFinished && !activeJob?.can_download"
              class="btn btn-primary"
              @click="resetAndClose"
            >
              Done
            </button>
          </template>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  mode: {
    type: String,
    default: 'import',
    validator: (v) => ['import', 'export'].includes(v),
  },
  categories: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['close', 'completed']);

// State
const selectedFile = ref(null);
const isDragging = ref(false);
const fileInput = ref(null);
const uploading = ref(false);
const exporting = ref(false);
const cancelling = ref(false);
const activeJob = ref(null);
const pollingInterval = ref(null);
const exportFilters = ref({
  is_published: '',
  category_id: '',
});

// Computed
const title = computed(() => props.mode === 'import' ? 'Import Posts' : 'Export Posts');

const isProcessing = computed(() =>
  activeJob.value && ['pending', 'processing'].includes(activeJob.value.status)
);

const isFinished = computed(() =>
  activeJob.value && ['completed', 'failed', 'cancelled'].includes(activeJob.value.status)
);

// Methods
const triggerFileInput = () => {
  fileInput.value?.click();
};

const handleFileSelect = (event) => {
  const file = event.target.files?.[0];
  if (file) {
    selectedFile.value = file;
  }
};

const handleDrop = (event) => {
  isDragging.value = false;
  const file = event.dataTransfer?.files?.[0];
  if (file && file.name.endsWith('.csv')) {
    selectedFile.value = file;
  }
};

const getStatusLabel = (status) => {
  const labels = {
    pending: 'Pending',
    processing: 'Processing',
    completed: 'Completed',
    failed: 'Failed',
    cancelled: 'Cancelled',
  };
  return labels[status] || status;
};

const startImport = async () => {
  if (!selectedFile.value) return;

  uploading.value = true;
  try {
    const formData = new FormData();
    formData.append('file', selectedFile.value);

    // Don't set Content-Type manually - let browser set it with correct boundary
    const response = await axios.post('/api/admin/posts/import', formData);

    if (response.data.success) {
      activeJob.value = response.data.data;
      startPolling();
    } else {
      alert(response.data.message || 'Failed to start import');
    }
  } catch (error) {
    alert(error.response?.data?.message || 'Failed to upload file');
  } finally {
    uploading.value = false;
  }
};

const startExport = async () => {
  exporting.value = true;
  try {
    const filters = {};
    if (exportFilters.value.is_published !== '') {
      filters.is_published = exportFilters.value.is_published;
    }
    if (exportFilters.value.category_id) {
      filters.category_id = exportFilters.value.category_id;
    }

    const response = await axios.post('/api/admin/posts/export', filters);

    if (response.data.success) {
      activeJob.value = response.data.data;
      startPolling();
    } else {
      alert(response.data.message || 'Failed to start export');
    }
  } catch (error) {
    alert(error.response?.data?.message || 'Failed to start export');
  } finally {
    exporting.value = false;
  }
};

const cancelJob = async () => {
  if (!activeJob.value) return;

  cancelling.value = true;
  try {
    const response = await axios.post(`/api/admin/posts/jobs/${activeJob.value.id}/cancel`);
    if (response.data.success) {
      activeJob.value.status = 'cancelled';
      stopPolling();
    }
  } catch (error) {
    alert(error.response?.data?.message || 'Failed to cancel job');
  } finally {
    cancelling.value = false;
  }
};

const downloadFile = () => {
  if (!activeJob.value?.id) return;

  // Use window.location to trigger download - browser will use Content-Disposition header
  window.location.href = `/api/admin/posts/jobs/${activeJob.value.id}/download`;
};

const pollJobStatus = async () => {
  if (!activeJob.value?.id) return;

  try {
    const response = await axios.get(`/api/admin/posts/jobs/${activeJob.value.id}`);
    if (response.data.success) {
      activeJob.value = response.data.data;

      if (isFinished.value) {
        stopPolling();
        if (activeJob.value.status === 'completed') {
          emit('completed', activeJob.value);
        }
      }
    }
  } catch (error) {
    console.error('Failed to poll job status:', error);
  }
};

const startPolling = () => {
  stopPolling();
  pollingInterval.value = setInterval(pollJobStatus, 2000);
};

const stopPolling = () => {
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value);
    pollingInterval.value = null;
  }
};

const checkActiveJobs = async () => {
  try {
    const response = await axios.get('/api/admin/posts/jobs/active');
    if (response.data.success && response.data.data) {
      const job = props.mode === 'import'
        ? response.data.data.import
        : response.data.data.export;

      if (job) {
        activeJob.value = job;
        if (!isFinished.value) {
          startPolling();
        }
      }
    }
  } catch (error) {
    console.error('Failed to check active jobs:', error);
  }
};

const handleClose = () => {
  if (isProcessing.value) {
    if (!confirm('A job is still in progress. Are you sure you want to close?')) {
      return;
    }
  }
  stopPolling();
  emit('close');
};

const resetAndClose = () => {
  stopPolling();
  emit('close');
};

// Lifecycle
onMounted(() => {
  checkActiveJobs();
});

onUnmounted(() => {
  stopPolling();
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
  max-width: 500px;
  width: 90%;
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

.close-btn:hover:not(:disabled) {
  background: #f3f4f6;
  color: #1f2937;
}

.close-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.modal-body {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid #e5e7eb;
  background: #f9fafb;
}

/* Upload Section */
.drop-zone {
  border: 2px dashed #d1d5db;
  border-radius: 12px;
  padding: 40px 24px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
}

.drop-zone:hover,
.drop-zone.drag-over {
  border-color: #667eea;
  background: #f5f3ff;
}

.drop-zone svg {
  color: #9ca3af;
  margin-bottom: 12px;
}

.drop-zone p {
  font-size: 16px;
  color: #374151;
  margin: 0 0 4px 0;
}

.drop-zone span {
  font-size: 14px;
  color: #9ca3af;
}

.selected-file {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  background: #f3f4f6;
  border-radius: 8px;
  margin-top: 16px;
}

.selected-file svg {
  color: #6b7280;
  flex-shrink: 0;
}

.selected-file span {
  flex: 1;
  font-size: 14px;
  color: #374151;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.remove-file {
  background: none;
  border: none;
  padding: 4px;
  cursor: pointer;
  color: #9ca3af;
  border-radius: 4px;
}

.remove-file:hover {
  background: #e5e7eb;
  color: #ef4444;
}

/* Progress Section */
.progress-section {
  padding: 8px 0;
}

.job-info {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.job-file {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #374151;
}

.job-file svg {
  color: #6b7280;
}

.job-title {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}

.job-status {
  font-size: 12px;
  font-weight: 500;
  padding: 4px 8px;
  border-radius: 4px;
}

.status-pending {
  background: #fef3c7;
  color: #92400e;
}

.status-processing {
  background: #dbeafe;
  color: #1e40af;
}

.status-completed {
  background: #d1fae5;
  color: #065f46;
}

.status-failed {
  background: #fee2e2;
  color: #991b1b;
}

.status-cancelled {
  background: #f3f4f6;
  color: #6b7280;
}

.progress-bar-container {
  height: 8px;
  background: #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 8px;
}

.progress-bar {
  height: 100%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 4px;
  transition: width 0.3s ease;
}

.progress-info {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  color: #6b7280;
}

.job-message {
  margin: 16px 0 0 0;
  padding: 12px;
  background: #f3f4f6;
  border-radius: 8px;
  font-size: 14px;
  color: #374151;
}

.job-error {
  margin: 16px 0 0 0;
  padding: 12px;
  background: #fee2e2;
  border-radius: 8px;
  font-size: 14px;
  color: #991b1b;
}

.result-summary {
  display: flex;
  gap: 16px;
  margin-top: 16px;
}

.result-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 500;
}

.result-item.success {
  color: #059669;
}

.result-item.failed {
  color: #dc2626;
}

/* Export Options */
.export-options {
  padding: 8px 0;
}

.export-description {
  margin: 0 0 20px 0;
  color: #6b7280;
  font-size: 14px;
}

.filter-group {
  margin-bottom: 16px;
}

.filter-group label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  margin-bottom: 6px;
}

.filter-group select {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  background: #fff;
  cursor: pointer;
}

.filter-group select:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  transition: all 0.2s ease;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  background: #f3f4f6;
  color: #374151;
}

.btn-secondary:hover:not(:disabled) {
  background: #e5e7eb;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
}

.btn-primary:hover:not(:disabled) {
  opacity: 0.9;
}

.btn-warning {
  background: #f59e0b;
  color: #fff;
}

.btn-warning:hover:not(:disabled) {
  background: #d97706;
}

.btn-success {
  background: #10b981;
  color: #fff;
}

.btn-success:hover:not(:disabled) {
  background: #059669;
}

@media (max-width: 480px) {
  .modal-content {
    width: 95%;
    max-height: 95vh;
  }

  .modal-header,
  .modal-body,
  .modal-footer {
    padding: 16px;
  }

  .drop-zone {
    padding: 32px 16px;
  }

  .modal-footer {
    flex-wrap: wrap;
  }

  .modal-footer .btn {
    flex: 1;
    justify-content: center;
  }
}
</style>
