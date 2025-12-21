<template>
  <div
    :class="['rich-comment-editor', { 'is-focused': isFocused, 'is-reply': isReply }]"
    @dragover.prevent="handleDragOver"
    @dragleave.prevent="handleDragLeave"
    @drop.prevent="handleDrop"
  >
    <!-- Drag Overlay -->
    <Transition name="fade">
      <div v-if="isDragging" class="drag-overlay">
        <div class="drag-content">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
            <polyline points="17 8 12 3 7 8" />
            <line x1="12" y1="3" x2="12" y2="15" />
          </svg>
          <span>Drop files here</span>
        </div>
      </div>
    </Transition>

    <!-- Editor Area -->
    <div class="editor-container">
      <!-- Content Editable -->
      <div
        ref="editorRef"
        class="editor-content"
        contenteditable="true"
        :data-placeholder="placeholder"
        @input="handleInput"
        @keydown="handleKeydown"
        @focus="handleFocus"
        @blur="handleBlur"
        @paste="handlePaste"
      ></div>

      <!-- Mention Dropdown -->
      <Transition name="dropdown">
        <div
          v-if="showMentionDropdown && mentionResults.length > 0"
          ref="mentionDropdownRef"
          class="mention-dropdown"
          :style="mentionDropdownStyle"
        >
          <div
            v-for="(user, index) in mentionResults"
            :key="user.id"
            :class="['mention-item', { active: mentionActiveIndex === index }]"
            @click="selectMention(user)"
            @mouseenter="mentionActiveIndex = index"
          >
            <img
              v-if="user.avatar"
              :src="user.avatar"
              :alt="user.name"
              class="mention-avatar"
            />
            <div v-else class="mention-avatar-placeholder">
              {{ getInitials(user.name) }}
            </div>
            <div class="mention-info">
              <span class="mention-name">{{ user.name }}</span>
              <span v-if="user.username" class="mention-username">@{{ user.username }}</span>
            </div>
          </div>
        </div>
      </Transition>

      <!-- Emoji Picker -->
      <Transition name="dropdown">
        <div v-if="showEmojiPicker" ref="emojiPickerRef" class="emoji-picker">
          <div class="emoji-categories">
            <button
              v-for="cat in emojiCategories"
              :key="cat.id"
              :class="['emoji-cat-btn', { active: activeEmojiCategory === cat.id }]"
              @click="activeEmojiCategory = cat.id"
              :title="cat.name"
            >
              {{ cat.icon }}
            </button>
          </div>
          <div class="emoji-grid">
            <button
              v-for="emoji in currentEmojis"
              :key="emoji"
              class="emoji-btn"
              @click="insertEmoji(emoji)"
            >
              {{ emoji }}
            </button>
          </div>
        </div>
      </Transition>
    </div>

    <!-- Attachments Preview -->
    <div v-if="attachments.length > 0" class="attachments-preview">
      <div
        v-for="(file, index) in attachments"
        :key="file.id || index"
        :class="['attachment-item', file.type]"
      >
        <div v-if="file.type === 'image'" class="attachment-image">
          <img :src="file.url || file.preview" :alt="file.filename" />
        </div>
        <div v-else class="attachment-file">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
            <polyline points="14 2 14 8 20 8" />
          </svg>
          <span class="file-name">{{ file.filename }}</span>
          <span class="file-size">{{ file.size_human || formatSize(file.size) }}</span>
        </div>
        <!-- Upload Progress -->
        <div v-if="file.uploading" class="upload-progress">
          <div class="progress-bar" :style="{ width: file.progress + '%' }"></div>
        </div>
        <!-- Remove Button -->
        <button
          v-if="!file.uploading"
          class="attachment-remove"
          @click="removeAttachment(index)"
          title="Remove"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="editor-toolbar">
      <div class="toolbar-left">
        <!-- Format Buttons -->
        <button
          type="button"
          class="toolbar-btn"
          title="Bold (Ctrl+B)"
          @click="applyFormat('bold')"
        >
          <span class="format-icon bold">B</span>
        </button>
        <button
          type="button"
          class="toolbar-btn"
          title="Italic (Ctrl+I)"
          @click="applyFormat('italic')"
        >
          <span class="format-icon italic">I</span>
        </button>
        <button
          type="button"
          class="toolbar-btn"
          title="Underline (Ctrl+U)"
          @click="applyFormat('underline')"
        >
          <span class="format-icon underline">U</span>
        </button>

        <span class="toolbar-divider"></span>

        <!-- Attach File -->
        <button
          type="button"
          class="toolbar-btn"
          title="Attach file"
          @click="triggerFileInput"
          :disabled="attachments.length >= maxFiles"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48" />
          </svg>
        </button>

        <!-- Add Image -->
        <button
          type="button"
          class="toolbar-btn"
          title="Add image"
          @click="triggerImageInput"
          :disabled="attachments.length >= maxFiles"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
            <circle cx="8.5" cy="8.5" r="1.5" />
            <polyline points="21 15 16 10 5 21" />
          </svg>
        </button>

        <!-- Emoji -->
        <button
          type="button"
          class="toolbar-btn"
          title="Add emoji"
          @click="toggleEmojiPicker"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" />
            <path d="M8 14s1.5 2 4 2 4-2 4-2" />
            <line x1="9" y1="9" x2="9.01" y2="9" />
            <line x1="15" y1="9" x2="15.01" y2="9" />
          </svg>
        </button>

        <!-- Code Block -->
        <button
          type="button"
          class="toolbar-btn"
          title="Insert code block"
          @click="insertCodeBlock"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="16 18 22 12 16 6" />
            <polyline points="8 6 2 12 8 18" />
          </svg>
        </button>
      </div>

      <div class="toolbar-right">
        <span v-if="attachments.length > 0" class="file-count">
          {{ attachments.length }}/{{ maxFiles }} files
        </span>
      </div>
    </div>

    <!-- Hidden File Inputs -->
    <input
      ref="fileInputRef"
      type="file"
      class="hidden-input"
      multiple
      :accept="acceptAllFiles"
      @change="handleFileSelect($event, 'file')"
    />
    <input
      ref="imageInputRef"
      type="file"
      class="hidden-input"
      multiple
      accept="image/jpeg,image/png,image/gif,image/webp"
      @change="handleFileSelect($event, 'image')"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { comments, users } from '../../services/blog';
