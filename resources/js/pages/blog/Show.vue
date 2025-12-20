<template>
  <div class="blog-show">
    <!-- Loading State -->
    <div v-if="loading.post" class="loading-container">
      <div class="container">
        <div class="loading-skeleton">
          <div class="skeleton-header">
            <div class="skeleton-breadcrumb"></div>
            <div class="skeleton-title"></div>
            <div class="skeleton-meta"></div>
          </div>
          <div class="skeleton-image"></div>
          <div class="skeleton-content">
            <div class="skeleton-line"></div>
            <div class="skeleton-line"></div>
            <div class="skeleton-line short"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-container">
      <div class="container">
        <div class="error-content">
          <div class="error-icon">&#128533;</div>
          <h1>{{ error }}</h1>
          <p>The post you're looking for doesn't exist or has been removed.</p>
          <router-link to="/blog" class="btn-back">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <path d="M10 12L6 8L10 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Back to Blog
          </router-link>
        </div>
      </div>
    </div>

    <!-- Post Content -->
    <article v-else-if="post" class="post-article">
      <!-- Hero Header -->
      <header class="post-header">
        <div class="container">
          <!-- Breadcrumb -->
          <nav class="breadcrumb">
            <router-link to="/blog" class="breadcrumb-link">Blog</router-link>
            <span class="breadcrumb-separator">/</span>
            <span v-if="post.category">
              <router-link
                :to="{ name: 'blog-category', params: { slug: post.category.slug } }"
                class="breadcrumb-link"
              >
                {{ post.category.name }}
              </router-link>
              <span class="breadcrumb-separator">/</span>
            </span>
            <span class="breadcrumb-current">{{ truncateTitle(post.title) }}</span>
          </nav>

          <!-- Title -->
          <h1 class="post-title">{{ post.title }}</h1>

          <!-- Meta -->
          <div class="post-meta">
            <div class="meta-item">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M8 8a3 3 0 100-6 3 3 0 000 6zM2 14a6 6 0 0112 0H2z"/>
              </svg>
              <span>{{ post.author_name || 'Admin' }}</span>
            </div>
            <div class="meta-item">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M4 0a1 1 0 00-1 1v1H2a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V4a2 2 0 00-2-2h-1V1a1 1 0 10-2 0v1H5V1a1 1 0 00-1-1zM2 6h12v8H2V6z"/>
              </svg>
              <span>{{ formatDate(post.published_at) }}</span>
            </div>
            <div class="meta-item">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M8 12a2 2 0 100-4 2 2 0 000 4z"/>
                <path fill-rule="evenodd" d="M.5 8C1.5 5 4.5 2 8 2s6.5 3 7.5 6c-1 3-4 6-7.5 6S1.5 11 .5 8zm13.5 0a5.5 5.5 0 11-11 0 5.5 5.5 0 0111 0z"/>
              </svg>
              <span>{{ formatNumber(post.views) }} views</span>
            </div>
            <div class="meta-item">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path fill-rule="evenodd" d="M8 15A7 7 0 108 1a7 7 0 000 14zm0-1A6 6 0 108 2a6 6 0 000 12zM8.5 4a.5.5 0 00-1 0v4a.5.5 0 00.276.447l2.5 1.25a.5.5 0 00.448-.894L8.5 7.382V4z"/>
              </svg>
              <span>{{ post.reading_time || 5 }} min read</span>
            </div>
          </div>
        </div>
      </header>

      <!-- Featured Image -->
      <div v-if="post.featured_image" class="featured-image-wrapper">
        <div class="container">
          <img
            :src="post.featured_image"
            :alt="post.title"
            class="featured-image"
          />
        </div>
      </div>

      <!-- Content Area -->
      <div class="post-content-area">
        <div class="container">
          <div class="content-layout">
            <!-- Main Content -->
            <div class="main-content">
              <!-- Post Body -->
              <div class="post-body" v-html="post.content"></div>

              <!-- Tags -->
              <div v-if="post.tags && post.tags.length > 0" class="post-tags">
                <span class="tags-label">Tags</span>
                <div class="tags-list">
                  <router-link
                    v-for="tag in post.tags"
                    :key="tag.id"
                    :to="{ name: 'blog-tag', params: { slug: tag.slug } }"
                    class="tag-link"
                  >
                    #{{ tag.name }}
                  </router-link>
                </div>
              </div>

              <!-- Share Buttons -->
              <div class="share-section">
                <span class="share-label">Share this post</span>
                <div class="share-buttons">
                  <button @click="shareTwitter" class="share-btn twitter" title="Share on Twitter">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                    </svg>
                  </button>
                  <button @click="shareFacebook" class="share-btn facebook" title="Share on Facebook">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                  </button>
                  <button @click="shareLinkedIn" class="share-btn linkedin" title="Share on LinkedIn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                    </svg>
                  </button>
                  <button @click="copyLink" class="share-btn copy" title="Copy link">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/>
                      <path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/>
                    </svg>
                    <span v-if="linkCopied" class="copy-feedback">Copied!</span>
                  </button>
                </div>
              </div>

              <!-- Comments Section -->
              <section id="comments">
                <CommentList :post-id="post.id" />
              </section>
            </div>

            <!-- Sidebar -->
            <aside class="content-sidebar">
              <!-- Author Card -->
              <div class="author-card">
                <img
                  v-if="post.author_avatar"
                  :src="post.author_avatar"
                  :alt="post.author_name || 'Author'"
                  class="author-avatar author-avatar-image"
                />
                <div v-else class="author-avatar">
                  {{ getInitials(post.author_name || 'Admin') }}
                </div>
                <div class="author-info">
                  <span class="author-label">Written by</span>
                  <span class="author-name">{{ post.author_name || 'Admin' }}</span>
                </div>
              </div>

              <!-- Related Posts -->
              <div v-if="relatedPosts.length > 0" class="related-posts">
                <h3 class="sidebar-title">Related Posts</h3>
                <div class="related-list">
                  <router-link
                    v-for="related in relatedPosts"
                    :key="related.id"
                    :to="{ name: 'blog-post', params: { slug: related.slug } }"
                    class="related-item"
                  >
                    <span class="related-title">{{ related.title }}</span>
                    <span class="related-meta">{{ related.reading_time || 5 }} min read</span>
                  </router-link>
                </div>
              </div>

              <!-- Table of Contents (placeholder) -->
              <div class="toc-card">
                <h3 class="sidebar-title">Quick Navigation</h3>
                <router-link to="/blog" class="toc-link">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10 12L6 8L10 4"/>
                  </svg>
                  Back to all posts
                </router-link>
              </div>
            </aside>
          </div>
        </div>
      </div>
    </article>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import { useBlogStore } from '../../stores/blog';
