<template>
  <div class="searchable-dropdown" v-click-outside="close">
    <div class="dropdown-trigger" @click="toggle">
      <div class="selected-value">
        <span v-if="selectedItem" class="selected-text">{{ selectedItem[labelKey] }}</span>
        <span v-else class="placeholder">{{ placeholder }}</span>
      </div>
      <div class="dropdown-actions">
        <button
          v-if="selectedItem && clearable"
          @click.stop="clearSelection"
          class="clear-btn"
          type="button"
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
        <span class="chevron-icon" :class="{ rotated: isOpen }">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 12 15 18 9" />
          </svg>
        </span>
      </div>
    </div>

    <Transition name="dropdown">
      <div v-if="isOpen" class="dropdown-panel">
        <!-- Search Input -->
        <div class="search-box">
          <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.35-4.35" />
          </svg>
          <input
            ref="searchInput"
            v-model="searchQuery"
            type="text"
            :placeholder="searchPlaceholder"
            class="search-input"
            @input="handleSearch"
          />
          <button
            v-if="searchQuery"
            @click="searchQuery = ''"
            class="clear-search-btn"
            type="button"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        </div>

        <!-- CRITICAL: Keep options-list always mounted to preserve scroll position -->
        <!-- Options List with Infinite Scroll -->
        <div
          v-show="props.items.length > 0"
          ref="optionsList"
          class="options-list"
          @scroll="handleScroll"
        >
          <template v-for="item in props.items" :key="item[valueKey]">
            <button
              v-show="shouldShowItem(item)"
              @click="selectItem(item)"
              :class="['option-item', { selected: isSelected(item) }]"
              type="button"
            >
              <span class="option-label">{{ item[labelKey] }}</span>
              <span v-if="item[descriptionKey]" class="option-description">
                {{ item[descriptionKey] }}
              </span>
              <svg
                v-if="isSelected(item)"
                class="check-icon"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
              >
                <polyline points="20 6 9 17 4 12" />
              </svg>
            </button>
          </template>

          <!-- Inline Loading Indicator (when loading more) -->
          <div v-if="loadingMore" class="loading-more">
            <div class="spinner-small"></div>
            <span>Loading more...</span>
          </div>

          <!-- Scroll Sentinel (invisible element to detect scroll end) -->
          <div v-if="hasMore && !loadingMore" ref="scrollSentinel" class="scroll-sentinel"></div>
        </div>

        <!-- Empty State -->
        <div v-if="!props.items.length && !props.loading" class="dropdown-empty">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.35-4.35" />
          </svg>
          <p>{{ emptyMessage }}</p>
        </div>

        <!-- Loading Overlay (initial search only, not load-more) -->
        <div v-if="props.loading && !loadingMore" class="dropdown-loading overlay">
          <div class="spinner"></div>
          <span>Searching...</span>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onUnmounted } from 'vue';

