<template>
  <div class="comment-filter-panel">
    <!-- Search Input -->
    <div class="filter-item filter-search">
      <div class="search-wrapper">
        <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        <input
          type="text"
          v-model="localFilters.search"
          placeholder="Search comments..."
          class="search-input"
          @input="handleSearchInput"
        />
        <button v-if="localFilters.search" @click="clearSearch" class="clear-btn" type="button">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Post Filter -->
    <div class="filter-item filter-dropdown">
      <SearchableDropdown
        ref="postDropdown"
        v-model="localFilters.post_id"
        :items="posts"
        label-key="title"
        value-key="id"
        description-key="excerpt"
        placeholder="All Posts"
        search-placeholder="Search posts..."
        empty-message="No posts found"
        :loading="postsLoading"
        :has-more="postsHasMore"
        :remote-search="true"
        @search="handlePostSearch"
        @load-more="loadMorePosts"
        @open="closeOtherDropdowns('post')"
      />
    </div>

    <!-- Author Filter -->
    <div class="filter-item filter-dropdown">
      <SearchableDropdown
        ref="authorDropdown"
        v-model="localFilters.author_id"
        :items="authors"
        label-key="name"
        value-key="id"
        description-key="email"
        placeholder="All Authors"
        search-placeholder="Search authors..."
        empty-message="No authors found"
        :loading="authorsLoading"
        :has-more="authorsHasMore"
        :remote-search="true"
        @search="handleAuthorSearch"
        @load-more="loadMoreAuthors"
        @open="closeOtherDropdowns('author')"
      />
    </div>

    <!-- Time Range Filter -->
    <div class="filter-item filter-time">
      <TimeFilter
        ref="timeFilter"
        v-model="localFilters.time_range"
        @change="handleTimeChange"
        @open="closeOtherDropdowns('time')"
      />
    </div>

    <!-- Status Filter -->
    <div class="filter-item filter-status">
      <div class="status-dropdown" v-click-outside="closeStatusDropdown">
        <div class="status-trigger" @click="toggleStatusDropdown">
          <div class="status-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" />
              <path v-if="localFilters.status === 'approved'" d="M9 12l2 2 4-4" />
              <line v-else-if="localFilters.status === 'rejected'" x1="15" y1="9" x2="9" y2="15" />
              <line v-else-if="localFilters.status === 'rejected'" x1="9" y1="9" x2="15" y2="15" />
            </svg>
          </div>
          <span class="status-label">{{ selectedStatusLabel }}</span>
          <span class="chevron-icon" :class="{ rotated: isStatusOpen }">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="6 9 12 15 18 9" />
            </svg>
          </span>
        </div>

        <Transition name="dropdown">
          <div v-if="isStatusOpen" class="status-panel">
            <button
              v-for="option in statusOptions"
              :key="option.value"
              @click="selectStatus(option)"
              :class="['status-option', { active: localFilters.status === option.value }]"
              type="button"
            >
              <div class="option-icon" :class="`icon-${option.value || 'all'}`">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <component :is="option.icon" />
                </svg>
              </div>
              <div class="option-content">
                <span class="option-label">{{ option.label }}</span>
                <span class="option-desc">{{ option.description }}</span>
              </div>
              <svg v-if="localFilters.status === option.value" class="check-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12" />
              </svg>
            </button>
          </div>
        </Transition>
      </div>
    </div>

    <!-- Clear All Filters -->
    <div v-if="hasActiveFilters" class="filter-item filter-actions">
      <button @click="clearAllFilters" class="clear-all-btn" type="button">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="3 6 5 6 21 6" />
          <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
          <line x1="10" y1="11" x2="10" y2="17" />
          <line x1="14" y1="11" x2="14" y2="17" />
        </svg>
        Clear All
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import SearchableDropdown from '../shared/SearchableDropdown.vue';
import TimeFilter from '../shared/TimeFilter.vue';
import axios from 'axios';

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({
      search: '',
      post_id: null,
      author_id: null,
      time_range: '1h',
      status: '',
    }),
  },
});

