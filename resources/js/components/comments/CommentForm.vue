<template>
  <form @submit.prevent="handleSubmit" class="comment-form">
    <h3 v-if="!isReply" class="text-lg font-bold mb-4">Leave a Comment</h3>

    <!-- Guest Info (if not authenticated) -->
    <div v-if="!isAuthenticated" class="grid md:grid-cols-2 gap-4 mb-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
        <input
          v-model="form.author_name"
          type="text"
          required
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="Your name"
        />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
        <input
          v-model="form.author_email"
          type="email"
          required
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="your@email.com"
        />
      </div>
    </div>

    <!-- Comment Content -->
    <div class="mb-4">
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ isReply ? 'Your Reply' : 'Comment' }} *
      </label>
      <textarea
        v-model="form.content"
        required
        rows="4"
        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
        :placeholder="isReply ? 'Write your reply...' : 'Write your comment...'"
      ></textarea>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg">
      {{ error }}
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-3">
      <button
        type="submit"
        :disabled="loading"
        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
      >
        <svg v-if="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        {{ isReply ? 'Post Reply' : 'Post Comment' }}
      </button>
      <button
        v-if="isReply"
        type="button"
        @click="$emit('cancel')"
        class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition"
      >
        Cancel
      </button>
    </div>
  </form>
</template>

<script setup>
import { ref, computed, reactive } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { useCommentsStore } from '../../stores/comments';

const props = defineProps({
  postId: {
    type: Number,
    required: true,
  },
  parentId: {
    type: Number,
    default: null,
  },
  isReply: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['submitted', 'cancel']);

const authStore = useAuthStore();
const commentsStore = useCommentsStore();

// Form state
const form = reactive({
  content: '',
  author_name: '',
  author_email: '',
});

const error = ref(null);

// Computed
const isAuthenticated = computed(() => authStore.isAuthenticated);
const loading = computed(() => props.isReply ? commentsStore.loading.reply : commentsStore.loading.create);

// Methods
const handleSubmit = async () => {
  error.value = null;

  // Validation
  if (!form.content.trim()) {
    error.value = 'Please enter a comment';
    return;
  }

  if (!isAuthenticated.value) {
    if (!form.author_name.trim()) {
      error.value = 'Please enter your name';
      return;
    }
    if (!form.author_email.trim()) {
      error.value = 'Please enter your email';
      return;
    }
  }

  let result;

  if (props.isReply && props.parentId) {
    result = await commentsStore.replyToComment(props.parentId, {
      content: form.content,
      author_name: form.author_name || undefined,
      author_email: form.author_email || undefined,
    });
  } else {
    result = await commentsStore.createComment(props.postId, {
      content: form.content,
      author_name: form.author_name || undefined,
      author_email: form.author_email || undefined,
    });
  }

  if (result.success) {
    // Reset form
    form.content = '';
    form.author_name = '';
    form.author_email = '';
    emit('submitted', result);
  } else {
    error.value = result.message;
  }
};
</script>
