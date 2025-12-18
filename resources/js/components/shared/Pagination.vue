<template>
  <nav v-if="totalPages > 1" class="pagination">
    <!-- Previous Button -->
    <button
      @click="goToPage(currentPage - 1)"
      :disabled="currentPage === 1"
      class="page-btn nav-btn"
    >
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
      </svg>
      <span class="nav-text">Previous</span>
    </button>

    <!-- Page Numbers -->
    <div class="page-numbers">
      <template v-for="page in pages" :key="page">
        <button
          v-if="page !== '...'"
          @click="goToPage(page)"
          :class="['page-btn', { 'is-active': page === currentPage }]"
        >
          {{ page }}
        </button>
        <span v-else class="page-ellipsis">...</span>
      </template>
    </div>

    <!-- Next Button -->
    <button
      @click="goToPage(currentPage + 1)"
      :disabled="currentPage === totalPages"
      class="page-btn nav-btn"
    >
      <span class="nav-text">Next</span>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
      </svg>
    </button>
  </nav>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  currentPage: {
    type: Number,
    required: true,
  },
  totalPages: {
    type: Number,
    required: true,
  },
  maxVisible: {
    type: Number,
    default: 5,
  },
});

const emit = defineEmits(['page-change']);

// Compute visible page numbers
const pages = computed(() => {
  const pageList = [];
  const total = props.totalPages;
  const current = props.currentPage;
  const maxVisible = props.maxVisible;

  if (total <= maxVisible) {
    // Show all pages
    for (let i = 1; i <= total; i++) {
      pageList.push(i);
    }
  } else {
    // Calculate range
    let start = Math.max(1, current - Math.floor(maxVisible / 2));
    let end = Math.min(total, start + maxVisible - 1);

    // Adjust start if end is at max
    if (end === total) {
      start = Math.max(1, end - maxVisible + 1);
    }

    // Add first page
    if (start > 1) {
      pageList.push(1);
      if (start > 2) {
        pageList.push('...');
      }
    }

    // Add middle pages
    for (let i = start; i <= end; i++) {
      pageList.push(i);
    }

    // Add last page
    if (end < total) {
      if (end < total - 1) {
        pageList.push('...');
      }
      pageList.push(total);
    }
  }

  return pageList;
});

// Methods
const goToPage = (page) => {
  if (page >= 1 && page <= props.totalPages && page !== props.currentPage) {
    emit('page-change', page);
  }
};
</script>

<style scoped>
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 32px;
}

.page-numbers {
  display: flex;
  align-items: center;
  gap: 4px;
}

.page-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 40px;
  height: 40px;
  padding: 0 12px;
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #666;
  cursor: pointer;
  transition: all 0.2s;
}

.page-btn:hover:not(:disabled):not(.is-active) {
  background: #f5f5f5;
  border-color: #ccc;
  color: #1a1a1a;
}

.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.page-btn.is-active {
  background: #1a1a1a;
  border-color: #1a1a1a;
  color: #fff;
}

/* Navigation buttons */
.nav-btn {
  gap: 6px;
  padding: 0 16px;
}

.nav-btn svg {
  flex-shrink: 0;
}

.nav-text {
  font-weight: 500;
}

/* Ellipsis */
.page-ellipsis {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  color: #999;
  font-size: 14px;
}

/* Responsive */
@media (max-width: 640px) {
  .pagination {
    gap: 4px;
  }

  .page-btn {
    min-width: 36px;
    height: 36px;
    padding: 0 8px;
    font-size: 13px;
  }

  .nav-btn {
    padding: 0 10px;
  }

  .nav-text {
    display: none;
  }

  .page-ellipsis {
    width: 32px;
    height: 36px;
  }
}
</style>
