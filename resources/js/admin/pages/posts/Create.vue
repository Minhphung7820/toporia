<template>
  <AdminLayout>
    <div class="post-create">
      <div class="page-header">
        <div>
          <h1>Create Post</h1>
          <p>Create a new blog post</p>
        </div>
        <div class="header-actions">
          <button @click="saveDraft" class="btn btn-secondary" :disabled="saving">
            Save Draft
          </button>
          <button @click="publish" class="btn btn-primary" :disabled="saving">
            {{ saving ? 'Saving...' : 'Publish' }}
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
              @input="generateSlug"
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
        </div>
      </div>

      <div v-if="error" class="error-message">
        {{ error }}
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import AdminLayout from '../../components/layout/AdminLayout.vue';
import RichEditor from '../../components/shared/RichEditor.vue';
import TagInput from '../../components/shared/TagInput.vue';
import ImageUpload from '../../components/shared/ImageUpload.vue';
import { usePostsStore } from '../../stores/posts';
import { useCategoriesStore } from '../../stores/categories';

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

const generateSlug = () => {
  form.slug = form.title
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');
};

const saveDraft = async () => {
  saving.value = true;
  error.value = null;
  try {
    const result = await postsStore.createPost({
      ...form,
      status: 'draft',
    });
    if (result.success) {
      router.push(`/admin/posts/${result.data.id}/edit`);
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to save draft';
  } finally {
    saving.value = false;
  }
};

const publish = async () => {
  saving.value = true;
  error.value = null;
  try {
    const data = {
      ...form,
      published_at: form.scheduled_at || new Date().toISOString(),
    };
    const result = await postsStore.createPost(data);
    if (result.success) {
      router.push('/admin/posts');
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to publish post';
  } finally {
    saving.value = false;
  }
};

onMounted(async () => {
  await categoriesStore.fetchSelectOptions();
  categories.value = categoriesStore.selectOptions;
});
</script>

<style scoped>
.post-create {
  max-width: 1400px;
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

.form-layout {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 24px;
}

.form-main {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
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
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  transition: border-color 0.2s ease;
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
</style>
