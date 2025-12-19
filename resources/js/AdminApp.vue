<template>
  <div id="admin-wrapper" class="min-h-screen bg-gray-100">
    <!-- Auth Loading State - shows BEFORE any admin UI -->
    <div v-if="!authReady" class="auth-loading">
      <div class="auth-loading-spinner"></div>
    </div>

    <!-- Admin UI - only renders AFTER auth check completes -->
    <template v-else>
      <!-- Route Loading Indicator (top progress bar) -->
      <div
        v-if="isRouteLoading"
        class="fixed top-0 left-0 right-0 z-50 h-1 bg-blue-100"
      >
        <div class="h-full bg-blue-600 route-loading-bar"></div>
      </div>

      <router-view />
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from './stores/auth';

const authStore = useAuthStore();
const router = useRouter();
const isRouteLoading = ref(false);

// Auth is ready when initialized AND has admin access (admin or editor role)
// If not authenticated or no admin access, the router guard will redirect
const authReady = computed(() => authStore.initialized && authStore.hasAdminAccess);

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

/* Auth loading state - clean minimal spinner */
.auth-loading {
  position: fixed;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
  z-index: 9999;
}

.auth-loading-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e2e8f0;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: auth-spin 0.8s linear infinite;
}

@keyframes auth-spin {
  to { transform: rotate(360deg); }
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
