<template>
  <div class="search-page">
    <!-- Hero Section -->
    <section class="search-hero">
      <div class="container">
        <nav class="breadcrumb">
          <router-link to="/blog">Blog</router-link>
          <span class="separator">/</span>
          <span>Search</span>
        </nav>
        <h1 class="hero-title">Search Results</h1>
        <p class="hero-subtitle">Find articles, tutorials, and insights</p>

        <!-- Search Form -->
        <form @submit.prevent="handleSearch" class="search-form">
          <div class="search-input-wrapper">
            <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8"/>
              <path d="m21 21-4.35-4.35"/>
            </svg>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search articles..."
              class="search-input"
              autofocus
            />
            <button
              v-if="searchQuery"
              type="button"
              @click="clearSearch"
              class="clear-btn"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <button type="submit" class="search-btn" :disabled="searchQuery.length < 2">
            Search
          </button>
        </form>
      </div>
    </section>

    <!-- Content -->
    <section class="search-content">
      <div class="container">
        <!-- Initial State -->
        <div v-if="!hasSearched" class="search-initial">
          <div class="initial-icon">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <circle cx="11" cy="11" r="8"/>
              <path d="m21 21-4.35-4.35"/>
            </svg>
          </div>
          <h2>Start your search</h2>
          <p>Enter at least 2 characters to search for articles</p>
        </div>

        <!-- Loading State -->
        <div v-else-if="loading.search" class="posts-list">
          <PostCardSkeleton v-for="i in 5" :key="i" />
        </div>

        <!-- Results -->
        <div v-else-if="searchResults.length > 0">
          <div class="results-header">
            <h2 class="results-title">
              Found <span class="highlight">{{ searchPagination.total }}</span> results for "{{ currentQuery }}"
            </h2>
          </div>

          <div class="posts-list">
            <PostCard
              v-for="post in searchResults"
              :key="post.id"
              :post="post"
            />
          </div>

          <!-- Cursor Pagination -->
          <div v-if="searchPagination.prevCursor || searchPagination.hasMore" class="cursor-pagination">
            <button
              @click="handlePrevPage"
              :disabled="!searchPagination.prevCursor || loading.search"
              class="page-btn"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
              Previous
            </button>
            <button
              @click="handleNextPage"
              :disabled="!searchPagination.hasMore || loading.search"
              class="page-btn"
            >
              Next
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>
        </div>

        <!-- No Results -->
        <div v-else-if="hasSearched" class="search-empty">
          <div class="empty-icon">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <circle cx="12" cy="12" r="10"/>
              <path d="M8 15s1.5-2 4-2 4 2 4 2"/>
              <line x1="9" y1="9" x2="9.01" y2="9"/>
              <line x1="15" y1="9" x2="15.01" y2="9"/>
            </svg>
          </div>
          <h2>No results found</h2>
          <p>No posts found for "<strong>{{ currentQuery }}</strong>"</p>
          <p class="empty-hint">Try different keywords or browse our categories</p>
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
const searchPagination = computed(() => blogStore.searchPagination);
const loading = computed(() => blogStore.loading);
const currentQuery = computed(() => blogStore.searchQuery);

// Get params from URL
const getQueryFromUrl = () => route.query.q || '';
const getCursorFromUrl = () => route.query.cursor || null;
const getDirectionFromUrl = () => route.query.direction || 'next';

// Update URL with search params
const updateUrl = (query, cursor = null, direction = 'next') => {
  const urlQuery = { q: query };
  if (cursor) {
    urlQuery.cursor = cursor;
    urlQuery.direction = direction;
  }
  router.replace({ query: urlQuery });
};

// Methods
const handleSearch = () => {
  if (searchQuery.value.trim().length < 2) {
    return;
  }

  hasSearched.value = true;
  updateUrl(searchQuery.value);
  blogStore.searchPosts(searchQuery.value, 10, null, 'next');
};

const clearSearch = () => {
  searchQuery.value = '';
  hasSearched.value = false;
  blogStore.clearSearch();
  router.replace({ query: {} });
};

