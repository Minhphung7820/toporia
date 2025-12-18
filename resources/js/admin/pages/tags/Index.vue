<template>
  <AdminLayout>
    <div class="tags-index">
      <div class="page-header">
        <div>
          <h1>Tags</h1>
          <p>Manage tags for your blog posts</p>
        </div>
        <div class="header-actions">
          <button v-if="unusedCount > 0" @click="cleanupTags" class="btn btn-warning">
            Cleanup {{ unusedCount }} unused
          </button>
          <button @click="showCreateModal = true" class="btn btn-primary">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 5v14M5 12h14" />
            </svg>
            New Tag
          </button>
        </div>
      </div>

      <div class="filters-bar">
        <div class="search-input">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
          <input v-model="filters.search" placeholder="Search tags..." @input="debouncedSearch" />
        </div>
        <div v-if="selectedCount > 0" class="bulk-actions">
          <button @click="showMergeModal = true" class="btn btn-small btn-secondary">
            Merge ({{ selectedCount }})
          </button>
          <button @click="bulkDelete" class="btn btn-small btn-danger">
            Delete ({{ selectedCount }})
          </button>
        </div>
      </div>

      <div class="tags-grid">
        <div v-if="loading" class="loading-state">
          <div class="spinner"></div>
        </div>
        <div v-else-if="tags.length === 0" class="empty-state">No tags found</div>
        <div v-else v-for="tag in tags" :key="tag.id" class="tag-card" :class="{ selected: selectedIds.includes(tag.id) }">
          <input type="checkbox" :checked="selectedIds.includes(tag.id)" @change="toggleSelection(tag.id)" />
          <div class="tag-info">
            <span class="tag-name">{{ tag.name }}</span>
            <span class="tag-count">{{ tag.posts_count || 0 }} posts</span>
          </div>
          <div class="tag-actions">
            <button @click="editTag(tag)" class="btn-icon" title="Edit">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
                <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
              </svg>
            </button>
            <button @click="confirmDelete(tag)" class="btn-icon text-red" title="Delete">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6" />
                <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <Pagination
        v-if="pagination.lastPage > 1"
        :current-page="pagination.currentPage"
        :last-page="pagination.lastPage"
        :total="pagination.total"
        @page-change="goToPage"
      />

      <!-- Create/Edit Modal -->
      <div v-if="showCreateModal || editingTag" class="modal-overlay" @click.self="closeModal">
        <div class="modal-content">
          <h3>{{ editingTag ? 'Edit Tag' : 'Create Tag' }}</h3>
          <form @submit.prevent="saveTag">
            <div class="form-group">
              <label>Name</label>
              <input v-model="tagForm.name" type="text" placeholder="Tag name" required />
            </div>
            <div class="form-group">
              <label>Slug</label>
              <input v-model="tagForm.slug" type="text" placeholder="tag-slug" />
            </div>
            <div class="modal-actions">
              <button type="button" @click="closeModal" class="btn btn-secondary">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="saving">
                {{ saving ? 'Saving...' : 'Save' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Merge Modal -->
      <div v-if="showMergeModal" class="modal-overlay" @click.self="showMergeModal = false">
        <div class="modal-content">
          <h3>Merge Tags</h3>
          <p>Select the target tag to merge {{ selectedCount }} tags into:</p>
          <select v-model="mergeTargetId" class="merge-select">
            <option value="">Select target tag</option>
            <option v-for="id in selectedIds" :key="id" :value="id">
              {{ tags.find(t => t.id === id)?.name }}
            </option>
          </select>
          <div class="modal-actions">
            <button @click="showMergeModal = false" class="btn btn-secondary">Cancel</button>
            <button @click="mergeTags" class="btn btn-primary" :disabled="!mergeTargetId">Merge</button>
          </div>
        </div>
      </div>

      <ConfirmDialog
        v-if="showDeleteDialog"
        title="Delete Tag"
        :message="`Delete '${tagToDelete?.name}'?`"
        confirm-text="Delete"
        confirm-class="danger"
        @confirm="deleteTag"
        @cancel="showDeleteDialog = false"
      />
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import AdminLayout from '../../components/layout/AdminLayout.vue';
import Pagination from '../../../components/shared/Pagination.vue';
import ConfirmDialog from '../../components/shared/ConfirmDialog.vue';
import { useTagsStore } from '../../stores/tags';
import { debounce } from 'lodash-es';

const store = useTagsStore();

const filters = ref({ search: '' });
const showCreateModal = ref(false);
const showMergeModal = ref(false);
const showDeleteDialog = ref(false);
const editingTag = ref(null);
const tagToDelete = ref(null);
const mergeTargetId = ref('');
const saving = ref(false);

const tagForm = reactive({ name: '', slug: '' });

const tags = computed(() => store.items);
const pagination = computed(() => store.pagination);
const loading = computed(() => store.loading);
const selectedIds = computed(() => store.selectedIds);
const selectedCount = computed(() => store.selectedCount);
const unusedCount = computed(() => store.unusedTagsCount);

const debouncedSearch = debounce(() => {
  store.setFilters(filters.value);
  store.fetchTags(1);
}, 300);

const goToPage = (page) => store.fetchTags(page);
const toggleSelection = (id) => store.toggleSelection(id);

const editTag = (tag) => {
  editingTag.value = tag;
  tagForm.name = tag.name;
  tagForm.slug = tag.slug;
};

const closeModal = () => {
  showCreateModal.value = false;
  editingTag.value = null;
  tagForm.name = '';
  tagForm.slug = '';
};

const saveTag = async () => {
  saving.value = true;
  try {
    if (editingTag.value) {
      await store.updateTag(editingTag.value.id, tagForm);
    } else {
      await store.createTag(tagForm);
    }
    closeModal();
  } catch (e) {
    console.error(e);
  } finally {
    saving.value = false;
  }
};

const confirmDelete = (tag) => {
  tagToDelete.value = tag;
  showDeleteDialog.value = true;
};

const deleteTag = async () => {
  if (tagToDelete.value) {
    await store.deleteTag(tagToDelete.value.id);
    showDeleteDialog.value = false;
    tagToDelete.value = null;
  }
};

const bulkDelete = () => store.bulkDelete();

const mergeTags = async () => {
  if (mergeTargetId.value) {
    const sourceIds = selectedIds.value.filter(id => id !== parseInt(mergeTargetId.value));
    await store.mergeTags(parseInt(mergeTargetId.value), sourceIds);
    showMergeModal.value = false;
    mergeTargetId.value = '';
  }
};

const cleanupTags = () => store.cleanupUnusedTags();

onMounted(() => {
  store.fetchTags(1);
  store.fetchStatistics();
});
</script>

<style scoped>
.tags-index { max-width: 1200px; }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 24px; }
.page-header h1 { font-size: 24px; font-weight: 700; color: #1f2937; margin: 0 0 4px 0; }
.page-header p { color: #6b7280; margin: 0; }
.header-actions { display: flex; gap: 12px; }
.btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; text-decoration: none; cursor: pointer; border: none; }
.btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; }
.btn-secondary { background: #f3f4f6; color: #374151; }
.btn-warning { background: #f59e0b; color: #fff; }
.btn-danger { background: #ef4444; color: #fff; }
.btn-small { padding: 6px 12px; font-size: 13px; }
.filters-bar { display: flex; gap: 12px; margin-bottom: 24px; align-items: center; }
.search-input { display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; flex: 1; max-width: 300px; }
.search-input svg { color: #9ca3af; }
.search-input input { border: none; outline: none; width: 100%; font-size: 14px; }
.bulk-actions { display: flex; gap: 8px; margin-left: auto; }
.tags-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-bottom: 24px; }
.loading-state, .empty-state { grid-column: 1 / -1; text-align: center; padding: 48px; color: #9ca3af; }
.spinner { width: 24px; height: 24px; border: 3px solid #e5e7eb; border-top-color: #667eea; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto; }
@keyframes spin { to { transform: rotate(360deg); } }
.tag-card { display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); border: 2px solid transparent; }
.tag-card.selected { border-color: #667eea; background: #f5f3ff; }
.tag-info { flex: 1; }
.tag-name { display: block; font-weight: 500; color: #1f2937; }
.tag-count { font-size: 12px; color: #9ca3af; }
.tag-actions { display: flex; gap: 4px; }
.btn-icon { padding: 4px; background: transparent; border: none; color: #9ca3af; cursor: pointer; border-radius: 4px; }
.btn-icon:hover { background: #f3f4f6; color: #1f2937; }
.btn-icon.text-red:hover { color: #ef4444; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); display: flex; align-items: center; justify-content: center; z-index: 9999; }
.modal-content { background: #fff; border-radius: 12px; padding: 24px; max-width: 400px; width: 90%; }
.modal-content h3 { font-size: 18px; font-weight: 600; margin: 0 0 16px 0; }
.modal-content p { color: #6b7280; margin: 0 0 16px 0; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 6px; }
.form-group input { width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; }
.merge-select { width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; margin-bottom: 16px; }
.modal-actions { display: flex; justify-content: flex-end; gap: 12px; }
</style>
