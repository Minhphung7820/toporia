<template>
  <div class="bg-white rounded-lg shadow p-6">
    <h3 class="text-lg font-bold mb-4">Categories</h3>

    <!-- Loading State -->
    <div v-if="loading" class="space-y-2">
      <div v-for="i in 5" :key="i" class="animate-pulse">
        <div class="h-4 bg-gray-200 rounded w-3/4"></div>
      </div>
    </div>

    <!-- Categories List -->
    <ul v-else-if="categories.length > 0" class="space-y-2">
      <li v-for="category in categories" :key="category.id">
        <router-link
          :to="{ name: 'blog-category', params: { slug: category.slug } }"
          class="flex items-center justify-between text-gray-700 hover:text-blue-600 py-1"
        >
          <span>{{ category.name }}</span>
          <span v-if="category.post_count" class="text-sm text-gray-400">
            ({{ category.post_count }})
          </span>
        </router-link>

        <!-- Nested Categories -->
        <ul v-if="category.children && category.children.length > 0" class="ml-4 mt-1 space-y-1">
          <li v-for="child in category.children" :key="child.id">
            <router-link
              :to="{ name: 'blog-category', params: { slug: child.slug } }"
              class="flex items-center justify-between text-gray-600 hover:text-blue-600 py-1 text-sm"
            >
              <span>— {{ child.name }}</span>
              <span v-if="child.post_count" class="text-gray-400">
                ({{ child.post_count }})
              </span>
            </router-link>
          </li>
        </ul>
      </li>
    </ul>

    <!-- Empty State -->
    <p v-else class="text-gray-500 text-sm">No categories found</p>
  </div>
</template>

<script setup>
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