const emit = defineEmits(['update:modelValue', 'apply']);

// Local filters state
const localFilters = ref({ ...props.modelValue });

// Posts data
const posts = ref([]);
const postsLoading = ref(false);
const postsHasMore = ref(false);
const postsCursor = ref(null);
const postsSearchQuery = ref('');

// Authors data
const authors = ref([]);
const authorsLoading = ref(false);
const authorsHasMore = ref(false);
const authorsCursor = ref(null);
const authorsSearchQuery = ref('');

// Debounce timers
let searchDebounceTimer = null;
let postSearchDebounceTimer = null;
let authorSearchDebounceTimer = null;

// Status dropdown state
const isStatusOpen = ref(false);

// Status options
const statusOptions = [
  {
    value: '',
    label: 'All Status',
    description: 'Show all comments',
    icon: 'path',
  },
  {
    value: 'approved',
    label: 'Approved',
    description: 'Only approved comments',
    icon: 'path',
  },
  {
    value: 'pending',
    label: 'Pending',
    description: 'Awaiting moderation',
    icon: 'path',
  },
  {
    value: 'rejected',
    label: 'Rejected',
    description: 'Rejected comments',
    icon: 'path',
  },
];

// Computed
const hasActiveFilters = computed(() => {
  return localFilters.value.search ||
         localFilters.value.post_id ||
         localFilters.value.author_id ||
         localFilters.value.status ||
         (localFilters.value.time_range && localFilters.value.time_range !== '1h');
});

const selectedStatusLabel = computed(() => {
  const option = statusOptions.find(opt => opt.value === localFilters.value.status);
  return option ? option.label : 'All Status';
});

// Methods
const applyFilters = () => {
  emit('update:modelValue', { ...localFilters.value });
  emit('apply', { ...localFilters.value });
};

const handleSearchInput = () => {
  if (searchDebounceTimer) {
    clearTimeout(searchDebounceTimer);
  }
  searchDebounceTimer = setTimeout(() => {
    applyFilters();
  }, 300);
};

const clearSearch = () => {
  localFilters.value.search = '';
  applyFilters();
};

const handleTimeChange = (data) => {
  applyFilters();
};

const toggleStatusDropdown = () => {
  if (!isStatusOpen.value) {
    closeOtherDropdowns('status');
  }
  isStatusOpen.value = !isStatusOpen.value;
};

const closeStatusDropdown = () => {
  isStatusOpen.value = false;
};

const selectStatus = (option) => {
  localFilters.value.status = option.value;
  applyFilters();
  closeStatusDropdown();
};

// Refs for dropdown components
const postDropdown = ref(null);
const authorDropdown = ref(null);
const timeFilter = ref(null);

// Close all dropdowns except the specified one
const closeOtherDropdowns = (except) => {
  if (except !== 'post' && postDropdown.value) {
    postDropdown.value.close();
  }
  if (except !== 'author' && authorDropdown.value) {
    authorDropdown.value.close();
  }
  if (except !== 'time' && timeFilter.value) {
    timeFilter.value.close();
  }
  if (except !== 'status') {
    closeStatusDropdown();
  }
};

const clearAllFilters = () => {
  localFilters.value = {
    search: '',
    post_id: null,
    author_id: null,
    time_range: '1h',
    status: '',
  };
  applyFilters();
};

// Load posts with search and cursor pagination
const loadPosts = async (search = '', cursor = null, append = false) => {
  postsLoading.value = true;
  try {
    const params = {
      limit: 20,
      search: search || undefined,
      cursor: cursor || undefined,
      published: 'true', // Only show published posts
    };

    const response = await axios.get('/api/admin/posts/search', { params });

    if (append) {
      // CRITICAL: Use push() instead of spread to preserve scroll position
      response.data.data.forEach(item => posts.value.push(item));
    } else {
      posts.value = response.data.data;
    }

    postsHasMore.value = response.data.has_more || false;
    postsCursor.value = response.data.next_cursor || null;
  } catch (error) {
    console.error('Failed to load posts:', error);
    posts.value = [];
    postsHasMore.value = false;
  } finally {
    postsLoading.value = false;
  }
};

