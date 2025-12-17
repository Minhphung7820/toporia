/**
 * Vue SPA Application Entry Point
 *
 * Single Page Application using Vue 3 and Vue Router
 */

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';

// Check if current path is a backend route that should NOT load Vue app
const backendPaths = ['/api/', '/auth/socialite/'];
const currentPath = window.location.pathname;
const isBackendRoute = backendPaths.some(path => currentPath.startsWith(path));

if (isBackendRoute) {
  // Don't mount Vue app for backend routes - let server handle it
  console.log('🔙 Backend route detected, skipping Vue app initialization');
  // Page will be handled by server
} else {
  // Create and mount Vue app for frontend routes
  try {
    const app = createApp(App);
    const pinia = createPinia();

    app.use(pinia);
    app.use(router);

    // Mount Vue app
    const rootElement = document.getElementById('app');
    if (!rootElement) {
      throw new Error('Root element #app not found!');
    }

    app.mount('#app');
    console.log('✅ Vue app mounted successfully!');
  } catch (error) {
    console.error('❌ Failed to mount Vue app:', error);
    const appElement = document.getElementById('app');
    if (appElement) {
      appElement.innerHTML = `
        <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
          <h1 style="color: red;">Error Loading Application</h1>
          <p>${error.message}</p>
          <p>Please check the browser console for more details.</p>
        </div>
      `;
    }
  }
}
