<template>
  <div class="home-page">
    <!-- Hero Section -->
    <section class="hero-section">
      <div class="container">
        <div class="hero-content">
          <h1 class="hero-title">Welcome to Toporia</h1>
          <p class="hero-subtitle">
            Discover insightful articles, tutorials, and discussions from our community
          </p>

          <!-- Search Box -->
          <form @submit.prevent="handleSearch" class="hero-search">
            <div class="search-wrapper">
              <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/>
                <path d="m21 21-4.35-4.35"/>
              </svg>
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Search articles, tutorials, topics..."
                class="search-input"
              />
              <button type="submit" class="search-btn" :disabled="searchQuery.length < 2">
                Search
              </button>
            </div>
          </form>

          <!-- Quick Stats -->
          <div class="hero-stats">
            <div class="stat-item">
              <span class="stat-value">{{ animatedStats.posts }}</span>
              <span class="stat-label">Articles</span>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-item">
              <span class="stat-value">{{ animatedStats.categories }}</span>
              <span class="stat-label">Categories</span>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-item">
              <span class="stat-value">{{ animatedStats.views }}</span>
              <span class="stat-label">Total Views</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Featured Posts Section -->
    <section class="featured-section" v-if="featuredPosts.length > 0">
      <div class="container">
        <div class="section-header">
          <h2 class="section-title">Featured Articles</h2>
          <router-link to="/blog" class="view-all-link">
            View all
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
          </router-link>
        </div>

        <div class="featured-grid">
          <!-- Main Featured Post -->
          <router-link
            v-if="featuredPosts[0]"
            :to="`/blog/${featuredPosts[0].slug}`"
            class="featured-main"
          >
            <div class="featured-image">
              <img
                v-if="featuredPosts[0].featured_image"
                :src="featuredPosts[0].featured_image"
                :alt="featuredPosts[0].title"
              />
              <div v-else class="image-placeholder">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                  <circle cx="8.5" cy="8.5" r="1.5"/>
                  <polyline points="21 15 16 10 5 21"/>
                </svg>
              </div>
              <div class="featured-overlay"></div>
            </div>
            <div class="featured-content">
              <span class="featured-category" v-if="featuredPosts[0].category">
                {{ featuredPosts[0].category.name }}
              </span>
              <h3 class="featured-title">{{ featuredPosts[0].title }}</h3>
              <p class="featured-excerpt">{{ truncate(featuredPosts[0].excerpt, 150) }}</p>
              <div class="featured-meta">
                <span class="meta-author">
                  <span v-if="featuredPosts[0].author_avatar" class="author-avatar">
                    <img :src="featuredPosts[0].author_avatar" :alt="featuredPosts[0].author_name" />
                  </span>
                  <span v-else class="author-avatar-placeholder">
                    {{ getInitials(featuredPosts[0].author_name) }}
                  </span>
                  {{ featuredPosts[0].author_name || 'Anonymous' }}
                </span>
                <span class="meta-date">{{ formatDate(featuredPosts[0].published_at) }}</span>
              </div>
            </div>
          </router-link>

          <!-- Secondary Featured Posts -->
          <div class="featured-secondary">
            <router-link
              v-for="post in featuredPosts.slice(1, 4)"
              :key="post.id"
              :to="`/blog/${post.slug}`"
              class="featured-card"
            >
              <div class="card-image">
                <img
                  v-if="post.featured_image"
                  :src="post.featured_image"
                  :alt="post.title"
                />
                <div v-else class="image-placeholder-sm">
                  <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                    <circle cx="8.5" cy="8.5" r="1.5"/>
                    <polyline points="21 15 16 10 5 21"/>
                  </svg>
                </div>
              </div>
              <div class="card-content">
                <span class="card-category" v-if="post.category">{{ post.category.name }}</span>
                <h4 class="card-title">{{ truncate(post.title, 60) }}</h4>
                <div class="card-meta">
                  <span>{{ formatDate(post.published_at) }}</span>
                  <span class="meta-views">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                      <circle cx="12" cy="12" r="3"/>
                    </svg>
                    {{ formatNumber(post.views) }}
                  </span>
                </div>
              </div>
            </router-link>
          </div>
        </div>
      </div>
    </section>

    <!-- Main Content -->
    <section class="main-content">
      <div class="container">
        <div class="content-layout">
          <!-- Latest Posts -->
          <div class="posts-column">
            <div class="section-header">
              <h2 class="section-title">Latest Articles</h2>
            </div>

            <div class="posts-list" v-if="!loading.latest && latestPosts.length > 0">
              <router-link
                v-for="post in latestPosts"
                :key="post.id"
                :to="`/blog/${post.slug}`"
                class="post-card"
              >
                <div class="post-image">
                  <img
                    v-if="post.featured_image"
                    :src="post.featured_image"
                    :alt="post.title"
                  />
                  <div v-else class="image-placeholder-sm">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                      <circle cx="8.5" cy="8.5" r="1.5"/>
                      <polyline points="21 15 16 10 5 21"/>
                    </svg>
                  </div>
                </div>
                <div class="post-content">
                  <div class="post-category" v-if="post.category">
                    {{ post.category.name }}
                  </div>
                  <h3 class="post-title">{{ post.title }}</h3>
                  <p class="post-excerpt">{{ truncate(post.excerpt, 120) }}</p>
                  <div class="post-meta">
                    <div class="meta-author">
                      <span v-if="post.author_avatar" class="author-avatar-sm">
                        <img :src="post.author_avatar" :alt="post.author_name" />
                      </span>
                      <span v-else class="author-avatar-sm-placeholder">
                        {{ getInitials(post.author_name) }}
                      </span>
                      <span>{{ post.author_name || 'Anonymous' }}</span>
                    </div>
                    <div class="meta-info">
                      <span class="meta-date">{{ formatDate(post.published_at) }}</span>
                      <span class="meta-views">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                          <circle cx="12" cy="12" r="3"/>
                        </svg>
                        {{ formatNumber(post.views) }}
                      </span>
                    </div>
                  </div>
                </div>
              </router-link>
            </div>

            <!-- Loading State -->
            <div class="posts-list" v-else-if="loading.latest">
              <div class="post-card skeleton" v-for="i in 5" :key="i">
                <div class="post-image skeleton-image"></div>
                <div class="post-content">
                  <div class="skeleton-category"></div>
                  <div class="skeleton-title"></div>
                  <div class="skeleton-excerpt"></div>
                  <div class="skeleton-meta"></div>
                </div>
              </div>
            </div>

            <!-- View More Button -->
            <div class="view-more" v-if="latestPosts.length > 0">
              <router-link to="/blog" class="btn-view-more">
                View All Articles
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
              </router-link>
            </div>
          </div>

          <!-- Sidebar -->
          <aside class="sidebar">
            <!-- Popular Posts -->
            <div class="sidebar-section">
              <h3 class="sidebar-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
                </svg>
                Popular Articles
              </h3>
              <div class="popular-posts" v-if="!loading.popular && popularPosts.length > 0">
                <router-link
                  v-for="(post, index) in popularPosts.slice(0, 5)"
                  :key="post.id"
                  :to="`/blog/${post.slug}`"
                  class="popular-post"
                >
                  <span class="popular-rank">{{ String(index + 1).padStart(2, '0') }}</span>
                  <div class="popular-content">
                    <h4 class="popular-title">{{ truncate(post.title, 50) }}</h4>
                    <div class="popular-meta">
                      <span>{{ formatNumber(post.views) }} views</span>
                    </div>
                  </div>
                </router-link>
              </div>
              <div class="sidebar-loading" v-else-if="loading.popular">
                <div class="skeleton-popular" v-for="i in 5" :key="i"></div>
              </div>
            </div>

            <!-- Categories -->
            <div class="sidebar-section">
              <h3 class="sidebar-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                </svg>
                Categories
              </h3>
              <div class="categories-list" v-if="!loading.categories && categoriesTree.length > 0">
                <router-link
                  v-for="category in categoriesTree.slice(0, 8)"
                  :key="category.id"
                  :to="`/blog/category/${category.slug}`"
                  class="category-item"
                >
                  <span class="category-name">{{ category.name }}</span>
                  <span class="category-count">{{ category.post_count || 0 }}</span>
                </router-link>
              </div>
              <div class="sidebar-loading" v-else-if="loading.categories">
                <div class="skeleton-category-item" v-for="i in 6" :key="i"></div>
              </div>
              <router-link to="/blog" class="sidebar-link" v-if="categoriesTree.length > 8">
                View all categories
              </router-link>
            </div>

            <!-- Tags Cloud -->
            <div class="sidebar-section" v-if="popularTags.length > 0">
              <h3 class="sidebar-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                  <line x1="7" y1="7" x2="7.01" y2="7"/>
                </svg>
                Popular Tags
              </h3>
              <div class="tags-cloud">
                <router-link
                  v-for="tag in popularTags.slice(0, 15)"
                  :key="tag.id"
                  :to="`/blog/tag/${tag.slug}`"
                  class="tag-item"
                >
                  {{ tag.name }}
                </router-link>
              </div>
            </div>
          </aside>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
      <div class="container">
        <div class="cta-content">
          <h2 class="cta-title">Stay Updated</h2>
          <p class="cta-description">
            Get the latest articles and tutorials delivered straight to your inbox
          </p>
          <div class="cta-actions">
            <router-link to="/blog" class="btn btn-primary">
              Explore Articles
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M5 12h14M12 5l7 7-7 7"/>
              </svg>
            </router-link>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useBlogStore } from '../stores/blog';
