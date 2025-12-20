<template>
  <div class="blog-page">
    <!-- Hero Section -->
    <section class="blog-hero">
      <div class="container">
        <h1 class="hero-title">Blog</h1>
        <p class="hero-subtitle">Discover insights, tutorials, and updates from our team</p>
      </div>
    </section>

    <!-- Main Content -->
    <section class="blog-content">
      <div class="container">
        <div class="blog-layout">
          <!-- Posts Column -->
          <main class="posts-column">
            <!-- Featured Posts -->
            <section v-if="featuredPosts.length > 0" class="featured-section">
              <h2 class="section-title">
                <span class="title-icon">&#9733;</span>
                Featured Posts
              </h2>
              <div class="featured-grid">
                <PostCard
                  v-for="post in featuredPosts"
                  :key="post.id"
                  :post="post"
                  featured
                />
              </div>
            </section>

            <!-- Latest Posts -->
            <section class="posts-section">
              <h2 class="section-title">Latest Posts</h2>

              <!-- Loading State -->
              <div v-if="loading.posts" class="posts-list">
                <PostCardSkeleton v-for="i in 3" :key="i" />
              </div>

              <!-- Posts List -->
              <div v-else-if="posts.length > 0" class="posts-list">
                <PostCard
                  v-for="post in posts"
                  :key="post.id"
                  :post="post"
                />
              </div>

              <!-- Empty State -->
              <div v-else class="empty-state">
                <div class="empty-icon">&#128196;</div>
                <h3>No posts found</h3>
                <p>Check back later for new content!</p>
              </div>

              <!-- Cursor Pagination -->
              <div v-if="pagination.prevCursor || pagination.hasMore" class="cursor-pagination">
                <button
                  @click="handlePrevPage"
                  :disabled="!pagination.prevCursor || loading.posts"
                  class="page-btn"
                >
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                  </svg>
                  Previous
                </button>
                <button
                  @click="handleNextPage"
                  :disabled="!pagination.hasMore || loading.posts"
                  class="page-btn"
                >
                  Next
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                  </svg>
                </button>
              </div>
            </section>
          </main>

          <!-- Sidebar -->
          <aside class="sidebar">
            <!-- Search -->
            <SearchBox @search="handleSearch" />

            <!-- Categories -->
            <CategoryList :categories="categoriesTree" :loading="loading.categories" />

            <!-- Popular Posts -->
            <div class="sidebar-card">
              <h3 class="sidebar-title">Popular Posts</h3>
              <div v-if="loading.popular" class="skeleton-list">
                <div v-for="i in 5" :key="i" class="skeleton-item">
                  <div class="skeleton-line"></div>
                  <div class="skeleton-line short"></div>
                </div>
              </div>
              <ul v-else class="popular-list">
                <li v-for="post in popularPosts" :key="post.id">
                  <router-link
                    :to="{ name: 'blog-post', params: { slug: post.slug } }"
                    class="popular-link"
                  >
                    <span class="popular-title">{{ post.title }}</span>
                    <span class="popular-views">{{ formatViews(post.views) }} views</span>
                  </router-link>
                </li>
              </ul>
            </div>

            <!-- Tag Cloud -->
            <TagCloud :tags="tagCloud" :loading="loading.tags" />
          </aside>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { onMounted, computed, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useBlogStore } from '../../stores/blog';
import PostCard from '../../components/blog/PostCard.vue';
import PostCardSkeleton from '../../components/blog/PostCardSkeleton.vue';
import SearchBox from '../../components/blog/SearchBox.vue';
import CategoryList from '../../components/blog/CategoryList.vue';
import TagCloud from '../../components/blog/TagCloud.vue';

const router = useRouter();
const route = useRoute();
const blogStore = useBlogStore();

// Computed properties
const posts = computed(() => blogStore.posts);
const featuredPosts = computed(() => blogStore.featuredPosts);
const popularPosts = computed(() => blogStore.popularPosts);
const categoriesTree = computed(() => blogStore.categoriesTree);
const tagCloud = computed(() => blogStore.tagCloud);
const pagination = computed(() => blogStore.pagination);
const loading = computed(() => blogStore.loading);

// Get cursor from URL
const getCursorFromUrl = () => route.query.cursor || null;
const getDirectionFromUrl = () => route.query.direction || 'next';

// Methods
const updateUrl = (cursor, direction) => {
  const query = {};
  if (cursor) {
    query.cursor = cursor;
    query.direction = direction;
  }
  router.replace({ query });
};

