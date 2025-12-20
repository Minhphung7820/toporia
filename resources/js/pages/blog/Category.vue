<template>
  <div class="category-page">
    <!-- Hero Section -->
    <section class="category-hero">
      <div class="container">
        <nav class="breadcrumb">
          <router-link to="/blog">Blog</router-link>
          <span class="separator">/</span>
          <span>Categories</span>
        </nav>

        <!-- Loading State -->
        <div v-if="loading.posts && !category" class="hero-loading">
          <div class="skeleton-title"></div>
          <div class="skeleton-desc"></div>
        </div>

        <!-- Category Info -->
        <template v-else-if="category">
          <h1 class="hero-title">{{ category.name }}</h1>
          <p v-if="category.description" class="hero-description">{{ category.description }}</p>
        </template>

        <!-- Error -->
        <template v-else-if="error">
          <h1 class="hero-title">Category Not Found</h1>
        </template>
      </div>
    </section>

    <!-- Content -->
    <section class="category-content">
      <div class="container">
        <!-- Loading State -->
        <div v-if="loading.posts" class="posts-list">
          <PostCardSkeleton v-for="i in 5" :key="i" />
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="error-state">
          <div class="error-icon">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <circle cx="12" cy="12" r="10"/>
              <path d="M8 15s1.5-2 4-2 4 2 4 2"/>
              <line x1="9" y1="9" x2="9.01" y2="9"/>
              <line x1="15" y1="9" x2="15.01" y2="9"/>
            </svg>
          </div>
          <h2>{{ error }}</h2>
          <p>The category you're looking for doesn't exist or has been removed.</p>
          <router-link to="/blog" class="back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Back to Blog
          </router-link>
        </div>

        <!-- Posts List -->
        <template v-else-if="posts.length > 0">
          <div class="results-header">
            <p class="results-count">
              Showing articles in <strong>{{ category?.name }}</strong>
            </p>
          </div>

          <div class="posts-list">
            <PostCard
              v-for="post in posts"
              :key="post.id"
              :post="post"
            />
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
        </template>

        <!-- Empty State -->
        <div v-else class="empty-state">
          <div class="empty-icon">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
              <rect x="9" y="3" width="6" height="4" rx="1"/>
              <line x1="9" y1="12" x2="15" y2="12"/>
              <line x1="9" y1="16" x2="13" y2="16"/>
            </svg>
          </div>
          <h2>No posts in this category</h2>
          <p>Check back later for new content!</p>
          <router-link to="/blog" class="back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Back to Blog
          </router-link>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useBlogStore } from '../../stores/blog';
import { useSettingsStore } from '../../stores/settings';
import PostCard from '../../components/blog/PostCard.vue';
import PostCardSkeleton from '../../components/blog/PostCardSkeleton.vue';

const route = useRoute();
const router = useRouter();
const blogStore = useBlogStore();
const settingsStore = useSettingsStore();

// Computed
const posts = computed(() => blogStore.posts);
const category = computed(() => blogStore.currentCategory);
const pagination = computed(() => blogStore.pagination);
const loading = computed(() => blogStore.loading);
const error = computed(() => blogStore.error);

// Get cursor from URL
const getCursorFromUrl = () => route.query.cursor || null;
const getDirectionFromUrl = () => route.query.direction || 'next';

// Update URL with cursor params
const updateUrl = (cursor = null, direction = 'next') => {
  const query = {};
  if (cursor) {
    query.cursor = cursor;
    query.direction = direction;
  }
  router.replace({ query });
};

