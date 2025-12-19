<template>
  <router-link :to="`/blog/category/${category.slug}`" class="category-card">
    <div class="card-icon">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
      </svg>
    </div>

    <div class="card-content">
      <h3 class="card-title">{{ category.name }}</h3>
      <p v-if="category.description" class="card-description">{{ truncatedDescription }}</p>
      <span class="card-count">{{ category.total_count || 0 }} {{ (category.total_count || 0) === 1 ? 'article' : 'articles' }}</span>
    </div>

    <!-- Children preview -->
    <div v-if="category.children && category.children.length > 0" class="card-children">
      <span
        v-for="child in category.children.slice(0, 3)"
        :key="child.id"
        class="child-tag"
      >
        {{ child.name }}
      </span>
      <span v-if="category.children.length > 3" class="child-more">
        +{{ category.children.length - 3 }} more
      </span>
    </div>

    <div class="card-arrow">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M9 5l7 7-7 7"/>
      </svg>
    </div>
  </router-link>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  category: {
    type: Object,
    required: true,
  },
});

const truncatedDescription = computed(() => {
  if (!props.category.description) return '';
  const maxLength = 80;
  if (props.category.description.length <= maxLength) {
    return props.category.description;
  }
  return props.category.description.substring(0, maxLength) + '...';
});
</script>

<style scoped>
.category-card {
  display: block;
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 12px;
  padding: 24px;
  text-decoration: none;
  transition: all 0.2s;
  position: relative;
}

.category-card:hover {
  border-color: #1a1a1a;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.card-icon {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f5f5f5;
  border-radius: 12px;
  margin-bottom: 16px;
  color: #666;
  transition: all 0.2s;
}

.category-card:hover .card-icon {
  background: #1a1a1a;
  color: #fff;
}

.card-content {
  margin-bottom: 12px;
}

.card-title {
  font-size: 18px;
  font-weight: 600;
  color: #1a1a1a;
  margin: 0 0 8px;
}

.card-description {
  font-size: 14px;
  color: #666;
  line-height: 1.5;
  margin: 0 0 8px;
}

.card-count {
  font-size: 13px;
  color: #999;
}

.card-children {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid #f0f0f0;
}

.child-tag {
  font-size: 12px;
  color: #666;
  background: #f5f5f5;
  padding: 4px 10px;
  border-radius: 12px;
}

.child-more {
  font-size: 12px;
  color: #999;
  padding: 4px 0;
}

.card-arrow {
  position: absolute;
  top: 24px;
  right: 24px;
  color: #ccc;
  transition: all 0.2s;
}

.category-card:hover .card-arrow {
  color: #1a1a1a;
  transform: translateX(4px);
}
</style>
