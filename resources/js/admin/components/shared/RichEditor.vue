<template>
  <div class="rich-editor">
    <Ckeditor
      v-model="content"
      :editor="editor"
      :config="editorConfig"
      @ready="onReady"
    />
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { Ckeditor } from '@ckeditor/ckeditor5-vue';
import ClassicEditor from '@ckeditor/ckeditor5-build-classic';
import '@ckeditor/ckeditor5-build-classic/build/ckeditor.js';

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Start writing your content...',
  },
});

const emit = defineEmits(['update:modelValue']);

const editor = ClassicEditor;
const content = ref(props.modelValue);

// Get CSRF token from cookie
const getCsrfToken = () => {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : '';
};

const editorConfig = {
  licenseKey: 'GPL',
  placeholder: props.placeholder,
  toolbar: {
    items: [
      'heading',
      '|',
      'bold',
      'italic',
      'underline',
      'strikethrough',
      '|',
      'bulletedList',
      'numberedList',
      '|',
      'outdent',
      'indent',
      '|',
      'blockQuote',
      'insertTable',
      '|',
      'link',
      'imageUpload',
      'mediaEmbed',
      '|',
      'undo',
      'redo',
    ],
  },
  heading: {
    options: [
      { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
      { model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
      { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
      { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' },
      { model: 'heading4', view: 'h4', title: 'Heading 4', class: 'ck-heading_heading4' },
    ],
  },
  image: {
    toolbar: ['imageTextAlternative', 'imageStyle:inline', 'imageStyle:block', 'imageStyle:side'],
  },
  table: {
    contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells'],
  },
  link: {
    addTargetToExternalLinks: true,
    defaultProtocol: 'https://',
  },
  simpleUpload: {
    uploadUrl: '/api/admin/upload',
    withCredentials: true,
    headers: {
      'X-XSRF-TOKEN': getCsrfToken(),
    },
  },
};

const onReady = () => {
  // Editor is ready
};

// Sync content to parent
watch(content, (newValue) => {
  emit('update:modelValue', newValue);
});

// Sync from parent
watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue !== content.value) {
      content.value = newValue;
    }
  }
);
</script>

<style>
/* CKEditor custom styles */
.rich-editor {
  border-radius: 8px;
  overflow: hidden;
}

.rich-editor .ck.ck-editor {
  width: 100%;
}

.rich-editor .ck.ck-editor__main > .ck-editor__editable {
  min-height: 300px;
  border-color: #e5e7eb;
}

.rich-editor .ck.ck-editor__main > .ck-editor__editable:focus {
  border-color: #667eea;
  box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
}

.rich-editor .ck.ck-toolbar {
  background: #f9fafb;
  border-color: #e5e7eb;
  border-radius: 8px 8px 0 0;
}

.rich-editor .ck.ck-button {
  border-radius: 4px;
}

.rich-editor .ck.ck-button:hover {
  background: #e5e7eb;
}

.rich-editor .ck.ck-button.ck-on {
  background: #667eea;
  color: #fff;
}

/* Content styles */
.rich-editor .ck-content h1 {
  font-size: 2em;
  font-weight: 700;
  margin: 0.67em 0;
}

.rich-editor .ck-content h2 {
  font-size: 1.5em;
  font-weight: 600;
  margin: 0.75em 0;
}

.rich-editor .ck-content h3 {
  font-size: 1.25em;
  font-weight: 600;
  margin: 0.83em 0;
}

.rich-editor .ck-content h4 {
  font-size: 1.1em;
  font-weight: 600;
  margin: 1em 0;
}

.rich-editor .ck-content p {
  margin: 0 0 1em 0;
  line-height: 1.6;
}

.rich-editor .ck-content blockquote {
  border-left: 4px solid #667eea;
  padding-left: 1em;
  margin: 1em 0;
  color: #6b7280;
  font-style: italic;
}

.rich-editor .ck-content ul,
.rich-editor .ck-content ol {
  padding-left: 1.5em;
  margin: 1em 0;
}

.rich-editor .ck-content a {
  color: #667eea;
  text-decoration: underline;
}

.rich-editor .ck-content img {
  max-width: 100%;
  height: auto;
  border-radius: 8px;
}

.rich-editor .ck-content table {
  width: 100%;
  border-collapse: collapse;
  margin: 1em 0;
}

.rich-editor .ck-content table td,
.rich-editor .ck-content table th {
  border: 1px solid #e5e7eb;
  padding: 0.5em;
}

.rich-editor .ck-content table th {
  background: #f9fafb;
  font-weight: 600;
}

.rich-editor .ck-content code {
  background: #f1f5f9;
  padding: 0.2em 0.4em;
  border-radius: 4px;
  font-family: 'Fira Code', monospace;
  font-size: 0.9em;
}

.rich-editor .ck-content pre {
  background: #1e293b;
  color: #e2e8f0;
  padding: 1em;
  border-radius: 8px;
  overflow-x: auto;
}

.rich-editor .ck-content pre code {
  background: transparent;
  padding: 0;
  color: inherit;
}
</style>
