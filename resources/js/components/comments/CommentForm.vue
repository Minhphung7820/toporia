<template>
  <div :class="['comment-form', { 'is-reply': isReply }]">
    <!-- Main Form Area -->
    <div class="form-container">
      <!-- Rich Comment Editor -->
      <RichCommentEditor
        ref="editorRef"
        v-model="form.content"
        :placeholder="isReply ? 'Write a reply...' : 'Add comment...'"
        :is-reply="isReply"
        @attachments-change="handleAttachmentsChange"
        @mention="handleMention"
      />

      <!-- Expanded Form Content -->
      <div v-show="isExpanded || form.content" class="form-expanded">
        <!-- Guest Fields -->
        <div v-if="!isAuthenticated && !isReply" class="guest-fields">
          <input
            v-model="form.author_name"
            type="text"
            placeholder="Your name"
            class="guest-input"
          />
          <input
            v-model="form.author_email"
            type="email"
            placeholder="Email address"
            class="guest-input"
          />
        </div>

        <!-- Submit Button -->
        <div class="form-footer">
          <div class="footer-info">
            <span v-if="form.attachments.length > 0" class="attachment-count">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48" />
              </svg>
              {{ form.attachments.length }} file{{ form.attachments.length > 1 ? 's' : '' }}
            </span>
            <span v-if="form.mentions.length > 0" class="mention-count">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="4" />
                <path d="M16 8v5a3 3 0 006 0v-1a10 10 0 10-3.92 7.94" />
              </svg>
              {{ form.mentions.length }} mention{{ form.mentions.length > 1 ? 's' : '' }}
            </span>
          </div>
          <button
            type="button"
            @click="handleSubmit"
            :disabled="loading || !canSubmit"
            class="btn-submit"
          >
            <svg v-if="loading" class="spinner" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity="0.3" />
              <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
            </svg>
            <span>{{ isReply ? 'Reply' : 'Submit' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="error-message">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm-1-7v2h2v-2h-2zm0-8v6h2V7h-2z"/>
      </svg>
      {{ error }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed, reactive, watch } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { useCommentsStore } from '../../stores/comments';
import RichCommentEditor from './RichCommentEditor.vue';

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

// Refs
const editorRef = ref(null);
const isExpanded = ref(false);

// Form state
const form = reactive({
  content: '',
  author_name: '',
  author_email: '',
  attachments: [],
  mentions: [],
});

const error = ref(null);

// Computed
const isAuthenticated = computed(() => authStore.isAuthenticated);
const loading = computed(() => props.isReply ? commentsStore.loading.reply : commentsStore.loading.create);

const canSubmit = computed(() => {
  const hasContent = form.content.replace(/<[^>]*>/g, '').trim().length > 0;
  const hasAttachments = form.attachments.length > 0;
  return hasContent || hasAttachments;
});

// Watch content changes to expand form
watch(() => form.content, (val) => {
  if (val) isExpanded.value = true;
});

// Methods
const handleAttachmentsChange = (attachments) => {
  form.attachments = attachments;
  if (attachments.length > 0) isExpanded.value = true;
};

const handleMention = (user) => {
  if (!form.mentions.find(m => m.userId === user.id)) {
    form.mentions.push({ userId: user.id, username: user.username || user.name });
  }
};

const handleSubmit = async () => {
  error.value = null;

  // Get content and mentions from editor
  const content = editorRef.value?.getContent() || '';
  const textContent = editorRef.value?.getTextContent() || '';
  const mentions = editorRef.value?.getMentions() || [];
  const attachments = editorRef.value?.getAttachments() || [];

  // Validation
  if (!textContent.trim() && attachments.length === 0) {
    error.value = 'Please enter a comment or attach a file';
    return;
  }

  if (!isAuthenticated.value && !props.isReply) {
    if (!form.author_name.trim()) {
      error.value = 'Please enter your name';
      return;
    }
    if (!form.author_email.trim()) {
      error.value = 'Please enter your email';
      return;
    }
  }

  // Prepare data
  const commentData = {
    content: content,
    author_name: form.author_name || undefined,
    author_email: form.author_email || undefined,
    attachments: attachments.map(a => ({
      path: a.path,
      filename: a.filename,
      mime_type: a.mime_type,
      size: a.size,
      type: a.type,
      width: a.width,
      height: a.height,
    })),
    mentions: mentions.map(m => m.userId),
  };

  let result;

  if (props.isReply && props.parentId) {
    result = await commentsStore.replyToComment(props.parentId, commentData);
  } else {
    result = await commentsStore.createComment(props.postId, commentData);
  }

  if (result.success) {
    // Reset form
    editorRef.value?.clear();
    form.content = '';
    form.author_name = '';
    form.author_email = '';
    form.attachments = [];
    form.mentions = [];
    isExpanded.value = false;
    emit('submitted', result);
  } else {
    error.value = result.message;
  }
};
</script>

<style scoped>
.comment-form {
  background: #f5f5f5;
  border-radius: 12px;
  padding: 16px;
}

.comment-form.is-reply {
  background: #fff;
  border: 1px solid #e5e5e5;
  padding: 12px;
}

.form-container {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* Expanded Form */
.form-expanded {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* Guest Fields */
.guest-fields {
  display: flex;
  gap: 12px;
}

.guest-input {
  flex: 1;
  padding: 10px 14px;
  background: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  font-size: 14px;
  transition: border-color 0.2s;
}

.guest-input:focus {
  outline: none;
  border-color: #ff6b35;
}

/* Footer */
.form-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.footer-info {
  display: flex;
  align-items: center;
  gap: 16px;
}

.attachment-count,
.mention-count {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #666;
}

/* Submit Button */
.btn-submit {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 24px;
  background: #ff6b35;
  color: #fff;
  border: none;
  border-radius: 24px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s, transform 0.1s;
}

.btn-submit:hover:not(:disabled) {
  background: #e55a2b;
}

.btn-submit:active:not(:disabled) {
  transform: scale(0.98);
}

.btn-submit:disabled {
  background: #ccc;
  cursor: not-allowed;
}

.spinner {
  width: 16px;
  height: 16px;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  100% { transform: rotate(360deg); }
}

/* Error Message */
.error-message {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  color: #dc2626;
  font-size: 13px;
  margin-top: 12px;
}

/* Responsive */
@media (max-width: 640px) {
  .comment-form {
    padding: 12px;
  }

  .guest-fields {
    flex-direction: column;
  }

  .form-footer {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }

  .footer-info {
    justify-content: center;
  }

  .btn-submit {
    width: 100%;
  }
}
</style>
