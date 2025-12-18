<template>
  <div class="blog-index">
    <!-- Hero Section -->
    <section class="hero bg-gradient-to-r from-blue-600 to-purple-600 text-white py-16">
      <div class="container mx-auto px-4">
        <h1 class="text-4xl md:text-5xl font-bold mb-4">Blog</h1>
        <p class="text-xl opacity-90">Discover insights, tutorials, and updates</p>
      </div>
    </section>

    <div class="container mx-auto px-4 py-8">
      <div class="flex flex-col lg:flex-row gap-8">
        <!-- Main Content -->
        <main class="lg:w-2/3">
          <!-- Featured Posts -->
          <section v-if="featuredPosts.length > 0" class="mb-12">
            <h2 class="text-2xl font-bold mb-6 flex items-center">
              <span class="mr-2">⭐</span> Featured Posts
            </h2>
            <div class="grid md:grid-cols-2 gap-6">
              <PostCard
                v-for="post in featuredPosts"
                :key="post.id"
                :post="post"
                featured
              />
            </div>
          </section>

          <!-- All Posts -->
          <section>
            <h2 class="text-2xl font-bold mb-6">Latest Posts</h2>

            <!-- Loading State -->
            <div v-if="loading.posts" class="space-y-6">
              <PostCardSkeleton v-for="i in 3" :key="i" />
            </div>

            <!-- Posts List -->
            <div v-else-if="posts.length > 0" class="space-y-6">
              <PostCard
                v-for="post in posts"
                :key="post.id"
                :post="post"
              />
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-12 text-gray-500">
              <p class="text-xl">No posts found</p>
              <p class="mt-2">Check back later for new content!</p>
            </div>

            <!-- Pagination -->
            <Pagination
              v-if="pagination.totalPages > 1"
              :current-page="pagination.page"
              :total-pages="pagination.totalPages"
              @page-change="handlePageChange"
              class="mt-8"
            />
          </section>
        </main>

        <!-- Sidebar -->
        <aside class="lg:w-1/3 space-y-8">
          <!-- Search -->
          <SearchBox @search="handleSearch" />

          <!-- Categories -->
          <CategoryList :categories="categories" :loading="loading.categories" />

          <!-- Popular Posts -->
          <section class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold mb-4">Popular Posts</h3>
            <div v-if="loading.popular" class="space-y-3">
              <div v-for="i in 5" :key="i" class="animate-pulse">
                <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
                <div class="h-3 bg-gray-200 rounded w-1/2"></div>
              </div>
            </div>
            <ul v-else class="space-y-3">
              <li v-for="post in popularPosts" :key="post.id">
                <router-link
                  :to="{ name: 'blog-post', params: { slug: post.slug } }"
                  class="text-gray-700 hover:text-blue-600 block"
                >
                  <span class="font-medium">{{ post.title }}</span>
                  <span class="text-sm text-gray-500 block">{{ post.views }} views</span>
                </router-link>
              </li>
            </ul>
          </section>

          <!-- Tag Cloud -->
          <TagCloud :tags="tagCloud" :loading="loading.tags" />
        </aside>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useBlogStore } from '../../stores/blog';
import PostCard from '../../components/blog/PostCard.vue';
import PostCardSkeleton from '../../components/blog/PostCardSkeleton.vue';
import Pagination from '../../components/shared/Pagination.vue';
import SearchBox from '../../components/blog/SearchBox.vue';
import CategoryList from '../../components/blog/CategoryList.vue';
import TagCloud from '../../components/blog/TagCloud.vue';

const router = useRouter();
const blogStore = useBlogStore();

// Computed properties
const posts = computed(() => blogStore.posts);
const featuredPosts = computed(() => blogStore.featuredPosts);
const popularPosts = computed(() => blogStore.popularPosts);
const categories = computed(() => blogStore.categories);
const tagCloud = computed(() => blogStore.tagCloud);
const pagination = computed(() => blogStore.pagination);
const loading = computed(() => blogStore.loading);

// Methods
const handlePageChange = (page) => {
  blogStore.fetchPosts(page);
  window.scrollTo({ top: 0, behavior: 'smooth' });
};

const handleSearch = (query) => {
  router.push({ name: 'blog-search', query: { q: query } });
};

// Fetch data on mount
onMounted(async () => {
  // Fetch posts and sidebar data in parallel
  await Promise.all([
    blogStore.fetchPosts(1, 10),
    blogStore.fetchFeaturedPosts(4),
    blogStore.fetchPopularPosts(5),
    blogStore.fetchCategories(),
    blogStore.fetchTagCloud(30),
  ]);
});
</script>

<style scoped>
.hero {
  background-image: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
</style>
