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
              <span v-if="comment.is_post_author" class="author-badge" title="Post Author">
                Author
              </span>
              <span v-if="comment.is_verified" class="verified-badge" title="Verified">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </span>
              <span class="comment-time">{{ formatDate(comment.created_at) }}</span>
            </div>
          </div>

          <!-- Content Text (rendered HTML with mentions) -->
          <div
            :class="['comment-content', { 'is-collapsed': !isExpanded && shouldCollapse }]"
            ref="commentContentRef"
            v-html="formattedContent"
          ></div>

          <!-- Expand/Collapse Toggle -->
          <button
            v-if="shouldCollapse"
            @click="toggleExpand"
            :class="['expand-toggle', { 'is-expanded': isExpanded }]"
          >
            <span class="expand-toggle-content">
              <span class="expand-toggle-text">{{ isExpanded ? 'Thu gọn' : 'Xem thêm' }}</span>
              <span class="expand-toggle-icon">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
              </span>
            </span>
          </button>

          <!-- Attachments -->
          <div v-if="comment.attachments && comment.attachments.length > 0" class="comment-attachments">
            <!-- Image Gallery -->
            <div v-if="imageAttachments.length > 0" :class="['image-gallery', `count-${Math.min(imageAttachments.length, 4)}`]">
              <div
                v-for="(img, index) in imageAttachments.slice(0, 4)"
                :key="img.id"
                class="gallery-item"
                @click="openLightbox(index)"
              >
                <img :src="img.url" :alt="img.filename" loading="lazy" />
                <span v-if="index === 3 && imageAttachments.length > 4" class="more-overlay">
                  +{{ imageAttachments.length - 4 }}
                </span>
              </div>
            </div>
            <!-- File Attachments -->
            <div v-if="fileAttachments.length > 0" class="file-attachments">
              <a
                v-for="file in fileAttachments"
                :key="file.id"
                :href="file.url"
                target="_blank"
                class="file-item"
              >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                  <polyline points="14 2 14 8 20 8" />
                </svg>
                <span class="file-info">
                  <span class="file-name">{{ file.filename }}</span>
                  <span class="file-size">{{ formatFileSize(file.size) }}</span>
                </span>
              </a>
            </div>
          </div>

          <!-- Lightbox Modal -->
          <Teleport to="body">
            <Transition name="lightbox">
              <div v-if="lightboxOpen" class="lightbox-overlay" @click.self="closeLightbox">
                <button class="lightbox-close" @click="closeLightbox">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                  </svg>
                </button>
                <button
                  v-if="lightboxIndex > 0"
                  class="lightbox-nav lightbox-prev"
                  @click="prevImage"
                >
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6" />
                  </svg>
                </button>
                <img
                  :src="imageAttachments[lightboxIndex]?.url"
                  :alt="imageAttachments[lightboxIndex]?.filename"
                  class="lightbox-image"
                />
                <button
                  v-if="lightboxIndex < imageAttachments.length - 1"
                  class="lightbox-nav lightbox-next"
                  @click="nextImage"
                >
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6" />
                  </svg>
                </button>
                <div class="lightbox-counter">
                  {{ lightboxIndex + 1 }} / {{ imageAttachments.length }}
                </div>
              </div>
            </Transition>
          </Teleport>

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
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useCommentsStore } from '../../stores/comments';
import { useSettingsStore } from '../../stores/settings';

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
const settingsStore = useSettingsStore();

// Refs
const commentContentRef = ref(null);

// Local state
const showMenu = ref(false);
const isExpanded = ref(false);
const contentHeight = ref(0);

// Content collapse threshold (in pixels)
const COLLAPSE_THRESHOLD = 200;

// Max reply depth (from settings)
const maxDepth = computed(() => settingsStore.commentsMaxDepth);

// Computed
const canReply = computed(() => {
  // Comments must be enabled AND within depth limit
  return settingsStore.commentsEnabled && props.depth < maxDepth.value - 1;
});
const loading = computed(() => commentsStore.loading);

// Check if content should be collapsible
const shouldCollapse = computed(() => contentHeight.value > COLLAPSE_THRESHOLD);

// Computed for attachments
const imageAttachments = computed(() => {
  if (!props.comment.attachments) return [];
  return props.comment.attachments.filter(att => att.type === 'image');
});

const fileAttachments = computed(() => {
  if (!props.comment.attachments) return [];
  return props.comment.attachments.filter(att => att.type === 'file');
});

