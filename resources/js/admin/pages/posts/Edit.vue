<template>
  <AdminLayout>
    <div class="post-edit">
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        Loading post...
      </div>

      <template v-else-if="post">
        <div class="page-header">
          <div>
            <h1>Edit Post</h1>
            <p>{{ post.title }}</p>
          </div>
          <div class="header-actions">
            <a :href="`/blog/${post.slug}`" target="_blank" class="btn btn-link">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6" />
                <polyline points="15 3 21 3 21 9" />
                <line x1="10" y1="14" x2="21" y2="3" />
              </svg>
              Preview
            </a>
            <button @click="save" class="btn btn-secondary" :disabled="saving">
              Save
            </button>
            <button v-if="!post.published_at" @click="publish" class="btn btn-primary" :disabled="saving">
              {{ saving ? 'Publishing...' : 'Publish' }}
            </button>
            <button v-else @click="unpublish" class="btn btn-warning" :disabled="saving">
              Unpublish
            </button>
          </div>
        </div>

        <div class="form-layout">
          <div class="form-main">
            <div class="form-group">
              <label for="title">Title</label>
              <input
                id="title"
                v-model="form.title"
                type="text"
                placeholder="Enter post title"
              />
            </div>

            <div class="form-group">
              <label for="slug">Slug</label>
              <input
                id="slug"
                v-model="form.slug"
                type="text"
                placeholder="post-url-slug"
              />
            </div>

            <div class="form-group">
              <label for="excerpt">Excerpt</label>
              <textarea
                id="excerpt"
                v-model="form.excerpt"
                rows="3"
                placeholder="Brief summary of the post..."
              ></textarea>
            </div>

            <div class="form-group">
              <label for="content">Content</label>
              <RichEditor v-model="form.content" />
            </div>
          </div>

          <div class="form-sidebar">
            <div class="sidebar-section status-section">
              <h3>Status</h3>
              <div class="status-info">
                <span class="badge" :class="getStatusClass">{{ getStatusLabel }}</span>
                <span v-if="post.published_at" class="status-date">
                  {{ formatDate(post.published_at) }}
                </span>
              </div>
              <div class="stats">
                <div class="stat">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                    <circle cx="12" cy="12" r="3" />
                  </svg>
                  {{ post.views_count?.toLocaleString() || 0 }} views
                </div>
                <div class="stat">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
                  </svg>
                  {{ post.comments_count || 0 }} comments
                </div>
              </div>
            </div>

            <div class="sidebar-section">
              <h3>Category</h3>
              <select v-model="form.category_id">
                <option value="">Select category</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                  {{ cat.name }}
                </option>
              </select>
            </div>

            <div class="sidebar-section">
              <h3>Tags</h3>
              <TagInput v-model="form.tags" />
            </div>

            <div class="sidebar-section">
              <h3>Featured Image</h3>
              <ImageUpload v-model="form.featured_image" />
            </div>

            <div class="sidebar-section">
              <h3>Options</h3>
              <label class="checkbox-label">
                <input type="checkbox" v-model="form.is_featured" />
                <span>Featured post</span>
              </label>
            </div>

            <div class="sidebar-section">
              <h3>Schedule</h3>
              <input
                type="datetime-local"
                v-model="form.scheduled_at"
                class="datetime-input"
              />
            </div>

            <div class="sidebar-section">
              <h3>SEO</h3>
              <div class="form-group small">
                <label>Meta Title</label>
                <input v-model="form.meta_title" type="text" placeholder="SEO title" />
              </div>
              <div class="form-group small">
                <label>Meta Description</label>
                <textarea v-model="form.meta_description" rows="2" placeholder="SEO description"></textarea>
              </div>
            </div>

            <div class="sidebar-section danger-zone">
              <h3>Danger Zone</h3>
              <button @click="confirmDelete" class="btn btn-danger">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="3 6 5 6 21 6" />
                  <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
                </svg>
                Delete Post
              </button>
            </div>
          </div>
        </div>

        <div v-if="error" class="error-message">
          {{ error }}
        </div>
      </template>

      <ConfirmDialog
        v-if="showDeleteDialog"
        title="Delete Post"
        message="Are you sure you want to delete this post? This action cannot be undone."
        confirm-text="Delete"
        confirm-class="danger"
        @confirm="deletePost"
        @cancel="showDeleteDialog = false"
      />
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminLayout from '../../components/layout/AdminLayout.vue';
import RichEditor from '../../components/shared/RichEditor.vue';
import TagInput from '../../components/shared/TagInput.vue';
import ImageUpload from '../../components/shared/ImageUpload.vue';
import ConfirmDialog from '../../components/shared/ConfirmDialog.vue';
import { usePostsStore } from '../../stores/posts';
import { useCategoriesStore } from '../../stores/categories';