import { useSettingsStore } from '../stores/settings';
import http from '../services/http';

const router = useRouter();
const blogStore = useBlogStore();
const settingsStore = useSettingsStore();

// Local state
const searchQuery = ref('');
const stats = ref({
  posts: 0,
  categories: 0,
  views: 0,
});

// Animated stats for counting effect
const animatedStats = reactive({
  posts: '0',
  categories: '0',
  views: '0',
});

// Computed
const featuredPosts = computed(() => blogStore.featuredPosts);
const latestPosts = computed(() => blogStore.latestPosts);
const popularPosts = computed(() => blogStore.popularPosts);
const categoriesTree = computed(() => blogStore.categoriesTree);
const popularTags = computed(() => blogStore.popularTags);
const loading = computed(() => blogStore.loading);

// Methods
const handleSearch = () => {
  if (searchQuery.value.trim().length >= 2) {
    router.push({
      name: 'blog-search',
      query: { q: searchQuery.value }
    });
  }
};

const formatNumber = (num) => {
  if (!num) return '0';
  if (num >= 1000000) {
    return (num / 1000000).toFixed(1) + 'M';
  }
  if (num >= 1000) {
    return (num / 1000).toFixed(1) + 'K';
  }
  return num.toLocaleString();
};

const formatDate = (dateStr) => {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  const now = new Date();
  const diff = now - date;
  const days = Math.floor(diff / (1000 * 60 * 60 * 24));

  if (days === 0) return 'Today';
  if (days === 1) return 'Yesterday';
  if (days < 7) return `${days} days ago`;
  if (days < 30) return `${Math.floor(days / 7)} weeks ago`;

  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
  });
};

