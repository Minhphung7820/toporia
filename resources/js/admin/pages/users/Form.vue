<template>
  <AdminLayout>
    <div class="user-form">
      <div class="page-header">
        <div>
          <h1>{{ isEdit ? 'Edit User' : 'Create User' }}</h1>
          <p>{{ isEdit ? 'Update user account details' : 'Create a new user account' }}</p>
        </div>
      </div>

      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        Loading...
      </div>

      <form v-else @submit.prevent="submit" class="form-container">
        <div class="form-group">
          <label for="name">Name <span class="required">*</span></label>
          <input id="name" v-model="form.name" type="text" placeholder="Full name" required />
        </div>

        <div class="form-group">
          <label for="email">Email <span class="required">*</span></label>
          <input id="email" v-model="form.email" type="email" placeholder="email@example.com" required />
        </div>

        <div class="form-group">
          <label for="password">Password {{ isEdit ? '' : '*' }}</label>
          <input
            id="password"
            v-model="form.password"
            type="password"
            :placeholder="isEdit ? 'Leave blank to keep current' : 'Enter password'"
            :required="!isEdit"
          />
          <span v-if="isEdit" class="hint">Leave blank to keep current password</span>
        </div>

        <div class="form-group">
          <label for="password_confirmation">Confirm Password</label>
          <input
            id="password_confirmation"
            v-model="form.password_confirmation"
            type="password"
            placeholder="Confirm password"
          />
        </div>

        <div class="form-group">
          <label for="role">Role</label>
          <select id="role" v-model="form.role">
            <option value="user">User</option>
            <option value="editor">Editor</option>
            <option value="admin">Admin</option>
          </select>
        </div>

        <div v-if="error" class="error-message">{{ error }}</div>

        <div class="form-actions">
          <router-link to="/admin/users" class="btn btn-secondary">Cancel</router-link>
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
import { useUsersStore } from '../../stores/users';

const route = useRoute();
const router = useRouter();
const store = useUsersStore();

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'user',
});

const saving = ref(false);
const error = ref(null);

const isEdit = computed(() => !!route.params.id);
const loading = computed(() => store.loading);
const user = computed(() => store.currentUser);

const populateForm = (data) => {
  form.name = data.name || '';
  form.email = data.email || '';
  form.role = data.role || 'user';
  form.password = '';
  form.password_confirmation = '';
};

const submit = async () => {
  if (form.password && form.password !== form.password_confirmation) {
    error.value = 'Passwords do not match';
    return;
  }

  saving.value = true;
  error.value = null;

  try {
    const data = { ...form };
    if (isEdit.value && !data.password) {
      delete data.password;
      delete data.password_confirmation;
    }

    if (isEdit.value) {
      await store.updateUser(route.params.id, data);
    } else {
      await store.createUser(data);
    }
    router.push('/admin/users');
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to save user';
  } finally {
    saving.value = false;
  }
};

watch(user, (newVal) => {
  if (newVal) populateForm(newVal);
});

onMounted(async () => {
  if (isEdit.value) {
    await store.fetchUser(route.params.id);
  }
});
</script>

<style scoped>
.user-form { max-width: 600px; }
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
.form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; }
.form-group input:focus, .form-group select:focus { outline: none; border-color: #667eea; }
.hint { display: block; margin-top: 4px; font-size: 12px; color: #9ca3af; }
.error-message { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
.form-actions { display: flex; justify-content: flex-end; gap: 12px; padding-top: 16px; border-top: 1px solid #e5e7eb; }
.btn { display: inline-flex; align-items: center; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; text-decoration: none; cursor: pointer; border: none; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary { background: #f3f4f6; color: #374151; }
.btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; }
</style>