const props = defineProps({
  modelValue: {
    type: [String, Number, Object],
    default: null,
  },
  items: {
    type: Array,
    required: true,
  },
  labelKey: {
    type: String,
    default: 'label',
  },
  valueKey: {
    type: String,
    default: 'value',
  },
  descriptionKey: {
    type: String,
    default: 'description',
  },
  placeholder: {
    type: String,
    default: 'Select an option',
  },
  searchPlaceholder: {
    type: String,
    default: 'Search...',
  },
  emptyMessage: {
    type: String,
    default: 'No results found',
  },
  clearable: {
    type: Boolean,
    default: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  hasMore: {
    type: Boolean,
    default: false,
  },
  // If true, search will emit event instead of local filter
  remoteSearch: {
    type: Boolean,
    default: false,
  },
  // Debounce delay for remote search (ms)
  searchDebounce: {
    type: Number,
    default: 300,
  },
});

const emit = defineEmits(['update:modelValue', 'search', 'load-more', 'open', 'items-appended']);

// State
const isOpen = ref(false);
const searchQuery = ref('');
const searchInput = ref(null);
const optionsList = ref(null);
const scrollSentinel = ref(null);
const loadingMore = ref(false);
let searchTimeout = null;
let intersectionObserver = null;

// Computed - memoized for performance
const selectedItem = computed(() => {
  if (!props.modelValue) return null;
  return props.items.find(item => item[props.valueKey] === props.modelValue);
});

// Memoize isSelected check to avoid re-computing on every render
const isSelected = (item) => {
  return item[props.valueKey] === props.modelValue;
};

// Memoize search query lowercase for performance
const searchQueryLower = computed(() => searchQuery.value.toLowerCase());

// Check if item should be shown (for v-show filtering)
const shouldShowItem = (item) => {
  // For remote search, show all items (server handles filtering)
  if (props.remoteSearch) return true;

  // No search query, show all
  if (!searchQuery.value) return true;

  // Local filtering - use memoized lowercase query
  const query = searchQueryLower.value;
  const label = String(item[props.labelKey]).toLowerCase();

  // Quick label check first (most common case)
  if (label.includes(query)) return true;

  // Only check description if label doesn't match
  if (item[props.descriptionKey]) {
    const description = String(item[props.descriptionKey]).toLowerCase();
    return description.includes(query);
  }

  return false;
};

// Methods
const toggle = () => {
  const willOpen = !isOpen.value;
  if (willOpen) {
    emit('open');
  }
  isOpen.value = willOpen;
  if (isOpen.value) {
    nextTick(() => {
      searchInput.value?.focus();
    });
  }
};

const close = () => {
  isOpen.value = false;
  searchQuery.value = '';
};

const selectItem = (item) => {
  emit('update:modelValue', item[props.valueKey]);
  close();
};

const clearSelection = () => {
  emit('update:modelValue', null);
};

const handleSearch = () => {
  if (!props.remoteSearch) return;

  // Clear existing timeout
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }

  // Debounce remote search
  searchTimeout = setTimeout(() => {
    emit('search', searchQuery.value);
  }, props.searchDebounce);
};

// Throttle scroll handler for better performance
let scrollThrottleTimer = null;

// Infinite Scroll - Handle scroll event (fallback for browsers without IntersectionObserver)
const handleScroll = () => {
  // Throttle scroll events to run max once per 100ms
  if (scrollThrottleTimer) return;

  scrollThrottleTimer = setTimeout(() => {
    scrollThrottleTimer = null;

    if (!optionsList.value || loadingMore.value || !props.hasMore) return;

    const scrollContainer = optionsList.value;
    const scrollTop = scrollContainer.scrollTop;
    const scrollHeight = scrollContainer.scrollHeight;
    const clientHeight = scrollContainer.clientHeight;

    // Trigger when user scrolls to 90% of the content (more conservative)
    const scrollPercentage = (scrollTop + clientHeight) / scrollHeight;

    if (scrollPercentage >= 0.9) {
      loadMore();
    }
  }, 100);
};

// Scroll preservation - simple approach
const savedScrollTop = ref(0);

// Load more items
const loadMore = () => {
  if (loadingMore.value || !props.hasMore || props.loading) return;

  // Save scroll position before loading
  const container = optionsList.value;
  if (container) {
    savedScrollTop.value = container.scrollTop;
  }

  loadingMore.value = true;
  emit('load-more');
};

// Setup Intersection Observer for infinite scroll
const setupIntersectionObserver = () => {
  // Cleanup existing observer
  if (intersectionObserver) {
    intersectionObserver.disconnect();
  }

  // Only setup if IntersectionObserver is supported
  if (!window.IntersectionObserver) return;

  intersectionObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        // When sentinel becomes visible, load more
        if (entry.isIntersecting && props.hasMore && !loadingMore.value && !props.loading) {
          loadMore();
        }
      });
    },
    {
      root: optionsList.value,
      rootMargin: '0px', // No preloading - wait until truly at bottom
      threshold: 0.5, // Trigger when sentinel is 50% visible
    }
  );

  // Observe the sentinel element
  if (scrollSentinel.value) {
    intersectionObserver.observe(scrollSentinel.value);
  }
};

