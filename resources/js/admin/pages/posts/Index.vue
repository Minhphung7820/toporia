<template>
  <AdminLayout>
    <div class="posts-index">
      <div class="page-header">
        <div>
          <h1>Posts</h1>
          <p>Manage your blog posts</p>
        </div>
        <router-link to="/admin/posts/create" class="btn btn-primary">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 5v14M5 12h14" />
          </svg>
          New Post
        </router-link>
      </div>

      <!-- Filters -->
      <div class="filters-bar">
        <div class="search-input">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
          <input
            type="text"
            v-model="filters.search"
            placeholder="Search posts..."
            @input="debouncedSearch"
          />
        </div>
        <select v-model="filters.status" @change="applyFilters">
          <option value="">All Status</option>
          <option value="published">Published</option>
          <option value="draft">Draft</option>
          <option value="scheduled">Scheduled</option>
        </select>
        <select v-model="filters.is_featured" @change="applyFilters">
          <option value="">All Posts</option>
          <option value="1">Featured</option>
          <option value="0">Not Featured</option>
        </select>
      </div>

      <!-- Posts Table -->
      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>Title</th>
              <th>Author</th>
              <th>Category</th>
              <th>Status</th>
              <th>Views</th>
              <th>Published</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="loading-cell">
                <div class="spinner"></div>
                Loading posts...
              </td>
            </tr>
            <tr v-else-if="posts.length === 0">
              <td colspan="7" class="empty-cell">
                No posts found
              </td>
            </tr>
            <tr v-else v-for="post in posts" :key="post.id">
              <td>
                <div class="post-title-cell">
                  <router-link :to="`/admin/posts/${post.id}/edit`" class="post-title">
                    {{ post.title }}
                  </router-link>
                  <span v-if="post.is_featured" class="badge badge-featured">Featured</span>
                </div>
              </td>
              <td>{{ post.author?.name || 'Unknown' }}</td>
              <td>{{ post.category?.name || '-' }}</td>
              <td>
                <span class="badge" :class="getStatusClass(post)">{{ getStatusLabel(post) }}</span>
              </td>
              <td>{{ post.views_count?.toLocaleString() || 0 }}</td>
              <td>{{ formatDate(post.published_at) }}</td>
              <td>
                <div class="actions">
                  <router-link :to="`/admin/posts/${post.id}/edit`" class="btn-icon" title="Edit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
                      <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
                    </svg>
                  </router-link>
                  <button
                    v-if="!post.published_at"
                    @click="publishPost(post.id)"
                    class="btn-icon text-green"
                    title="Publish"
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M22 11.08V12a10 10 0 11-5.93-9.14" />
                      <polyline points="22 4 12 14.01 9 11.01" />
                    </svg>
                  </button>
                  <button
                    v-else
                    @click="unpublishPost(post.id)"
                    class="btn-icon text-orange"
                    title="Unpublish"
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <circle cx="12" cy="12" r="10" />
                      <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
                    </svg>
                  </button>
                  <button
                    @click="toggleFeatured(post.id)"
                    class="btn-icon"
                    :class="{ 'text-yellow': post.is_featured }"
                    :title="post.is_featured ? 'Unfeature' : 'Feature'"
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" :fill="post.is_featured ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2">
                      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                    </svg>
                  </button>
                  <button @click="confirmDelete(post)" class="btn-icon text-red" title="Delete">
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

      <!-- Pagination -->
      <Pagination
        v-if="pagination.lastPage > 1"
        :current-page="pagination.currentPage"
        :last-page="pagination.lastPage"
        :total="pagination.total"
        @page-change="goToPage"
      />

      <!-- Delete Confirmation Modal -->
      <ConfirmDialog
        v-if="showDeleteDialog"
        title="Delete Post"
        :message="`Are you sure you want to delete '${postToDelete?.title}'? This action cannot be undone.`"
        confirm-text="Delete"
        confirm-class="danger"
        @confirm="deletePost"
        @cancel="showDeleteDialog = false"
      />
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AdminLayout from '../../components/layout/AdminLayout.vue';
import Pagination from '../../../components/shared/Pagination.vue';
import ConfirmDialog from '../../components/shared/ConfirmDialog.vue';
import { usePostsStore } from '../../stores/posts';
import { debounce } from 'lodash-es';

