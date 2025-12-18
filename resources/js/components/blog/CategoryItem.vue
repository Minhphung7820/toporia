<template>
  <li class="category-item">
    <div class="category-row" :style="{ paddingLeft: depth * 16 + 'px' }">
      <!-- Expand/Collapse Toggle -->
      <button
        v-if="hasChildren"
        class="toggle-btn"
        @click="toggleExpanded"
        :aria-expanded="isExpanded"
      >
        <svg
          width="12"
          height="12"
          viewBox="0 0 12 12"
          fill="none"
          :class="{ rotated: isExpanded }"
        >
          <path d="M4 2L8 6L4 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <span v-else class="toggle-spacer"></span>

      <!-- Category Link -->
      <router-link
        :to="{ name: 'blog-category', params: { slug: category.slug } }"
        class="category-link"
        :class="{ 'has-children': hasChildren }"
      >
        <span class="category-name">{{ category.name }}</span>
        <span v-if="category.post_count !== undefined" class="category-count">
          {{ category.post_count }}
        </span>
      </router-link>
    </div>

    <!-- Children (Recursive) -->
    <ul v-if="hasChildren && isExpanded" class="children-list">
      <CategoryItem
        v-for="childCategory in category.children"
        :key="childCategory.id"
        :category="childCategory"
        :depth="depth + 1"
      />
    </ul>
  </li>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  category: {
    type: Object,
    required: true,
  },
  depth: {
    type: Number,
    default: 0,
  },
});

const isExpanded = ref(props.depth < 1); // Auto-expand first level

const hasChildren = computed(() => {
  return props.category.children && props.category.children.length > 0;
});

const toggleExpanded = () => {
  isExpanded.value = !isExpanded.value;
};
</script>

<style scoped>
.category-item {
  list-style: none;
}

.category-row {
  display: flex;
  align-items: center;
  gap: 4px;
}

.toggle-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border: none;
  background: transparent;
  color: #999;
  cursor: pointer;
  padding: 0;
  border-radius: 4px;
  transition: all 0.2s;
  flex-shrink: 0;
}

.toggle-btn:hover {
  background: #f5f5f5;
  color: #666;
}

.toggle-btn svg {
  transition: transform 0.2s ease;
}

.toggle-btn svg.rotated {
  transform: rotate(90deg);
}

.toggle-spacer {
  width: 20px;
  flex-shrink: 0;
}

.category-link {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex: 1;
  padding: 8px 10px;
  border-radius: 6px;
  text-decoration: none;
  transition: all 0.2s;
  min-width: 0;
}

.category-link:hover {
  background: #f5f5f5;
}

.category-link:hover .category-name {
  color: #1a1a1a;
}

.category-name {
  font-size: 14px;
  color: #444;
  transition: color 0.2s;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.category-link.has-children .category-name {
  font-weight: 500;
}

.category-count {
  font-size: 12px;
  color: #999;
  background: #f5f5f5;
  padding: 2px 8px;
  border-radius: 10px;
  flex-shrink: 0;
  margin-left: 8px;
}

.children-list {
  list-style: none;
  margin-top: 2px;
}
</style>
