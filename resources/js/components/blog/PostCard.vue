<template>
  <article
    :class="[
      'bg-white rounded-lg shadow-md overflow-hidden transition hover:shadow-lg',
      featured ? 'border-2 border-yellow-400' : ''
    ]"
  >
    <div :class="featured ? 'md:flex' : ''">
      <!-- Featured Image -->
      <router-link
        v-if="post.featured_image"
        :to="postUrl"
        :class="featured ? 'md:w-1/2 md:flex-shrink-0' : ''"
      >
        <img
          :src="post.featured_image"
          :alt="post.title"
          :class="[
            'w-full object-cover',
            featured ? 'h-48 md:h-full' : 'h-48'
          ]"
        />
      </router-link>

      <!-- Content -->
      <div :class="['p-6', featured && post.featured_image ? 'md:w-1/2' : '']">
        <!-- Category Badge -->
        <div v-if="post.category" class="mb-2">
          <router-link
            :to="{ name: 'blog-category', params: { slug: post.category.slug || post.category_slug } }"
            class="text-xs font-semibold uppercase text-blue-600 hover:text-blue-800"
          >
            {{ post.category.name || post.category_name }}
          </router-link>
        </div>

        <!-- Title -->
        <h2 :class="['font-bold text-gray-800 mb-2', featured ? 'text-2xl' : 'text-xl']">
          <router-link :to="postUrl" class="hover:text-blue-600 transition">
            {{ post.title }}
          </router-link>
        </h2>

        <!-- Excerpt -->
        <p class="text-gray-600 mb-4 line-clamp-3">
          {{ post.excerpt || truncateContent(post.content, 150) }}
        </p>

        <!-- Meta -->
        <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500">
          <span class="flex items-center">
            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
            </svg>
            {{ formatDate(post.published_at) }}
          </span>
          <span class="flex items-center">
            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
            </svg>
            {{ post.reading_time || 1 }} min
          </span>
          <span class="flex items-center">
            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
              <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
              <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
            </svg>
            {{ post.views || 0 }}
          </span>
        </div>

        <!-- Tags -->
        <div v-if="post.tags && post.tags.length > 0" class="mt-4 flex flex-wrap gap-2">
          <router-link
            v-for="tag in post.tags.slice(0, 3)"
            :key="tag.id || tag.slug"
            :to="{ name: 'blog-tag', params: { slug: tag.slug } }"
            class="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded hover:bg-blue-100 hover:text-blue-700"
          >
            #{{ tag.name }}
          </router-link>
        </div>

        <!-- Read More Link -->
        <router-link
          :to="postUrl"
          class="inline-flex items-center mt-4 text-blue-600 hover:text-blue-800 font-medium"
        >
          Read more
          <svg class="w-4 h-4 ml-1" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
          </svg>
        </router-link>
      </div>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  post: {
    type: Object,
    required: true,
  },
  featured: {
    type: Boolean,
    default: false,
  },
});

// Computed
const postUrl = computed(() => ({
  name: 'blog-post',
  params: { slug: props.post.slug },
}));

// Methods
const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const truncateContent = (content, maxLength) => {
  if (!content) return '';
  // Strip HTML tags
  const text = content.replace(/<[^>]*>/g, '');
  if (text.length <= maxLength) return text;
  return text.substring(0, maxLength) + '...';
};
</script>

<style scoped>
.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
