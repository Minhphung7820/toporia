<template>
  <AdminLayout>
    <div class="categories-index">
      <div class="page-header">
        <div>
          <h1>Categories</h1>
          <p>Organize your blog posts with categories</p>
        </div>
        <router-link to="/admin/categories/create" class="btn btn-primary">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 5v14M5 12h14" />
          </svg>
          New Category
        </router-link>
      </div>

      <div class="filters-bar">
        <div class="search-input">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
          <input
            type="text"
            v-model="filters.search"
            placeholder="Search categories..."
            @input="debouncedSearch"
          />
        </div>
        <select v-model="filters.is_active" @change="applyFilters">
          <option value="">All Status</option>
          <option value="1">Active</option>
          <option value="0">Inactive</option>
        </select>
      </div>

      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Slug</th>
              <th>Posts</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="5" class="loading-cell">
                <div class="spinner"></div>
                Loading categories...
              </td>
            </tr>
            <tr v-else-if="categories.length === 0">
              <td colspan="5" class="empty-cell">No categories found</td>
            </tr>
            <tr v-else v-for="category in categories" :key="category.id">
              <td>
                <div class="category-name">
                  <span v-if="category.parent_id" class="indent">└</span>
                  {{ category.name }}
                </div>
              </td>
              <td class="text-muted">{{ category.slug }}</td>
              <td>{{ category.posts_count || 0 }}</td>
              <td>
                <span class="badge" :class="category.is_active ? 'badge-success' : 'badge-secondary'">
                  {{ category.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td>
                <div class="actions">
                  <router-link :to="`/admin/categories/${category.id}/edit`" class="btn-icon" title="Edit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
                      <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
                    </svg>
                  </router-link>
                  <button @click="toggleActive(category.id)" class="btn-icon" :title="category.is_active ? 'Deactivate' : 'Activate'">
                    <svg v-if="category.is_active" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                      <circle cx="12" cy="12" r="3" />
                    </svg>
                    <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24" />
                      <line x1="1" y1="1" x2="23" y2="23" />
                    </svg>
                  </button>
                  <button @click="confirmDelete(category)" class="btn-icon text-red" title="Delete">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="3 6 5 6 21 6" />
                      <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination
        v-if="pagination.lastPage > 1"
        :current-page="pagination.currentPage"
        :last-page="pagination.lastPage"
        :per-page="pagination.perPage"
        :total="pagination.total"
        :from="pagination.from"
        :to="pagination.to"
        @page-change="goToPage"
        @per-page-change="onPerPageChange"
      />

      <ConfirmDialog
        v-if="showDeleteDialog"
        title="Delete Category"
        :message="`Are you sure you want to delete '${categoryToDelete?.name}'? Posts in this category will become uncategorized.`"
        confirm-text="Delete"
        confirm-class="danger"
        @confirm="deleteCategory"
        @cancel="showDeleteDialog = false"
      />
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminLayout from '../../components/layout/AdminLayout.vue';
import Pagination from '../../components/shared/Pagination.vue';
import ConfirmDialog from '../../components/shared/ConfirmDialog.vue';
import { useCategoriesStore } from '../../stores/categories';
import { debounce } from 'lodash-es';

const route = useRoute();
const router = useRouter();
const store = useCategoriesStore();

const filters = ref({ search: '', is_active: '' });
const showDeleteDialog = ref(false);
const categoryToDelete = ref(null);

const categories = computed(() => store.items || []);
const pagination = computed(() => store.pagination);
const loading = computed(() => store.loading);

const debouncedSearch = debounce(() => applyFilters(), 300);

// Sync filters to URL
const updateUrl = (params) => {
  const query = { ...route.query };
  Object.keys(params).forEach((key) => {
    if (params[key] !== '' && params[key] !== null && params[key] !== undefined) {
      query[key] = String(params[key]);
    } else {
      delete query[key];
    }
  });
  router.replace({ query });
};

const applyFilters = () => {
  store.setFilters(filters.value);
  updateUrl({ ...filters.value, page: 1 });
  store.fetchCategories(1);
};

const goToPage = (page) => {
  updateUrl({ page });
  store.fetchCategories(page);
};

const onPerPageChange = (perPage) => {
  store.setPerPage(perPage);
  updateUrl({ per_page: perPage, page: 1 });
  store.fetchCategories(1);
};

const toggleActive = async (id) => {
  await store.toggleActive(id);
};

const confirmDelete = (category) => {
  categoryToDelete.value = category;
  showDeleteDialog.value = true;
};

const deleteCategory = async () => {
  if (categoryToDelete.value) {
    await store.deleteCategory(categoryToDelete.value.id);
    showDeleteDialog.value = false;
    categoryToDelete.value = null;
  }
};

// Initialize from URL params
onMounted(() => {
  const page = parseInt(route.query.page) || 1;
  const perPage = parseInt(route.query.per_page) || 20;
  const search = route.query.search || '';
  const isActive = route.query.is_active || '';

  filters.value = { search, is_active: isActive };
  store.setFilters(filters.value);
  store.setPerPage(perPage);
  store.fetchCategories(page);
});
</script>

<style scoped>
.categories-index { max-width: 1000px; }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 24px; }
.page-header h1 { font-size: 24px; font-weight: 700; color: #1f2937; margin: 0 0 4px 0; }
.page-header p { color: #6b7280; margin: 0; }
.btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; text-decoration: none; cursor: pointer; border: none; }
.btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; }
.filters-bar { display: flex; gap: 12px; margin-bottom: 24px; }
.search-input { display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; flex: 1; max-width: 300px; }
.search-input svg { color: #9ca3af; }
.search-input input { border: none; outline: none; width: 100%; font-size: 14px; }
.filters-bar select { padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; background: #fff; }
.table-container { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); overflow: hidden; margin-bottom: 24px; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #e5e7eb; }
.data-table th { background: #f9fafb; font-weight: 600; font-size: 13px; color: #6b7280; text-transform: uppercase; }
.loading-cell, .empty-cell { text-align: center; padding: 48px !important; color: #9ca3af; }
.spinner { width: 24px; height: 24px; border: 3px solid #e5e7eb; border-top-color: #667eea; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 12px; }
@keyframes spin { to { transform: rotate(360deg); } }
.category-name { display: flex; align-items: center; gap: 8px; font-weight: 500; }
.indent { color: #9ca3af; }
.text-muted { color: #9ca3af; }
.badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
.badge-success { background: #d1fae5; color: #065f46; }
.badge-secondary { background: #f3f4f6; color: #6b7280; }
.actions { display: flex; gap: 4px; }
.btn-icon { padding: 6px; background: transparent; border: none; border-radius: 6px; color: #6b7280; cursor: pointer; text-decoration: none; display: inline-flex; }
.btn-icon:hover { background: #f3f4f6; color: #1f2937; }
.btn-icon.text-red:hover { color: #ef4444; }
</style>
