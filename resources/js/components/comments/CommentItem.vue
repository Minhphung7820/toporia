<template>
  <div :class="['comment-item', depth > 0 ? 'ml-8 pl-4 border-l-2 border-gray-200' : '']">
    <article class="bg-gray-50 rounded-lg p-4">
      <!-- Comment Header -->
      <header class="flex items-center gap-3 mb-3">
        <!-- Avatar -->
        <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold">
          {{ getInitials(comment.author_name || 'Anonymous') }}
        </div>

        <!-- Author Info -->
        <div class="flex-1">
          <h4 class="font-semibold text-gray-800">
            {{ comment.author_name || 'Anonymous' }}
          </h4>
          <time class="text-sm text-gray-500">
            {{ formatDate(comment.created_at) }}
          </time>
        </div>
      </header>

      <!-- Comment Content -->
      <div class="text-gray-700 mb-3 whitespace-pre-line">
        {{ comment.content }}
      </div>

      <!-- Comment Actions -->
      <footer class="flex items-center gap-4 text-sm">
        <!-- Like Button -->
        <button
          @click="handleLike"
          :disabled="loading.like"
          class="flex items-center gap-1 text-gray-500 hover:text-red-500 transition disabled:opacity-50"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
          </svg>
          <span>{{ comment.likes_count || 0 }}</span>
        </button>

        <!-- Reply Button -->
        <button
          v-if="canReply"
          @click="$emit('reply', comment.id)"
          class="flex items-center gap-1 text-gray-500 hover:text-blue-500 transition"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
          </svg>
          <span>Reply</span>
        </button>
      </footer>
    </article>

    <!-- Nested Replies -->
    <div v-if="replies && replies.length > 0" class="mt-4 space-y-4">
      <CommentItem
        v-for="reply in replies"
        :key="reply.comment.id"
        :comment="reply.comment"
        :replies="reply.replies"
        :post-id="postId"
        :depth="depth + 1"
        @reply="$emit('reply', $event)"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useCommentsStore } from '../../stores/comments';

const props = defineProps({
  comment: {
    type: Object,
    required: true,
  },
  replies: {
    type: Array,
    default: () => [],
  },
  postId: {
    type: Number,
    required: true,
  },
  depth: {
    type: Number,
    default: 0,
  },
});

defineEmits(['reply']);

const commentsStore = useCommentsStore();

// Max reply depth (from backend)
const MAX_DEPTH = 3;

// Computed
const canReply = computed(() => props.depth < MAX_DEPTH - 1);
const loading = computed(() => commentsStore.loading);

// Methods
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
  const now = new Date();
  const diff = now - date;

  // Less than a minute
  if (diff < 60000) {
    return 'Just now';
  }

  // Less than an hour
  if (diff < 3600000) {
    const minutes = Math.floor(diff / 60000);
    return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
  }

  // Less than a day
  if (diff < 86400000) {
    const hours = Math.floor(diff / 3600000);
    return `${hours} hour${hours > 1 ? 's' : ''} ago`;
  }

  // Less than a week
  if (diff < 604800000) {
    const days = Math.floor(diff / 86400000);
    return `${days} day${days > 1 ? 's' : ''} ago`;
  }

  // Default: full date
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const handleLike = async () => {
  await commentsStore.likeComment(props.comment.id);
};
</script>