const truncate = (text, length) => {
  if (!text) return '';
  if (text.length <= length) return text;
  return text.substring(0, length).trim() + '...';
};

const getInitials = (name) => {
  if (!name) return '?';
  const parts = name.trim().split(' ');
  if (parts.length >= 2) {
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  }
  return name.substring(0, 2).toUpperCase();
};

// Format number for display (with suffix)
const formatStatNumber = (num) => {
  if (!num) return '0';
  if (num >= 1000000) {
    return (num / 1000000).toFixed(1) + 'M';
  }
  if (num >= 1000) {
    return (num / 1000).toFixed(1) + 'K';
  }
  return num.toLocaleString();
};

// Animate counting effect
const animateCount = (key, targetValue, duration = 1500) => {
  const startTime = performance.now();
  const startValue = 0;

  const animate = (currentTime) => {
    const elapsed = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);

    // Easing function (ease-out cubic)
    const easeOut = 1 - Math.pow(1 - progress, 3);

    const currentValue = Math.floor(startValue + (targetValue - startValue) * easeOut);
    animatedStats[key] = formatStatNumber(currentValue);

    if (progress < 1) {
      requestAnimationFrame(animate);
    } else {
      animatedStats[key] = formatStatNumber(targetValue);
    }
  };

  requestAnimationFrame(animate);
};

const fetchStats = async () => {
  try {
    const response = await http.get('/blog/stats');
    if (response.data.success) {
      const data = response.data.data;
      stats.value = data;

      // Trigger counting animations
      animateCount('posts', data.posts || 0, 1200);
      animateCount('categories', data.categories || 0, 800);
      animateCount('views', data.views || 0, 1500);
    }
  } catch (error) {
    console.error('Failed to fetch stats:', error);
  }
};

