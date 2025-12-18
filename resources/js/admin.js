/**
 * Admin SPA Application Entry Point
 *
 * Separate Vue application for the admin panel.
 */

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import AdminApp from './AdminApp.vue';
import router from './admin/router';

// Check if current path is an admin path
const currentPath = window.location.pathname;
const isAdminRoute = currentPath.startsWith('/admin');

if (!isAdminRoute) {
  console.log('🔙 Not an admin route, skipping admin app initialization');
} else {
  try {
    const app = createApp(AdminApp);
    const pinia = createPinia();

    app.use(pinia);
    app.use(router);

    // Mount Vue app
    const rootElement = document.getElementById('admin-app');
    if (!rootElement) {
      throw new Error('Root element #admin-app not found!');
    }

    app.mount('#admin-app');
    console.log('✅ Admin Vue app mounted successfully!');
  } catch (error) {
    console.error('❌ Failed to mount Admin Vue app:', error);
    const appElement = document.getElementById('admin-app');
    if (appElement) {
      appElement.innerHTML = `
        <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
          <h1 style="color: red;">Error Loading Admin Panel</h1>
          <p>${error.message}</p>
          <p>Please check the browser console for more details.</p>
        </div>
      `;
    }
  }
}