// Format content with mentions and code blocks styled
// Preserves original order of text and code blocks
const formattedContent = computed(() => {
  let content = props.comment.content || '';

  // Process the content to preserve order and structure
  // Split into parts: code blocks and text segments
  const result = [];
  // Match <pre> blocks with any attributes, containing optional <code> tags
  const preRegex = /<pre[^>]*>([\s\S]*?)<\/pre>/gi;
  let lastIndex = 0;
  let match;

  while ((match = preRegex.exec(content)) !== null) {
    // Get text before this code block
    const textBefore = content.slice(lastIndex, match.index);
    // Strip only HTML tags (starting with letter or /), not PHP tags like <?php
    const cleanText = textBefore.replace(/<\/?[a-zA-Z][^>]*>/g, '').trim();
    if (cleanText) {
      result.push(`<div class="text-segment">${cleanText}</div>`);
    }

    // Extract code content from the <pre> block
    const preContent = match[1];
    let codeContent = '';

    // Check if there's a <code> tag inside
    const codeMatch = preContent.match(/<code[^>]*>([\s\S]*?)<\/code>/i);
    if (codeMatch && codeMatch[1]) {
      codeContent = codeMatch[1];
    } else {
      // No <code> tag, use pre content directly (strip only HTML tags, not PHP/XML tags)
      // Only strip tags that start with < followed by a letter or /
      codeContent = preContent.replace(/<\/?[a-zA-Z][^>]*>/g, '');
    }

    // Only add if there's actual code content
    if (codeContent.trim()) {
      result.push(`<pre class="code-block"><code>${codeContent}</code></pre>`);
    }

    lastIndex = match.index + match[0].length;
  }

  // Get any remaining text after the last code block
  const remainingText = content.slice(lastIndex);
  // Strip only HTML tags (starting with letter or /), not PHP tags like <?php
  const cleanRemaining = remainingText.replace(/<\/?[a-zA-Z][^>]*>/g, '').trim();
  if (cleanRemaining) {
    result.push(`<div class="text-segment">${cleanRemaining}</div>`);
  }

  // If no structured parts were found, try plain text
  let processedContent = '';
  if (result.length > 0) {
    processedContent = result.join('');
  } else {
    // Strip only HTML tags (starting with letter or /), not PHP tags like <?php
    const plainText = content.replace(/<\/?[a-zA-Z][^>]*>/g, '').trim();
    if (plainText) {
      processedContent = `<div class="text-segment">${plainText}</div>`;
    }
  }

  // Style mention spans if they exist
  processedContent = processedContent.replace(
    /<span class="mention"[^>]*>@([^<]+)<\/span>/g,
    '<span class="mention-tag">@$1</span>'
  );

  // Also handle plain @mentions from backend if not already wrapped
  if (props.comment.mentions && props.comment.mentions.length > 0) {
    props.comment.mentions.forEach(mention => {
      const name = mention.name || mention.username;
      if (name) {
        const regex = new RegExp(`@${name}(?![^<]*>)`, 'g');
        processedContent = processedContent.replace(regex, `<span class="mention-tag">@${name}</span>`);
      }
    });
  }

  return processedContent;
});

// Lightbox state
const lightboxOpen = ref(false);
const lightboxIndex = ref(0);

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

// Toggle expand/collapse
const toggleExpand = () => {
  isExpanded.value = !isExpanded.value;
};

// Measure content height
const measureContentHeight = () => {
  if (commentContentRef.value) {
    // Temporarily remove max-height to measure full height
    const el = commentContentRef.value;
    const originalMaxHeight = el.style.maxHeight;
    const originalOverflow = el.style.overflow;
    el.style.maxHeight = 'none';
    el.style.overflow = 'visible';
    contentHeight.value = el.scrollHeight;
    el.style.maxHeight = originalMaxHeight;
    el.style.overflow = originalOverflow;
  }
};

const handleReport = () => {
  showMenu.value = false;
  // Implement report functionality
  alert('Report functionality coming soon');
};

// Lightbox methods
const openLightbox = (index) => {
  lightboxIndex.value = index;
  lightboxOpen.value = true;
  document.body.style.overflow = 'hidden';
};

const closeLightbox = () => {
  lightboxOpen.value = false;
  document.body.style.overflow = '';
};

const nextImage = () => {
  if (lightboxIndex.value < imageAttachments.value.length - 1) {
    lightboxIndex.value++;
  }
};

const prevImage = () => {
  if (lightboxIndex.value > 0) {
    lightboxIndex.value--;
  }
};

const handleLightboxKeydown = (e) => {
  if (!lightboxOpen.value) return;
  if (e.key === 'Escape') closeLightbox();
  if (e.key === 'ArrowRight') nextImage();
  if (e.key === 'ArrowLeft') prevImage();
};

