<template>
  <div class="post-card-skeleton">
    <div class="skeleton-image"></div>
    <div class="skeleton-content">
      <div class="skeleton-category"></div>
      <div class="skeleton-title"></div>
      <div class="skeleton-excerpt">
        <div class="skeleton-line"></div>
        <div class="skeleton-line"></div>
        <div class="skeleton-line short"></div>
      </div>
      <div class="skeleton-meta">
        <div class="skeleton-meta-item"></div>
        <div class="skeleton-meta-item"></div>
        <div class="skeleton-meta-item"></div>
      </div>
      <div class="skeleton-tags">
        <div class="skeleton-tag"></div>
        <div class="skeleton-tag"></div>
      </div>
      <div class="skeleton-link"></div>
    </div>
  </div>
</template>

<style scoped>
.post-card-skeleton {
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 12px;
  overflow: hidden;
}

.skeleton-image {
  height: 200px;
  background: linear-gradient(90deg, #e5e5e5 25%, #f0f0f0 50%, #e5e5e5 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}

.skeleton-content {
  padding: 24px;
}

.skeleton-category {
  height: 12px;
  width: 80px;
  background: linear-gradient(90deg, #e5e5e5 25%, #f0f0f0 50%, #e5e5e5 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 4px;
  margin-bottom: 12px;
}

.skeleton-title {
  height: 24px;
  width: 75%;
  background: linear-gradient(90deg, #e5e5e5 25%, #f0f0f0 50%, #e5e5e5 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 4px;
  margin-bottom: 12px;
}

.skeleton-excerpt {
  margin-bottom: 16px;
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
  width: 60%;
}

.skeleton-meta {
  display: flex;
  gap: 16px;
  margin-bottom: 16px;
}

.skeleton-meta-item {
  height: 14px;
  width: 70px;
  background: linear-gradient(90deg, #e5e5e5 25%, #f0f0f0 50%, #e5e5e5 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 4px;
}

.skeleton-tags {
  display: flex;
  gap: 6px;
  margin-bottom: 16px;
}

.skeleton-tag {
  height: 26px;
  width: 60px;
  background: linear-gradient(90deg, #e5e5e5 25%, #f0f0f0 50%, #e5e5e5 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 4px;
}

.skeleton-link {
  height: 16px;
  width: 100px;
  background: linear-gradient(90deg, #e5e5e5 25%, #f0f0f0 50%, #e5e5e5 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 4px;
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
@media (max-width: 768px) {
  .skeleton-content {
    padding: 20px;
  }

  .skeleton-title {
    height: 20px;
  }
}
</style>
