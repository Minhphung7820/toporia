<template>
  <Teleport to="body">
    <div class="modal-overlay" @click.self="handleClose">
      <div class="modal-content">
        <!-- Header with gradient -->
        <div class="modal-header" :class="mode">
          <div class="header-title">
            <svg v-if="mode === 'import'" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
              <polyline points="17 8 12 3 7 8" />
              <line x1="12" y1="3" x2="12" y2="15" />
            </svg>
            <svg v-else width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
              <polyline points="7 10 12 15 17 10" />
              <line x1="12" y1="15" x2="12" y2="3" />
            </svg>
            <h3>{{ title }}</h3>
          </div>
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
                :class="{ 'drag-over': isDragging, 'has-file': selectedFile }"
                @dragover.prevent="isDragging = true"
                @dragleave="isDragging = false"
                @drop.prevent="handleDrop"
                @click="triggerFileInput"
              >
                <div class="drop-icon">
                  <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                    <polyline points="17 8 12 3 7 8" />
                    <line x1="12" y1="3" x2="12" y2="15" />
                  </svg>
                </div>
                <p class="drop-title">Drag & drop CSV file here</p>
                <span class="drop-subtitle">or click to browse</span>
                <div class="drop-hint">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="16" x2="12" y2="12" />
                    <line x1="12" y1="8" x2="12.01" y2="8" />
                  </svg>
                  Supported format: CSV
                </div>
              </div>
              <input
                ref="fileInput"
                type="file"
                accept=".csv"
                @change="handleFileSelect"
                style="display: none"
              />

              <!-- Selected File Card -->
              <div v-if="selectedFile" class="selected-file">
                <div class="file-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                  </svg>
                </div>
                <div class="file-info">
                  <span class="file-name">{{ selectedFile.name }}</span>
                  <span class="file-size">{{ formatFileSize(selectedFile.size) }}</span>
                </div>
                <button class="remove-file" @click.stop="selectedFile = null">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Active job: Show progress -->
            <div v-else class="progress-section">
              <!-- Job Header -->
              <div class="job-header">
                <div class="job-type-icon import">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                    <polyline points="17 8 12 3 7 8" />
                    <line x1="12" y1="3" x2="12" y2="15" />
                  </svg>
                </div>
                <div class="job-header-info">
                  <span class="job-filename">{{ activeJob.original_filename || 'Import file' }}</span>
                  <span class="job-status-badge" :class="`status-${activeJob.status}`">
                    {{ getStatusLabel(activeJob.status) }}
                  </span>
                </div>
              </div>

              <!-- Progress Bar -->
              <div class="progress-wrapper">
                <div class="progress-bar-container">
                  <div class="progress-bar" :class="activeJob.status" :style="{ width: `${activeJob.progress}%` }"></div>
                </div>
                <div class="progress-info">
                  <span class="progress-percent">{{ activeJob.progress }}%</span>
                  <span v-if="activeJob.total_rows > 0" class="progress-rows">
                    {{ activeJob.processed_rows.toLocaleString() }} / {{ activeJob.total_rows.toLocaleString() }} rows
                  </span>
                </div>
              </div>

              <!-- Message Box -->
              <div v-if="activeJob.message" class="message-box info">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10" />
                  <line x1="12" y1="16" x2="12" y2="12" />
                  <line x1="12" y1="8" x2="12.01" y2="8" />
                </svg>
                {{ activeJob.message }}
              </div>

              <!-- Error Box -->
              <div v-if="activeJob.error_message" class="message-box error">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10" />
                  <path d="M15 9l-6 6M9 9l6 6" />
                </svg>
                {{ activeJob.error_message }}
              </div>

              <!-- Result Stats -->
              <div v-if="activeJob.status === 'completed'" class="result-stats">
                <div class="stat-item success">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 11-5.93-9.14" />
                    <polyline points="22 4 12 14.01 9 11.01" />
                  </svg>
                  <div class="stat-content">
                    <span class="stat-value">{{ activeJob.success_rows.toLocaleString() }}</span>
                    <span class="stat-label">imported</span>
                  </div>
                </div>
                <div v-if="activeJob.failed_rows > 0" class="stat-item failed">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M15 9l-6 6M9 9l6 6" />
                  </svg>
                  <div class="stat-content">
                    <span class="stat-value">{{ activeJob.failed_rows.toLocaleString() }}</span>
                    <span class="stat-label">failed</span>
                  </div>
                </div>
              </div>

              <!-- Completed Time -->
              <div v-if="activeJob.completed_at" class="completed-time">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10" />
                  <polyline points="12 6 12 12 16 14" />
                </svg>
                Completed at: {{ formatDateTime(activeJob.completed_at) }}
              </div>
            </div>
          </template>

          <!-- Export Mode -->
          <template v-else>
            <!-- No active job: Show export options -->
            <div v-if="!activeJob" class="export-section">
              <div class="export-intro">
                <div class="intro-icon">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                    <line x1="16" y1="13" x2="8" y2="13" />
                    <line x1="16" y1="17" x2="8" y2="17" />
                    <polyline points="10 9 9 9 8 9" />
                  </svg>
                </div>
                <p class="intro-text">Export posts to CSV file with optional filters</p>
              </div>

              <div class="filters-section">
                <h4 class="filters-title">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
                  </svg>
                  Filter Options
                </h4>

                <div class="filter-grid">
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
              </div>
            </div>

            <!-- Active job: Show progress -->
            <div v-else class="progress-section">
              <!-- Job Header -->
              <div class="job-header">
                <div class="job-type-icon export">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                    <polyline points="7 10 12 15 17 10" />
                    <line x1="12" y1="15" x2="12" y2="3" />
                  </svg>
                </div>
                <div class="job-header-info">
                  <span class="job-filename">{{ activeJob.status === 'completed' ? 'Export completed' : 'Exporting posts...' }}</span>
                  <span class="job-status-badge" :class="`status-${activeJob.status}`">
                    {{ getStatusLabel(activeJob.status) }}
                  </span>
                </div>
              </div>

              <!-- Progress Bar -->
              <div class="progress-wrapper">
                <div class="progress-bar-container">
                  <div class="progress-bar" :class="activeJob.status" :style="{ width: `${activeJob.progress}%` }"></div>
                </div>
                <div class="progress-info">
                  <span class="progress-percent">{{ activeJob.progress }}%</span>
                  <span v-if="activeJob.total_rows > 0" class="progress-rows">
                    {{ activeJob.processed_rows.toLocaleString() }} / {{ activeJob.total_rows.toLocaleString() }} rows
                  </span>
                </div>
              </div>

              <!-- Message Box -->
              <div v-if="activeJob.message" class="message-box info">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10" />
                  <line x1="12" y1="16" x2="12" y2="12" />
                  <line x1="12" y1="8" x2="12.01" y2="8" />
                </svg>
                {{ activeJob.message }}
              </div>

              <!-- Error Box -->
              <div v-if="activeJob.error_message" class="message-box error">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10" />
                  <path d="M15 9l-6 6M9 9l6 6" />
                </svg>
                {{ activeJob.error_message }}
              </div>

              <!-- Completed Time -->
              <div v-if="activeJob.completed_at" class="completed-time">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10" />
                  <polyline points="12 6 12 12 16 14" />
                </svg>
                Completed at: {{ formatDateTime(activeJob.completed_at) }}
              </div>
            </div>
          </template>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" @click="handleClose" :disabled="isProcessing">
            {{ isFinished ? 'Close' : (activeJob ? 'Close' : 'Cancel') }}
          </button>

          <!-- Import buttons -->
          <template v-if="mode === 'import'">
            <button
              v-if="activeJob && activeJob.can_cancel"
              class="btn btn-warning"
              @click="cancelJob"
              :disabled="cancelling"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" />
                <path d="M15 9l-6 6M9 9l6 6" />
              </svg>
              {{ cancelling ? 'Cancelling...' : 'Cancel' }}
            </button>
            <button
              v-if="!activeJob && selectedFile"
              class="btn btn-primary"
              @click="startImport"
              :disabled="uploading"
            >
              <svg v-if="!uploading" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                <polyline points="17 8 12 3 7 8" />
                <line x1="12" y1="3" x2="12" y2="15" />
              </svg>
              <svg v-else class="spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 12a9 9 0 11-6.219-8.56" />
              </svg>
              {{ uploading ? 'Uploading...' : 'Start Import' }}
            </button>
            <button
              v-if="isFinished"
              class="btn btn-primary"
              @click="startNewImport"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
              </svg>
              New Import
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
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" />
                <path d="M15 9l-6 6M9 9l6 6" />
              </svg>
              {{ cancelling ? 'Cancelling...' : 'Cancel' }}
            </button>
            <button
              v-if="!activeJob"
              class="btn btn-primary"
              @click="startExport"
              :disabled="exporting"
            >
              <svg v-if="!exporting" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
              </svg>
              <svg v-else class="spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 12a9 9 0 11-6.219-8.56" />
              </svg>
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
              v-if="isFinished"
              class="btn btn-primary"
              @click="startNewExport"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
              </svg>
              New Export
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
import { useJobProgress } from '@/admin/composables/useJobProgress';

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

