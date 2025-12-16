<template>
  <div class="error-page">
    <div class="error-content">
      <div class="error-icon">⚡</div>
      <h1 class="error-code">500</h1>
      <h2 class="error-title">Server Error</h2>
      <p class="error-message">
        Something went wrong on our end. We're working to fix it.
      </p>
      <div class="error-actions">
        <button @click="retry" class="btn btn-secondary">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M3.333 10C3.333 6.318 6.318 3.333 10 3.333C13.682 3.333 16.667 6.318 16.667 10C16.667 13.682 13.682 16.667 10 16.667M10 6.667V10L12.5 12.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span>Try Again</span>
        </button>
        <router-link to="/" class="btn btn-primary">
          <span>Go Home</span>
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M3.333 10L16.667 10M16.667 10L11.667 5M16.667 10L11.667 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';

const router = useRouter();

const retry = () => {
  window.location.reload();
};
</script>

<style scoped>
.error-page {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  background: #fafafa;
  padding: 20px;
}

.error-content {
  text-align: center;
  max-width: 500px;
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 16px;
  padding: 60px 40px;
}

.error-icon {
  font-size: 64px;
  margin-bottom: 24px;
}

.error-code {
  font-size: clamp(64px, 12vw, 120px);
  font-weight: 800;
  margin: 0;
  line-height: 1;
  color: #1a1a1a;
}

.error-title {
  font-size: clamp(24px, 4vw, 32px);
  font-weight: 600;
  margin: 16px 0;
  color: #1a1a1a;
}

.error-message {
  font-size: 16px;
  margin: 16px 0 32px;
  color: #666;
  line-height: 1.6;
}

.error-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
  flex-wrap: wrap;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  border: none;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
}

.btn-primary {
  background: #1a1a1a;
  color: white;
}

.btn-primary:hover {
  background: #333;
}

.btn-secondary {
  background: #fff;
  color: #1a1a1a;
  border: 1px solid #ddd;
}

.btn-secondary:hover {
  background: #f5f5f5;
  border-color: #ccc;
}

.btn svg {
  flex-shrink: 0;
}

@media (max-width: 768px) {
  .error-content {
    padding: 40px 24px;
  }

  .error-actions {
    flex-direction: column;
    width: 100%;
  }

  .btn {
    width: 100%;
    justify-content: center;
  }

  .error-icon {
    font-size: 48px;
  }
}
</style>