import { debounce } from '../../utils/debounce';

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Add comment...',
  },
  isReply: {
    type: Boolean,
    default: false,
  },
  maxFiles: {
    type: Number,
    default: 9,
  },
  maxFileSize: {
    type: Number,
    default: 15 * 1024 * 1024, // 15MB
  },
});

const emit = defineEmits(['update:modelValue', 'attachments-change', 'mention']);

// Refs
const editorRef = ref(null);
const fileInputRef = ref(null);
const imageInputRef = ref(null);
const mentionDropdownRef = ref(null);
const emojiPickerRef = ref(null);

// State
const isFocused = ref(false);
const isDragging = ref(false);
const attachments = ref([]);

// Mention state
const showMentionDropdown = ref(false);
const mentionQuery = ref('');
const mentionResults = ref([]);
const mentionActiveIndex = ref(0);
const mentionStartPosition = ref(0);
const mentionDropdownStyle = ref({});

// Emoji state
const showEmojiPicker = ref(false);
const activeEmojiCategory = ref('smileys');

// File types
const acceptAllFiles = 'image/jpeg,image/png,image/gif,image/webp,application/pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip,.rar';

// Emoji data
const emojiCategories = [
  { id: 'smileys', name: 'Smileys', icon: '😀' },
  { id: 'gestures', name: 'Gestures', icon: '👋' },
  { id: 'hearts', name: 'Hearts', icon: '❤️' },
  { id: 'objects', name: 'Objects', icon: '🎉' },
  { id: 'nature', name: 'Nature', icon: '🌟' },
];

