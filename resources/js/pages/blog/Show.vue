<template>
  <div class="blog-show">
    <!-- Loading State -->
    <div v-if="loading.post" class="container mx-auto px-4 py-8">
      <div class="max-w-4xl mx-auto animate-pulse">
        <div class="h-8 bg-gray-200 rounded w-3/4 mb-4"></div>
        <div class="h-4 bg-gray-200 rounded w-1/2 mb-8"></div>
        <div class="h-64 bg-gray-200 rounded mb-8"></div>
        <div class="space-y-3">
          <div class="h-4 bg-gray-200 rounded"></div>
          <div class="h-4 bg-gray-200 rounded"></div>
          <div class="h-4 bg-gray-200 rounded w-5/6"></div>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="container mx-auto px-4 py-16 text-center">
      <h1 class="text-2xl font-bold text-gray-800 mb-4">{{ error }}</h1>
      <router-link to="/blog" class="text-blue-600 hover:underline">
        ← Back to Blog
      </router-link>
    </div>

    <!-- Post Content -->
    <article v-else-if="post" class="post-article">
      <!-- Hero Header -->
      <header class="bg-gradient-to-r from-gray-800 to-gray-900 text-white py-16">
        <div class="container mx-auto px-4 max-w-4xl">
          <!-- Breadcrumb -->
          <nav class="mb-6 text-sm opacity-75">
            <router-link to="/blog" class="hover:opacity-100">Blog</router-link>
            <span class="mx-2">/</span>
            <span v-if="post.category">
              <router-link
                :to="{ name: 'blog-category', params: { slug: post.category.slug } }"
                class="hover:opacity-100"
              >
                {{ post.category.name }}
              </router-link>
              <span class="mx-2">/</span>
            </span>
            <span>{{ post.title }}</span>
          </nav>

          <!-- Title -->
          <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold mb-6">
            {{ post.title }}
          </h1>

          <!-- Meta -->
          <div class="flex flex-wrap items-center gap-4 text-sm opacity-90">
            <span class="flex items-center">
              <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" />
              </svg>
              {{ post.author_name || 'Admin' }}
            </span>
            <span class="flex items-center">
              <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
              </svg>
              {{ formatDate(post.published_at) }}
            </span>
            <span class="flex items-center">
              <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
              </svg>
              {{ post.views }} views
            </span>
            <span class="flex items-center">
              <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
              </svg>
              {{ post.reading_time }} min read
            </span>
          </div>
        </div>
      </header>

      <!-- Featured Image -->
      <div v-if="post.featured_image" class="container mx-auto px-4 max-w-4xl -mt-8">
        <img
          :src="post.featured_image"
          :alt="post.title"
          class="w-full h-64 md:h-96 object-cover rounded-lg shadow-xl"
        />
      </div>

      <!-- Content -->
      <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
          <div class="flex flex-col lg:flex-row gap-8">
            <!-- Main Content -->
            <div class="lg:w-3/4">
              <!-- Post Content -->
              <div
                class="prose prose-lg max-w-none mb-8"
                v-html="post.content"
              ></div>

              <!-- Tags -->
              <div v-if="post.tags && post.tags.length > 0" class="mb-8">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Tags</h3>
                <div class="flex flex-wrap gap-2">
                  <router-link
                    v-for="tag in post.tags"
                    :key="tag.id"
                    :to="{ name: 'blog-tag', params: { slug: tag.slug } }"
                    class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm hover:bg-blue-100 hover:text-blue-700 transition"
                  >
                    #{{ tag.name }}
                  </router-link>
                </div>
              </div>

              <!-- Share Buttons -->
              <div class="flex items-center gap-4 py-6 border-t border-b border-gray-200 mb-8">
                <span class="text-sm font-semibold text-gray-500">Share:</span>
                <button @click="shareTwitter" class="text-gray-500 hover:text-blue-400">
                  <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                  </svg>
                </button>
                <button @click="shareFacebook" class="text-gray-500 hover:text-blue-600">
                  <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                  </svg>
                </button>
                <button @click="copyLink" class="text-gray-500 hover:text-green-600">
                  <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M12.586 4.586a2 2 0 112.828 2.828l-3 3a2 2 0 01-2.828 0 1 1 0 00-1.414 1.414 4 4 0 005.656 0l3-3a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-5 5a2 2 0 012.828 0 1 1 0 101.414-1.414 4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l3-3z" />
                  </svg>
                </button>
              </div>

              <!-- Comments Section -->
              <section id="comments">
                <h2 class="text-2xl font-bold mb-6">
                  Comments ({{ commentsCount }})
                </h2>
                <CommentList :post-id="post.id" />
              </section>
            </div>

            <!-- Sidebar -->
            <aside class="lg:w-1/4">
              <!-- Related Posts -->
              <div v-if="relatedPosts.length > 0" class="sticky top-4">
                <h3 class="text-lg font-bold mb-4">Related Posts</h3>
                <div class="space-y-4">
                  <router-link
                    v-for="related in relatedPosts"
                    :key="related.id"
                    :to="{ name: 'blog-post', params: { slug: related.slug } }"
                    class="block p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition"
                  >
                    <h4 class="font-medium text-gray-800 line-clamp-2">{{ related.title }}</h4>
                    <p class="text-sm text-gray-500 mt-1">{{ related.reading_time }} min read</p>
                  </router-link>
                </div>
              </div>
            </aside>
          </div>
        </div>
      </div>
    </article>
  </div>
</template>

<script setup>
import { onMounted, computed, watch, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import { useBlogStore } from '../../stores/blog';
import { useCommentsStore } from '../../stores/comments';
import CommentList from '../../components/comments/CommentList.vue';

const route = useRoute();
const blogStore = useBlogStore();
const commentsStore = useCommentsStore();

// Computed
const post = computed(() => blogStore.currentPost);
const relatedPosts = computed(() => blogStore.relatedPosts);
const loading = computed(() => blogStore.loading);
const error = computed(() => blogStore.error);
const commentsCount = computed(() => commentsStore.commentsCount);

// Methods
const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
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

const copyLink = async () => {
  try {
    await navigator.clipboard.writeText(window.location.href);
    alert('Link copied to clipboard!');
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
    // Fetch related posts and comments in parallel
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
.prose :deep(img) {
  @apply rounded-lg;
}

.prose :deep(pre) {
  @apply bg-gray-800 text-gray-100 rounded-lg;
}

.prose :deep(code) {
  @apply bg-gray-100 text-gray-800 px-1 rounded;
}

.prose :deep(pre code) {
  @apply bg-transparent text-inherit p-0;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