// Lifecycle
onMounted(async () => {
  // Wait for settings to be loaded first
  if (!settingsStore.initialized) {
    await settingsStore.initialize();
  }

  // Use settings for counts
  const featuredCount = settingsStore.featuredPostsCount;
  const popularCount = settingsStore.popularPostsCount;

  // Fetch all data in parallel
  await Promise.all([
    blogStore.fetchFeaturedPosts(featuredCount),
    blogStore.fetchLatestPosts(8),
    blogStore.fetchPopularPosts(popularCount),
    blogStore.fetchCategoriesTreeWithCounts(),
    blogStore.fetchPopularTags(15),
    fetchStats(),
  ]);
});
</script>

<style scoped>
.home-page {
  min-height: 100vh;
  background: #f8fafc;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1.5rem;
}

/* Hero Section */
.hero-section {
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
  padding: 80px 0 60px;
  text-align: center;
  position: relative;
  overflow: hidden;
}

.hero-section::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
  opacity: 0.5;
}

.hero-content {
  position: relative;
  z-index: 1;
  max-width: 700px;
  margin: 0 auto;
}

.hero-title {
  font-size: clamp(2rem, 5vw, 3rem);
  font-weight: 700;
  color: #fff;
  margin-bottom: 12px;
  letter-spacing: -0.02em;
}

.hero-subtitle {
  font-size: 1.125rem;
  color: rgba(255, 255, 255, 0.7);
  margin-bottom: 32px;
  line-height: 1.6;
}

/* Hero Search */
.hero-search {
  max-width: 560px;
  margin: 0 auto 40px;
}

.search-wrapper {
  display: flex;
  align-items: center;
  background: #fff;
  border-radius: 12px;
  padding: 6px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.search-icon {
  margin-left: 16px;
  color: #9ca3af;
  flex-shrink: 0;
}

.search-input {
  flex: 1;
  border: none;
  padding: 12px 16px;
  font-size: 1rem;
  background: transparent;
  outline: none;
}

.search-input::placeholder {
  color: #9ca3af;
}

.search-btn {
  padding: 12px 24px;
  background: #1a1a2e;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 0.9375rem;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.search-btn:hover:not(:disabled) {
  background: #16213e;
}

.search-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Hero Stats */
.hero-stats {
  display: inline-flex;
  align-items: center;
  gap: 24px;
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(10px);
  padding: 16px 32px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.stat-item {
  text-align: center;
}

.stat-value {
  display: block;
  font-size: 1.5rem;
  font-weight: 700;
  color: #fff;
}

.stat-label {
  font-size: 0.8125rem;
  color: rgba(255, 255, 255, 0.6);
}

.stat-divider {
  width: 1px;
  height: 32px;
  background: rgba(255, 255, 255, 0.2);
}

/* Section Headers */
.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
}

.section-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1a1a2e;
}

.view-all-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #1a1a2e;
  font-weight: 500;
  font-size: 0.9375rem;
  text-decoration: none;
  transition: color 0.2s;
}

.view-all-link:hover {
  color: #16213e;
}

/* Featured Section */
.featured-section {
  padding: 60px 0;
  background: #fff;
  border-bottom: 1px solid #e5e7eb;
}

.featured-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
}

.featured-main {
  position: relative;
  border-radius: 16px;
  overflow: hidden;
  text-decoration: none;
  display: block;
  aspect-ratio: 16/10;
}

.featured-image {
  position: absolute;
  inset: 0;
}

.featured-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s;
}

.featured-main:hover .featured-image img {
  transform: scale(1.05);
}

.featured-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0.2) 50%, transparent 100%);
}

.featured-content {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 24px;
  color: #fff;
}

.featured-category {
  display: inline-block;
  background: #1a1a2e;
  color: #fff;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 4px 12px;
  border-radius: 50px;
  margin-bottom: 12px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.featured-title {
  font-size: 1.5rem;
  font-weight: 700;
  margin-bottom: 8px;
  line-height: 1.3;
}

.featured-excerpt {
  font-size: 0.9375rem;
  opacity: 0.85;
  line-height: 1.5;
  margin-bottom: 12px;
}

.featured-meta {
  display: flex;
  align-items: center;
  gap: 16px;
  font-size: 0.875rem;
  opacity: 0.8;
}

.meta-author {
  display: flex;
  align-items: center;
  gap: 8px;
}

.author-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  overflow: hidden;
  border: 2px solid rgba(255, 255, 255, 0.3);
  flex-shrink: 0;
}