const emojis = {
  smileys: ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '😉', '😊', '😇', '🥰', '😍', '🤩', '😘', '😗', '😚', '😙', '🥲', '😋', '😛', '😜', '🤪', '😝', '🤑', '🤗', '🤭', '🤫', '🤔', '🤐', '🤨', '😐', '😑', '😶', '😏', '😒', '🙄', '😬', '🤥', '😌', '😔', '😪', '🤤', '😴', '😷', '🤒', '🤕'],
  gestures: ['👋', '🤚', '🖐️', '✋', '🖖', '👌', '🤌', '🤏', '✌️', '🤞', '🤟', '🤘', '🤙', '👈', '👉', '👆', '🖕', '👇', '☝️', '👍', '👎', '✊', '👊', '🤛', '🤜', '👏', '🙌', '👐', '🤲', '🤝', '🙏', '✍️', '💪', '🦾', '🦿', '🦵', '🦶', '👂', '🦻', '👃', '🧠', '🫀', '🫁', '🦷', '🦴', '👀', '👁️', '👅', '👄'],
  hearts: ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '💟', '♥️', '💋', '💌', '💐', '🌹', '🥀', '🌺', '🌸', '💮', '🏵️', '🌻', '🌼', '🌷'],
  objects: ['🎉', '🎊', '🎈', '🎁', '🏆', '🥇', '🥈', '🥉', '⚽', '🏀', '🏈', '⚾', '🥎', '🎾', '🏐', '🎱', '🎮', '🕹️', '🎲', '🎯', '🎳', '🎸', '🎹', '🎺', '🎻', '🥁', '🎬', '🎨', '🎭', '🎪', '🎤', '🎧', '🎼', '🎵', '🎶', '📷', '📸', '📹', '🎥', '📽️', '📺', '📻', '🎙️', '💻', '🖥️', '📱', '📞', '☎️', '💡'],
  nature: ['🌟', '⭐', '🌙', '☀️', '🌤️', '⛅', '🌥️', '☁️', '🌦️', '🌧️', '⛈️', '🌩️', '🌨️', '❄️', '☃️', '⛄', '🌬️', '💨', '🌪️', '🌈', '🔥', '💧', '🌊', '🌱', '🌲', '🌳', '🌴', '🌵', '🌾', '🌿', '☘️', '🍀', '🍁', '🍂', '🍃', '🌍', '🌎', '🌏', '🌐', '🗺️', '🧭', '🏔️', '⛰️', '🌋', '🗻', '🏕️', '🏖️', '🏜️', '🏝️'],
};

const currentEmojis = computed(() => emojis[activeEmojiCategory.value] || []);

// Methods
const handleFocus = () => {
  isFocused.value = true;
};

const handleBlur = () => {
  isFocused.value = false;
  // Delay hiding mention dropdown to allow click
  setTimeout(() => {
    if (!mentionDropdownRef.value?.contains(document.activeElement)) {
      showMentionDropdown.value = false;
    }
  }, 200);
};

const handleInput = () => {
  const content = editorRef.value?.innerHTML || '';
  emit('update:modelValue', content);
  checkForMention();
};

const handleKeydown = (e) => {
  // Format shortcuts
  if (e.ctrlKey || e.metaKey) {
    switch (e.key.toLowerCase()) {
      case 'b':
        e.preventDefault();
        applyFormat('bold');
        break;
      case 'i':
        e.preventDefault();
        applyFormat('italic');
        break;
      case 'u':
        e.preventDefault();
        applyFormat('underline');
        break;
    }
  }

  // Mention navigation
  if (showMentionDropdown.value && mentionResults.value.length > 0) {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      mentionActiveIndex.value = (mentionActiveIndex.value + 1) % mentionResults.value.length;
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      mentionActiveIndex.value = mentionActiveIndex.value === 0
        ? mentionResults.value.length - 1
        : mentionActiveIndex.value - 1;
    } else if (e.key === 'Enter' || e.key === 'Tab') {
      e.preventDefault();
      selectMention(mentionResults.value[mentionActiveIndex.value]);
    } else if (e.key === 'Escape') {
      showMentionDropdown.value = false;
    }
  }
};

