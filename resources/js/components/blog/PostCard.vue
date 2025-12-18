<template>
  <article :class="['post-card', { featured: featured }]">
    <!-- Featured Image -->
    <router-link
      v-if="post.featured_image"
      :to="postUrl"
      class="image-link"
    >
      <img
        :src="post.featured_image"
        :alt="post.title"
        class="post-image"
      />
      <span v-if="featured" class="featured-badge">Featured</span>
    </router-link>

    <!-- Content -->
    <div class="post-content">
      <!-- Category Badge -->
      <div v-if="post.category" class="category-badge">
        <router-link
          :to="{ name: 'blog-category', params: { slug: post.category.slug || post.category_slug } }"
        >
          {{ post.category.name || post.category_name }}
        </router-link>
      </div>

      <!-- Title -->
      <h2 class="post-title">
        <router-link :to="postUrl">
          {{ post.title }}
        </router-link>
      </h2>

      <!-- Excerpt -->
      <p class="post-excerpt">
        {{ post.excerpt || truncateContent(post.content, 150) }}
      </p>

      <!-- Meta -->
      <div class="post-meta">
        <span class="meta-item">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
            <path d="M4 0a1 1 0 00-1 1v1H2a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V4a2 2 0 00-2-2h-1V1a1 1 0 10-2 0v1H5V1a1 1 0 00-1-1zM2 6h12v8H2V6z"/>
          </svg>
          {{ formatDate(post.published_at) }}
        </span>
        <span class="meta-item">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
            <path fill-rule="evenodd" d="M8 15A7 7 0 108 1a7 7 0 000 14zm0-1A6 6 0 108 2a6 6 0 000 12zM8.5 4a.5.5 0 00-1 0v4a.5.5 0 00.276.447l2.5 1.25a.5.5 0 00.448-.894L8.5 7.382V4z"/>
          </svg>
          {{ post.reading_time || 5 }} min
        </span>
        <span class="meta-item">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
            <path d="M8 12a2 2 0 100-4 2 2 0 000 4z"/>
            <path fill-rule="evenodd" d="M.5 8C1.5 5 4.5 2 8 2s6.5 3 7.5 6c-1 3-4 6-7.5 6S1.5 11 .5 8zm13.5 0a5.5 5.5 0 11-11 0 5.5 5.5 0 0111 0z"/>
          </svg>
          {{ formatViews(post.views) }}
        </span>
      </div>

      <!-- Tags -->
      <div v-if="post.tags && post.tags.length > 0" class="post-tags">
        <router-link
          v-for="tag in post.tags.slice(0, 3)"
          :key="tag.id || tag.slug"
          :to="{ name: 'blog-tag', params: { slug: tag.slug } }"
          class="tag-link"
        >
          #{{ tag.name }}
        </router-link>
      </div>

      <!-- Read More Link -->
      <router-link :to="postUrl" class="read-more">
        Read article
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
          <path d="M6 12L10 8L6 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </router-link>
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

const formatViews = (views) => {
  if (!views) return '0';
  if (views >= 1000000) {
    return (views / 1000000).toFixed(1) + 'M';
  }
  if (views >= 1000) {
    return (views / 1000).toFixed(1) + 'K';
  }
  return views;
};

const truncateContent = (content, maxLength) => {
  if (!content) return '';
  const text = content.replace(/<[^>]*>/g, '');
  if (text.length <= maxLength) return text;
  return text.substring(0, maxLength) + '...';
};
</script>

<style scoped>
.post-card {
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 12px;
  overflow: hidden;
  transition: all 0.2s;
}

.post-card:hover {
  border-color: #ccc;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.post-card.featured {
  border-color: #f59e0b;
  border-width: 2px;
}

/* Image */
.image-link {
  display: block;
  position: relative;
  overflow: hidden;
}

.post-image {
  width: 100%;
  height: 200px;
  object-fit: cover;
  transition: transform 0.3s ease;
}

.post-card:hover .post-image {
  transform: scale(1.02);
}

.featured-badge {
  position: absolute;
  top: 12px;
  left: 12px;
  background: #f59e0b;
  color: #fff;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  padding: 4px 10px;
  border-radius: 4px;
  letter-spacing: 0.5px;
}

/* Content */
.post-content {
  padding: 24px;
}

/* Category */
.category-badge {
  margin-bottom: 12px;
}

.category-badge a {
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  color: #666;
  text-decoration: none;
  letter-spacing: 0.5px;
  transition: color 0.2s;
}

.category-badge a:hover {
  color: #1a1a1a;
}

/* Title */
.post-title {
  font-size: 20px;
  font-weight: 700;
  line-height: 1.3;
  margin-bottom: 12px;
}

.post-card.featured .post-title {
  font-size: 22px;
}

.post-title a {
  color: #1a1a1a;
  text-decoration: none;
  transition: color 0.2s;
}

.post-title a:hover {
  color: #666;
}

/* Excerpt */
.post-excerpt {
  font-size: 14px;
  color: #666;
  line-height: 1.6;
  margin-bottom: 16px;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Meta */
.post-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 16px;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 13px;
  color: #999;
}

.meta-item svg {
  color: #ccc;
}

/* Tags */
.post-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 16px;
}

.tag-link {
  font-size: 12px;
  color: #666;
  background: #f5f5f5;
  padding: 4px 10px;
  border-radius: 4px;
  text-decoration: none;
  transition: all 0.2s;
}

.tag-link:hover {
  background: #1a1a1a;
  color: #fff;
}

/* Read More */
.read-more {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 500;
  color: #1a1a1a;
  text-decoration: none;
  transition: all 0.2s;
}

.read-more:hover {
  gap: 10px;
}

.read-more svg {
  transition: transform 0.2s;
}

.read-more:hover svg {
  transform: translateX(2px);
}

/* Responsive */
@media (max-width: 768px) {
  .post-content {
    padding: 20px;
  }

  .post-title {
    font-size: 18px;
  }

  .post-card.featured .post-title {
    font-size: 20px;
  }
}
</style>