// Cleanup intersection observer on unmount
const cleanupIntersectionObserver = () => {
  if (intersectionObserver) {
    intersectionObserver.disconnect();
    intersectionObserver = null;
  }
};

// Click outside directive
const vClickOutside = {
  mounted(el, binding) {
    el.clickOutsideEvent = (event) => {
      if (!(el === event.target || el.contains(event.target))) {
        binding.value();
      }
    };
    document.addEventListener('click', el.clickOutsideEvent);
  },
  unmounted(el) {
    document.removeEventListener('click', el.clickOutsideEvent);
  },
};

// Watch for search query changes (local search)
watch(searchQuery, () => {
  if (props.remoteSearch) {
    handleSearch();
  }
});

// Watch for scrollSentinel changes (when hasMore changes or items update)
watch(scrollSentinel, (newSentinel) => {
  if (newSentinel && isOpen.value) {
    nextTick(() => {
      setupIntersectionObserver();
    });
  }
});

// Watch for loading state changes
watch(() => props.loading, (newLoading) => {
  if (!newLoading) {
    loadingMore.value = false;
  }
});

// CRITICAL: Restore scroll when items are appended
watch(
  () => props.items.length,
  async (newLen, oldLen) => {
    // Only restore when load-more and items increased
    if (!loadingMore.value || !oldLen || newLen <= oldLen) return;

    // Wait for DOM update
    await nextTick();

    // Restore scroll position
    requestAnimationFrame(() => {
      const container = optionsList.value;
      if (container && savedScrollTop.value) {
        container.scrollTop = savedScrollTop.value;
      }
      // Turn off loadingMore flag
      loadingMore.value = false;
      savedScrollTop.value = 0;
    });
  }
);

// Watch for when dropdown opens to setup observer
watch(isOpen, (newValue) => {
  if (newValue) {
    nextTick(() => {
      setupIntersectionObserver();
    });
  } else {
    cleanupIntersectionObserver();
    loadingMore.value = false;
  }
});

// Cleanup on unmount
onUnmounted(() => {
  cleanupIntersectionObserver();
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  if (scrollThrottleTimer) {
    clearTimeout(scrollThrottleTimer);
  }
});

// Expose methods for parent component
defineExpose({
  close,
});
</script>

<style scoped>
.searchable-dropdown {
  position: relative;
  width: 100%;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}

/* Trigger */
.dropdown-trigger {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  height: 42px;
  box-sizing: border-box;
  user-select: none;
}

.dropdown-trigger:hover {
  border-color: #d1d5db;
  background: #fafbfc;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.dropdown-trigger:active {
  transform: scale(0.995);
}

.dropdown-trigger:focus-within {
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.selected-value {
  flex: 1;
  min-width: 0;
}

.selected-text {
  color: #111827;
  font-size: 14px;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: block;
  line-height: 1.5;
}

.placeholder {
  color: #9ca3af;
  font-size: 14px;
  line-height: 1.5;
}

.dropdown-actions {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
  margin-left: 8px;
}

.clear-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 5px;
  background: transparent;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  border-radius: 6px;
  transition: all 0.15s ease;
}

.clear-btn:hover {
  background: #f3f4f6;
  color: #6b7280;
  transform: scale(1.05);
}

.clear-btn:active {
  transform: scale(0.95);
}