const handlePaste = async (e) => {
  // Check for pasted files
  const items = e.clipboardData?.items;
  if (items) {
    const files = [];
    for (const item of items) {
      if (item.kind === 'file') {
        const file = item.getAsFile();
        if (file) files.push(file);
      }
    }
    if (files.length > 0) {
      e.preventDefault();
      await uploadFiles(files);
      return;
    }
  }

  // Paste as plain text
  e.preventDefault();
  const text = e.clipboardData?.getData('text/plain') || '';
  document.execCommand('insertText', false, text);
};

// Format methods
const applyFormat = (format) => {
  editorRef.value?.focus();
  document.execCommand(format, false, null);
};

// File handling
const triggerFileInput = () => fileInputRef.value?.click();
const triggerImageInput = () => imageInputRef.value?.click();

const handleFileSelect = (e, type) => {
  const files = Array.from(e.target.files || []);
  if (files.length > 0) {
    uploadFiles(files);
  }
  e.target.value = '';
};

const handleDragOver = (e) => {
  isDragging.value = true;
};

const handleDragLeave = (e) => {
  isDragging.value = false;
};

const handleDrop = async (e) => {
  isDragging.value = false;
  const files = Array.from(e.dataTransfer?.files || []);
  if (files.length > 0) {
    await uploadFiles(files);
  }
};

const uploadFiles = async (files) => {
  // Check remaining slots
  const remaining = props.maxFiles - attachments.value.length;
  if (remaining <= 0) {
    alert(`Maximum ${props.maxFiles} files allowed`);
    return;
  }

  const filesToUpload = files.slice(0, remaining);

  // Validate file sizes
  for (const file of filesToUpload) {
    if (file.size > props.maxFileSize) {
      alert(`File "${file.name}" is too large. Maximum size: 15MB`);
      return;
    }
  }

  // Add files with preview
  const newAttachments = filesToUpload.map((file, index) => {
    const isImage = file.type.startsWith('image/');
    return {
      id: `temp_${Date.now()}_${index}`,
      file,
      filename: file.name,
      size: file.size,
      type: isImage ? 'image' : 'file',
      preview: isImage ? URL.createObjectURL(file) : null,
      uploading: true,
      progress: 0,
    };
  });

  attachments.value.push(...newAttachments);

  // Simulate progress animation (fetch doesn't support real progress)
  let progressInterval = null;
  const simulateProgress = () => {
    progressInterval = setInterval(() => {
      newAttachments.forEach((att) => {
        if (att.uploading && att.progress < 90) {
          att.progress += Math.random() * 15;
          if (att.progress > 90) att.progress = 90;
        }
      });
    }, 200);
  };
  simulateProgress();

  // Upload to server
  try {
    const response = await comments.uploadFiles(filesToUpload);

    // Clear progress simulation
    if (progressInterval) clearInterval(progressInterval);

    if (response.data.success) {
      const uploadedFiles = response.data.data.files;

      // Update attachments with server data
      newAttachments.forEach((att, index) => {
        const uploaded = uploadedFiles[index];
        if (uploaded) {
          att.url = uploaded.url;
          att.path = uploaded.path;
          att.mime_type = uploaded.mime_type;
        }
        // Mark as done uploading
        att.progress = 100;
        att.uploading = false;
      });

      emitAttachmentsChange();
    } else {
      // Upload returned success: false
      throw new Error(response.data.message || 'Upload failed');
    }
  } catch (error) {
    // Clear progress simulation
    if (progressInterval) clearInterval(progressInterval);

    console.error('Upload failed:', error);
    // Remove failed uploads
    newAttachments.forEach((att) => {
      const idx = attachments.value.findIndex((a) => a.id === att.id);
      if (idx !== -1) attachments.value.splice(idx, 1);
    });
    const errorMsg = error.response?.data?.message || error.message || 'Failed to upload files. Please try again.';
    alert(errorMsg);
  }
};

