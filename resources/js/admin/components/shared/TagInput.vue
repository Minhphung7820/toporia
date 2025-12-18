<template>
  <div class="tag-input">
    <div class="tags-container">
      <span v-for="(tag, index) in modelValue" :key="index" class="tag">
        {{ tag }}
        <button type="button" @click="removeTag(index)" class="tag-remove">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </span>
      <input
        ref="input"
        v-model="newTag"
        type="text"
        :placeholder="modelValue.length === 0 ? placeholder : ''"
        @keydown.enter.prevent="addTag"
        @keydown.backspace="handleBackspace"
        @keydown.tab.prevent="addTag"
      />
    </div>
    <p class="hint">Press Enter or Tab to add a tag</p>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  placeholder: {
    type: String,
    default: 'Add tags...',
  },
});

const emit = defineEmits(['update:modelValue']);

const input = ref(null);
const newTag = ref('');

const addTag = () => {
  const tag = newTag.value.trim().toLowerCase();
  if (tag && !props.modelValue.includes(tag)) {
    emit('update:modelValue', [...props.modelValue, tag]);
  }
  newTag.value = '';
};

const removeTag = (index) => {
  const tags = [...props.modelValue];
  tags.splice(index, 1);
  emit('update:modelValue', tags);
};

const handleBackspace = () => {
  if (newTag.value === '' && props.modelValue.length > 0) {
    removeTag(props.modelValue.length - 1);
  }
};
</script>

<style scoped>
.tag-input {
  width: 100%;
}

.tags-container {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  padding: 8px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  min-height: 42px;
  cursor: text;
}

.tags-container:focus-within {
  border-color: #667eea;
}

.tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  background: #f3f4f6;
  border-radius: 4px;
  font-size: 13px;
  color: #374151;
}

.tag-remove {
  padding: 0;
  background: transparent;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.tag-remove:hover {
  color: #ef4444;
}

.tags-container input {
  flex: 1;
  min-width: 100px;
  border: none;
  outline: none;
  font-size: 14px;
  padding: 4px 0;
}

.hint {
  margin: 6px 0 0 0;
  font-size: 12px;
  color: #9ca3af;
}
</style>
