<template>
  <div v-if="lastPage > 1" class="pagination-wrapper">
    <div class="pagination-info">
      <span class="pagination-text">
        Showing <strong>{{ from }}</strong> to <strong>{{ to }}</strong> of <strong>{{ total }}</strong> results
      </span>
    </div>

    <nav class="pagination" role="navigation" aria-label="Pagination">
      <!-- Previous Button -->
      <button
        type="button"
        class="pagination-btn pagination-prev"
        :disabled="currentPage <= 1"
        @click="goToPage(currentPage - 1)"
        aria-label="Previous page"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M15 18l-6-6 6-6" />
        </svg>
        <span class="btn-text">Previous</span>
      </button>

      <!-- Page Numbers -->
      <div class="pagination-pages">
        <!-- First page -->
        <button
          v-if="showFirstPage"
          type="button"
          class="pagination-page"
          :class="{ active: currentPage === 1 }"
          @click="goToPage(1)"
        >
          1
        </button>

        <!-- Left ellipsis -->
        <span v-if="showLeftEllipsis" class="pagination-ellipsis">...</span>

        <!-- Visible pages -->
        <button
          v-for="page in visiblePages"
          :key="page"
          type="button"
          class="pagination-page"
          :class="{ active: currentPage === page }"
          @click="goToPage(page)"
        >
          {{ page }}
        </button>

        <!-- Right ellipsis -->
        <span v-if="showRightEllipsis" class="pagination-ellipsis">...</span>

        <!-- Last page -->
        <button
          v-if="showLastPage"
          type="button"
          class="pagination-page"
          :class="{ active: currentPage === lastPage }"
          @click="goToPage(lastPage)"
        >
          {{ lastPage }}
        </button>
      </div>

      <!-- Next Button -->
      <button
        type="button"
        class="pagination-btn pagination-next"
        :disabled="currentPage >= lastPage"
        @click="goToPage(currentPage + 1)"
        aria-label="Next page"
      >
        <span class="btn-text">Next</span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 18l6-6-6-6" />
        </svg>
      </button>
    </nav>

    <!-- Per Page Selector -->
    <div v-if="showPerPage" class="pagination-per-page">
      <label for="per-page-select">Per page:</label>
      <select
        id="per-page-select"
        :value="perPage"
        @change="onPerPageChange"
        class="per-page-select"
      >
        <option v-for="option in perPageOptions" :key="option" :value="option">
          {{ option }}
        </option>
      </select>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  currentPage: {
    type: Number,
    required: true,
  },
  lastPage: {
    type: Number,
    required: true,
  },
  perPage: {
    type: Number,
    default: 20,
  },
  total: {
    type: Number,
    required: true,
  },
  from: {
    type: Number,
    default: 0,
  },
  to: {
    type: Number,
    default: 0,
  },
  maxVisiblePages: {
    type: Number,
    default: 5,
  },
  showPerPage: {
    type: Boolean,
    default: true,
  },
  perPageOptions: {
    type: Array,
    default: () => [10, 20, 50, 100],
  },
});

const emit = defineEmits(['page-change', 'per-page-change']);

// Calculate visible page numbers
const visiblePages = computed(() => {
  const pages = [];
  const total = props.lastPage;
  const current = props.currentPage;
  const maxVisible = props.maxVisiblePages;

  if (total <= maxVisible + 2) {
    // Show all pages if total is small
    for (let i = 1; i <= total; i++) {
      pages.push(i);
    }
  } else {
    // Calculate start and end of visible range
    let start = Math.max(2, current - Math.floor(maxVisible / 2));
    let end = Math.min(total - 1, start + maxVisible - 1);

    // Adjust start if end is at maximum
    if (end === total - 1) {
      start = Math.max(2, end - maxVisible + 1);
    }

    for (let i = start; i <= end; i++) {
      pages.push(i);
    }
  }

  // Filter out first and last page (they are shown separately)
  return pages.filter((p) => p !== 1 && p !== total);
});

const showFirstPage = computed(() => props.lastPage > 1);
const showLastPage = computed(() => props.lastPage > 1 && props.lastPage !== 1);

const showLeftEllipsis = computed(() => {
  if (props.lastPage <= props.maxVisiblePages + 2) return false;
  return visiblePages.value.length > 0 && visiblePages.value[0] > 2;
});

const showRightEllipsis = computed(() => {
  if (props.lastPage <= props.maxVisiblePages + 2) return false;
  return (
    visiblePages.value.length > 0 &&
    visiblePages.value[visiblePages.value.length - 1] < props.lastPage - 1
  );
});

const goToPage = (page) => {
  if (page >= 1 && page <= props.lastPage && page !== props.currentPage) {
    emit('page-change', page);
  }
};

const onPerPageChange = (event) => {
  emit('per-page-change', parseInt(event.target.value, 10));
};
</script>

<style scoped>
.pagination-wrapper {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 16px 0;
  border-top: 1px solid #e5e7eb;
  margin-top: 16px;
}

.pagination-info {
  color: #6b7280;
  font-size: 14px;
}

.pagination-text strong {
  color: #111827;
  font-weight: 600;
}

.pagination {
  display: flex;
  align-items: center;
  gap: 4px;
}

.pagination-btn {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 8px 12px;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  background: #fff;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.pagination-btn:hover:not(:disabled) {
  background: #f9fafb;
  border-color: #9ca3af;
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pagination-pages {
  display: flex;
  align-items: center;
  gap: 4px;
}

.pagination-page {
  min-width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  background: #fff;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.pagination-page:hover:not(.active) {
  background: #f9fafb;
  border-color: #9ca3af;
}

.pagination-page.active {
  color: #fff;
  background: #4f46e5;
  border-color: #4f46e5;
}

.pagination-ellipsis {
  min-width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6b7280;
  font-size: 14px;
}

.pagination-per-page {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #6b7280;
}

.per-page-select {
  padding: 6px 28px 6px 12px;
  font-size: 14px;
  color: #374151;
  background: #fff url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e") right 8px center no-repeat;
  background-size: 16px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  cursor: pointer;
  appearance: none;
}

.per-page-select:hover {
  border-color: #9ca3af;
}

.per-page-select:focus {
  outline: none;
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

/* Responsive */
@media (max-width: 768px) {
  .pagination-wrapper {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }

  .pagination {
    justify-content: center;
    order: -1;
  }

  .pagination-info {
    text-align: center;
  }

  .pagination-per-page {
    justify-content: center;
  }

  .btn-text {
    display: none;
  }

  .pagination-btn {
    padding: 8px;
  }
}

@media (max-width: 480px) {
  .pagination-pages {
    display: none;
  }
}
</style>