// Format file size
const formatFileSize = (bytes) => {
  if (!bytes) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
};

// Close menu when clicking outside
const handleClickOutside = (e) => {
  if (!e.target.closest('.more-menu')) {
    showMenu.value = false;
  }
};

// Setup copy buttons for code blocks
const setupCodeBlockCopyButtons = () => {
  if (!commentContentRef.value) return;

  const codeBlocks = commentContentRef.value.querySelectorAll('.code-block');
  codeBlocks.forEach((block) => {
    // Check if copy button already exists
    if (block.querySelector('.code-copy-btn')) return;

    // Skip empty code blocks (no code element or empty code)
    const codeEl = block.querySelector('code');
    if (!codeEl || !codeEl.textContent?.trim()) {
      // Hide empty code blocks
      block.style.display = 'none';
      return;
    }

    // Create copy button
    const copyBtn = document.createElement('button');
    copyBtn.className = 'code-copy-btn';
    copyBtn.innerHTML = `
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
        <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"></path>
      </svg>
      <span>Copy</span>
    `;

    copyBtn.addEventListener('click', async () => {
      const code = block.querySelector('code');
      if (code) {
        try {
          await navigator.clipboard.writeText(code.textContent || '');
          copyBtn.innerHTML = `
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            <span>Copied!</span>
          `;
          copyBtn.classList.add('copied');
          setTimeout(() => {
            copyBtn.innerHTML = `
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"></path>
              </svg>
              <span>Copy</span>
            `;
            copyBtn.classList.remove('copied');
          }, 2000);
        } catch (err) {
          console.error('Failed to copy:', err);
        }
      }
    });

    // Insert copy button into the code block header area
    block.insertBefore(copyBtn, block.firstChild);
  });
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
  document.addEventListener('keydown', handleLightboxKeydown);
  // Setup copy buttons and measure content height after content is rendered
  nextTick(() => {
    setupCodeBlockCopyButtons();
    measureContentHeight();
  });
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  document.removeEventListener('keydown', handleLightboxKeydown);
  // Ensure body scroll is restored
  document.body.style.overflow = '';
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

.author-badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
  color: #fff;
  border-radius: 10px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
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
  position: relative;
  overflow: hidden;
  transition: max-height 0.3s ease;
}

/* Collapsed state */
.comment-content.is-collapsed {
  max-height: 200px;
}

/* Gradient fade overlay for collapsed content */
.comment-content.is-collapsed::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 70px;
  background: linear-gradient(
    to bottom,
    rgba(255, 255, 255, 0) 0%,
    rgba(255, 255, 255, 0.95) 70%,
    rgba(255, 255, 255, 1) 100%
  );
  pointer-events: none;
  z-index: 1;
}

/* Expand/Collapse toggle button */
.expand-toggle {
  display: inline-flex;
  align-items: center;
  padding: 0;
  margin-bottom: 12px;
  background: transparent;
  border: none;
  font-size: 13px;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  transition: color 0.15s ease;
}

.expand-toggle:hover {
  color: #1a1a1a;
}

.expand-toggle-content {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.expand-toggle-text {
  position: relative;
}

.expand-toggle-text::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  bottom: -1px;
  height: 1px;
  background: currentColor;
  opacity: 0;
  transition: opacity 0.15s ease;
}

.expand-toggle:hover .expand-toggle-text::after {
  opacity: 0.4;
}

.expand-toggle-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: transform 0.2s ease;
}

.expand-toggle.is-expanded .expand-toggle-icon {
  transform: rotate(180deg);
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
  margin-top: 16px;
  position: relative;
}

/* Reply items positioning */
.comment-item.is-reply {
  position: relative;
  margin-top: 16px;
}

.comment-item.is-reply:first-child {
  margin-top: 0;
}

/* Thread connector - L-shaped line for each reply */
.thread-connector {
  position: absolute;
  left: -32px;
  top: -16px;
  width: 20px;
  height: 36px;
  border-left: 2px solid #e0e0e0;
  border-bottom: 2px solid #e0e0e0;
  border-bottom-left-radius: 12px;
  border-right: none;
  border-top: none;
  background: #fff;
}

/* Vertical continuation line for non-last siblings */
.comment-item.is-reply:not(:last-child)::after {
  content: '';
  position: absolute;
  left: -32px;
  top: 20px;
  bottom: -16px;
  width: 2px;
  background: #e0e0e0;
  z-index: 0;
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

  /* Collapsed state - smaller on mobile */
  .comment-content.is-collapsed {
    max-height: 150px;
  }

  .comment-content.is-collapsed::after {
    height: 50px;
  }

  /* Expand toggle - mobile optimized */
  .expand-toggle {
    font-size: 12px;
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
    width: 14px;
    height: 30px;
    top: -12px;
  }

  .comment-item.is-reply:not(:last-child)::after {
    left: -24px;
    top: 18px;
    bottom: -12px;
  }
}

