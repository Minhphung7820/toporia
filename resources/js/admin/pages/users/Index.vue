<template>
  <AdminLayout>
    <div class="users-index">
      <div class="page-header">
        <div>
          <h1>Users</h1>
          <p>Manage user accounts</p>
        </div>
        <router-link to="/admin/users/create" class="btn btn-primary">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 5v14M5 12h14" />
          </svg>
          New User
        </router-link>
      </div>

      <!-- Stats -->
      <div class="stats-row" v-if="statistics">
        <div class="stat-item">
          <span class="stat-value">{{ statistics.total || 0 }}</span>
          <span class="stat-label">Total Users</span>
        </div>
        <div class="stat-item">
          <span class="stat-value">{{ statistics.admin_count || 0 }}</span>
          <span class="stat-label">Admins</span>
        </div>
        <div class="stat-item">
          <span class="stat-value">{{ statistics.verified_count || 0 }}</span>
          <span class="stat-label">Verified</span>
        </div>
        <div class="stat-item">
          <span class="stat-value">{{ statistics.new_this_month || 0 }}</span>
          <span class="stat-label">New This Month</span>
        </div>
      </div>

      <div class="filters-bar">
        <div class="search-input">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
          <input v-model="filters.search" placeholder="Search users..." @input="debouncedSearch" />
        </div>
        <select v-model="filters.role" @change="applyFilters">
          <option value="">All Roles</option>
          <option value="admin">Admin</option>
          <option value="editor">Editor</option>
          <option value="user">User</option>
        </select>
        <select v-model="filters.verified" @change="applyFilters">
          <option value="">All Status</option>
          <option value="1">Verified</option>
          <option value="0">Unverified</option>
        </select>
      </div>

      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>User</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="6" class="loading-cell">
                <div class="spinner"></div>
                Loading users...
              </td>
            </tr>
            <tr v-else-if="users.length === 0">
              <td colspan="6" class="empty-cell">No users found</td>
            </tr>
            <tr v-else v-for="user in users" :key="user.id">
              <td>
                <div class="user-cell">
                  <div class="user-avatar">{{ getInitials(user.name) }}</div>
                  <span class="user-name">{{ user.name }}</span>
                </div>
              </td>
              <td>{{ user.email }}</td>
              <td>
                <select
                  :value="user.role"
                  @change="changeRole(user.id, $event.target.value)"
                  class="role-select"
                >
                  <option value="admin">Admin</option>
                  <option value="editor">Editor</option>
                  <option value="user">User</option>
                </select>
              </td>
              <td>
                <span class="badge" :class="user.email_verified_at ? 'badge-success' : 'badge-warning'">
                  {{ user.email_verified_at ? 'Verified' : 'Unverified' }}
                </span>
              </td>
              <td>{{ formatDate(user.created_at) }}</td>
              <td>
                <div class="actions">
                  <router-link :to="`/admin/users/${user.id}/edit`" class="btn-icon" title="Edit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
                      <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
                    </svg>
                  </router-link>
                  <button @click="confirmDelete(user)" class="btn-icon text-red" title="Delete">
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
        title="Delete User"
        :message="`Delete user '${userToDelete?.name}'? This cannot be undone.`"
        confirm-text="Delete"
        confirm-class="danger"
        @confirm="deleteUser"
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
import { useUsersStore } from '../../stores/users';
import { debounce } from 'lodash-es';

const route = useRoute();
const router = useRouter();
const store = useUsersStore();

const filters = ref({ search: '', role: '', verified: '' });
const showDeleteDialog = ref(false);
const userToDelete = ref(null);

const users = computed(() => store.items || []);
const pagination = computed(() => store.pagination);
const loading = computed(() => store.loading);
const statistics = computed(() => store.statistics);

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

const debouncedSearch = debounce(() => applyFilters(), 300);

const applyFilters = () => {
  store.setFilters(filters.value);
  updateUrl({ ...filters.value, page: 1 });
  store.fetchUsers(1);
};

const goToPage = (page) => {
  updateUrl({ page });
  store.fetchUsers(page);
};

const onPerPageChange = (perPage) => {
  store.setPerPage(perPage);
  updateUrl({ per_page: perPage, page: 1 });
  store.fetchUsers(1);
};

const getInitials = (name) => name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);

const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const changeRole = async (id, role) => {
  await store.changeRole(id, role);
};

const confirmDelete = (user) => {
  userToDelete.value = user;
  showDeleteDialog.value = true;
};

const deleteUser = async () => {
  if (userToDelete.value) {
    await store.deleteUser(userToDelete.value.id);
    showDeleteDialog.value = false;
    userToDelete.value = null;
  }
};

// Initialize from URL params
onMounted(() => {
  const page = parseInt(route.query.page) || 1;
  const perPage = parseInt(route.query.per_page) || 20;
  const search = route.query.search || '';
  const role = route.query.role || '';
  const verified = route.query.verified || '';

  filters.value = { search, role, verified };
  store.setFilters(filters.value);
  store.setPerPage(perPage);
  store.fetchUsers(page);
  store.fetchStatistics();
});
</script>

<style scoped>
.users-index { max-width: 1200px; }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 24px; }
.page-header h1 { font-size: 24px; font-weight: 700; color: #1f2937; margin: 0 0 4px 0; }
.page-header p { color: #6b7280; margin: 0; }
.btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; text-decoration: none; cursor: pointer; border: none; }
.btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; }
.stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-item { background: #fff; padding: 16px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); text-align: center; }
.stat-value { display: block; font-size: 24px; font-weight: 700; color: #1f2937; }
.stat-label { font-size: 13px; color: #6b7280; }
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
.user-cell { display: flex; align-items: center; gap: 12px; }
.user-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; }
.user-name { font-weight: 500; }
.role-select { padding: 4px 8px; border: 1px solid #e5e7eb; border-radius: 4px; font-size: 13px; background: #fff; }
.badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
.badge-success { background: #d1fae5; color: #065f46; }
.badge-warning { background: #fef3c7; color: #92400e; }
.actions { display: flex; gap: 4px; }
.btn-icon { padding: 6px; background: transparent; border: none; border-radius: 6px; color: #6b7280; cursor: pointer; text-decoration: none; display: inline-flex; }
.btn-icon:hover { background: #f3f4f6; color: #1f2937; }
.btn-icon.text-red:hover { color: #ef4444; }
</style>