const route = useRoute();
const router = useRouter();
const postsStore = usePostsStore();
const categoriesStore = useCategoriesStore();

const form = reactive({
  title: '',
  slug: '',
  excerpt: '',
  content: '',
  category_id: '',
  tags: [],
  featured_image: '',
  is_featured: false,
  scheduled_at: '',
  meta_title: '',
  meta_description: '',
});

const saving = ref(false);
const error = ref(null);
const categories = ref([]);
const showDeleteDialog = ref(false);

const post = computed(() => postsStore.currentPost);
const loading = computed(() => postsStore.loading);

const getStatusClass = computed(() => {
  if (!post.value) return '';
  if (post.value.published_at) {
    const pubDate = new Date(post.value.published_at);
    if (pubDate > new Date()) return 'badge-scheduled';
    return 'badge-published';
  }
  return 'badge-draft';
});

const getStatusLabel = computed(() => {
  if (!post.value) return '';
  if (post.value.published_at) {
    const pubDate = new Date(post.value.published_at);
    if (pubDate > new Date()) return 'Scheduled';
    return 'Published';
  }
  return 'Draft';
});

const formatDate = (date) => {
  if (!date) return '';
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
};

const populateForm = (postData) => {
  form.title = postData.title || '';
  form.slug = postData.slug || '';
  form.excerpt = postData.excerpt || '';
  form.content = postData.content || '';
  // Ensure category_id is a number for proper select matching
  form.category_id = postData.category_id ? Number(postData.category_id) : '';
  form.tags = postData.tags?.map((t) => t.name) || [];
  form.featured_image = postData.featured_image || '';
  form.is_featured = postData.is_featured || false;
  form.scheduled_at = postData.scheduled_at ? postData.scheduled_at.slice(0, 16) : '';
  form.meta_title = postData.meta_title || '';
  form.meta_description = postData.meta_description || '';
};

const save = async () => {
  saving.value = true;
  error.value = null;
  try {
    await postsStore.updatePost(route.params.id, form);
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to save post';
  } finally {
    saving.value = false;
  }
};

const publish = async () => {
  saving.value = true;
  error.value = null;
  try {
    await postsStore.publishPost(route.params.id);
    await postsStore.fetchPost(route.params.id);
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to publish post';
  } finally {
    saving.value = false;
  }
};

const unpublish = async () => {
  saving.value = true;
  error.value = null;
  try {
    await postsStore.unpublishPost(route.params.id);
    await postsStore.fetchPost(route.params.id);
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to unpublish post';
  } finally {
    saving.value = false;
  }
};

const confirmDelete = () => {
  showDeleteDialog.value = true;
};

const deletePost = async () => {
  try {
    await postsStore.deletePost(route.params.id);
    router.push('/admin/posts');
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to delete post';
    showDeleteDialog.value = false;
  }
};

watch(post, (newPost) => {
  if (newPost) {
    populateForm(newPost);
  }
}, { immediate: true });

onMounted(async () => {
  await Promise.all([
    postsStore.fetchPost(route.params.id),
    categoriesStore.fetchSelectOptions(),
  ]);
  categories.value = categoriesStore.selectOptions;
  // Populate form after data is loaded
  if (postsStore.currentPost) {
    populateForm(postsStore.currentPost);
  }
});
</script>

<style scoped>
.post-edit {
  max-width: 1400px;
  width: 100%;
  overflow-x: hidden;
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 64px;
  color: #9ca3af;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e5e7eb;
  border-top-color: #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 16px;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 24px;
}

