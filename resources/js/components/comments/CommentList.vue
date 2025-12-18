<template>
  <div class="comments-section">
    <!-- Comment Form -->
    <CommentForm
      v-if="!replyingTo"
      :post-id="postId"
      @submitted="handleCommentSubmitted"
      class="mb-8"
    />

    <!-- Loading State -->
    <div v-if="loading.fetch" class="space-y-4">
      <CommentSkeleton v-for="i in 3" :key="i" />
    </div>

    <!-- Comments List -->
    <div v-else-if="comments.length > 0" class="space-y-6">
      <CommentItem
        v-for="item in comments"
        :key="item.comment.id"
        :comment="item.comment"
        :replies="item.replies"
        :post-id="postId"
        @reply="handleReply"
      />
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-8 text-gray-500">
      <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
      </svg>
      <p>No comments yet. Be the first to comment!</p>
    </div>

    <!-- Reply Modal -->
    <div
      v-if="replyingTo"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
      @click.self="cancelReply"
    >
      <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-bold">Reply to Comment</h3>
          <button @click="cancelReply" class="text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
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
  </div>
</template>

<script setup>
import { computed } from 'vue';
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

// Computed
const comments = computed(() => commentsStore.comments);
const loading = computed(() => commentsStore.loading);
const replyingTo = computed(() => commentsStore.replyingTo);

// Methods
const handleCommentSubmitted = (result) => {
  if (result.pendingApproval) {
    alert('Your comment has been submitted and is pending approval.');
  }
};

const handleReply = (commentId) => {
  commentsStore.setReplyingTo(commentId);
};

const handleReplySubmitted = (result) => {
  commentsStore.cancelReply();
  if (result.pendingApproval) {
    alert('Your reply has been submitted and is pending approval.');
  }
};

const cancelReply = () => {
  commentsStore.cancelReply();
};
</script>
