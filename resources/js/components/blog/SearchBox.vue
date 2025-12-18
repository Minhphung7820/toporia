<template>
  <div class="bg-white rounded-lg shadow p-6">
    <h3 class="text-lg font-bold mb-4">Search</h3>
    <form @submit.prevent="handleSearch">
      <div class="relative">
        <input
          v-model="query"
          type="text"
          placeholder="Search posts..."
          class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        />
        <button
          type="submit"
          class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-blue-600"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const emit = defineEmits(['search']);
const query = ref('');

const handleSearch = () => {
  if (query.value.trim().length >= 2) {
    emit('search', query.value);
  }
};
</script>
