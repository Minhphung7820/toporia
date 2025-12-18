<template>
  <div class="blog-category">
    <!-- Header -->
    <header class="bg-gradient-to-r from-green-600 to-teal-600 text-white py-12">
      <div class="container mx-auto px-4">
        <nav class="mb-4 text-sm opacity-75">
          <router-link to="/blog" class="hover:opacity-100">Blog</router-link>
          <span class="mx-2">/</span>
          <span>Categories</span>
        </nav>
        <h1 class="text-3xl md:text-4xl font-bold">
          <span v-if="loading.posts" class="animate-pulse bg-white/20 rounded h-10 w-64 inline-block"></span>
          <span v-else-if="category">{{ category.name }}</span>
          <span v-else>Category</span>
        </h1>
        <p v-if="category && category.description" class="mt-2 opacity-90">
          {{ category.description }}
        </p>
      </div>
    </header>

    <!-- Content -->
    <div class="container mx-auto px-4 py-8">
      <!-- Loading State -->
      <div v-if="loading.posts" class="space-y-6">
        <PostCardSkeleton v-for="i in 3" :key="i" />
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="text-center py-12">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">{{ error }}</h2>
        <router-link to="/blog" class="text-blue-600 hover:underline">
          ← Back to Blog
        </router-link>
      </div>

      <!-- Posts List -->
      <div v-else-if="posts.length > 0" class="space-y-6">
        <PostCard
          v-for="post in posts"
          :key="post.id"
          :post="post"
        />

        <!-- Pagination -->
        <Pagination
          v-if="pagination.totalPages > 1"
          :current-page="pagination.page"
          :total-pages="pagination.totalPages"
          @page-change="handlePageChange"
          class="mt-8"
        />
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-12 text-gray-500">
        <p class="text-xl">No posts in this category</p>
        <router-link to="/blog" class="text-blue-600 hover:underline mt-4 inline-block">
          ← Back to Blog
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useBlogStore } from '../../stores/blog';
import PostCard from '../../components/blog/PostCard.vue';
import PostCardSkeleton from '../../components/blog/PostCardSkeleton.vue';
import Pagination from '../../components/shared/Pagination.vue';

const route = useRoute();
const blogStore = useBlogStore();

// Computed
const posts = computed(() => blogStore.posts);
const category = computed(() => blogStore.currentCategory);
const pagination = computed(() => blogStore.pagination);
const loading = computed(() => blogStore.loading);
const error = computed(() => blogStore.error);

// Methods
const handlePageChange = (page) => {
  blogStore.fetchPostsByCategory(route.params.slug, page);
  window.scrollTo({ top: 0, behavior: 'smooth' });
};

const fetchData = (slug) => {
  blogStore.fetchPostsByCategory(slug);
};

// Watch route changes
watch(() => route.params.slug, (newSlug) => {
  if (newSlug) {
    fetchData(newSlug);
  }
});

// Lifecycle
onMounted(() => {
  if (route.params.slug) {
    fetchData(route.params.slug);
  }
});
</script>