const removeAttachment = async (index) => {
  const attachment = attachments.value[index];

  // Delete from server if already uploaded
  if (attachment.path) {
    try {
      await comments.deleteFile(attachment.path);
    } catch (error) {
      console.error('Failed to delete file:', error);
    }
  }

  // Revoke object URL if exists
  if (attachment.preview) {
    URL.revokeObjectURL(attachment.preview);
  }

  attachments.value.splice(index, 1);
  emitAttachmentsChange();
};

const formatSize = (bytes) => {
  const units = ['B', 'KB', 'MB', 'GB'];
  let i = 0;
  while (bytes >= 1024 && i < units.length - 1) {
    bytes /= 1024;
    i++;
  }
  return `${bytes.toFixed(1)} ${units[i]}`;
};

const emitAttachmentsChange = () => {
  emit('attachments-change', attachments.value.filter((a) => !a.uploading));
};

// Mention methods
const checkForMention = debounce(async () => {
  const selection = window.getSelection();
  if (!selection || selection.rangeCount === 0) return;

  const range = selection.getRangeAt(0);
  const text = range.startContainer.textContent || '';
  const cursorPos = range.startOffset;

  // Find @ before cursor
  let atPos = -1;
  for (let i = cursorPos - 1; i >= 0; i--) {
    if (text[i] === '@') {
      atPos = i;
      break;
    }
    if (text[i] === ' ' || text[i] === '\n') break;
  }

  if (atPos >= 0) {
    const query = text.substring(atPos + 1, cursorPos);
    if (query.length >= 2) {
      mentionQuery.value = query;
      mentionStartPosition.value = atPos;
      await searchUsers(query);
    } else if (query.length === 0) {
      showMentionDropdown.value = false;
    }
  } else {
    showMentionDropdown.value = false;
  }
}, 300);

const searchUsers = async (query) => {
  try {
    const response = await users.search(query, 10);
    if (response.data.success) {
      mentionResults.value = response.data.data;
      mentionActiveIndex.value = 0;
      showMentionDropdown.value = mentionResults.value.length > 0;

      if (showMentionDropdown.value) {
        await nextTick();
        positionMentionDropdown();
      }
    }
  } catch (error) {
    console.error('User search failed:', error);
    showMentionDropdown.value = false;
  }
};

const positionMentionDropdown = () => {
  const selection = window.getSelection();
  if (!selection || selection.rangeCount === 0) return;

  const range = selection.getRangeAt(0);
  const rect = range.getBoundingClientRect();
  const editorRect = editorRef.value?.getBoundingClientRect();

  if (editorRect) {
    mentionDropdownStyle.value = {
      top: `${rect.bottom - editorRect.top + 8}px`,
      left: `${Math.max(0, rect.left - editorRect.left)}px`,
    };
  }
};

const selectMention = (user) => {
  const selection = window.getSelection();
  if (!selection || selection.rangeCount === 0) return;

  const range = selection.getRangeAt(0);
  const textNode = range.startContainer;

  if (textNode.nodeType === Node.TEXT_NODE) {
    const text = textNode.textContent || '';
    const beforeMention = text.substring(0, mentionStartPosition.value);
    const afterMention = text.substring(range.startOffset);

    // Create mention span
    const mentionSpan = document.createElement('span');
    mentionSpan.className = 'mention-tag';
    mentionSpan.contentEditable = 'false';
    mentionSpan.dataset.userId = user.id;
    mentionSpan.textContent = `@${user.username || user.name}`;

    // Replace text
    const parent = textNode.parentNode;
    const beforeText = document.createTextNode(beforeMention);
    const afterText = document.createTextNode(' ' + afterMention);

    parent.insertBefore(beforeText, textNode);
    parent.insertBefore(mentionSpan, textNode);
    parent.insertBefore(afterText, textNode);
    parent.removeChild(textNode);

    // Set cursor after mention
    const newRange = document.createRange();
    newRange.setStart(afterText, 1);
    newRange.collapse(true);
    selection.removeAllRanges();
    selection.addRange(newRange);
  }

  showMentionDropdown.value = false;
  emit('mention', user);
  handleInput();
};

