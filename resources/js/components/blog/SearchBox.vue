<template>
  <div class="search-box">
    <h3 class="widget-title">Search</h3>
    <form @submit.prevent="handleSearch" class="search-form">
      <div class="search-input-wrapper">
        <input
          v-model="query"
          type="text"
          placeholder="Search posts..."
          class="search-input"
        />
        <button type="submit" class="search-btn" :disabled="query.trim().length < 2">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </button>
      </div>
      <p v-if="showHint" class="search-hint">Press Enter to search</p>
    </form>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const emit = defineEmits(['search']);
const query = ref('');

const showHint = computed(() => query.value.trim().length > 0 && query.value.trim().length < 2);

const handleSearch = () => {
  if (query.value.trim().length >= 2) {
    emit('search', query.value);
  }
};
</script>

<style scoped>
.search-box {
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 12px;
  padding: 24px;
}

.widget-title {
  font-size: 16px;
  font-weight: 700;
  color: #1a1a1a;
  margin: 0 0 16px;
}

.search-form {
  position: relative;
}

.search-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.search-input {
  width: 100%;
  padding: 12px 48px 12px 14px;
  border: 1px solid #e5e5e5;
  border-radius: 8px;
  font-size: 14px;
  background: #fafafa;
  transition: all 0.2s;
  font-family: inherit;
}

.search-input:focus {
  outline: none;
  border-color: #1a1a1a;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(26, 26, 26, 0.1);
}

.search-input::placeholder {
  color: #999;
}

.search-btn {
  position: absolute;
  right: 4px;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  background: #1a1a1a;
  border: none;
  border-radius: 6px;
  color: #fff;
  cursor: pointer;
  transition: all 0.2s;
}

.search-btn:hover:not(:disabled) {
  background: #333;
}

.search-btn:disabled {
  background: #ccc;
  cursor: not-allowed;
}

.search-hint {
  font-size: 12px;
  color: #999;
  margin: 8px 0 0;
}
</style>
