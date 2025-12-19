<template>
  <div class="category-list-card">
    <h3 class="card-title">Categories</h3>

    <!-- Loading State -->
    <div v-if="loading" class="skeleton-list">
      <div v-for="i in 5" :key="i" class="skeleton-item"></div>
    </div>

    <!-- Categories Tree -->
    <ul v-else-if="categories.length > 0" class="category-list">
      <CategoryItem
        v-for="category in categories"
        :key="category.id"
        :category="category"
        :depth="0"
      />
    </ul>

    <!-- View All Link -->
    <router-link
      v-if="!loading && categories.length > 0"
      to="/blog/categories"
      class="view-all-link"
    >
      View all categories
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M9 5l7 7-7 7" />
      </svg>
    </router-link>

    <!-- Empty State -->
    <p v-else-if="!loading && categories.length === 0" class="empty-text">No categories found</p>
  </div>
</template>

<script setup>
import CategoryItem from './CategoryItem.vue';

defineProps({
  categories: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
});
</script>

<style scoped>
.category-list-card {
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 12px;
  padding: 24px;
}

.card-title {
  font-size: 16px;
  font-weight: 600;
  color: #1a1a1a;
  margin-bottom: 16px;
}

.category-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.view-all-link {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid #f0f0f0;
  font-size: 13px;
  font-weight: 500;
  color: #666;
  text-decoration: none;
  transition: color 0.15s;
}

.view-all-link:hover {
  color: #1a1a1a;
}

.view-all-link svg {
  transition: transform 0.15s;
}

.view-all-link:hover svg {
  transform: translateX(2px);
}

.skeleton-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.skeleton-item {
  height: 20px;
  background: linear-gradient(90deg, #f0f0f0 25%, #e5e5e5 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 4px;
}

.skeleton-item:nth-child(2),
.skeleton-item:nth-child(4) {
  width: 80%;
}

.skeleton-item:nth-child(3),
.skeleton-item:nth-child(5) {
  width: 60%;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.empty-text {
  color: #999;
  font-size: 14px;
  text-align: center;
  padding: 16px 0;
}
</style>
