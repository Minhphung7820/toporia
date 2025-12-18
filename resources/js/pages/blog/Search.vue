<template>
  <div class="blog-search">
    <!-- Header -->
    <header class="bg-gradient-to-r from-gray-700 to-gray-900 text-white py-12">
      <div class="container mx-auto px-4">
        <nav class="mb-4 text-sm opacity-75">
          <router-link to="/blog" class="hover:opacity-100">Blog</router-link>
          <span class="mx-2">/</span>
          <span>Search</span>
        </nav>
        <h1 class="text-3xl md:text-4xl font-bold mb-6">Search Results</h1>

        <!-- Search Form -->
        <form @submit.prevent="handleSearch" class="max-w-xl">
          <div class="flex gap-2">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search posts..."
              class="flex-1 px-4 py-3 rounded-lg text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <button
              type="submit"
              class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
            >
              Search
            </button>
          </div>
        </form>
      </div>
    </header>

    <!-- Content -->
    <div class="container mx-auto px-4 py-8">
      <!-- Initial State -->
      <div v-if="!hasSearched" class="text-center py-12 text-gray-500">
        <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <p class="text-xl">Enter a search term to find posts</p>
      </div>

      <!-- Loading State -->
      <div v-else-if="loading.search" class="space-y-6">
        <PostCardSkeleton v-for="i in 3" :key="i" />
      </div>

      <!-- Results -->
      <div v-else-if="searchResults.length > 0">
        <p class="text-gray-600 mb-6">
          Found {{ searchResults.length }} results for "{{ currentQuery }}"
        </p>
        <div class="space-y-6">
          <PostCard
            v-for="post in searchResults"
            :key="post.id"
            :post="post"
          />
        </div>
      </div>

      <!-- No Results -->
      <div v-else-if="hasSearched" class="text-center py-12 text-gray-500">
        <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-xl">No posts found for "{{ currentQuery }}"</p>
        <p class="mt-2">Try different keywords or browse our categories</p>
        <router-link to="/blog" class="text-blue-600 hover:underline mt-4 inline-block">
          ← Back to Blog
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useBlogStore } from '../../stores/blog';
import PostCard from '../../components/blog/PostCard.vue';
import PostCardSkeleton from '../../components/blog/PostCardSkeleton.vue';

const route = useRoute();
const router = useRouter();
const blogStore = useBlogStore();

// Local state
const searchQuery = ref('');
const hasSearched = ref(false);

// Computed
const searchResults = computed(() => blogStore.searchResults);
const loading = computed(() => blogStore.loading);
const currentQuery = computed(() => blogStore.searchQuery);

// Methods
const handleSearch = () => {
  if (searchQuery.value.trim().length < 2) {
    return;
  }

  hasSearched.value = true;
  router.push({ query: { q: searchQuery.value } });
  blogStore.searchPosts(searchQuery.value);
};

const performSearch = (query) => {
  if (query && query.trim().length >= 2) {
    searchQuery.value = query;
    hasSearched.value = true;
    blogStore.searchPosts(query);
  }
};

// Watch query param changes
watch(() => route.query.q, (newQuery) => {
  if (newQuery) {
    performSearch(newQuery);
  }
});

// Lifecycle
onMounted(() => {
  if (route.query.q) {
    performSearch(route.query.q);
  }
});
</script>
