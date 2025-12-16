<template>
  <div class="page-container">
    <div class="content-container">
      <div class="card">
        <h1 class="page-title">Change password</h1>
        <p class="page-subtitle">Update your account password</p>

        <form @submit.prevent="handleChangePassword" class="form">
          <div v-if="error" class="alert alert-error">{{ error }}</div>
          <div v-if="success" class="alert alert-success">{{ successMessage }}</div>

          <div class="form-group">
            <label for="current_password">Current Password</label>
            <input id="current_password" v-model="form.current_password" type="password" required placeholder="Enter current password" :disabled="loading" />
            <span v-if="errors.current_password" class="error-text">{{ errors.current_password }}</span>
          </div>

          <div class="form-group">
            <label for="password">New Password</label>
            <input id="password" v-model="form.password" type="password" required placeholder="At least 8 characters" :disabled="loading" />
            <span v-if="errors.password" class="error-text">{{ errors.password }}</span>
          </div>

          <div class="form-group">
            <label for="password_confirmation">Confirm New Password</label>
            <input id="password_confirmation" v-model="form.password_confirmation" type="password" required placeholder="Confirm new password" :disabled="loading" />
            <span v-if="errors.password_confirmation" class="error-text">{{ errors.password_confirmation }}</span>
          </div>

          <button type="submit" class="btn btn-primary" :disabled="loading">
            {{ loading ? 'Updating...' : 'Update password' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { useAuthStore } from '../stores/auth';

export default {
  name: 'ChangePassword',
  setup() {
    return { authStore: useAuthStore() };
  },
  data() {
    return {
      form: { current_password: '', password: '', password_confirmation: '' },
      errors: {},
      error: '',
      success: false,
      successMessage: '',
    };
  },
  computed: {
    loading() { return this.authStore.loading; },
  },
  methods: {
    async handleChangePassword() {
      this.error = '';
      this.errors = {};
      this.success = false;
      const result = await this.authStore.changePassword(this.form);
      if (result.success) {
        this.success = true;
        this.successMessage = result.message || 'Password changed successfully';
        this.form = { current_password: '', password: '', password_confirmation: '' };
      } else {
        this.error = result.message || 'Failed to change password';
        if (result.errors) this.errors = result.errors;
      }
    },
  },
};
</script>

<style scoped>
.page-container {
  min-height: calc(100vh - 120px);
  padding: 2rem 1rem;
  background: #fafafa;
}

.content-container { max-width: 500px; margin: 0 auto; }

.card {
  background: #fff;
  border-radius: 12px;
  padding: 2rem;
  border: 1px solid #e5e5e5;
}

.page-title { font-size: 1.5rem; font-weight: 600; color: #1a1a1a; margin-bottom: 0.25rem; }
.page-subtitle { color: #666; margin-bottom: 1.5rem; font-size: 0.9rem; }

.form-group { margin-bottom: 1.25rem; }
.form-group label { display: block; margin-bottom: 0.5rem; color: #1a1a1a; font-weight: 500; font-size: 0.9rem; }

.form-group input {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 0.95rem;
  transition: border-color 0.2s;
}

.form-group input:focus { outline: none; border-color: #1a1a1a; }
.form-group input:disabled { background-color: #f5f5f5; cursor: not-allowed; }
.error-text { display: block; color: #dc2626; font-size: 0.8rem; margin-top: 0.25rem; }

.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 8px;
  font-size: 0.95rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary { background: #1a1a1a; color: #fff; }
.btn-primary:hover:not(:disabled) { background: #333; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.alert { padding: 0.875rem 1rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.9rem; }
.alert-error { background-color: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.alert-success { background-color: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }

@media (max-width: 480px) { .card { padding: 1.5rem; } }
</style>