const handlePostSearch = (query) => {
  postsSearchQuery.value = query;
  if (postSearchDebounceTimer) {
    clearTimeout(postSearchDebounceTimer);
  }
  postSearchDebounceTimer = setTimeout(() => {
    loadPosts(query, null, false);
  }, 300);
};

const loadMorePosts = () => {
  if (!postsHasMore.value || postsLoading.value) return;
  loadPosts(postsSearchQuery.value, postsCursor.value, true);
};

// Load authors with search and cursor pagination
const loadAuthors = async (search = '', cursor = null, append = false) => {
  authorsLoading.value = true;
  try {
    const params = {
      limit: 20,
      search: search || undefined,
      cursor: cursor || undefined,
      role: 'user', // Only load regular users who can comment
    };

    const response = await axios.get('/api/admin/users/search', { params });

    if (append) {
      // CRITICAL: Use push() instead of spread to preserve scroll position
      response.data.data.forEach(item => authors.value.push(item));
    } else {
      authors.value = response.data.data;
    }

    authorsHasMore.value = response.data.has_more || false;
    authorsCursor.value = response.data.next_cursor || null;
  } catch (error) {
    console.error('Failed to load authors:', error);
  } finally {
    authorsLoading.value = false;
  }
};

const handleAuthorSearch = (query) => {
  authorsSearchQuery.value = query;
  if (authorSearchDebounceTimer) {
    clearTimeout(authorSearchDebounceTimer);
  }
  authorSearchDebounceTimer = setTimeout(() => {
    loadAuthors(query, null, false);
  }, 300);
};

const loadMoreAuthors = () => {
  if (!authorsHasMore.value || authorsLoading.value) return;
  loadAuthors(authorsSearchQuery.value, authorsCursor.value, true);
};

// Watch for external filter changes
watch(() => props.modelValue, (newVal) => {
  localFilters.value = { ...newVal };
}, { deep: true });

// Initial load
loadPosts();
loadAuthors();
</script>

<style scoped>
.comment-filter-panel {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  padding: 20px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

/* Search Input */
.filter-search {
  flex: 1;
  min-width: 280px;
}

.search-wrapper {
  position: relative;
  width: 100%;
}

.search-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
  pointer-events: none;
  z-index: 1;
}

.search-input {
  width: 100%;
  height: 42px;
  padding: 10px 40px 10px 44px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  font-size: 14px;
  color: #111827;
  outline: none;
  transition: all 0.2s ease;
  box-sizing: border-box;
}

.search-input:focus {
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.search-input::placeholder {
  color: #9ca3af;
}

.clear-btn {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
  background: transparent;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  border-radius: 4px;
  transition: all 0.15s ease;
  z-index: 1;
}

.clear-btn:hover {
  background: #f3f4f6;
  color: #6b7280;
}

/* Filter Dropdowns */
.filter-dropdown {
  min-width: 200px;
  position: relative;
}

.filter-time {
  min-width: 200px;
  position: relative;
}

/* Status Dropdown */
.filter-status {
  min-width: 200px;
  position: relative;
}

.status-dropdown {
  position: relative;
  width: 100%;
}

.status-trigger {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s ease;
  height: 42px;
  box-sizing: border-box;
  user-select: none;
}

.status-trigger:hover {
  border-color: #d1d5db;
  background: #f9fafb;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.status-trigger:active {
  transform: scale(0.995);
}

.status-icon {
  display: flex;
  align-items: center;
  color: #6b7280;
  flex-shrink: 0;
}

.status-label {
  flex: 1;
  color: #111827;
  font-size: 14px;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.status-trigger .chevron-icon {
  display: flex;
  align-items: center;
  color: #6b7280;
  transition: transform 0.2s ease;
  flex-shrink: 0;
}

.status-trigger .chevron-icon.rotated {
  transform: rotate(180deg);
}

.status-panel {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  right: 0;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1),
              0 2px 8px rgba(0, 0, 0, 0.06);
  z-index: 1000;
  min-width: 280px;
  width: max-content;
  max-width: 320px;
  overflow: hidden;
}

.status-option {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 14px 16px;
  background: transparent;
  border: none;
  text-align: left;
  cursor: pointer;
  transition: all 0.15s ease;
  border-bottom: 1px solid #f9fafb;
}

.status-option:last-child {
  border-bottom: none;
}

.status-option:hover {
  background: #f9fafb;
  padding-left: 20px;
}

.status-option:active {
  background: #f3f4f6;
}

.status-option.active {
  background: #eff6ff;
  border-left: 3px solid #4f46e5;
  padding-right: 44px;
}

.status-option.active:hover {
  background: #dbeafe;
  padding-left: 13px;
}

.option-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  flex-shrink: 0;
  transition: all 0.2s ease;
}

