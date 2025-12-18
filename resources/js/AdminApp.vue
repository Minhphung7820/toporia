<template>
  <div id="admin-wrapper" class="min-h-screen bg-gray-100">
    <!-- Loading Screen -->
    <div v-if="!authStore.initialized" class="flex items-center justify-center h-screen">
      <div class="text-center">
        <div class="animate-spin w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full mx-auto mb-4"></div>
        <p class="text-gray-600">Loading...</p>
      </div>
    </div>

    <!-- App Content -->
    <template v-else>
      <router-view />
    </template>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useAuthStore } from './stores/auth';

const authStore = useAuthStore();

onMounted(async () => {
  if (!authStore.initialized) {
    await authStore.initialize();
  }
});
</script>

<style>
#admin-wrapper {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
</style>
