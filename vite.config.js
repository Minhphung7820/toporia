import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import toporiaVitePlugin from './packages/framework/src/Support/Vite/ToporiaVitePlugin.js';

/**
 * Vite Configuration for Toporia Framework
 *
 * This configuration file sets up Vite for asset bundling.
 * Uses custom Toporia Vite Plugin.
 *
 * Installation:
 *   npm install --save-dev vite
 *
 * Usage:
 *   npm run dev        # Start development server with HMR
 *   npm run build      # Build for production
 */

export default defineConfig({
  plugins: [
    vue(),
    toporiaVitePlugin({
      input: [
        'resources/js/app.js',
        // Add more entry points here:
        // 'resources/js/admin.js',
        // 'resources/css/app.css',
      ],
      manifestPath: 'public/build/.vite/manifest.json',
      publicDir: 'public',
    }),
  ],
  resolve: {
    alias: {
      '@': '/resources/js',
    },
  },
  build: {
    outDir: 'public/build',
    manifest: true,
    rollupOptions: {
      input: {
        app: 'resources/js/app.js',
      },
      output: {
        entryFileNames: 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash].[ext]',
      },
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    hmr: {
      host: 'localhost',
    },
    cors: true,
  },
});

