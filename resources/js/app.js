/**
 * Vue SPA Application Entry Point
 *
 * Single Page Application using Vue 3 and Vue Router
 */

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';

// Create and mount Vue app
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