/* Comment Attachments */
.comment-attachments {
  margin-top: 12px;
  margin-bottom: 12px;
}

/* Image Gallery */
.image-gallery {
  display: grid;
  gap: 4px;
  border-radius: 10px;
  overflow: hidden;
  margin-bottom: 8px;
  max-width: 400px;
}

.image-gallery.count-1 {
  grid-template-columns: 1fr;
  max-width: 280px;
}

.image-gallery.count-2 {
  grid-template-columns: 1fr 1fr;
  max-width: 320px;
}

.image-gallery.count-3 {
  grid-template-columns: repeat(3, 1fr);
  max-width: 360px;
}

.image-gallery.count-4 {
  grid-template-columns: repeat(4, 1fr);
  max-width: 400px;
}

.gallery-item {
  position: relative;
  aspect-ratio: 1;
  cursor: pointer;
  overflow: hidden;
  background: #f0f0f0;
}

.image-gallery.count-1 .gallery-item {
  aspect-ratio: 4/3;
  max-height: 180px;
}

.image-gallery.count-2 .gallery-item {
  max-height: 140px;
}

.image-gallery.count-3 .gallery-item,
.image-gallery.count-4 .gallery-item {
  max-height: 90px;
}

.gallery-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.2s;
}

.gallery-item:hover img {
  transform: scale(1.05);
}

.more-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.6);
  color: #fff;
  font-size: 18px;
  font-weight: 600;
}

/* File Attachments */
.file-attachments {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.file-item {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: #f5f5f5;
  border-radius: 8px;
  color: #333;
  text-decoration: none;
  font-size: 13px;
  transition: background 0.15s;
  max-width: fit-content;
}

.file-item:hover {
  background: #eee;
}

.file-item svg {
  flex-shrink: 0;
  color: #666;
}

.file-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.file-name {
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 200px;
}

.file-size {
  font-size: 11px;
  color: #888;
}

/* Text Segment in Content */
.comment-content :deep(.text-segment) {
  display: block;
  margin: 8px 0;
  line-height: 1.6;
}

.comment-content :deep(.text-segment):first-child {
  margin-top: 0;
}

.comment-content :deep(.text-segment):last-child {
  margin-bottom: 0;
}

/* Mention Tags in Content */
.comment-content :deep(.mention-tag) {
  display: inline;
  padding: 2px 6px;
  background: #e8f4fc;
  color: #1d72b8;
  border-radius: 4px;
  font-weight: 500;
  font-size: 13px;
}

/* Code Block in Comment Content */
.comment-content :deep(.code-block) {
  position: relative;
  display: block;
  margin: 12px 0;
  padding: 0;
  background: #1e1e1e;
  border-radius: 8px;
  border: 1px solid #333;
  overflow: hidden;
}

.comment-content :deep(.code-block)::before {
  content: 'Code';
  display: block;
  padding: 10px 14px;
  background: #2d2d2d;
  border-bottom: 1px solid #333;
  color: #888;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Copy Button */
.comment-content :deep(.code-copy-btn) {
  position: absolute;
  top: 6px;
  right: 8px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 5px 10px;
  background: #3d3d3d;
  border: 1px solid #4d4d4d;
  border-radius: 5px;
  color: #aaa;
  font-size: 11px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
  z-index: 2;
}

.comment-content :deep(.code-copy-btn:hover) {
  background: #4d4d4d;
  color: #fff;
  border-color: #5d5d5d;
}

.comment-content :deep(.code-copy-btn.copied) {
  background: #22c55e;
  border-color: #16a34a;
  color: #fff;
}

.comment-content :deep(.code-copy-btn svg) {
  flex-shrink: 0;
}

.comment-content :deep(.code-block code) {
  display: block;
  padding: 14px 16px;
  margin: 0;
  color: #d4d4d4;
  font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', 'Consolas', monospace;
  font-size: 13px;
  line-height: 1.5;
  white-space: pre;
  overflow-x: auto;
  overflow-y: auto;
  max-height: 400px;
  tab-size: 2;
}

/* Custom scrollbar for code blocks */
.comment-content :deep(.code-block code)::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}

.comment-content :deep(.code-block code)::-webkit-scrollbar-track {
  background: #2d2d2d;
  border-radius: 3px;
}

.comment-content :deep(.code-block code)::-webkit-scrollbar-thumb {
  background: #555;
  border-radius: 3px;
}

.comment-content :deep(.code-block code)::-webkit-scrollbar-thumb:hover {
  background: #666;
}

.comment-content :deep(.code-block code)::-webkit-scrollbar-corner {
  background: #2d2d2d;
}

/* Hide ::before when copy button exists (copy button replaces header) */
.comment-content :deep(.code-block:has(.code-copy-btn))::before {
  display: none;
}

/* Hide empty code blocks (no code content) */
.comment-content :deep(.code-block:not(:has(code))),
.comment-content :deep(.code-block:has(code:empty)) {
  display: none;
}

/* Ensure no extra margin at bottom */
.comment-content :deep(.code-block) {
  margin-bottom: 0;
}

.comment-content :deep(.code-block):first-child {
  margin-top: 0;
}

/* Syntax Highlighting Classes */
.comment-content :deep(.code-block code .keyword) {
  color: #c586c0;
}

.comment-content :deep(.code-block code .string) {
  color: #ce9178;
}

.comment-content :deep(.code-block code .comment) {
  color: #6a9955;
  font-style: italic;
}

.comment-content :deep(.code-block code .function) {
  color: #dcdcaa;
}

.comment-content :deep(.code-block code .variable) {
  color: #9cdcfe;
}

.comment-content :deep(.code-block code .number) {
  color: #b5cea8;
}

.comment-content :deep(.code-block code br) {
  display: block;
  content: '';
  margin: 0;
}

/* Lightbox Overlay */
.lightbox-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.9);
}

