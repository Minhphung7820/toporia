<template>
  <div class="bg-white rounded-lg shadow p-6">
    <h3 class="text-lg font-bold mb-4">Tags</h3>

    <!-- Loading State -->
    <div v-if="loading" class="flex flex-wrap gap-2">
      <div v-for="i in 10" :key="i" class="animate-pulse">
        <div class="h-6 bg-gray-200 rounded-full w-16"></div>
      </div>
    </div>

    <!-- Tags Cloud -->
    <div v-else-if="tags.length > 0" class="flex flex-wrap gap-2">
      <router-link
        v-for="item in tags"
        :key="item.tag?.id || item.id"
        :to="{ name: 'blog-tag', params: { slug: item.tag?.slug || item.slug } }"
        :class="[
          'px-3 py-1 rounded-full text-sm transition',
          getTagClass(item.weight || 1)
        ]"
      >
        #{{ item.tag?.name || item.name }}
      </router-link>
    </div>

    <!-- Empty State -->
    <p v-else class="text-gray-500 text-sm">No tags found</p>
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

// Get tag class based on weight (1-5)
const getTagClass = (weight) => {
  const classes = {
    1: 'bg-gray-100 text-gray-600 hover:bg-gray-200',
    2: 'bg-gray-200 text-gray-700 hover:bg-gray-300',
    3: 'bg-blue-100 text-blue-700 hover:bg-blue-200',
    4: 'bg-blue-200 text-blue-800 hover:bg-blue-300',
    5: 'bg-blue-500 text-white hover:bg-blue-600',
  };

  const roundedWeight = Math.min(5, Math.max(1, Math.round(weight)));
  return classes[roundedWeight];
};
</script>
