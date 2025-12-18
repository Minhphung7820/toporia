<template>
  <div class="category-list-card">
    <h3 class="card-title">Categories</h3>

    <!-- Loading State -->
    <div v-if="loading" class="skeleton-list">
      <div v-for="i in 5" :key="i" class="skeleton-item"></div>
    </div>

    <!-- Categories Tree -->
    <ul v-else-if="categories.length > 0" class="category-tree">
      <CategoryItem
        v-for="category in categories"
        :key="category.id"
        :category="category"
        :depth="0"
      />
    </ul>

    <!-- Empty State -->
    <p v-else class="empty-text">No categories found</p>
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

.category-tree {
  list-style: none;
}

.skeleton-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.skeleton-item {
  height: 16px;
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
}
</style>