// Use SSE composable for real-time progress
const { job: streamedJob, isConnected, connect: connectStream, disconnect: disconnectStream } = useJobProgress();

// State
const selectedFile = ref(null);
const isDragging = ref(false);
const fileInput = ref(null);
const uploading = ref(false);
const exporting = ref(false);
const cancelling = ref(false);
const activeJob = ref(null);
const exportFilters = ref({
  is_published: '',
  category_id: '',
});

// Watch streamed job updates and sync to activeJob
watch(streamedJob, (newJob) => {
  if (newJob) {
    activeJob.value = newJob;

    // Emit completed event when job finishes successfully
    if (newJob.status === 'completed') {
      emit('completed', newJob);
    }
  }
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

const formatDateTime = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleString('vi-VN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
};

const formatFileSize = (bytes) => {
  if (!bytes) return '';
  const units = ['B', 'KB', 'MB', 'GB'];
  let unitIndex = 0;
  let size = bytes;
  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024;
    unitIndex++;
  }
  return `${size.toFixed(1)} ${units[unitIndex]}`;
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
      // Connect to SSE stream for real-time updates
      connectStream(response.data.data.id);
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
      // Connect to SSE stream for real-time updates
      connectStream(response.data.data.id);
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
      disconnectStream();
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

const checkActiveJobs = async () => {
  try {
    const response = await axios.get('/api/admin/posts/jobs/active');
    if (response.data.success && response.data.data) {
      const job = props.mode === 'import'
        ? response.data.data.import
        : response.data.data.export;

      if (job) {
        activeJob.value = job;
        // Only connect stream if job is still in progress
        if (['pending', 'processing'].includes(job.status)) {
          connectStream(job.id);
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
  disconnectStream();
  emit('close');
};

const startNewImport = () => {
  activeJob.value = null;
  selectedFile.value = null;
  disconnectStream();
};

const startNewExport = () => {
  activeJob.value = null;
  disconnectStream();
};

// Lifecycle
onMounted(() => {
  checkActiveJobs();
});

onUnmounted(() => {
  disconnectStream();
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
  max-width: 520px;
  width: 90%;
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
}

.modal-header.import {
  background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
}

.modal-header.export {
  background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
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

.close-btn:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.3);
}

.close-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Modal Body */
.modal-body {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
}

/* Drop Zone */
.drop-zone {
  border: 2px dashed #d1d5db;
  border-radius: 16px;
  padding: 40px 24px;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s ease;
  background: #fafafa;
}

.drop-zone:hover,
.drop-zone.drag-over {
  border-color: #3b82f6;
  background: #eff6ff;
}

.drop-zone.has-file {
  border-color: #10b981;
  background: #f0fdf4;
}

.drop-icon {
  width: 64px;
  height: 64px;
  border-radius: 16px;
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 16px;
  color: #3b82f6;
}

.drop-title {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.drop-subtitle {
  font-size: 14px;
  color: #6b7280;
}

.drop-hint {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 16px;
  padding: 6px 12px;
  background: #f3f4f6;
  border-radius: 20px;
  font-size: 12px;
  color: #6b7280;
}

/* Selected File */
.selected-file {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 12px;
  margin-top: 16px;
}

.file-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: #dcfce7;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #16a34a;
  flex-shrink: 0;
}

.file-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.file-name {
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.file-size {
  font-size: 12px;
  color: #6b7280;
}

.remove-file {
  background: none;
  border: none;
  padding: 6px;
  cursor: pointer;
  color: #9ca3af;
  border-radius: 6px;
  transition: all 0.2s;
}

.remove-file:hover {
  background: #fee2e2;
  color: #ef4444;
}

/* Progress Section */
.progress-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.job-header {
  display: flex;
  align-items: center;
  gap: 14px;
}

.job-type-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.job-type-icon.import {
  background: #dbeafe;
  color: #2563eb;
}

.job-type-icon.export {
  background: #f3e8ff;
  color: #7c3aed;
}

.job-header-info {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.job-filename {
  font-size: 15px;
  font-weight: 600;
  color: #1f2937;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.job-status-badge {
  font-size: 11px;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  flex-shrink: 0;
}

.job-status-badge.status-pending {
  background: #fef3c7;
  color: #92400e;
}

.job-status-badge.status-processing {
  background: #dbeafe;
  color: #1e40af;
}

.job-status-badge.status-completed {
  background: #dcfce7;
  color: #166534;
}

.job-status-badge.status-failed {
  background: #fee2e2;
  color: #991b1b;
}

.job-status-badge.status-cancelled {
  background: #f1f5f9;
  color: #475569;
}

/* Progress Bar */
.progress-wrapper {
  background: #f8fafc;
  padding: 16px;
  border-radius: 12px;
}

.progress-bar-container {
  height: 10px;
  background: #e5e7eb;
  border-radius: 5px;
  overflow: hidden;
}

.progress-bar {
  height: 100%;
  border-radius: 5px;
  transition: width 0.3s ease;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.progress-bar.completed {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.progress-bar.failed {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.progress-bar.cancelled {
  background: #9ca3af;
}

.progress-info {
  display: flex;
  justify-content: space-between;
  margin-top: 10px;
  font-size: 13px;
}

.progress-percent {
  font-weight: 600;
  color: #1f2937;
}

.progress-rows {
  color: #6b7280;
}

/* Message Boxes */
.message-box {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 14px 16px;
  border-radius: 10px;
  font-size: 14px;
  line-height: 1.5;
}

.message-box svg {
  flex-shrink: 0;
  margin-top: 2px;
}

.message-box.info {
  background: #f8fafc;
  color: #475569;
  border: 1px solid #e2e8f0;
}

.message-box.error {
  background: #fef2f2;
  color: #b91c1c;
  border: 1px solid #fecaca;
}

/* Result Stats */
.result-stats {
  display: flex;
  gap: 12px;
}

.stat-item {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 12px;
}

.stat-item.success {
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
}

.stat-item.failed {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

.stat-content {
  display: flex;
  flex-direction: column;
}

.stat-value {
  font-size: 20px;
  font-weight: 700;
  line-height: 1.2;
}

.stat-label {
  font-size: 12px;
  opacity: 0.8;
}

/* Completed Time */
.completed-time {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  background: #f0fdf4;
  border-radius: 10px;
  font-size: 14px;
  color: #166534;
}

/* Export Section */
.export-section {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.export-intro {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
  background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
  border-radius: 12px;
}

.intro-icon {
  width: 56px;
  height: 56px;
  border-radius: 14px;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #7c3aed;
  box-shadow: 0 2px 8px rgba(124, 58, 237, 0.15);
  flex-shrink: 0;
}

.intro-text {
  font-size: 15px;
  color: #4c1d95;
  margin: 0;
  line-height: 1.5;
}

.filters-section {
  background: #f8fafc;
  border-radius: 12px;
  padding: 20px;
}

.filters-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  margin: 0 0 16px 0;
}

.filters-title svg {
  color: #6b7280;
}

.filter-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.filter-group label {
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.filter-group select {
  padding: 10px 32px 10px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  background: #fff url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e") right 8px center no-repeat;
  background-size: 16px;
  cursor: pointer;
  appearance: none;
  transition: all 0.2s;
}

.filter-group select:focus {
  outline: none;
  border-color: #8b5cf6;
  box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

/* Modal Footer */
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 24px;
  border-top: 1px solid #e5e7eb;
  background: #f9fafb;
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
  cursor: pointer;
  border: none;
  transition: all 0.2s ease;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  background: #fff;
  color: #374151;
  border: 1px solid #e5e7eb;
}

.btn-secondary:hover:not(:disabled) {
  background: #f9fafb;
  border-color: #d1d5db;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
}

.btn-primary:hover:not(:disabled) {
  opacity: 0.9;
  transform: translateY(-1px);
}

.btn-warning {
  background: #f59e0b;
  color: #fff;
}

.btn-warning:hover:not(:disabled) {
  background: #d97706;
}

.btn-success {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #fff;
}

.btn-success:hover:not(:disabled) {
  opacity: 0.9;
  transform: translateY(-1px);
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 480px) {
  .modal-content {
    width: 100%;
    height: 100%;
    max-height: 100vh;
    border-radius: 0;
  }

  .modal-header {
    padding: 16px 20px;
  }

  .header-title {
    gap: 10px;
  }

  .header-title svg {
    width: 20px;
    height: 20px;
  }

  .header-title h3 {
    font-size: 16px;
  }

  .modal-body {
    padding: 20px;
  }

  .drop-zone {
    padding: 32px 16px;
  }

  .drop-icon {
    width: 56px;
    height: 56px;
  }

  .drop-icon svg {
    width: 28px;
    height: 28px;
  }

  .drop-title {
    font-size: 15px;
  }

  .job-type-icon {
    width: 42px;
    height: 42px;
  }

  .job-type-icon svg {
    width: 18px;
    height: 18px;
  }

  .job-filename {
    font-size: 14px;
  }

  .job-status-badge {
    font-size: 10px;
    padding: 3px 8px;
  }

  .progress-wrapper {
    padding: 14px;
  }

  .result-stats {
    flex-direction: column;
    gap: 10px;
  }

  .stat-value {
    font-size: 18px;
  }

  .export-intro {
    flex-direction: column;
    text-align: center;
    gap: 12px;
    padding: 16px;
  }

  .intro-icon {
    width: 48px;
    height: 48px;
  }

  .filter-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .modal-footer {
    padding: 14px 20px;
    flex-wrap: wrap;
  }

  .modal-footer .btn {
    flex: 1;
    min-width: calc(50% - 5px);
  }
}
</style>