const insertCodeBlock = () => {
  editorRef.value?.focus();

  // Create a wrapper div with pre > code structure
  const wrapper = document.createElement('div');
  wrapper.className = 'code-block-wrapper';
  wrapper.contentEditable = 'false';

  const pre = document.createElement('pre');
  pre.className = 'code-block';

  const code = document.createElement('code');
  code.textContent = '';

  // Use textarea for input to avoid contentEditable issues
  const textarea = document.createElement('textarea');
  textarea.className = 'code-input';
  textarea.placeholder = 'Paste or type code here...';
  textarea.rows = 6;
  textarea.spellcheck = false;
  textarea.addEventListener('input', (e) => {
    // Update the code element for display/submission
    code.textContent = e.target.value;
    handleInput();
  });

  pre.appendChild(textarea);
  wrapper.appendChild(pre);

  // Insert at cursor position
  const selection = window.getSelection();
  if (selection && selection.rangeCount > 0) {
    const range = selection.getRangeAt(0);
    range.deleteContents();

    // Create a container div to ensure code block is on its own line
    const container = document.createElement('div');
    container.className = 'code-block-container';
    container.appendChild(wrapper);

    // Check if we're inside a text node and need to split it
    const startContainer = range.startContainer;
    if (startContainer.nodeType === Node.TEXT_NODE && startContainer.textContent) {
      // Split the text node at cursor position
      const textBefore = startContainer.textContent.substring(0, range.startOffset);
      const textAfter = startContainer.textContent.substring(range.startOffset);

      // Create elements for before and after text
      const beforeDiv = document.createElement('div');
      beforeDiv.textContent = textBefore;

      const afterDiv = document.createElement('div');
      afterDiv.innerHTML = textAfter || '<br>';

      // Replace the text node with: beforeDiv + container + afterDiv
      const parent = startContainer.parentNode;
      parent.insertBefore(beforeDiv, startContainer);
      parent.insertBefore(container, startContainer);
      parent.insertBefore(afterDiv, startContainer);
      parent.removeChild(startContainer);
    } else {
      // Insert the container directly
      range.insertNode(container);

      // Add a new paragraph after for continuing text
      const afterP = document.createElement('div');
      afterP.innerHTML = '<br>';
      container.after(afterP);
    }

    // Focus the textarea
    setTimeout(() => textarea.focus(), 0);
  }

  handleInput();
};

const getInitials = (name) => {
  return name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
};

// Emoji methods
const toggleEmojiPicker = () => {
  showEmojiPicker.value = !showEmojiPicker.value;
};

const insertEmoji = (emoji) => {
  editorRef.value?.focus();
  document.execCommand('insertText', false, emoji);
  showEmojiPicker.value = false;
};

// Close dropdowns on outside click
const handleOutsideClick = (e) => {
  if (emojiPickerRef.value && !emojiPickerRef.value.contains(e.target)) {
    showEmojiPicker.value = false;
  }
};

// Helper to convert textarea code blocks to pre>code for output
const getProcessedContent = () => {
  if (!editorRef.value) return '';

  // Clone the editor to manipulate
  const clone = editorRef.value.cloneNode(true);

  // Convert code-block-wrapper with textarea to pre>code
  clone.querySelectorAll('.code-block-wrapper').forEach((wrapper) => {
    const textarea = wrapper.querySelector('.code-input');
    const codeValue = textarea?.value || '';

    if (codeValue.trim()) {
      // Create proper pre>code structure
      const pre = document.createElement('pre');
      pre.className = 'code-block';
      const code = document.createElement('code');
      code.textContent = codeValue;
      pre.appendChild(code);

      // If wrapper is inside a container, replace the container with the pre
      const container = wrapper.closest('.code-block-container');
      if (container) {
        container.replaceWith(pre);
      } else {
        wrapper.replaceWith(pre);
      }
    } else {
      // Remove empty code blocks (and container if exists)
      const container = wrapper.closest('.code-block-container');
      if (container) {
        container.remove();
      } else {
        wrapper.remove();
      }
    }
  });

  return clone.innerHTML;
};