const handleNextPage = async () => {
  if (pagination.value.nextCursor) {
    updateUrl(pagination.value.nextCursor, 'next');
    await blogStore.fetchPosts(pagination.value.nextCursor, 'next');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
};

const handlePrevPage = async () => {
  if (pagination.value.prevCursor) {
    updateUrl(pagination.value.prevCursor, 'prev');
    await blogStore.fetchPosts(pagination.value.prevCursor, 'prev');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
};

const handleSearch = (query) => {
  router.push({ name: 'blog-search', query: { q: query } });
};

const formatViews = (views) => {
  if (views >= 1000000) {
    return (views / 1000000).toFixed(1) + 'M';
  }
  if (views >= 1000) {
    return (views / 1000).toFixed(1) + 'K';
  }
  return views;
};

// Fetch data on mount - use cursor from URL if present
onMounted(async () => {
  const cursor = getCursorFromUrl();
  const direction = getDirectionFromUrl();

  await Promise.all([
    blogStore.fetchPosts(cursor, direction, 10),
    blogStore.fetchFeaturedPosts(4),
    blogStore.fetchPopularPosts(5),
    blogStore.fetchCategoriesTreeWithCounts(), // Hierarchical tree with post counts
    blogStore.fetchTagCloud(30),
  ]);
});

// Watch for URL changes (e.g., browser back/forward)
watch(() => route.query, async (newQuery) => {
  if (route.name === 'blog') {
    const cursor = newQuery.cursor || null;
    const direction = newQuery.direction || 'next';
    await blogStore.fetchPosts(cursor, direction, 10);
  }
});
</script>

<style scoped>
.blog-page {
  min-height: 100vh;
  background: #fafafa;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1.5rem;
}

/* Hero Section */
.blog-hero {
  background: #fff;
  border-bottom: 1px solid #e5e5e5;
  padding: 60px 0;
  text-align: center;
}

.hero-title {
  font-size: clamp(36px, 6vw, 56px);
  font-weight: 800;
  color: #1a1a1a;
  margin-bottom: 12px;
  letter-spacing: -0.02em;
}

.hero-subtitle {
  font-size: clamp(16px, 2vw, 18px);
  color: #666;
  max-width: 500px;
  margin: 0 auto;
}

/* Blog Content */
.blog-content {
  padding: 48px 0 80px;
}

.blog-layout {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 48px;
}

/* Posts Column */
.posts-column {
  min-width: 0;
}

.section-title {
  font-size: 22px;
  font-weight: 700;
  color: #1a1a1a;
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.title-icon {
  color: #f59e0b;
}

/* Featured Section */
.featured-section {
  margin-bottom: 48px;
}

.featured-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 24px;
}

/* Posts List */
.posts-section {
  margin-bottom: 32px;
}

.posts-list {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 64px 24px;
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 12px;
}

.empty-icon {
  font-size: 48px;
  margin-bottom: 16px;
}

.empty-state h3 {
  font-size: 20px;
  font-weight: 600;
  color: #1a1a1a;
  margin-bottom: 8px;
}

.empty-state p {
  color: #666;
  font-size: 14px;
}

/* Cursor Pagination */
.cursor-pagination {
  display: flex;
  justify-content: center;
  gap: 16px;
  margin-top: 32px;
}

.cursor-pagination .page-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #666;
  cursor: pointer;
  transition: all 0.2s;
}

.cursor-pagination .page-btn:hover:not(:disabled) {
  background: #1a1a1a;
  border-color: #1a1a1a;
  color: #fff;
}

.cursor-pagination .page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* Sidebar */
.sidebar {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.sidebar-card {
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 12px;
  padding: 24px;
}

.sidebar-title {
  font-size: 16px;
  font-weight: 600;
  color: #1a1a1a;
  margin-bottom: 16px;
}

/* Popular Posts */
.popular-list {
  list-style: none;
}

.popular-list li {
  border-bottom: 1px solid #f0f0f0;
}

.popular-list li:last-child {
  border-bottom: none;
}

.popular-link {
  display: block;
  padding: 12px 0;
  text-decoration: none;
  transition: all 0.2s;
}

.popular-link:hover .popular-title {
  color: #1a1a1a;
}

.popular-title {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #444;
  margin-bottom: 4px;
  line-height: 1.4;
  transition: color 0.2s;
}

.popular-views {
  font-size: 12px;
  color: #999;
}

/* Skeleton Loading */
.skeleton-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.skeleton-item {
  padding: 12px 0;
  border-bottom: 1px solid #f0f0f0;
}

.skeleton-item:last-child {
  border-bottom: none;
}

.skeleton-line {
  height: 14px;
  background: linear-gradient(90deg, #f0f0f0 25%, #e5e5e5 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 4px;
  margin-bottom: 6px;
}

.skeleton-line.short {
  width: 60%;
  height: 12px;
  margin-bottom: 0;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Responsive */
@media (max-width: 1024px) {
  .blog-layout {
    grid-template-columns: 1fr 280px;
    gap: 32px;
  }
}

@media (max-width: 900px) {
  .blog-layout {
    grid-template-columns: 1fr;
  }

  .sidebar {
    order: -1;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
  }
}

@media (max-width: 768px) {
  .container {
    padding: 0 1.5rem;
  }

  .blog-hero {
    padding: 40px 0;
  }

  .blog-content {
    padding: 32px 0 60px;
  }

  .featured-grid {
    grid-template-columns: 1fr;
  }

  .sidebar {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .blog-hero {
    padding: 32px 0;
  }

  .section-title {
    font-size: 18px;
  }

  .sidebar-card {
    padding: 20px;
  }
}
</style>