.chevron-icon {
  display: flex;
  align-items: center;
  color: #6b7280;
  transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.chevron-icon.rotated {
  transform: rotate(180deg);
}

/* Panel */
.dropdown-panel {
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
  max-height: 360px;
  min-width: 360px;
  width: max-content;
  max-width: 450px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* Search Box */
.search-box {
  position: relative;
  padding: 12px 12px;
  border-bottom: 1px solid #f3f4f6;
  flex-shrink: 0;
  background: #fafbfc;
  border-radius: 12px 12px 0 0;
}

.search-icon {
  position: absolute;
  left: 22px;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
  pointer-events: none;
  transition: color 0.2s ease;
}

.search-box:focus-within .search-icon {
  color: #4f46e5;
}

.search-input {
  width: 100%;
  padding: 9px 36px 9px 36px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  color: #111827;
  outline: none;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  background: white;
  box-sizing: border-box;
}

.search-input:hover {
  border-color: #d1d5db;
}

.search-input:focus {
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
  background: white;
}

.search-input::placeholder {
  color: #9ca3af;
}

.clear-search-btn {
  position: absolute;
  right: 22px;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 5px;
  background: transparent;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  border-radius: 6px;
  transition: all 0.15s ease;
}

.clear-search-btn:hover {
  background: #f3f4f6;
  color: #6b7280;
  transform: translateY(-50%) scale(1.05);
}

.clear-search-btn:active {
  transform: translateY(-50%) scale(0.95);
}

/* Loading State */
.dropdown-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 36px 20px;
  gap: 14px;
  color: #6b7280;
  font-size: 14px;
}

/* Loading overlay - doesn't unmount the list */
.dropdown-loading.overlay {
  position: absolute;
  top: 56px; /* Below search box */
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(2px);
  z-index: 10;
  border-radius: 0 0 12px 12px;
  padding: 20px;
}

.spinner {
  width: 28px;
  height: 28px;
  border: 3px solid #f3f4f6;
  border-top-color: #4f46e5;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Infinite Scroll - Loading More Indicator */
.loading-more {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 16px;
  color: #6b7280;
  font-size: 13px;
  font-weight: 500;
  background: linear-gradient(to bottom, transparent, #fafbfc);
}

.spinner-small {
  width: 18px;
  height: 18px;
  border: 2px solid #e5e7eb;
  border-top-color: #4f46e5;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

/* Infinite Scroll - Sentinel Element */
.scroll-sentinel {
  height: 1px;
  width: 100%;
  pointer-events: none;
  visibility: hidden;
}

/* Options List */
.options-list {
  overflow-y: auto;
  overflow-x: hidden;
  flex: 1;
  min-height: 0;
  max-height: 260px;
  /* CRITICAL: Disable smooth scrolling to prevent auto-scroll to top during load-more */
  scroll-behavior: auto;
  /* Enable momentum scrolling on iOS */
  -webkit-overflow-scrolling: touch;
  /* Performance: CSS containment for better rendering */
  contain: layout style paint;
  /* Performance: Enable GPU acceleration */
  will-change: scroll-position;
}

.option-item {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  width: 100%;
  padding: 13px 18px;
  background: transparent;
  border: none;
  text-align: left;
  cursor: pointer;
  transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
  border-bottom: 1px solid #f9fafb;
  /* Performance: Isolate repaints to individual items */
  contain: layout style;
}

.option-item:last-child {
  border-bottom: none;
}

.option-item:hover {
  background: #f9fafb;
  padding-left: 22px;
}

.option-item:active {
  background: #f3f4f6;
}

.option-item.selected {
  background: #eff6ff;
  padding-right: 48px;
  border-left: 3px solid #4f46e5;
}

.option-item.selected:hover {
  background: #dbeafe;
  padding-left: 15px;
}

.option-label {
  font-size: 14px;
  font-weight: 500;
  color: #111827;
  margin-bottom: 3px;
  line-height: 1.4;
  word-break: break-word;
}

.option-item.selected .option-label {
  color: #1e40af;
}

.option-description {
  font-size: 12px;
  color: #6b7280;
  line-height: 1.5;
  word-break: break-word;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.option-item.selected .option-description {
  color: #3b82f6;
}

.check-icon {
  position: absolute;
  right: 18px;
  top: 50%;
  transform: translateY(-50%);
  color: #4f46e5;
  flex-shrink: 0;
  animation: checkIn 0.2s ease-out;
}

@keyframes checkIn {
  from {
    opacity: 0;
    transform: translateY(-50%) scale(0.5);
  }
  to {
    opacity: 1;
    transform: translateY(-50%) scale(1);
  }
}

/* Empty State */
.dropdown-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  color: #9ca3af;
}

.dropdown-empty svg {
  margin-bottom: 14px;
  opacity: 0.7;
}

.dropdown-empty p {
  margin: 0;
  font-size: 14px;
  font-weight: 500;
}

/* Load More */
.load-more-section {
  border-top: 1px solid #f3f4f6;
  padding: 10px 14px;
  flex-shrink: 0;
  background: #fafbfc;
}

.load-more-btn {
  width: 100%;
  padding: 9px 12px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  color: #6b7280;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.load-more-btn:hover {
  background: white;
  border-color: #d1d5db;
  color: #374151;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  transform: translateY(-1px);
}

.load-more-btn:active {
  transform: translateY(0);
  box-shadow: none;
}

/* Scrollbar Styling */
.options-list::-webkit-scrollbar {
  width: 8px;
}

.options-list::-webkit-scrollbar-track {
  background: #fafbfc;
  border-radius: 0 12px 12px 0;
}

.options-list::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 4px;
  border: 2px solid #fafbfc;
}

.options-list::-webkit-scrollbar-thumb:hover {
  background: #9ca3af;
}

/* Firefox Scrollbar */
.options-list {
  scrollbar-width: thin;
  scrollbar-color: #d1d5db #fafbfc;
}

/* Transitions */
.dropdown-enter-active {
  transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.dropdown-leave-active {
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.dropdown-enter-from {
  opacity: 0;
  transform: translateY(-12px) scale(0.95);
}

.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-8px) scale(0.98);
}

/* Responsive Design */
@media (max-width: 768px) {
  .dropdown-panel {
    max-height: 340px;
    top: calc(100% + 6px);
    min-width: 280px;
    max-width: calc(100vw - 40px);
    left: 50%;
    right: auto;
    transform: translateX(-50%);
  }

  .search-box {
    padding: 12px;
  }

  .option-item {
    padding: 12px 16px;
  }

  .option-item:hover {
    padding-left: 20px;
  }

  .option-item.selected {
    padding-right: 44px;
  }

  .option-item.selected:hover {
    padding-left: 13px;
  }
}

@media (max-width: 640px) {
  .dropdown-panel {
    max-height: 300px;
    border-radius: 10px;
    min-width: 280px;
    max-width: calc(100vw - 32px);
    left: 50%;
    right: auto;
    transform: translateX(-50%);
  }

  .search-box {
    padding: 10px;
  }

  .search-input {
    padding: 9px 36px;
    font-size: 13px;
  }

  .option-item {
    padding: 11px 14px;
  }

  .option-item:hover {
    padding-left: 18px;
  }

  .option-item.selected {
    padding-right: 42px;
  }

  .option-item.selected:hover {
    padding-left: 11px;
  }

  .option-label {
    font-size: 13px;
  }

  .option-description {
    font-size: 11px;
  }

  .dropdown-empty {
    padding: 36px 20px;
  }

  .dropdown-loading {
    padding: 32px 18px;
  }

  .load-more-section {
    padding: 8px 10px;
  }

  .load-more-btn {
    padding: 8px 10px;
    font-size: 12px;
  }
}

@media (max-width: 480px) {
  .dropdown-panel {
    max-height: 260px;
    min-width: 240px;
    max-width: calc(100vw - 24px);
    left: 50%;
    right: auto;
    transform: translateX(-50%);
  }

  .dropdown-trigger {
    padding: 9px 12px;
    height: 40px;
  }

  .selected-text,
  .placeholder {
    font-size: 13px;
  }
}

/* Dark mode support (optional, can be enabled if needed) */
@media (prefers-color-scheme: dark) {
  /* Uncomment for dark mode support
  .searchable-dropdown {
    color-scheme: dark;
  }
  */
}
</style>