// Expose methods
defineExpose({
  getContent: () => getProcessedContent(),
  getTextContent: () => editorRef.value?.textContent || '',
  getAttachments: () => attachments.value.filter((a) => !a.uploading),
  getMentions: () => {
    const mentions = [];
    editorRef.value?.querySelectorAll('.mention-tag').forEach((el) => {
      mentions.push({
        userId: parseInt(el.dataset.userId),
        text: el.textContent,
      });
    });
    return mentions;
  },
  clear: () => {
    if (editorRef.value) editorRef.value.innerHTML = '';
    attachments.value.forEach((a) => {
      if (a.preview) URL.revokeObjectURL(a.preview);
    });
    attachments.value = [];
  },
  focus: () => editorRef.value?.focus(),
});

// Lifecycle
onMounted(() => {
  document.addEventListener('click', handleOutsideClick);
});

onUnmounted(() => {
  document.removeEventListener('click', handleOutsideClick);
  attachments.value.forEach((a) => {
    if (a.preview) URL.revokeObjectURL(a.preview);
  });
});

// Watch for external value changes
watch(
  () => props.modelValue,
  (newVal) => {
    if (editorRef.value && editorRef.value.innerHTML !== newVal) {
      editorRef.value.innerHTML = newVal;
    }
  }
);
</script>

<style scoped>
.rich-comment-editor {
  position: relative;
  background: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 12px;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.rich-comment-editor.is-focused {
  border-color: #ff6b35;
  box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
}

.rich-comment-editor.is-reply {
  border-radius: 8px;
}

/* Drag Overlay */
.drag-overlay {
  position: absolute;
  inset: 0;
  background: rgba(255, 107, 53, 0.1);
  border: 2px dashed #ff6b35;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10;
}

.drag-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  color: #ff6b35;
  font-weight: 500;
}

/* Editor Container */
.editor-container {
  position: relative;
}

.editor-content {
  min-height: 80px;
  max-height: 300px;
  overflow-y: auto;
  padding: 14px 16px;
  font-size: 15px;
  line-height: 1.5;
  outline: none;
}

.editor-content:empty::before {
  content: attr(data-placeholder);
  color: #999;
  pointer-events: none;
}

.editor-content :deep(.mention-tag) {
  display: inline-block;
  padding: 2px 6px;
  background: #e3f2fd;
  color: #1976d2;
  border-radius: 4px;
  font-weight: 500;
  cursor: default;
}

/* Code Block Wrapper in Editor */
.editor-content :deep(.code-block-wrapper) {
  margin: 12px 0;
  user-select: none;
}

.editor-content :deep(.code-block) {
  display: block;
  margin: 0;
  padding: 0;
  background: #1e1e1e;
  border-radius: 8px;
  border: 1px solid #333;
  overflow: hidden;
}

.editor-content :deep(.code-block)::before {
  content: 'Code';
  display: block;
  padding: 8px 14px;
  background: #2d2d2d;
  border-bottom: 1px solid #333;
  color: #888;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.editor-content :deep(.code-block .code-input) {
  display: block;
  width: 100%;
  min-height: 120px;
  max-height: 400px;
  padding: 12px 14px;
  background: transparent;
  border: none;
  color: #d4d4d4;
  font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', 'Consolas', monospace;
  font-size: 13px;
  line-height: 1.5;
  resize: vertical;
  outline: none;
  box-sizing: border-box;
  white-space: pre;
  overflow-x: auto;
  tab-size: 2;
}

.editor-content :deep(.code-block .code-input)::placeholder {
  color: #666;
}

.editor-content :deep(.code-block:focus-within) {
  outline: 2px solid #ff6b35;
  outline-offset: -2px;
}

/* Mention Dropdown */
.mention-dropdown {
  position: absolute;
  min-width: 240px;
  max-width: 320px;
  max-height: 240px;
  overflow-y: auto;
  background: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
  z-index: 100;
}