const handleNextPage = async () => {
  if (searchPagination.value.nextCursor) {
    updateUrl(currentQuery.value, searchPagination.value.nextCursor, 'next');
    await blogStore.searchPosts(currentQuery.value, 10, searchPagination.value.nextCursor, 'next');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
};

const handlePrevPage = async () => {
  if (searchPagination.value.prevCursor) {
    updateUrl(currentQuery.value, searchPagination.value.prevCursor, 'prev');
    await blogStore.searchPosts(currentQuery.value, 10, searchPagination.value.prevCursor, 'prev');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
};

const performSearch = (query, cursor = null, direction = 'next') => {
  if (query && query.trim().length >= 2) {
    searchQuery.value = query;
    hasSearched.value = true;
    blogStore.searchPosts(query, 10, cursor, direction);
  }
};

// Watch query param changes (for browser back/forward)
watch(() => route.query, (newQuery) => {
  if (route.name === 'blog-search') {
    const query = newQuery.q;
    const cursor = newQuery.cursor || null;
    const direction = newQuery.direction || 'next';

    if (query && query !== currentQuery.value) {
      performSearch(query, cursor, direction);
    } else if (query && cursor) {
      blogStore.searchPosts(query, 10, cursor, direction);
    }
  }
});

// Lifecycle
onMounted(() => {
  const query = getQueryFromUrl();
  const cursor = getCursorFromUrl();
  const direction = getDirectionFromUrl();

  if (query) {
    performSearch(query, cursor, direction);
  }
});
</script>

<style scoped>
.search-page {
  min-height: 100vh;
  background: #fafafa;
}

.container {
  max-width: 900px;
  margin: 0 auto;
  padding: 0 2rem;
}

/* Hero Section */
.search-hero {
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

.hero-subtitle {
  font-size: 16px;
  color: #666;
  margin: 0 0 32px;
}

/* Search Form */
.search-form {
  display: flex;
  gap: 12px;
  max-width: 600px;
}

.search-input-wrapper {
  position: relative;
  flex: 1;
}

.search-icon {
  position: absolute;
  left: 16px;
  top: 50%;
  transform: translateY(-50%);
  color: #999;
  pointer-events: none;
}

.search-input {
  width: 100%;
  padding: 14px 44px 14px 48px;
  font-size: 16px;
  border: 2px solid #e5e5e5;
  border-radius: 12px;
  background: #fff;
  transition: all 0.2s;
}

.search-input:focus {
  outline: none;
  border-color: #1a1a1a;
}

.search-input::placeholder {
  color: #999;
}

.clear-btn {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f0f0f0;
  border: none;
  border-radius: 50%;
  color: #666;
  cursor: pointer;
  transition: all 0.2s;
}

.clear-btn:hover {
  background: #e5e5e5;
  color: #1a1a1a;
}

.search-btn {
  padding: 14px 28px;
  font-size: 15px;
  font-weight: 600;
  color: #fff;
  background: #1a1a1a;
  border: none;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s;
}

.search-btn:hover:not(:disabled) {
  background: #333;
}

.search-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Content */
.search-content {
  padding: 48px 0 80px;
}

/* Initial State */
.search-initial {
  text-align: center;
  padding: 80px 20px;
}

.initial-icon {
  margin-bottom: 24px;
}

.initial-icon svg {
  color: #ccc;
}

.search-initial h2 {
  font-size: 24px;
  font-weight: 700;
  color: #1a1a1a;
  margin: 0 0 12px;
}

.search-initial p {
  font-size: 15px;
  color: #666;
  margin: 0;
}

/* Results Header */
.results-header {
  margin-bottom: 32px;
}

.results-title {
  font-size: 18px;
  font-weight: 600;
  color: #666;
  margin: 0;
}

.results-title .highlight {
  color: #1a1a1a;
  font-weight: 700;
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

/* Empty State */
.search-empty {
  text-align: center;
  padding: 80px 20px;
}

.empty-icon {
  margin-bottom: 24px;
}

.empty-icon svg {
  color: #ccc;
}

.search-empty h2 {
  font-size: 24px;
  font-weight: 700;
  color: #1a1a1a;
  margin: 0 0 12px;
}

.search-empty p {
  font-size: 15px;
  color: #666;
  margin: 0 0 8px;
}

.empty-hint {
  color: #999;
  margin-bottom: 24px !important;
}

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

  .search-hero {
    padding: 32px 0 40px;
  }

  .hero-title {
    font-size: 28px;
  }

  .hero-subtitle {
    margin-bottom: 24px;
  }

  .search-form {
    flex-direction: column;
  }

  .search-btn {
    width: 100%;
  }

  .search-content {
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
  .search-hero {
    padding: 24px 0 32px;
  }

  .hero-title {
    font-size: 24px;
  }

  .search-input {
    padding: 12px 40px 12px 44px;
    font-size: 15px;
  }

  .search-btn {
    padding: 12px 20px;
    font-size: 14px;
  }

  .results-title {
    font-size: 16px;
  }
}
</style>
