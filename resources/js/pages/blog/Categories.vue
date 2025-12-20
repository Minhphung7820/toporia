<template>
  <div class="categories-page">
    <!-- Hero Section -->
    <section class="categories-hero">
      <div class="container">
        <nav class="breadcrumb">
          <router-link to="/blog">Blog</router-link>
          <span class="separator">/</span>
          <span>Categories</span>
        </nav>

        <h1 class="hero-title">All Categories</h1>
        <p class="hero-description">Browse articles by category</p>
      </div>
    </section>

    <!-- Content -->
    <section class="categories-content">
      <div class="container">
        <!-- Loading State -->
        <div v-if="loading.categories" class="categories-grid">
          <div v-for="i in 6" :key="i" class="category-card skeleton">
            <div class="skeleton-icon"></div>
            <div class="skeleton-title"></div>
            <div class="skeleton-count"></div>
          </div>
        </div>

        <!-- Categories Grid -->
        <div v-else-if="categoriesTree.length > 0" class="categories-grid">
          <CategoryCard
            v-for="category in categoriesTree"
            :key="category.id"
            :category="category"
          />
        </div>

        <!-- Empty State -->
        <div v-else class="empty-state">
          <div class="empty-icon">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
            </svg>
          </div>
          <h2>No categories yet</h2>
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
import { computed, onMounted } from 'vue';
import { useBlogStore } from '../../stores/blog';
import CategoryCard from '../../components/blog/CategoryCard.vue';

const blogStore = useBlogStore();

const categoriesTree = computed(() => blogStore.categoriesTree);
const loading = computed(() => blogStore.loading);

onMounted(async () => {
  await blogStore.fetchCategoriesTreeWithCounts();
});
</script>

<style scoped>
.categories-page {
  min-height: 100vh;
  background: #fafafa;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1.5rem;
}

/* Hero Section */
.categories-hero {
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
}

/* Content */
.categories-content {
  padding: 48px 0 80px;
}

/* Categories Grid */
.categories-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 24px;
}

/* Skeleton */
.category-card.skeleton {
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 12px;
  padding: 24px;
}

.skeleton-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: linear-gradient(90deg, #f0f0f0 25%, #e5e5e5 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  margin-bottom: 16px;
}

.skeleton-title {
  width: 60%;
  height: 24px;
  border-radius: 4px;
  background: linear-gradient(90deg, #f0f0f0 25%, #e5e5e5 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  margin-bottom: 8px;
}

.skeleton-count {
  width: 40%;
  height: 16px;
  border-radius: 4px;
  background: linear-gradient(90deg, #f0f0f0 25%, #e5e5e5 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
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

  .categories-hero {
    padding: 32px 0 40px;
  }

  .hero-title {
    font-size: 28px;
  }

  .categories-content {
    padding: 32px 0 60px;
  }

  .categories-grid {
    grid-template-columns: 1fr;
  }
}
</style>
