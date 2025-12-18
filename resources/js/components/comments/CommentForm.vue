<template>
  <div :class="['comment-form', { 'is-reply': isReply }]">
    <!-- Main Form Area -->
    <div class="form-container">
      <!-- Textarea -->
      <div class="textarea-wrapper">
        <textarea
          ref="textareaRef"
          v-model="form.content"
          :placeholder="isReply ? 'Write a reply...' : 'Add comment...'"
          class="comment-textarea"
          rows="3"
          @focus="isExpanded = true"
        ></textarea>
      </div>

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

        <!-- Toolbar & Submit -->
        <div class="form-footer">
          <!-- Toolbar -->
          <div class="toolbar">
            <button type="button" class="toolbar-btn" title="Bold" @click="insertFormat('bold')">
              <span class="toolbar-icon">B</span>
            </button>
            <button type="button" class="toolbar-btn" title="Italic" @click="insertFormat('italic')">
              <span class="toolbar-icon italic">I</span>
            </button>
            <button type="button" class="toolbar-btn" title="Underline" @click="insertFormat('underline')">
              <span class="toolbar-icon underline">U</span>
            </button>
            <span class="toolbar-divider"></span>
            <button type="button" class="toolbar-btn" title="Attach file" disabled>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48" />
              </svg>
            </button>
            <button type="button" class="toolbar-btn" title="Add image" disabled>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                <circle cx="8.5" cy="8.5" r="1.5" />
                <polyline points="21 15 16 10 5 21" />
              </svg>
            </button>
            <button type="button" class="toolbar-btn" title="Add emoji" disabled>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" />
                <path d="M8 14s1.5 2 4 2 4-2 4-2" />
                <line x1="9" y1="9" x2="9.01" y2="9" />
                <line x1="15" y1="9" x2="15.01" y2="9" />
              </svg>
            </button>
            <button type="button" class="toolbar-btn" title="Mention" disabled>
              <span class="toolbar-icon">@</span>
            </button>
          </div>

          <!-- Submit Button -->
          <button
            type="button"
            @click="handleSubmit"
            :disabled="loading || !form.content.trim()"
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

// Refs
const textareaRef = ref(null);
const isExpanded = ref(false);

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
const insertFormat = (format) => {
  // Simple format insertion (placeholder - could be enhanced)
  const textarea = textareaRef.value;
  if (!textarea) return;

  const start = textarea.selectionStart;
  const end = textarea.selectionEnd;
  const selectedText = form.content.substring(start, end);

  let formattedText = selectedText;
  switch (format) {
    case 'bold':
      formattedText = `**${selectedText}**`;
      break;
    case 'italic':
      formattedText = `_${selectedText}_`;
      break;
    case 'underline':
      formattedText = `__${selectedText}__`;
      break;
  }

  form.content = form.content.substring(0, start) + formattedText + form.content.substring(end);

  // Restore focus
  setTimeout(() => {
    textarea.focus();
    textarea.setSelectionRange(start + formattedText.length, start + formattedText.length);
  }, 0);
};

const handleSubmit = async () => {
  error.value = null;

  // Validation
  if (!form.content.trim()) {
    error.value = 'Please enter a comment';
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

/* Textarea */
.textarea-wrapper {
  position: relative;
}

.comment-textarea {
  width: 100%;
  padding: 14px 16px;
  background: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  font-size: 15px;
  font-family: inherit;
  line-height: 1.5;
  resize: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.comment-textarea:focus {
  outline: none;
  border-color: #ff6b35;
  box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
}

.comment-textarea::placeholder {
  color: #999;
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

/* Toolbar */
.toolbar {
  display: flex;
  align-items: center;
  gap: 4px;
}

.toolbar-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  background: transparent;
  border: none;
  border-radius: 6px;
  color: #666;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}

.toolbar-btn:hover:not(:disabled) {
  background: #e5e5e5;
  color: #333;
}

.toolbar-btn:disabled {
  color: #ccc;
  cursor: not-allowed;
}

.toolbar-icon {
  font-size: 15px;
  font-weight: 700;
  font-family: Georgia, serif;
}

.toolbar-icon.italic {
  font-style: italic;
}

.toolbar-icon.underline {
  text-decoration: underline;
}

.toolbar-divider {
  width: 1px;
  height: 20px;
  background: #e0e0e0;
  margin: 0 6px;
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

  .toolbar {
    justify-content: center;
  }

  .btn-submit {
    width: 100%;
  }
}
</style>