// Methods
const handleNextPage = async () => {
  if (pagination.value.nextCursor) {
    updateUrl(pagination.value.nextCursor, 'next');
    await blogStore.fetchPostsByCategory(
      route.params.slug,
      pagination.value.nextCursor,
      'next',
      settingsStore.postsPerPage
    );
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
};

const handlePrevPage = async () => {
  if (pagination.value.prevCursor) {
    updateUrl(pagination.value.prevCursor, 'prev');
    await blogStore.fetchPostsByCategory(
      route.params.slug,
      pagination.value.prevCursor,
      'prev',
      settingsStore.postsPerPage
    );
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
};

const fetchData = async (slug, cursor = null, direction = 'next') => {
  await blogStore.fetchPostsByCategory(slug, cursor, direction, settingsStore.postsPerPage);
};

// Watch route changes (including slug and query params)
watch(
  () => [route.params.slug, route.query],
  async ([newSlug, newQuery], [oldSlug]) => {
    if (newSlug) {
      // If slug changed, reset cursor
      if (newSlug !== oldSlug) {
        await fetchData(newSlug, null, 'next');
      } else {
        // Same slug, check for cursor changes (browser back/forward)
        const cursor = newQuery?.cursor || null;
        const direction = newQuery?.direction || 'next';
        await fetchData(newSlug, cursor, direction);
      }
    }
  }
);

// Lifecycle
onMounted(async () => {
  if (route.params.slug) {
    const cursor = getCursorFromUrl();
    const direction = getDirectionFromUrl();
    await fetchData(route.params.slug, cursor, direction);
  }
});
</script>

<style scoped>
.category-page {
  min-height: 100vh;
  background: #fafafa;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1.5rem;
}

/* Hero Section */
.category-hero {
  background: #fff;
  border-bottom: 1px solid #e5e5e5;
  padding: 48px 0 56px;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #666;
  margin-bottom: 16px;
}

.breadcrumb a {
  color: #666;
  text-decoration: none;
  transition: color 0.2s;
}

.breadcrumb a:hover {
  color: #1a1a1a;
}

.breadcrumb .separator {
  color: #ccc;
}

.hero-title {
  font-size: clamp(32px, 5vw, 48px);
  font-weight: 800;
  color: #1a1a1a;
  margin: 0 0 8px;
  letter-spacing: -0.02em;
}

.hero-description {
  font-size: 16px;
  color: #666;
  margin: 0;
  line-height: 1.6;
  max-width: 600px;
}

/* Hero Loading */
.hero-loading {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.skeleton-title {
  width: 280px;
  height: 48px;
  background: linear-gradient(90deg, #f0f0f0 25%, #e5e5e5 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 8px;
}

.skeleton-desc {
  width: 400px;
  height: 20px;
  background: linear-gradient(90deg, #f0f0f0 25%, #e5e5e5 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 4px;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Content */
.category-content {
  padding: 48px 0 80px;
}

/* Results Header */
.results-header {
  margin-bottom: 24px;
}

.results-count {
  font-size: 15px;
  color: #666;
  margin: 0;
}

.results-count strong {
  color: #1a1a1a;
}

/* Posts List */
.posts-list {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Cursor Pagination */
.cursor-pagination {
  display: flex;
  justify-content: center;
  gap: 16px;
  margin-top: 48px;
}

.page-btn {
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

.page-btn:hover:not(:disabled) {
  background: #1a1a1a;
  border-color: #1a1a1a;
  color: #fff;
}

.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* Error State */
.error-state {
  text-align: center;
  padding: 80px 20px;
}

.error-icon {
  margin-bottom: 24px;
}

.error-icon svg {
  color: #ccc;
}

.error-state h2 {
  font-size: 24px;
  font-weight: 700;
  color: #1a1a1a;
  margin: 0 0 12px;
}

.error-state p {
  font-size: 15px;
  color: #666;
  margin: 0 0 24px;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 80px 20px;
}

.empty-icon {
  margin-bottom: 24px;
}

.empty-icon svg {
  color: #ccc;
}

.empty-state h2 {
  font-size: 24px;
  font-weight: 700;
  color: #1a1a1a;
  margin: 0 0 12px;
}

.empty-state p {
  font-size: 15px;
  color: #666;
  margin: 0 0 24px;
}

/* Back Link */
.back-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #1a1a1a;
  text-decoration: none;
  transition: color 0.2s;
}

.back-link:hover {
  color: #666;
}

/* Responsive */
@media (max-width: 768px) {
  .container {
    padding: 0 1.5rem;
  }

  .category-hero {
    padding: 32px 0 40px;
  }

  .hero-title {
    font-size: 28px;
  }

  .category-content {
    padding: 32px 0 60px;
  }

  .cursor-pagination {
    flex-direction: column;
    align-items: stretch;
  }

  .page-btn {
    justify-content: center;
  }
}

@media (max-width: 480px) {
  .category-hero {
    padding: 24px 0 32px;
  }

  .hero-title {
    font-size: 24px;
  }

  .skeleton-title {
    width: 200px;
    height: 32px;
  }

  .skeleton-desc {
    width: 280px;
  }
}
</style>
