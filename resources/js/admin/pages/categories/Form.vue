<template>
  <AdminLayout>
    <div class="category-form">
      <div class="page-header">
        <div>
          <h1>{{ isEdit ? 'Edit Category' : 'Create Category' }}</h1>
          <p>{{ isEdit ? 'Update category details' : 'Add a new category for your posts' }}</p>
        </div>
      </div>

      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        Loading...
      </div>

      <form v-else @submit.prevent="submit" class="form-container">
        <div class="form-group">
          <label for="name">Name <span class="required">*</span></label>
          <input
            id="name"
            v-model="form.name"
            type="text"
            placeholder="Category name"
            @input="generateSlug"
            required
          />
        </div>

        <div class="form-group">
          <label for="slug">Slug</label>
          <input
            id="slug"
            v-model="form.slug"
            type="text"
            placeholder="category-slug"
          />
          <span class="hint">Leave empty to auto-generate from name</span>
        </div>

        <div class="form-group">
          <label for="parent_id">Parent Category</label>
          <select id="parent_id" v-model="form.parent_id">
            <option value="">None (Top Level)</option>
            <option v-for="cat in parentOptions" :key="cat.id" :value="cat.id">
              {{ cat.name }}
            </option>
          </select>
        </div>

        <div class="form-group">
          <label for="description">Description</label>
          <textarea
            id="description"
            v-model="form.description"
            rows="3"
            placeholder="Optional description..."
          ></textarea>
        </div>

        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" v-model="form.is_active" />
            <span>Active</span>
          </label>
          <span class="hint">Only active categories are shown on the site</span>
        </div>

        <div v-if="error" class="error-message">{{ error }}</div>

        <div class="form-actions">
          <router-link to="/admin/categories" class="btn btn-secondary">Cancel</router-link>
          <button type="submit" class="btn btn-primary" :disabled="saving">
            {{ saving ? 'Saving...' : (isEdit ? 'Update' : 'Create') }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminLayout from '../../components/layout/AdminLayout.vue';
import { useCategoriesStore } from '../../stores/categories';

const route = useRoute();
const router = useRouter();
const store = useCategoriesStore();

const form = reactive({
  name: '',
  slug: '',
  description: '',
  parent_id: '',
  is_active: true,
});

const saving = ref(false);
const error = ref(null);
const parentOptions = ref([]);

const isEdit = computed(() => !!route.params.id);
const loading = computed(() => store.loading);
const category = computed(() => store.currentCategory);

const generateSlug = () => {
  if (!isEdit.value) {
    form.slug = form.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  }
};

const populateForm = (data) => {
  form.name = data.name || '';
  form.slug = data.slug || '';
  form.description = data.description || '';
  form.parent_id = data.parent_id || '';
  form.is_active = data.is_active ?? true;
};

const submit = async () => {
  saving.value = true;
  error.value = null;
  try {
    if (isEdit.value) {
      await store.updateCategory(route.params.id, form);
    } else {
      await store.createCategory(form);
    }
    router.push('/admin/categories');
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to save category';
  } finally {
    saving.value = false;
  }
};

watch(category, (newVal) => {
  if (newVal) populateForm(newVal);
});

onMounted(async () => {
  const excludeId = isEdit.value ? route.params.id : null;
  await store.fetchSelectOptions(excludeId);
  parentOptions.value = store.selectOptions;

  if (isEdit.value) {
    await store.fetchCategory(route.params.id);
  }
});
</script>

<style scoped>
.category-form { max-width: 600px; }
.page-header { margin-bottom: 24px; }
.page-header h1 { font-size: 24px; font-weight: 700; color: #1f2937; margin: 0 0 4px 0; }
.page-header p { color: #6b7280; margin: 0; }
.loading-state { display: flex; flex-direction: column; align-items: center; padding: 64px; background: #fff; border-radius: 12px; }
.spinner { width: 32px; height: 32px; border: 3px solid #e5e7eb; border-top-color: #667eea; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 16px; }
@keyframes spin { to { transform: rotate(360deg); } }
.form-container { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px; }
.required { color: #ef4444; }
.form-group input[type="text"], .form-group textarea, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; }
.form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: #667eea; }
.hint { display: block; margin-top: 4px; font-size: 12px; color: #9ca3af; }
.checkbox-label { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; }
.checkbox-label input { width: 16px; height: 16px; }
.error-message { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
.form-actions { display: flex; justify-content: flex-end; gap: 12px; padding-top: 16px; border-top: 1px solid #e5e7eb; }
.btn { display: inline-flex; align-items: center; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; text-decoration: none; cursor: pointer; border: none; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary { background: #f3f4f6; color: #374151; }
.btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; }
</style>