.author-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.author-avatar-placeholder {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
  color: #fff;
  font-size: 0.75rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid rgba(255, 255, 255, 0.3);
  flex-shrink: 0;
}

/* Featured Secondary */
.featured-secondary {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.featured-card {
  display: flex;
  gap: 16px;
  background: #f8fafc;
  border-radius: 12px;
  overflow: hidden;
  text-decoration: none;
  transition: all 0.2s;
  border: 1px solid #e5e7eb;
}

.featured-card:hover {
  background: #fff;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}

.card-image {
  width: 140px;
  flex-shrink: 0;
}

.card-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.card-content {
  padding: 16px 16px 16px 0;
  flex: 1;
  display: flex;
  flex-direction: column;
}

.card-category {
  font-size: 0.75rem;
  font-weight: 600;
  color: #1a1a2e;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 6px;
}

.card-title {
  font-size: 1rem;
  font-weight: 600;
  color: #1a1a2e;
  line-height: 1.4;
  margin-bottom: auto;
}

.card-meta {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 0.8125rem;
  color: #6b7280;
  margin-top: 8px;
}

.meta-views {
  display: flex;
  align-items: center;
  gap: 4px;
}

/* Main Content Layout */
.main-content {
  padding: 60px 0;
}

.content-layout {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 40px;
}

/* Posts List */
.posts-column {
  min-width: 0;
}

.posts-list {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.post-card {
  display: flex;
  gap: 20px;
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
  text-decoration: none;
  transition: all 0.2s;
  border: 1px solid #e5e7eb;
}

.post-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}

.post-image {
  width: 220px;
  height: 160px;
  flex-shrink: 0;
}

.post-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.post-content {
  padding: 20px 20px 20px 0;
  flex: 1;
  display: flex;
  flex-direction: column;
}

.post-category {
  display: inline-block;
  font-size: 0.75rem;
  font-weight: 600;
  color: #1a1a2e;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 8px;
}

.post-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1a1a2e;
  line-height: 1.4;
  margin-bottom: 8px;
}

.post-excerpt {
  font-size: 0.9375rem;
  color: #6b7280;
  line-height: 1.5;
  margin-bottom: 12px;
}

.post-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: auto;
}

.meta-author {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.875rem;
  color: #4b5563;
}

.author-avatar-sm {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
}