const store = usePostsStore();

const filters = ref({
  search: '',
  status: '',
  is_featured: '',
});

const showDeleteDialog = ref(false);
const postToDelete = ref(null);

const posts = computed(() => store.items);
const pagination = computed(() => store.pagination);
const loading = computed(() => store.loading);

const debouncedSearch = debounce(() => {
  applyFilters();
}, 300);

const applyFilters = () => {
  store.setFilters(filters.value);
  store.fetchPosts(1);
};

const goToPage = (page) => {
  store.fetchPosts(page);
};

const getStatusClass = (post) => {
  if (post.published_at) {
    const pubDate = new Date(post.published_at);
    if (pubDate > new Date()) return 'badge-scheduled';
    return 'badge-published';
  }
  return 'badge-draft';
};

const getStatusLabel = (post) => {
  if (post.published_at) {
    const pubDate = new Date(post.published_at);
    if (pubDate > new Date()) return 'Scheduled';
    return 'Published';
  }
  return 'Draft';
};

const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};

const publishPost = async (id) => {
  await store.publishPost(id);
};

const unpublishPost = async (id) => {
  await store.unpublishPost(id);
};

const toggleFeatured = async (id) => {
  await store.toggleFeatured(id);
};

const confirmDelete = (post) => {
  postToDelete.value = post;
  showDeleteDialog.value = true;
};

const deletePost = async () => {
  if (postToDelete.value) {
    await store.deletePost(postToDelete.value.id);
    showDeleteDialog.value = false;
    postToDelete.value = null;
  }
};

onMounted(() => {
  store.fetchPosts(1);
});
</script>

<style scoped>
.posts-index {
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

.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
  border: none;
  transition: all 0.2s ease;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
}

.btn-primary:hover {
  opacity: 0.9;
  transform: translateY(-1px);
}

.filters-bar {
  display: flex;
  gap: 12px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}

.search-input {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  flex: 1;
  max-width: 300px;
}

.search-input svg {
  color: #9ca3af;
  flex-shrink: 0;
}

.search-input input {
  border: none;
  outline: none;
  width: 100%;
  font-size: 14px;
}

.filters-bar select {
  padding: 8px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  background: #fff;
  cursor: pointer;
}

.table-container {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  margin-bottom: 24px;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th,
.data-table td {
  padding: 12px 16px;
  text-align: left;
  border-bottom: 1px solid #e5e7eb;
}

.data-table th {
  background: #f9fafb;
  font-weight: 600;
  font-size: 13px;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.data-table td {
  font-size: 14px;
  color: #374151;
}

.loading-cell,
.empty-cell {
  text-align: center;
  padding: 48px !important;
  color: #9ca3af;
}

.spinner {
  width: 24px;
  height: 24px;
  border: 3px solid #e5e7eb;
  border-top-color: #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 12px;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.post-title-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}

.post-title {
  color: #1f2937;
  text-decoration: none;
  font-weight: 500;
}

.post-title:hover {
  color: #4f46e5;
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

.badge-featured {
  background: #fef3c7;
  color: #92400e;
}

.actions {
  display: flex;
  gap: 4px;
}

.btn-icon {
  padding: 6px;
  background: transparent;
  border: none;
  border-radius: 6px;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.btn-icon:hover {
  background: #f3f4f6;
  color: #1f2937;
}

.btn-icon.text-green:hover {
  color: #10b981;
}

.btn-icon.text-orange:hover {
  color: #f59e0b;
}

.btn-icon.text-yellow {
  color: #f59e0b;
}

.btn-icon.text-red:hover {
  color: #ef4444;
}

@media (max-width: 768px) {
  .filters-bar {
    flex-direction: column;
  }

  .search-input {
    max-width: none;
  }

  .table-container {
    overflow-x: auto;
  }

  .data-table {
    min-width: 800px;
  }
}
</style>
