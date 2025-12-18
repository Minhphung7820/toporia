<template>
  <nav class="flex items-center justify-center gap-1">
    <!-- Previous Button -->
    <button
      @click="goToPage(currentPage - 1)"
      :disabled="currentPage === 1"
      class="px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
      </svg>
    </button>

    <!-- Page Numbers -->
    <template v-for="page in pages" :key="page">
      <button
        v-if="page !== '...'"
        @click="goToPage(page)"
        :class="[
          'px-4 py-2 rounded-lg border text-sm font-medium',
          page === currentPage
            ? 'bg-blue-600 text-white border-blue-600'
            : 'border-gray-300 text-gray-700 hover:bg-gray-100'
        ]"
      >
        {{ page }}
      </button>
      <span v-else class="px-2 py-2 text-gray-500">...</span>
    </template>

    <!-- Next Button -->
    <button
      @click="goToPage(currentPage + 1)"
      :disabled="currentPage === totalPages"
      class="px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
