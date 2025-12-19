<template>
  <div class="image-upload">
    <div v-if="modelValue" class="image-preview">
      <img :src="modelValue" alt="Preview" />
      <button type="button" @click="removeImage" class="remove-btn" title="Remove image">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18" />
          <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
      </button>
    </div>
    <div v-else class="upload-area" @click="triggerUpload" @dragover.prevent @drop.prevent="handleDrop">
      <input
        ref="fileInput"
        type="file"
        accept="image/*"
        class="file-input"
        @change="handleFileSelect"
      />
      <div class="upload-content">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
          <circle cx="8.5" cy="8.5" r="1.5" />
          <polyline points="21 15 16 10 5 21" />
        </svg>
        <p>Click or drag image to upload</p>
        <span>PNG, JPG, GIF up to 5MB</span>
      </div>
    </div>
    <div v-if="uploading" class="upload-progress">
      <div class="progress-bar" :style="{ width: progress + '%' }"></div>
    </div>
    <div v-if="error" class="upload-error">{{ error }}</div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import http from '../../../services/http';

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['update:modelValue']);

const fileInput = ref(null);
const uploading = ref(false);
const progress = ref(0);
const error = ref(null);

const triggerUpload = () => {
  fileInput.value?.click();
};

const handleFileSelect = (event) => {
  const file = event.target.files?.[0];
  if (file) {
    uploadFile(file);
  }
};

const handleDrop = (event) => {
  const file = event.dataTransfer.files?.[0];
  if (file && file.type.startsWith('image/')) {
    uploadFile(file);
  }
};

const uploadFile = async (file) => {
  // Validate file size (5MB max)
  if (file.size > 5 * 1024 * 1024) {
    error.value = 'File size must be less than 5MB';
    return;
  }

  // Validate file type
  if (!file.type.startsWith('image/')) {
    error.value = 'Only image files are allowed';
    return;
  }

  uploading.value = true;
  progress.value = 0;
  error.value = null;

  const formData = new FormData();
  formData.append('file', file);

  try {
    const response = await http.post('/admin/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
      onUploadProgress: (progressEvent) => {
        progress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
      },
    });

    if (response.data.url || response.data.uploaded) {
      emit('update:modelValue', response.data.url);
    } else {
      error.value = response.data.error?.message || response.data.message || 'Upload failed';
    }
  } catch (err) {
    error.value = err.response?.data?.error?.message || err.response?.data?.message || 'Upload failed';
  } finally {
    uploading.value = false;
    progress.value = 0;
  }
};

const removeImage = () => {
  emit('update:modelValue', '');
  if (fileInput.value) {
    fileInput.value.value = '';
  }
};
</script>

<style scoped>
.image-upload {
  width: 100%;
  max-width: 100%;
}

.image-preview {
  position: relative;
  border-radius: 8px;
  overflow: hidden;
}

.image-preview img {
  width: 100%;
  height: auto;
  display: block;
}

.remove-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 28px;
  height: 28px;
  background: rgba(0, 0, 0, 0.6);
  border: none;
  border-radius: 50%;
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s ease;
}

.remove-btn:hover {
  background: rgba(239, 68, 68, 0.9);
}

.upload-area {
  border: 2px dashed #e5e7eb;
  border-radius: 8px;
  padding: 24px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s ease;
}

.upload-area:hover {
  border-color: #667eea;
  background: #f9fafb;
}

.file-input {
  display: none;
}

.upload-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  color: #9ca3af;
}

.upload-content svg {
  color: #d1d5db;
}

.upload-content p {
  margin: 0;
  font-size: 14px;
  color: #6b7280;
}

.upload-content span {
  font-size: 12px;
}

.upload-progress {
  margin-top: 8px;
  height: 4px;
  background: #e5e7eb;
  border-radius: 2px;
  overflow: hidden;
}

.progress-bar {
  height: 100%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  transition: width 0.3s ease;
}

.upload-error {
  margin-top: 8px;
  font-size: 13px;
  color: #ef4444;
}
</style>