.lightbox-close {
  position: absolute;
  top: 16px;
  right: 16px;
  padding: 8px;
  background: rgba(255, 255, 255, 0.1);
  border: none;
  border-radius: 50%;
  color: #fff;
  cursor: pointer;
  transition: background 0.2s;
  z-index: 10;
}

.lightbox-close:hover {
  background: rgba(255, 255, 255, 0.2);
}

.lightbox-nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  padding: 12px;
  background: rgba(255, 255, 255, 0.1);
  border: none;
  border-radius: 50%;
  color: #fff;
  cursor: pointer;
  transition: background 0.2s;
  z-index: 10;
}

.lightbox-nav:hover {
  background: rgba(255, 255, 255, 0.2);
}

.lightbox-prev {
  left: 16px;
}

.lightbox-next {
  right: 16px;
}

.lightbox-image {
  max-width: 90vw;
  max-height: 90vh;
  object-fit: contain;
  border-radius: 4px;
}

.lightbox-counter {
  position: absolute;
  bottom: 16px;
  left: 50%;
  transform: translateX(-50%);
  padding: 6px 12px;
  background: rgba(0, 0, 0, 0.6);
  border-radius: 16px;
  color: #fff;
  font-size: 13px;
}

/* Lightbox Transition */
.lightbox-enter-active,
.lightbox-leave-active {
  transition: opacity 0.2s ease;
}

.lightbox-enter-from,
.lightbox-leave-to {
  opacity: 0;
}

/* Responsive for attachments */
@media (max-width: 640px) {
  .image-gallery {
    max-width: 280px;
  }

  .image-gallery.count-1 {
    max-width: 220px;
  }

  .image-gallery.count-1 .gallery-item {
    max-height: 140px;
  }

  .image-gallery.count-2 {
    max-width: 240px;
  }

  .image-gallery.count-2 .gallery-item {
    max-height: 100px;
  }

  .image-gallery.count-3,
  .image-gallery.count-4 {
    max-width: 280px;
  }

  .image-gallery.count-3 .gallery-item,
  .image-gallery.count-4 .gallery-item {
    max-height: 70px;
  }

  .more-overlay {
    font-size: 14px;
  }

  .file-name {
    max-width: 150px;
  }

  /* Code block mobile */
  .comment-content :deep(.code-block) {
    margin: 10px 0;
    border-radius: 6px;
  }

  .comment-content :deep(.code-block)::before {
    font-size: 10px;
    padding: 8px 12px;
  }

  .comment-content :deep(.code-copy-btn) {
    padding: 4px 8px;
    font-size: 10px;
    top: 5px;
    right: 6px;
  }

  .comment-content :deep(.code-copy-btn span) {
    display: none;
  }

  .comment-content :deep(.code-block code) {
    padding: 12px;
    font-size: 12px;
    line-height: 1.4;
  }

  .lightbox-nav {
    padding: 8px;
  }

  .lightbox-prev {
    left: 8px;
  }

  .lightbox-next {
    right: 8px;
  }
}
</style>