.author-avatar-sm img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.author-avatar-sm-placeholder {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
  color: #fff;
  font-size: 0.625rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.meta-info {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 0.8125rem;
  color: #9ca3af;
}

/* Skeleton Loading */
.skeleton {
  animation: pulse 1.5s infinite;
}

.skeleton-image {
  background: #e5e7eb;
}

.skeleton-category {
  width: 80px;
  height: 14px;
  background: #e5e7eb;
  border-radius: 4px;
  margin-bottom: 8px;
}

.skeleton-title {
  width: 80%;
  height: 20px;
  background: #e5e7eb;
  border-radius: 4px;
  margin-bottom: 8px;
}

.skeleton-excerpt {
  width: 100%;
  height: 40px;
  background: #e5e7eb;
  border-radius: 4px;
  margin-bottom: 12px;
}

.skeleton-meta {
  width: 60%;
  height: 16px;
  background: #e5e7eb;
  border-radius: 4px;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

/* View More */
.view-more {
  text-align: center;
  margin-top: 32px;
}

.btn-view-more {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 28px;
  background: #1a1a2e;
  color: #fff;
  border-radius: 8px;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.2s;
}

.btn-view-more:hover {
  background: #16213e;
  transform: translateY(-1px);
}

/* Sidebar */
.sidebar {
  display: flex;
  flex-direction: column;
  gap: 32px;
}

.sidebar-section {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  border: 1px solid #e5e7eb;
}

.sidebar-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 1.125rem;
  font-weight: 600;
  color: #1a1a2e;
  margin-bottom: 20px;
}

.sidebar-title svg {
  color: #1a1a2e;
}

/* Popular Posts */
.popular-posts {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.popular-post {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  text-decoration: none;
  padding-bottom: 16px;
  border-bottom: 1px solid #f3f4f6;
}

.popular-post:last-child {
  padding-bottom: 0;
  border-bottom: none;
}

.popular-rank {
  font-size: 1.25rem;
  font-weight: 700;
  color: #e5e7eb;
  line-height: 1;
  min-width: 28px;
}

.popular-content {
  flex: 1;
}

.popular-title {
  font-size: 0.9375rem;
  font-weight: 500;
  color: #1a1a2e;
  line-height: 1.4;
  margin-bottom: 4px;
  transition: color 0.2s;
}

.popular-post:hover .popular-title {
  color: #1a1a2e;
}

.popular-meta {
  font-size: 0.8125rem;
  color: #9ca3af;
}

/* Categories List */
.categories-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.category-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 12px;
  background: #f8fafc;
  border-radius: 8px;
  text-decoration: none;
  transition: all 0.2s;
}

.category-item:hover {
  background: #f1f5f9;
  transform: translateX(4px);
}

.category-name {
  font-size: 0.9375rem;
  color: #374151;
  font-weight: 500;
}

.category-count {
  font-size: 0.8125rem;
  color: #9ca3af;
  background: #e5e7eb;
  padding: 2px 8px;
  border-radius: 50px;
}

.sidebar-link {
  display: block;
  margin-top: 16px;
  text-align: center;
  color: #1a1a2e;
  font-size: 0.9375rem;
  font-weight: 500;
  text-decoration: none;
}

.sidebar-link:hover {
  text-decoration: underline;
}

/* Tags Cloud */
.tags-cloud {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.tag-item {
  padding: 6px 12px;
  background: #f3f4f6;
  color: #4b5563;
  font-size: 0.8125rem;
  border-radius: 50px;
  text-decoration: none;
  transition: all 0.2s;
}

.tag-item:hover {
  background: #1a1a2e;
  color: #fff;
}

/* Image Placeholders */
.image-placeholder {
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);
  display: flex;
  align-items: center;
  justify-content: center;
}

.image-placeholder svg {
  color: #9ca3af;
}

.image-placeholder-sm {
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
  display: flex;
  align-items: center;
  justify-content: center;
}

.image-placeholder-sm svg {
  color: #9ca3af;
}

/* Sidebar Loading */
.sidebar-loading {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.skeleton-popular,
.skeleton-category-item {
  height: 48px;
  background: #e5e7eb;
  border-radius: 8px;
  animation: pulse 1.5s infinite;
}

/* CTA Section */
.cta-section {
  background: #1a1a2e;
  padding: 60px 0;
  position: relative;
  overflow: hidden;
}

.cta-section::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
  opacity: 0.5;
}

.cta-content {
  text-align: center;
  max-width: 500px;
  margin: 0 auto;
  position: relative;
  z-index: 1;
}

.cta-title {
  font-size: 2rem;
  font-weight: 700;
  color: #fff;
  margin-bottom: 12px;
}

.cta-description {
  font-size: 1.125rem;
  color: rgba(255, 255, 255, 0.7);
  margin-bottom: 28px;
}

.cta-actions {
  display: flex;
  justify-content: center;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 14px 28px;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.2s;
}

.btn-primary {
  background: #fff;
  color: #1a1a2e;
}

.btn-primary:hover {
  background: #f8fafc;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

/* Responsive - Tablet Landscape */
@media (max-width: 1024px) {
  .content-layout {
    grid-template-columns: 1fr;
    gap: 48px;
  }

  .sidebar {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
  }

  .sidebar-section {
    margin-bottom: 0;
  }

  .featured-grid {
    grid-template-columns: 1fr;
    gap: 20px;
  }

  .featured-main {
    aspect-ratio: 16/9;
  }

  .featured-secondary {
    flex-direction: row;
    flex-wrap: wrap;
    gap: 16px;
  }

  .featured-card {
    flex: 1 1 calc(50% - 8px);
    min-width: 280px;
  }

  .section-title {
    font-size: 1.375rem;
  }
}

/* Responsive - Tablet Portrait */
@media (max-width: 768px) {
  .container {
    padding: 0 1.25rem;
  }

  /* Hero */
  .hero-section {
    padding: 48px 0 40px;
  }

  .hero-title {
    font-size: 1.75rem;
    line-height: 1.2;
  }

  .hero-subtitle {
    font-size: 1rem;
    margin-bottom: 24px;
  }

  .search-wrapper {
    flex-direction: column;
    padding: 12px;
    gap: 10px;
  }

  .search-icon {
    display: none;
  }

  .search-input {
    width: 100%;
    padding: 14px 16px;
    text-align: center;
  }

  .search-btn {
    width: 100%;
    padding: 14px;
  }

  .hero-search {
    margin-bottom: 32px;
  }

  .hero-stats {
    flex-direction: row;
    gap: 0;
    padding: 16px 20px;
    width: 100%;
    max-width: 400px;
  }

  .stat-item {
    flex: 1;
  }

  .stat-value {
    font-size: 1.25rem;
  }

  .stat-label {
    font-size: 0.75rem;
  }

  .stat-divider {
    width: 1px;
    height: 40px;
  }

  /* Featured Section */
  .featured-section,
  .main-content {
    padding: 40px 0;
  }

  .section-header {
    margin-bottom: 20px;
  }

  .section-title {
    font-size: 1.25rem;
  }

  .view-all-link {
    font-size: 0.875rem;
  }

  .featured-main {
    aspect-ratio: 16/10;
    min-height: 280px;
  }

  .featured-content {
    padding: 20px;
  }

  .featured-title {
    font-size: 1.25rem;
    margin-bottom: 6px;
  }

  .featured-excerpt {
    display: none;
  }

  .featured-meta {
    font-size: 0.8125rem;
  }

  .featured-secondary {
    gap: 12px;
  }

  .featured-card {
    flex-direction: row;
    flex: 1 1 100%;
    min-width: 0;
  }

  .card-image {
    width: 120px;
    height: auto;
    min-height: 100px;
    flex-shrink: 0;
  }

  .card-content {
    padding: 12px 12px 12px 0;
  }

  .card-category {
    font-size: 0.6875rem;
    margin-bottom: 4px;
  }

  .card-title {
    font-size: 0.9375rem;
    line-height: 1.3;
  }

  .card-meta {
    font-size: 0.75rem;
    margin-top: 6px;
  }

  /* Latest Posts */
  .posts-list {
    gap: 16px;
  }

  .post-card {
    flex-direction: row;
  }

  .post-image {
    width: 140px;
    height: auto;
    min-height: 120px;
    flex-shrink: 0;
  }

  .post-content {
    padding: 12px 12px 12px 0;
  }

  .post-category {
    font-size: 0.6875rem;
    margin-bottom: 6px;
  }

  .post-title {
    font-size: 1rem;
    margin-bottom: 6px;
    line-height: 1.3;
  }

  .post-excerpt {
    font-size: 0.875rem;
    margin-bottom: 8px;
    -webkit-line-clamp: 2;
  }

  .post-meta {
    flex-direction: row;
    flex-wrap: wrap;
    gap: 8px 16px;
  }

  .meta-author {
    font-size: 0.8125rem;
  }

  .meta-info {
    font-size: 0.75rem;
  }

  /* Sidebar */
  .sidebar {
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
  }

  .sidebar-section {
    padding: 20px;
  }

  .sidebar-title {
    font-size: 1rem;
    margin-bottom: 16px;
  }

  .popular-post {
    padding-bottom: 12px;
    gap: 10px;
  }

  .popular-rank {
    font-size: 1rem;
    min-width: 24px;
  }

  .popular-title {
    font-size: 0.875rem;
  }

  .category-item {
    padding: 8px 10px;
  }

  .category-name {
    font-size: 0.875rem;
  }

  .tag-item {
    padding: 5px 10px;
    font-size: 0.75rem;
  }

  /* View More */
  .view-more {
    margin-top: 24px;
  }

  .btn-view-more {
    padding: 10px 24px;
    font-size: 0.9375rem;
  }

  /* CTA */
  .cta-section {
    padding: 48px 0;
  }

  .cta-title {
    font-size: 1.5rem;
  }

  .cta-description {
    font-size: 1rem;
    margin-bottom: 24px;
  }

  .btn {
    padding: 12px 24px;
    font-size: 0.9375rem;
  }
}

/* Responsive - Mobile */
@media (max-width: 480px) {
  .container {
    padding: 0 1rem;
  }

  /* Hero */
  .hero-section {
    padding: 40px 0 32px;
  }

  .hero-title {
    font-size: 1.5rem;
  }

  .hero-subtitle {
    font-size: 0.9375rem;
    margin-bottom: 20px;
  }

  .hero-search {
    margin-bottom: 24px;
  }

  .search-input {
    padding: 12px 14px;
    font-size: 0.9375rem;
  }

  .search-btn {
    padding: 12px;
    font-size: 0.9375rem;
  }

  .hero-stats {
    padding: 14px 16px;
    max-width: 100%;
  }

  .stat-value {
    font-size: 1.125rem;
  }

  .stat-label {
    font-size: 0.6875rem;
  }

  .stat-divider {
    height: 32px;
  }

  /* Featured */
  .featured-section,
  .main-content {
    padding: 32px 0;
  }

  .section-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 16px;
  }

  .section-title {
    font-size: 1.125rem;
  }

  .featured-main {
    aspect-ratio: 4/3;
    min-height: 240px;
  }

  .featured-category {
    font-size: 0.625rem;
    padding: 3px 10px;
    margin-bottom: 8px;
  }

  .featured-title {
    font-size: 1.125rem;
  }

  .featured-meta {
    gap: 12px;
    font-size: 0.75rem;
  }

  .author-avatar,
  .author-avatar-placeholder {
    width: 24px;
    height: 24px;
    font-size: 0.625rem;
  }

  .featured-card {
    flex-direction: column;
  }

  .card-image {
    width: 100%;
    height: 140px;
  }

  .card-content {
    padding: 14px;
  }

  .card-title {
    font-size: 0.9375rem;
  }

  /* Posts */
  .post-card {
    flex-direction: column;
  }

  .post-image {
    width: 100%;
    height: 160px;
  }

  .post-content {
    padding: 14px;
  }

  .post-title {
    font-size: 1rem;
  }

  .post-excerpt {
    -webkit-line-clamp: 2;
    margin-bottom: 10px;
  }

  .post-meta {
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
  }

  .author-avatar-sm,
  .author-avatar-sm-placeholder {
    width: 20px;
    height: 20px;
    font-size: 0.5rem;
  }

  /* Sidebar */
  .sidebar {
    grid-template-columns: 1fr;
    gap: 16px;
  }

  .sidebar-section {
    padding: 16px;
  }

  .sidebar-title {
    font-size: 0.9375rem;
    margin-bottom: 14px;
    gap: 8px;
  }

  .sidebar-title svg {
    width: 16px;
    height: 16px;
  }

  .popular-posts {
    gap: 12px;
  }

  .popular-post {
    padding-bottom: 10px;
  }

  .popular-rank {
    font-size: 0.9375rem;
  }

  .popular-title {
    font-size: 0.8125rem;
  }

  .popular-meta {
    font-size: 0.75rem;
  }

  .categories-list {
    gap: 6px;
  }

  .category-item {
    padding: 8px 10px;
  }

  .category-name {
    font-size: 0.8125rem;
  }

  .category-count {
    font-size: 0.75rem;
    padding: 2px 6px;
  }

  .tags-cloud {
    gap: 6px;
  }

  .tag-item {
    padding: 4px 8px;
    font-size: 0.6875rem;
  }

  /* CTA */
  .cta-section {
    padding: 40px 0;
  }

  .cta-title {
    font-size: 1.375rem;
  }

  .cta-description {
    font-size: 0.9375rem;
    margin-bottom: 20px;
  }

  .btn {
    padding: 12px 20px;
    font-size: 0.875rem;
    width: 100%;
    justify-content: center;
  }

  /* Image placeholders */
  .image-placeholder svg {
    width: 40px;
    height: 40px;
  }

  .image-placeholder-sm svg {
    width: 28px;
    height: 28px;
  }
}

/* Extra small screens */
@media (max-width: 360px) {
  .container {
    padding: 0 0.875rem;
  }

  .hero-title {
    font-size: 1.375rem;
  }

  .hero-stats {
    padding: 12px 14px;
  }

  .stat-value {
    font-size: 1rem;
  }

  .featured-main {
    min-height: 200px;
  }

  .featured-title {
    font-size: 1rem;
  }

  .post-image {
    height: 140px;
  }

  .cta-title {
    font-size: 1.25rem;
  }
}
</style>
