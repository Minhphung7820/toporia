<template>
  <div :class="['comment-item', { 'is-reply': depth > 0 }]">
    <!-- Main Comment -->
    <div class="comment-wrapper">
      <!-- Thread connector for replies -->
      <div v-if="depth > 0" class="thread-connector"></div>
      <article class="comment-card">
        <!-- Avatar -->
        <div class="comment-avatar-wrapper">
          <img
            v-if="comment.author_avatar"
            :src="comment.author_avatar"
            :alt="comment.author_name"
            class="avatar-image"
          />
          <div
            v-else
            class="avatar-placeholder"
            :style="{ backgroundColor: getAvatarColor(comment.author_name) }"
          >
            {{ getInitials(comment.author_name || 'Anonymous') }}
          </div>
        </div>

        <!-- Content -->
        <div class="comment-body">
          <!-- Header -->
          <div class="comment-header">
            <div class="author-info">
              <span class="author-name">{{ comment.author_name || 'Anonymous' }}</span>
              <span v-if="comment.is_verified" class="verified-badge" title="Verified">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </span>
              <span class="comment-time">{{ formatDate(comment.created_at) }}</span>
            </div>
          </div>

          <!-- Content Text -->
          <div class="comment-content">{{ comment.content }}</div>

          <!-- Actions -->
          <div class="comment-actions">
            <!-- Like -->
            <button
              @click="handleLike"
              :disabled="loading.like"
              :class="['action-btn', 'like-btn', { active: comment.is_liked }]"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 9V5a3 3 0 00-3-3l-4 9v11h11.28a2 2 0 002-1.7l1.38-9a2 2 0 00-2-2.3zM7 22H4a2 2 0 01-2-2v-7a2 2 0 012-2h3" />
              </svg>
              <span class="action-count" :class="{ highlight: comment.likes_count > 0 }">
                {{ comment.likes_count || 0 }}
              </span>
            </button>

            <!-- Dislike -->
            <button
              @click="handleDislike"
              :disabled="loading.like"
              :class="['action-btn', 'dislike-btn', { active: comment.is_disliked }]"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10 15v4a3 3 0 003 3l4-9V2H5.72a2 2 0 00-2 1.7l-1.38 9a2 2 0 002 2.3zm7-13h2.67A2.31 2.31 0 0122 4v7a2.31 2.31 0 01-2.33 2H17" />
              </svg>
              <span class="action-count">{{ comment.dislikes_count || 0 }}</span>
            </button>

            <!-- Reply -->
            <button
              v-if="canReply"
              @click="$emit('reply', comment.id)"
              class="action-btn reply-btn"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z" />
              </svg>
              <span>Reply</span>
            </button>

            <!-- More Menu -->
            <div class="more-menu">
              <button @click="toggleMenu" class="action-btn more-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                  <circle cx="12" cy="12" r="1.5" />
                  <circle cx="6" cy="12" r="1.5" />
                  <circle cx="18" cy="12" r="1.5" />
                </svg>
              </button>
              <Transition name="menu">
                <div v-if="showMenu" class="menu-dropdown">
                  <button @click="handleReport" class="menu-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z" />
                      <line x1="4" y1="22" x2="4" y2="15" />
                    </svg>
                    Report
                  </button>
                </div>
              </Transition>
            </div>
          </div>
        </div>
      </article>
    </div>

    <!-- Nested Replies -->
    <div v-if="replies && replies.length > 0" class="replies-container">
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
import { ref, computed, onMounted, onUnmounted } from 'vue';
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

// Local state
const showMenu = ref(false);

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

// Generate consistent color based on name
const getAvatarColor = (name) => {
  if (!name) return '#666';
  const colors = ['#1a1a1a', '#4f46e5', '#0891b2', '#059669', '#d97706', '#dc2626', '#7c3aed', '#db2777'];
  let hash = 0;
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash);
  }
  return colors[Math.abs(hash) % colors.length];
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

const handleDislike = async () => {
  // Implement dislike if API supports it
  // For now, just a placeholder
};

const toggleMenu = () => {
  showMenu.value = !showMenu.value;
};

