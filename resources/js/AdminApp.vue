<template>
  <div id="admin-wrapper" class="min-h-screen bg-gray-100">
    <!-- Route Loading Indicator (top progress bar) -->
    <div
      v-if="isRouteLoading"
      class="fixed top-0 left-0 right-0 z-50 h-1 bg-blue-100"
    >
      <div class="h-full bg-blue-600 route-loading-bar"></div>
    </div>

    <router-view />
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from './stores/auth';

const authStore = useAuthStore();
const router = useRouter();
const isRouteLoading = ref(false);

// Store cleanup functions for navigation guards
let removeBeforeEach = null;
let removeAfterEach = null;
let removeOnError = null;

onMounted(() => {
  // Register navigation guards ONLY once on mount
  // Store the cleanup functions returned by router guard registration
  removeBeforeEach = router.beforeEach((_to, _from, next) => {
    isRouteLoading.value = true;
    next();
  });

  removeAfterEach = router.afterEach(() => {
    // Small delay to ensure component is rendered
    setTimeout(() => {
      isRouteLoading.value = false;
    }, 50);
  });

  removeOnError = router.onError((error) => {
    console.error('Router error:', error);
    isRouteLoading.value = false;
  });

  // Initialize auth in background - don't block rendering
  if (!authStore.initialized) {
    authStore.initialize();
  }
});

// CRITICAL: Cleanup guards when component unmounts to prevent duplicate registration
onUnmounted(() => {
  if (removeBeforeEach) removeBeforeEach();
  if (removeAfterEach) removeAfterEach();
  if (removeOnError) removeOnError();
});
</script>

<style>
#admin-wrapper {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Route loading progress bar */
.route-loading-bar {
  animation: loading-progress 1s ease-in-out infinite;
}

@keyframes loading-progress {
  0% { width: 0%; }
  50% { width: 70%; }
  100% { width: 100%; }
}
</style>
