<template>
  <div class="comment-skeleton">
    <div class="skeleton-header">
      <div class="skeleton-avatar"></div>
      <div class="skeleton-author">
        <div class="skeleton-name"></div>
        <div class="skeleton-time"></div>
      </div>
    </div>
    <div class="skeleton-content">
      <div class="skeleton-line"></div>
      <div class="skeleton-line short"></div>
    </div>
    <div class="skeleton-actions">
      <div class="skeleton-btn"></div>
      <div class="skeleton-btn"></div>
    </div>
  </div>
</template>

<style scoped>
.comment-skeleton {
  background: #fafafa;
  border: 1px solid #e5e5e5;
  border-radius: 10px;
  padding: 20px;
}

.skeleton-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 14px;
}

.skeleton-avatar {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  background: linear-gradient(90deg, #e5e5e5 25%, #f0f0f0 50%, #e5e5e5 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}

.skeleton-author {
  flex: 1;
}

.skeleton-name {
  height: 16px;
  width: 120px;
  background: linear-gradient(90deg, #e5e5e5 25%, #f0f0f0 50%, #e5e5e5 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 4px;
  margin-bottom: 6px;
}

.skeleton-time {
  height: 12px;
  width: 80px;
  background: linear-gradient(90deg, #e5e5e5 25%, #f0f0f0 50%, #e5e5e5 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 4px;
}

.skeleton-content {
  margin-bottom: 14px;
}

.skeleton-line {
  height: 14px;
  background: linear-gradient(90deg, #e5e5e5 25%, #f0f0f0 50%, #e5e5e5 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 4px;
  margin-bottom: 8px;
}

.skeleton-line.short {
  width: 75%;
}

.skeleton-actions {
  display: flex;
  gap: 12px;
}

.skeleton-btn {
  height: 28px;
  width: 60px;
  background: linear-gradient(90deg, #e5e5e5 25%, #f0f0f0 50%, #e5e5e5 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 6px;
}

@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

/* Responsive */
@media (max-width: 640px) {
  .comment-skeleton {
    padding: 16px;
  }

  .skeleton-avatar {
    width: 36px;
    height: 36px;
  }

  .skeleton-name {
    width: 100px;
  }
}
</style>