.option-icon.icon-all {
  background: #f3f4f6;
  color: #6b7280;
}

.option-icon.icon-approved {
  background: #d1fae5;
  color: #10b981;
}

.option-icon.icon-pending {
  background: #fef3c7;
  color: #f59e0b;
}

.option-icon.icon-rejected {
  background: #fee2e2;
  color: #ef4444;
}

.status-option:hover .option-icon {
  transform: scale(1.1);
}

.option-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.option-label {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  line-height: 1.4;
}

.status-option.active .option-label {
  color: #1e40af;
}

.option-desc {
  font-size: 12px;
  color: #6b7280;
  line-height: 1.4;
}

.status-option.active .option-desc {
  color: #3b82f6;
}

.status-option .check-icon {
  position: absolute;
  right: 16px;
  color: #4f46e5;
  flex-shrink: 0;
  animation: checkIn 0.2s ease-out;
}

@keyframes checkIn {
  from {
    opacity: 0;
    transform: scale(0.5);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

/* Clear All Button */
.filter-actions {
  margin-left: auto;
}

.clear-all-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 16px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 10px;
  color: #dc2626;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.clear-all-btn:hover {
  background: #fee2e2;
  border-color: #fca5a5;
}

.clear-all-btn svg {
  flex-shrink: 0;
}

/* Responsive */
@media (max-width: 1024px) {
  .comment-filter-panel {
    gap: 10px;
  }

  .filter-search {
    min-width: 100%;
    order: 1;
  }

  .filter-dropdown,
  .filter-time,
  .filter-status {
    min-width: calc(50% - 5px);
    flex: 1;
  }

  .filter-actions {
    width: 100%;
    margin-left: 0;
    order: 10;
  }

  .clear-all-btn {
    width: 100%;
    justify-content: center;
  }
}

@media (max-width: 768px) {
  .status-panel {
    min-width: 260px;
    max-width: calc(100vw - 40px);
    left: 50%;
    right: auto;
    transform: translateX(-50%);
  }
}

@media (max-width: 640px) {
  .filter-dropdown,
  .filter-time,
  .filter-status {
    min-width: 100%;
  }

  .status-panel {
    min-width: 240px;
    max-width: calc(100vw - 32px);
    left: 50%;
    right: auto;
    transform: translateX(-50%);
  }

  .status-option {
    padding: 12px 14px;
  }

  .status-option:hover {
    padding-left: 18px;
  }

  .status-option.active {
    padding-right: 40px;
  }

  .status-option.active:hover {
    padding-left: 11px;
  }

  .option-icon {
    width: 28px;
    height: 28px;
  }

  .option-label {
    font-size: 13px;
  }

  .option-desc {
    font-size: 11px;
  }
}

@media (max-width: 480px) {
  .status-panel {
    min-width: 220px;
    max-width: calc(100vw - 24px);
  }

  .status-trigger {
    padding: 9px 12px;
    height: 40px;
  }

  .status-label {
    font-size: 13px;
  }

  .status-option {
    padding: 11px 12px;
  }

  .option-icon {
    width: 26px;
    height: 26px;
  }

  .option-icon svg {
    width: 14px;
    height: 14px;
  }
}
</style>