const handleReport = () => {
  showMenu.value = false;
  // Implement report functionality
  alert('Report functionality coming soon');
};

// Close menu when clicking outside
const handleClickOutside = (e) => {
  if (!e.target.closest('.more-menu')) {
    showMenu.value = false;
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<style scoped>
.comment-item {
  margin-bottom: 16px;
}

.comment-wrapper {
  display: flex;
  position: relative;
}

.comment-card {
  display: flex;
  gap: 12px;
  width: 100%;
  padding-left: 0;
}

/* Avatar */
.comment-avatar-wrapper {
  flex-shrink: 0;
}

.avatar-image {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
}

.avatar-placeholder {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 14px;
  font-weight: 600;
}

/* Body */
.comment-body {
  flex: 1;
  min-width: 0;
}

/* Header */
.comment-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}

.author-info {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.author-name {
  font-size: 14px;
  font-weight: 600;
  color: #1a1a1a;
}

.verified-badge {
  display: inline-flex;
  color: #3b82f6;
}

.comment-time {
  font-size: 13px;
  color: #999;
}

/* Content */
.comment-content {
  font-size: 14px;
  color: #333;
  line-height: 1.6;
  white-space: pre-line;
  margin-bottom: 10px;
}

/* Actions */
.comment-actions {
  display: flex;
  align-items: center;
  gap: 4px;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 10px;
  background: transparent;
  border: none;
  border-radius: 4px;
  font-size: 13px;
  color: #666;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}

.action-btn:hover:not(:disabled) {
  background: #f5f5f5;
}

.action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.action-count {
  font-weight: 500;
}

.action-count.highlight {
  color: #ff6b35;
}

/* Like/Dislike buttons */
.like-btn.active {
  color: #ff6b35;
}

.like-btn.active svg {
  fill: #ff6b35;
  stroke: #ff6b35;
}

.dislike-btn.active {
  color: #666;
}

.dislike-btn.active svg {
  fill: #666;
}

/* Reply button */
.reply-btn:hover {
  color: #1a1a1a;
}

/* More Menu */
.more-menu {
  position: relative;
  margin-left: auto;
}

.more-btn {
  padding: 6px;
}

.menu-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  right: 0;
  min-width: 120px;
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  z-index: 50;
  overflow: hidden;
}

.menu-item {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 10px 14px;
  background: transparent;
  border: none;
  font-size: 13px;
  color: #444;
  text-align: left;
  cursor: pointer;
  transition: background 0.15s;
}

.menu-item:hover {
  background: #f5f5f5;
}

/* Menu Transition */
.menu-enter-active,
.menu-leave-active {
  transition: all 0.15s ease;
}

.menu-enter-from,
.menu-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

/* Nested Replies */
.replies-container {
  margin-left: 52px;
  padding-left: 0;
  margin-top: 12px;
  position: relative;
}

/* Thread connector - L-shaped line for each reply */
.thread-connector {
  position: absolute;
  left: -32px;
  top: 0;
  width: 20px;
  height: 20px;
  border-bottom: 1px solid #e0e0e0;
  border-left: 1px solid #e0e0e0;
  border-bottom-left-radius: 10px;
}

/* Extend the vertical line for replies that have siblings below */
.comment-item.is-reply {
  position: relative;
}

.comment-item.is-reply:not(:last-child)::before {
  content: '';
  position: absolute;
  left: -32px;
  top: 20px;
  bottom: -16px;
  width: 1px;
  background: #e0e0e0;
}

/* Responsive */
@media (max-width: 640px) {
  .avatar-image,
  .avatar-placeholder {
    width: 36px;
    height: 36px;
    font-size: 12px;
  }

  .author-name {
    font-size: 13px;
  }

  .comment-content {
    font-size: 13px;
  }

  .action-btn {
    padding: 5px 8px;
    font-size: 12px;
  }

  .replies-container {
    margin-left: 36px;
  }

  .thread-connector {
    left: -24px;
    width: 16px;
  }

  .comment-item.is-reply:not(:last-child)::before {
    left: -24px;
  }
}
</style>