.mention-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  cursor: pointer;
  transition: background 0.15s;
}

.mention-item:hover,
.mention-item.active {
  background: #f5f5f5;
}

.mention-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
}

.mention-avatar-placeholder {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
}

.mention-info {
  flex: 1;
  min-width: 0;
}

.mention-name {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #1a1a1a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.mention-username {
  display: block;
  font-size: 12px;
  color: #666;
}

/* Emoji Picker */
.emoji-picker {
  position: absolute;
  bottom: calc(100% + 8px);
  left: 0;
  width: 320px;
  background: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 12px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
  z-index: 100;
  overflow: hidden;
}

.emoji-categories {
  display: flex;
  border-bottom: 1px solid #e5e5e5;
  padding: 8px;
  gap: 4px;
}

.emoji-cat-btn {
  flex: 1;
  padding: 8px;
  background: transparent;
  border: none;
  border-radius: 6px;
  font-size: 18px;
  cursor: pointer;
  transition: background 0.15s;
}

.emoji-cat-btn:hover {
  background: #f5f5f5;
}

.emoji-cat-btn.active {
  background: #fff3e0;
}

.emoji-grid {
  display: grid;
  grid-template-columns: repeat(8, 1fr);
  gap: 4px;
  padding: 8px;
  max-height: 200px;
  overflow-y: auto;
}

.emoji-btn {
  padding: 6px;
  background: transparent;
  border: none;
  border-radius: 6px;
  font-size: 20px;
  cursor: pointer;
  transition: background 0.15s, transform 0.1s;
}

.emoji-btn:hover {
  background: #f5f5f5;
  transform: scale(1.1);
}

/* Attachments Preview */
.attachments-preview {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 8px 12px;
  border-top: 1px solid #e5e5e5;
}

.attachment-item {
  position: relative;
  border-radius: 8px;
  overflow: hidden;
}

.attachment-item.image {
  width: 80px;
  height: 80px;
}

.attachment-item.file {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: #f5f5f5;
  border: 1px solid #e0e0e0;
}

.attachment-image {
  width: 100%;
  height: 100%;
}

.attachment-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.attachment-file svg {
  color: #666;
  flex-shrink: 0;
}

.file-name {
  font-size: 12px;
  font-weight: 500;
  color: #333;
  max-width: 100px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.file-size {
  font-size: 11px;
  color: #999;
}

.upload-progress {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: rgba(0, 0, 0, 0.1);
}

.progress-bar {
  height: 100%;
  background: #ff6b35;
  transition: width 0.2s;
}

.attachment-remove {
  position: absolute;
  top: 4px;
  right: 4px;
  width: 20px;
  height: 20px;
  padding: 0;
  background: rgba(0, 0, 0, 0.6);
  border: none;
  border-radius: 50%;
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.15s;
}

.attachment-item:hover .attachment-remove {
  opacity: 1;
}

/* Toolbar */
.editor-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  border-top: 1px solid #e5e5e5;
  background: #fafafa;
  border-radius: 0 0 12px 12px;
}

.toolbar-left {
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

.format-icon {
  font-size: 15px;
  font-weight: 700;
  font-family: Georgia, serif;
}

.format-icon.bold {
  font-weight: 800;
}

.format-icon.italic {
  font-style: italic;
}

.format-icon.underline {
  text-decoration: underline;
}

.toolbar-divider {
  width: 1px;
  height: 20px;
  background: #e0e0e0;
  margin: 0 6px;
}

.toolbar-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.file-count {
  font-size: 12px;
  color: #999;
}

/* Hidden input */
.hidden-input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.dropdown-enter-active,
.dropdown-leave-active {
  transition: all 0.15s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

/* Responsive */
@media (max-width: 640px) {
  .emoji-picker {
    width: calc(100vw - 40px);
    left: 50%;
    transform: translateX(-50%);
  }

  .emoji-grid {
    grid-template-columns: repeat(6, 1fr);
  }

  .attachment-item.image {
    width: 60px;
    height: 60px;
  }
}
</style>
