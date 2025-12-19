<template>
  <div class="comments-wrapper">
    <!-- Comments Header -->
    <div class="comments-header">
      <h3 class="comments-title">
        Comments
        <span v-if="totalCount > 0" class="comment-count">{{ totalCount }}</span>
      </h3>

      <!-- Sort Dropdown -->
      <div class="sort-dropdown" v-if="comments.length > 0">
        <button @click="toggleSort" class="sort-trigger">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M7 15l5 5 5-5M7 9l5-5 5 5" />
          </svg>
          {{ sortLabel }}
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 12 15 18 9" />
          </svg>
        </button>
        <Transition name="dropdown">
          <div v-if="showSort" class="sort-menu">
            <button
              v-for="option in sortOptions"
              :key="option.value"
              @click="selectSort(option.value)"
              :class="['sort-option', { active: sortBy === option.value }]"
            >
              {{ option.label }}
            </button>
          </div>
        </Transition>
      </div>
    </div>

    <!-- Comment Form -->
    <CommentForm
      v-if="!replyingTo"
      :post-id="postId"
      @submitted="handleCommentSubmitted"
      class="comment-form-section"
    />

    <!-- Loading State -->
    <div v-if="loading.fetch" class="loading-comments">
      <CommentSkeleton v-for="i in 3" :key="i" />
    </div>

    <!-- Comments List -->
    <div v-else-if="comments.length > 0" class="comments-list">
      <CommentItem
        v-for="item in sortedComments"
        :key="item.comment.id"
        :comment="item.comment"
        :replies="item.replies"
        :post-id="postId"
        @reply="handleReply"
      />
    </div>

    <!-- Empty State -->
    <div v-else class="empty-comments">
      <div class="empty-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
      </div>
      <p class="empty-title">No comments yet</p>
      <p class="empty-text">Be the first to share your thoughts!</p>
    </div>

    <!-- Inline Reply Form -->
    <Teleport to="body">
      <Transition name="modal">
        <div
          v-if="replyingTo"
          class="modal-overlay"
          @click.self="cancelReply"
        >
          <div class="modal-content">
            <div class="modal-header">
              <h3 class="modal-title">Reply to comment</h3>
              <button @click="cancelReply" class="modal-close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <CommentForm
              :post-id="postId"
              :parent-id="replyingTo"
              is-reply
              @submitted="handleReplySubmitted"
              @cancel="cancelReply"
            />
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Success Toast -->
    <Transition name="toast">
      <div v-if="showToast" class="toast-message">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M5 13l4 4L19 7" />
        </svg>
        {{ toastMessage }}
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useCommentsStore } from '../../stores/comments';
import CommentItem from './CommentItem.vue';
import CommentForm from './CommentForm.vue';
import CommentSkeleton from './CommentSkeleton.vue';

const props = defineProps({
  postId: {
    type: Number,
    required: true,
  },
});

const commentsStore = useCommentsStore();

// Local state
const showToast = ref(false);
const toastMessage = ref('');
const showSort = ref(false);
const sortBy = ref('newest');

const sortOptions = [
  { value: 'newest', label: 'Most recent' },
  { value: 'oldest', label: 'Oldest first' },
  { value: 'popular', label: 'Most liked' },
];

// Computed
const comments = computed(() => commentsStore.comments);
const loading = computed(() => commentsStore.loading);
const replyingTo = computed(() => commentsStore.replyingTo);

const sortLabel = computed(() => {
  const option = sortOptions.find(o => o.value === sortBy.value);
  return option ? option.label : 'Most recent';
});

const sortedComments = computed(() => {
  const items = [...comments.value];

  switch (sortBy.value) {
    case 'oldest':
      return items.sort((a, b) =>
        new Date(a.comment.created_at) - new Date(b.comment.created_at)
      );
    case 'popular':
      return items.sort((a, b) =>
        (b.comment.likes_count || 0) - (a.comment.likes_count || 0)
      );
    case 'newest':
    default:
      return items.sort((a, b) =>
        new Date(b.comment.created_at) - new Date(a.comment.created_at)
      );
  }
});

// Count total comments including replies
const totalCount = computed(() => {
  const countReplies = (items) => {
    return items.reduce((count, item) => {
      return count + 1 + (item.replies ? countReplies(item.replies) : 0);
    }, 0);
  };
  return countReplies(comments.value);
});

// Methods
const toggleSort = () => {
  showSort.value = !showSort.value;
};

const selectSort = (value) => {
  sortBy.value = value;
  showSort.value = false;
};

const showSuccessToast = (message) => {
  toastMessage.value = message;
  showToast.value = true;
  setTimeout(() => {
    showToast.value = false;
  }, 3000);
};