.page-header h1 {
  font-size: 24px;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.page-header p {
  color: #6b7280;
  margin: 0;
  max-width: 400px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.header-actions {
  display: flex;
  gap: 12px;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  text-decoration: none;
  transition: all 0.2s ease;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
}

.btn-secondary {
  background: #fff;
  color: #374151;
  border: 1px solid #e5e7eb;
}

.btn-secondary:hover {
  background: #f9fafb;
}

.btn-warning {
  background: #f59e0b;
  color: #fff;
}

.btn-warning:hover {
  background: #d97706;
}

.btn-link {
  background: transparent;
  color: #6b7280;
  padding: 10px 12px;
}

.btn-link:hover {
  color: #4f46e5;
}

.btn-danger {
  background: #ef4444;
  color: #fff;
  width: 100%;
  justify-content: center;
}

.btn-danger:hover {
  background: #dc2626;
}

.form-layout {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 24px;
  max-width: 100%;
}

.form-main {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  min-width: 0;
  max-width: 100%;
  overflow: hidden;
}

.form-group {
  margin-bottom: 20px;
}

.form-group.small {
  margin-bottom: 12px;
}

.form-group label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  margin-bottom: 6px;
}

.form-group input[type="text"],
.form-group textarea,
.form-group select {
  width: 100%;
  max-width: 100%;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  transition: border-color 0.2s ease;
  box-sizing: border-box;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
  outline: none;
  border-color: #667eea;
}

.form-sidebar {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
  max-width: 100%;
}

.sidebar-section {
  background: #fff;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.sidebar-section h3 {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 12px 0;
}

.sidebar-section select,
.sidebar-section input,
.sidebar-section textarea {
  width: 100%;
  max-width: 100%;
  padding: 8px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  box-sizing: border-box;
}

.sidebar-section input:focus,
.sidebar-section textarea:focus,
.sidebar-section select:focus {
  outline: none;
  border-color: #667eea;
}

.status-section .status-info {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.status-date {
  font-size: 12px;
  color: #6b7280;
}

.stats {
  display: flex;
  gap: 16px;
}

.stat {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
  color: #6b7280;
}

.badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 500;
}

.badge-published {
  background: #d1fae5;
  color: #065f46;
}

.badge-draft {
  background: #fef3c7;
  color: #92400e;
}

.badge-scheduled {
  background: #dbeafe;
  color: #1e40af;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-size: 14px;
  color: #374151;
}

.checkbox-label input {
  width: 16px;
  height: 16px;
}

.datetime-input {
  width: 100%;
  max-width: 100%;
  padding: 8px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  box-sizing: border-box;
}

.danger-zone {
  border: 1px solid #fecaca;
  background: #fef2f2;
}

.danger-zone h3 {
  color: #dc2626;
}

.error-message {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #dc2626;
  padding: 12px 16px;
  border-radius: 8px;
  margin-top: 24px;
}

@media (max-width: 1024px) {
  .form-layout {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: stretch;
    gap: 16px;
  }

  .page-header > div:first-child {
    width: 100%;
  }

  .page-header h1 {
    font-size: 20px;
  }

  .page-header p {
    max-width: 100%;
    font-size: 13px;
  }

  .header-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .header-actions .btn {
    padding: 8px 14px;
    font-size: 13px;
  }

  .header-actions .btn-link {
    padding: 8px 10px;
  }

  .form-main {
    padding: 16px;
    border-radius: 10px;
  }

  .form-group {
    margin-bottom: 16px;
  }

  .sidebar-section {
    padding: 14px;
    border-radius: 10px;
  }

  .sidebar-section h3 {
    font-size: 13px;
    margin-bottom: 10px;
  }

  .status-section .stats {
    gap: 12px;
  }

  .stat {
    font-size: 12px;
  }
}

@media (max-width: 480px) {
  .page-header {
    gap: 12px;
    margin-bottom: 16px;
  }

  .page-header h1 {
    font-size: 18px;
  }

  .header-actions {
    flex-direction: column;
    gap: 8px;
  }

  .header-actions .btn {
    width: 100%;
    justify-content: center;
  }

  .header-actions .btn-link {
    width: auto;
    margin-right: auto;
  }

  .form-main {
    padding: 14px;
  }

  .form-group label {
    font-size: 13px;
  }

  .form-group input,
  .form-group textarea,
  .form-group select {
    padding: 10px;
    font-size: 14px;
  }

  .form-sidebar {
    gap: 12px;
  }

  .sidebar-section {
    padding: 12px;
  }

  .btn-danger {
    padding: 10px 16px;
    font-size: 13px;
  }
}
</style>