import { useCommentsStore } from '../../stores/comments';
import CommentList from '../../components/comments/CommentList.vue';

const route = useRoute();
const blogStore = useBlogStore();
const commentsStore = useCommentsStore();

const linkCopied = ref(false);

// Computed
const post = computed(() => blogStore.currentPost);
const relatedPosts = computed(() => blogStore.relatedPosts);
const loading = computed(() => blogStore.loading);
const error = computed(() => blogStore.error);

// Methods
const truncateTitle = (title) => {
  if (title.length > 30) {
    return title.substring(0, 30) + '...';
  }
  return title;
};

const getInitials = (name) => {
  return name
    .split(' ')
    .map(word => word[0])
    .join('')
    .toUpperCase()
    .substring(0, 2);
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
};

const formatNumber = (num) => {
  if (num >= 1000000) {
    return (num / 1000000).toFixed(1) + 'M';
  }
  if (num >= 1000) {
    return (num / 1000).toFixed(1) + 'K';
  }
  return num;
};

const shareTwitter = () => {
  const url = window.location.href;
  const text = post.value.title;
  window.open(`https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`, '_blank');
};

const shareFacebook = () => {
  const url = window.location.href;
  window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`, '_blank');
};

const shareLinkedIn = () => {
  const url = window.location.href;
  window.open(`https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(url)}`, '_blank');
};

const copyLink = async () => {
  try {
    await navigator.clipboard.writeText(window.location.href);
    linkCopied.value = true;
    setTimeout(() => {
      linkCopied.value = false;
    }, 2000);
  } catch (err) {
    console.error('Failed to copy link:', err);
  }
};

// Fetch post data
const fetchPostData = async (slug) => {
  blogStore.clearCurrentPost();
  commentsStore.clearComments();

  await blogStore.fetchPost(slug);

  if (blogStore.currentPost) {
    await Promise.all([
      blogStore.fetchRelatedPosts(blogStore.currentPost.id, 4),
      commentsStore.fetchComments(blogStore.currentPost.id),
    ]);
  }
};

// Watch route changes
watch(() => route.params.slug, (newSlug) => {
  if (newSlug) {
    fetchPostData(newSlug);
  }
});

// Lifecycle
onMounted(() => {
  if (route.params.slug) {
    fetchPostData(route.params.slug);
  }
});

onUnmounted(() => {
  blogStore.clearCurrentPost();
  commentsStore.clearComments();
});
</script>

<style scoped>
.blog-show {
  min-height: 100vh;
  background: #fafafa;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1.5rem;
}

/* Loading State */
.loading-container {
  padding: 60px 0;
}

.loading-skeleton {
  max-width: 800px;
  margin: 0 auto;
}

.skeleton-header {
  margin-bottom: 32px;
}

.skeleton-breadcrumb {
  height: 14px;
  width: 200px;
  background: linear-gradient(90deg, #f0f0f0 25%, #e5e5e5 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 4px;
  margin-bottom: 24px;
}

.skeleton-title {
  height: 40px;
  width: 80%;
  background: linear-gradient(90deg, #f0f0f0 25%, #e5e5e5 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 6px;
  margin-bottom: 16px;
}

.skeleton-meta {
  height: 16px;
  width: 50%;
  background: linear-gradient(90deg, #f0f0f0 25%, #e5e5e5 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 4px;
}

.skeleton-image {
  height: 400px;
  background: linear-gradient(90deg, #f0f0f0 25%, #e5e5e5 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 12px;
  margin-bottom: 32px;
}

.skeleton-content {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.skeleton-line {
  height: 16px;
  background: linear-gradient(90deg, #f0f0f0 25%, #e5e5e5 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 4px;
}

.skeleton-line.short {
  width: 70%;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Error State */
.error-container {
  padding: 100px 0;
}

.error-content {
  text-align: center;
  max-width: 400px;
  margin: 0 auto;
}

.error-icon {
  font-size: 64px;
  margin-bottom: 24px;
}

.error-content h1 {
  font-size: 24px;
  font-weight: 700;
  color: #1a1a1a;
  margin-bottom: 12px;
}

.error-content p {
  color: #666;
  margin-bottom: 32px;
}

.btn-back {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  background: #1a1a1a;
  color: #fff;
  text-decoration: none;
  border-radius: 8px;
  font-weight: 500;
  transition: all 0.2s;
}

.btn-back:hover {
  background: #333;
}

/* Post Header */
.post-header {
  background: #fff;
  border-bottom: 1px solid #e5e5e5;
  padding: 48px 0;
}

.breadcrumb {
  font-size: 14px;
  margin-bottom: 24px;
}

.breadcrumb-link {
  color: #666;
  text-decoration: none;
  transition: color 0.2s;
}

.breadcrumb-link:hover {
  color: #1a1a1a;
}

.breadcrumb-separator {
  color: #ccc;
  margin: 0 8px;
}

.breadcrumb-current {
  color: #999;
}

.post-title {
  font-size: clamp(28px, 5vw, 42px);
  font-weight: 800;
  color: #1a1a1a;
  line-height: 1.2;
  margin-bottom: 24px;
  letter-spacing: -0.02em;
}

.post-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  color: #666;
}

.meta-item svg {
  color: #999;
}

/* Featured Image */
.featured-image-wrapper {
  background: #fff;
  padding-bottom: 32px;
}

.featured-image {
  width: 100%;
  max-height: 500px;
  object-fit: cover;
  border-radius: 12px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

/* Content Area */
.post-content-area {
  padding: 48px 0 80px;
}

.content-layout {
  display: grid;
  grid-template-columns: 1fr 280px;
  gap: 48px;
}

.main-content {
  min-width: 0;
}

/* Post Body */
.post-body {
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 12px;
  padding: 40px;
  margin-bottom: 32px;
  font-size: 17px;
  line-height: 1.8;
  color: #333;
}

.post-body :deep(h1),
.post-body :deep(h2),
.post-body :deep(h3),
.post-body :deep(h4) {
  color: #1a1a1a;
  font-weight: 700;
  margin-top: 32px;
  margin-bottom: 16px;
  line-height: 1.3;
}

.post-body :deep(h2) {
  font-size: 24px;
}

.post-body :deep(h3) {
  font-size: 20px;
}

.post-body :deep(p) {
  margin-bottom: 16px;
}

.post-body :deep(img) {
  max-width: 100%;
  height: auto;
  border-radius: 8px;
  margin: 24px 0;
}

.post-body :deep(pre) {
  background: #1a1a1a;
  color: #e5e5e5;
  padding: 20px;
  border-radius: 8px;
  overflow-x: auto;
  margin: 24px 0;
  font-size: 14px;
}

.post-body :deep(code) {
  background: #f5f5f5;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 0.9em;
}

.post-body :deep(pre code) {
  background: transparent;
  padding: 0;
}

.post-body :deep(blockquote) {
  border-left: 4px solid #1a1a1a;
  padding-left: 20px;
  margin: 24px 0;
  color: #666;
  font-style: italic;
}

.post-body :deep(ul),
.post-body :deep(ol) {
  margin: 16px 0;
  padding-left: 24px;
}

.post-body :deep(li) {
  margin-bottom: 8px;
}

.post-body :deep(a) {
  color: #1a1a1a;
  text-decoration: underline;
  text-underline-offset: 2px;
}

.post-body :deep(a:hover) {
  color: #666;
}

/* Tags */
.post-tags {
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 24px;
}

.tags-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  color: #999;
  margin-bottom: 12px;
  letter-spacing: 0.5px;
}

.tags-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.tag-link {
  padding: 6px 12px;
  background: #f5f5f5;
  color: #666;
  text-decoration: none;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 500;
  transition: all 0.2s;
}

.tag-link:hover {
  background: #1a1a1a;
  color: #fff;
}

/* Share Section */
.share-section {
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 32px;
  display: flex;
  align-items: center;
  gap: 16px;
}

.share-label {
  font-size: 14px;
  font-weight: 600;
  color: #1a1a1a;
}

.share-buttons {
  display: flex;
  gap: 8px;
}

.share-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border: 1px solid #e5e5e5;
  border-radius: 8px;
  background: #fff;
  color: #666;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
}

.share-btn:hover {
  border-color: #ccc;
}

.share-btn.twitter:hover {
  background: #1da1f2;
  border-color: #1da1f2;
  color: #fff;
}

.share-btn.facebook:hover {
  background: #1877f2;
  border-color: #1877f2;
  color: #fff;
}

.share-btn.linkedin:hover {
  background: #0a66c2;
  border-color: #0a66c2;
  color: #fff;
}

.share-btn.copy:hover {
  background: #1a1a1a;
  border-color: #1a1a1a;
  color: #fff;
}

.copy-feedback {
  position: absolute;
  top: -30px;
  left: 50%;
  transform: translateX(-50%);
  background: #1a1a1a;
  color: #fff;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  white-space: nowrap;
}

/* Sidebar */
.content-sidebar {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.sidebar-title {
  font-size: 14px;
  font-weight: 600;
  color: #1a1a1a;
  margin-bottom: 16px;
}

/* Author Card */
.author-card {
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 12px;
  padding: 24px;
  display: flex;
  align-items: center;
  gap: 16px;
}

.author-avatar {
  width: 48px;
  height: 48px;
  background: #1a1a1a;
  color: #fff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 16px;
  flex-shrink: 0;
}

.author-avatar-image {
  object-fit: cover;
}

.author-info {
  display: flex;
  flex-direction: column;
}

.author-label {
  font-size: 12px;
  color: #999;
}

.author-name {
  font-weight: 600;
  color: #1a1a1a;
}

/* Related Posts */
.related-posts {
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 12px;
  padding: 24px;
  position: sticky;
  top: 80px;
}

.related-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.related-item {
  display: block;
  padding: 12px;
  background: #fafafa;
  border-radius: 8px;
  text-decoration: none;
  transition: all 0.2s;
}

.related-item:hover {
  background: #f0f0f0;
}

.related-title {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #1a1a1a;
  line-height: 1.4;
  margin-bottom: 4px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.related-meta {
  font-size: 12px;
  color: #999;
}

/* TOC Card */
.toc-card {
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 12px;
  padding: 24px;
}

.toc-link {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #666;
  text-decoration: none;
  font-size: 14px;
  transition: color 0.2s;
}

.toc-link:hover {
  color: #1a1a1a;
}

/* Responsive */
@media (max-width: 1024px) {
  .content-layout {
    grid-template-columns: 1fr 240px;
    gap: 32px;
  }
}

@media (max-width: 900px) {
  .content-layout {
    grid-template-columns: 1fr;
  }

  .content-sidebar {
    order: -1;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
  }

  .related-posts {
    position: static;
  }
}

@media (max-width: 768px) {
  .container {
    padding: 0 1.5rem;
  }

  .post-header {
    padding: 32px 0;
  }

  .post-meta {
    gap: 12px;
  }

  .meta-item {
    font-size: 13px;
  }

  .post-body {
    padding: 24px;
    font-size: 16px;
  }

  .content-sidebar {
    grid-template-columns: 1fr;
  }

  .share-section {
    flex-direction: column;
    align-items: flex-start;
  }
}

@media (max-width: 480px) {
  .post-title {
    font-size: 24px;
  }

  .post-body {
    padding: 20px;
  }

  .comments-section {
    padding: 20px;
  }
}
</style>