const handleCommentSubmitted = (result) => {
  if (result.pendingApproval) {
    showSuccessToast('Your comment is pending approval');
  } else {
    showSuccessToast('Comment posted successfully');
  }
};

const handleReply = (commentId) => {
  commentsStore.setReplyingTo(commentId);
};

const handleReplySubmitted = (result) => {
  commentsStore.cancelReply();
  if (result.pendingApproval) {
    showSuccessToast('Your reply is pending approval');
  } else {
    showSuccessToast('Reply posted successfully');
  }
};

const cancelReply = () => {
  commentsStore.cancelReply();
};

// Close sort dropdown when clicking outside
const handleClickOutside = (e) => {
  if (!e.target.closest('.sort-dropdown')) {
    showSort.value = false;
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
.comments-wrapper {
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 12px;
  padding: 32px;
}

/* Comments Header */
.comments-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
}

/* Comment Form Section */
.comment-form-section {
  margin-bottom: 32px;
  padding-bottom: 32px;
  border-bottom: 1px solid #e5e5e5;
}

.comments-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 20px;
  font-weight: 700;
  color: #1a1a1a;
  margin: 0;
}

.comment-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 28px;
  height: 28px;
  padding: 0 8px;
  background: #ff6b35;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  border-radius: 14px;
}

/* Sort Dropdown */
.sort-dropdown {
  position: relative;
}

.sort-trigger {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  background: transparent;
  border: none;
  font-size: 14px;
  font-weight: 500;
  color: #666;
  cursor: pointer;
  border-radius: 6px;
  transition: background 0.15s, color 0.15s;
}

.sort-trigger:hover {
  background: #f5f5f5;
  color: #333;
}

.sort-menu {
  position: absolute;
  top: calc(100% + 4px);
  right: 0;
  min-width: 160px;
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  z-index: 100;
  overflow: hidden;
}

.sort-option {
  display: block;
  width: 100%;
  padding: 10px 14px;
  background: transparent;
  border: none;
  font-size: 14px;
  color: #444;
  text-align: left;
  cursor: pointer;
  transition: background 0.15s;
}

.sort-option:hover {
  background: #f5f5f5;
}

.sort-option.active {
  color: #ff6b35;
  font-weight: 500;
}

/* Dropdown Transition */
.dropdown-enter-active,
.dropdown-leave-active {
  transition: all 0.15s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

/* Loading State */
.loading-comments {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Comments List */
.comments-list {
  display: flex;
  flex-direction: column;
}

/* Empty State */
.empty-comments {
  text-align: center;
  padding: 48px 20px;
  background: #f9f9f9;
  border-radius: 12px;
}

.empty-icon {
  margin-bottom: 16px;
}

.empty-icon svg {
  color: #ccc;
}

.empty-title {
  font-size: 18px;
  font-weight: 600;
  color: #1a1a1a;
  margin: 0 0 8px;
}

.empty-text {
  font-size: 14px;
  color: #666;
  margin: 0;
}

/* Modal Overlay */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
  backdrop-filter: blur(2px);
}

.modal-content {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
  max-width: 520px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #e5e5e5;
}

.modal-title {
  font-size: 16px;
  font-weight: 600;
  color: #1a1a1a;
  margin: 0;
}

.modal-close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  background: #f5f5f5;
  border: none;
  border-radius: 6px;
  color: #666;
  cursor: pointer;
  transition: all 0.15s;
}

.modal-close:hover {
  background: #e5e5e5;
  color: #1a1a1a;
}

.modal-content :deep(.comment-form) {
  border: none;
  border-radius: 0 0 12px 12px;
  background: #fff;
}

/* Modal Transition */
.modal-enter-active,
.modal-leave-active {
  transition: all 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from .modal-content,
.modal-leave-to .modal-content {
  transform: scale(0.95) translateY(-10px);
}

/* Toast Message */
.toast-message {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  background: #1a1a1a;
  color: #fff;
  font-size: 14px;
  font-weight: 500;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  z-index: 1100;
}

.toast-message svg {
  color: #22c55e;
}

/* Toast Transition */
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(10px);
}

/* Responsive */
@media (max-width: 640px) {
  .comments-wrapper {
    padding: 20px;
  }

  .comments-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }

  .comments-title {
    font-size: 18px;
  }

  .comment-form-section {
    margin-bottom: 24px;
    padding-bottom: 24px;
  }

  .modal-content {
    margin: 10px;
    max-height: calc(100vh - 40px);
  }

  .empty-comments {
    padding: 32px 16px;
  }
}
</style>
