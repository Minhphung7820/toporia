<template>
  <div class="tag-cloud">
    <h3 class="widget-title">Popular Tags</h3>

    <!-- Loading State -->
    <div v-if="loading" class="tags-skeleton">
      <div v-for="i in 8" :key="i" class="tag-skeleton"></div>
    </div>

    <!-- Tags Cloud -->
    <div v-else-if="tags.length > 0" class="tags-list">
      <router-link
        v-for="item in tags"
        :key="item.tag?.id || item.id"
        :to="{ name: 'blog-tag', params: { slug: item.tag?.slug || item.slug } }"
        :class="['tag-item', `weight-${getWeight(item.weight || 1)}`]"
      >
        <span class="tag-hash">#</span>{{ item.tag?.name || item.name }}
        <span v-if="item.count" class="tag-count">{{ item.count }}</span>
      </router-link>
    </div>

    <!-- Empty State -->
    <p v-else class="empty-text">No tags found</p>
  </div>
</template>

<script setup>
defineProps({
  tags: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

// Get weight class (1-5)
const getWeight = (weight) => {
  return Math.min(5, Math.max(1, Math.round(weight)));
};
</script>

<style scoped>
.tag-cloud {
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 12px;
  padding: 24px;
}

.widget-title {
  font-size: 16px;
  font-weight: 700;
  color: #1a1a1a;
  margin: 0 0 16px;
}

/* Tags List */
.tags-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.tag-item {
  display: inline-flex;
  align-items: center;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.2s;
}

.tag-hash {
  opacity: 0.6;
  margin-right: 1px;
}

.tag-count {
  margin-left: 6px;
  font-size: 11px;
  opacity: 0.7;
}

/* Weight variations */
.tag-item.weight-1 {
  background: #f5f5f5;
  color: #666;
}

.tag-item.weight-1:hover {
  background: #e5e5e5;
  color: #1a1a1a;
}

.tag-item.weight-2 {
  background: #e5e5e5;
  color: #444;
}

.tag-item.weight-2:hover {
  background: #d5d5d5;
  color: #1a1a1a;
}

.tag-item.weight-3 {
  background: #1a1a1a;
  color: #fff;
}

.tag-item.weight-3:hover {
  background: #333;
}

.tag-item.weight-4 {
  background: #1a1a1a;
  color: #fff;
  font-size: 14px;
}

.tag-item.weight-4:hover {
  background: #333;
}

.tag-item.weight-5 {
  background: #1a1a1a;
  color: #fff;
  font-size: 15px;
  font-weight: 600;
}

.tag-item.weight-5:hover {
  background: #333;
}

/* Empty State */
.empty-text {
  font-size: 14px;
  color: #999;
  margin: 0;
}

/* Loading Skeleton */
.tags-skeleton {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.tag-skeleton {
  height: 32px;
  border-radius: 6px;
  background: linear-gradient(90deg, #e5e5e5 25%, #f0f0f0 50%, #e5e5e5 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}

.tag-skeleton:nth-child(1) { width: 70px; }
.tag-skeleton:nth-child(2) { width: 90px; }
.tag-skeleton:nth-child(3) { width: 60px; }
.tag-skeleton:nth-child(4) { width: 80px; }
.tag-skeleton:nth-child(5) { width: 75px; }
.tag-skeleton:nth-child(6) { width: 65px; }
.tag-skeleton:nth-child(7) { width: 85px; }
.tag-skeleton:nth-child(8) { width: 55px; }

@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}
</style>
