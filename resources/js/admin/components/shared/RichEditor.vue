<template>
  <div class="rich-editor">
    <div class="editor-toolbar">
      <button type="button" @click="execCommand('bold')" title="Bold">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z" />
          <path d="M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z" />
        </svg>
      </button>
      <button type="button" @click="execCommand('italic')" title="Italic">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="19" y1="4" x2="10" y2="4" />
          <line x1="14" y1="20" x2="5" y2="20" />
          <line x1="15" y1="4" x2="9" y2="20" />
        </svg>
      </button>
      <button type="button" @click="execCommand('underline')" title="Underline">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M6 3v7a6 6 0 006 6 6 6 0 006-6V3" />
          <line x1="4" y1="21" x2="20" y2="21" />
        </svg>
      </button>
      <span class="separator"></span>
      <button type="button" @click="execCommand('insertUnorderedList')" title="Bullet List">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="8" y1="6" x2="21" y2="6" />
          <line x1="8" y1="12" x2="21" y2="12" />
          <line x1="8" y1="18" x2="21" y2="18" />
          <circle cx="4" cy="6" r="1" fill="currentColor" />
          <circle cx="4" cy="12" r="1" fill="currentColor" />
          <circle cx="4" cy="18" r="1" fill="currentColor" />
        </svg>
      </button>
      <button type="button" @click="execCommand('insertOrderedList')" title="Numbered List">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="10" y1="6" x2="21" y2="6" />
          <line x1="10" y1="12" x2="21" y2="12" />
          <line x1="10" y1="18" x2="21" y2="18" />
          <text x="2" y="8" font-size="8" fill="currentColor">1</text>
          <text x="2" y="14" font-size="8" fill="currentColor">2</text>
          <text x="2" y="20" font-size="8" fill="currentColor">3</text>
        </svg>
      </button>
      <span class="separator"></span>
      <select @change="formatBlock($event)">
        <option value="">Format</option>
        <option value="h1">Heading 1</option>
        <option value="h2">Heading 2</option>
        <option value="h3">Heading 3</option>
        <option value="p">Paragraph</option>
        <option value="blockquote">Quote</option>
      </select>
      <span class="separator"></span>
      <button type="button" @click="insertLink" title="Insert Link">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71" />
          <path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71" />
        </svg>
      </button>
      <button type="button" @click="insertImage" title="Insert Image">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
          <circle cx="8.5" cy="8.5" r="1.5" />
          <polyline points="21 15 16 10 5 21" />
        </svg>
      </button>
    </div>
    <div
      ref="editor"
      class="editor-content"
      contenteditable="true"
      @input="handleInput"
      @paste="handlePaste"
    ></div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['update:modelValue']);

const editor = ref(null);

const execCommand = (command, value = null) => {
  document.execCommand(command, false, value);
  editor.value?.focus();
};

const formatBlock = (event) => {
  const tag = event.target.value;
  if (tag) {
    document.execCommand('formatBlock', false, `<${tag}>`);
  }
  event.target.value = '';
  editor.value?.focus();
};

const insertLink = () => {
  const url = prompt('Enter URL:');
  if (url) {
    document.execCommand('createLink', false, url);
  }
  editor.value?.focus();
};

const insertImage = () => {
  const url = prompt('Enter image URL:');
  if (url) {
    document.execCommand('insertImage', false, url);
  }
  editor.value?.focus();
};

const handleInput = () => {
  emit('update:modelValue', editor.value?.innerHTML || '');
};

const handlePaste = (event) => {
  event.preventDefault();
  const text = event.clipboardData?.getData('text/plain') || '';
  document.execCommand('insertText', false, text);
};

watch(
  () => props.modelValue,
  (newValue) => {
    if (editor.value && editor.value.innerHTML !== newValue) {
      editor.value.innerHTML = newValue;
    }
  }
);

onMounted(() => {
  if (editor.value && props.modelValue) {
    editor.value.innerHTML = props.modelValue;
  }
});
</script>

<style scoped>
.rich-editor {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
}

.editor-toolbar {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 8px 12px;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
  flex-wrap: wrap;
}

.editor-toolbar button {
  padding: 6px;
  background: transparent;
  border: none;
  border-radius: 4px;
  color: #6b7280;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.editor-toolbar button:hover {
  background: #e5e7eb;
  color: #1f2937;
}

.editor-toolbar select {
  padding: 4px 8px;
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  font-size: 13px;
  background: #fff;
}

.separator {
  width: 1px;
  height: 20px;
  background: #e5e7eb;
  margin: 0 4px;
}

.editor-content {
  min-height: 300px;
  padding: 16px;
  outline: none;
  font-size: 14px;
  line-height: 1.6;
}

.editor-content:focus {
  background: #fff;
}

.editor-content h1 {
  font-size: 24px;
  font-weight: 700;
  margin: 0 0 16px 0;
}

.editor-content h2 {
  font-size: 20px;
  font-weight: 600;
  margin: 0 0 12px 0;
}

.editor-content h3 {
  font-size: 18px;
  font-weight: 600;
  margin: 0 0 8px 0;
}

.editor-content p {
  margin: 0 0 12px 0;
}

.editor-content blockquote {
  border-left: 3px solid #667eea;
  margin: 0 0 12px 0;
  padding-left: 16px;
  color: #6b7280;
  font-style: italic;
}

.editor-content ul,
.editor-content ol {
  margin: 0 0 12px 0;
  padding-left: 24px;
}

.editor-content a {
  color: #667eea;
}

.editor-content img {
  max-width: 100%;
  height: auto;
  border-radius: 8px;
}
</style>
