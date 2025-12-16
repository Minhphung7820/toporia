<template>
  <div class="auth-page">
    <div class="auth-container">
      <div class="auth-card">
        <h1 class="auth-title">Reset password</h1>
        <p class="auth-subtitle">Enter your new password</p>

        <form @submit.prevent="handleResetPassword" class="auth-form">
          <div v-if="error" class="alert alert-error">{{ error }}</div>

          <div class="form-group">
            <label for="email">Email</label>
            <input id="email" v-model="form.email" type="email" required placeholder="you@example.com" :disabled="loading || success" />
            <span v-if="errors.email" class="error-text">{{ errors.email }}</span>
          </div>

          <div class="form-group">
            <label for="token">Reset Token</label>
            <input id="token" v-model="form.token" type="text" required placeholder="Enter reset token" :disabled="loading || success" />
            <span v-if="errors.token" class="error-text">{{ errors.token }}</span>
          </div>

          <div class="form-group">
            <label for="password">New Password</label>
            <input id="password" v-model="form.password" type="password" required placeholder="At least 8 characters" :disabled="loading || success" />
            <span v-if="errors.password" class="error-text">{{ errors.password }}</span>
          </div>

          <div class="form-group">
            <label for="password_confirmation">Confirm Password</label>
            <input id="password_confirmation" v-model="form.password_confirmation" type="password" required placeholder="Confirm password" :disabled="loading || success" />
            <span v-if="errors.password_confirmation" class="error-text">{{ errors.password_confirmation }}</span>
          </div>

          <button v-if="!success" type="submit" class="btn btn-primary btn-block" :disabled="loading">
            {{ loading ? 'Resetting...' : 'Reset password' }}
          </button>

          <div v-else class="success-message">
            <div class="alert alert-success">
              <strong>Password reset successful!</strong>
              <p>You can now login with your new password.</p>
            </div>
            <router-link to="/login" class="btn btn-primary btn-block">Go to login</router-link>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { useAuthStore } from '../stores/auth';

export default {
  name: 'ResetPassword',
  setup() {
    return { authStore: useAuthStore() };
  },
  data() {
    return {
      form: { email: '', token: '', password: '', password_confirmation: '' },
      errors: {},
      error: '',
      success: false,
    };
  },
  computed: {
    loading() { return this.authStore.loading; },
  },
  mounted() {
    const params = new URLSearchParams(window.location.search);
    this.form.token = params.get('token') || '';
    this.form.email = params.get('email') || '';
  },
  methods: {
    async handleResetPassword() {
      this.error = '';
      this.errors = {};
      const result = await this.authStore.resetPassword(this.form);
      if (result.success) {
        this.success = true;
      } else {
        this.error = result.message || 'Failed to reset password';
        if (result.errors) this.errors = result.errors;
      }
    },
  },
};
</script>

<style scoped>
.auth-page {
  min-height: calc(100vh - 120px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem 1rem;
  background: #fafafa;
}

.auth-container { width: 100%; max-width: 400px; }

.auth-card {
  background: #fff;
  border-radius: 12px;
  padding: 2.5rem;
  border: 1px solid #e5e5e5;
}

.auth-title { font-size: 1.5rem; font-weight: 600; color: #1a1a1a; margin-bottom: 0.25rem; }
.auth-subtitle { color: #666; margin-bottom: 1.5rem; font-size: 0.9rem; }

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
  display: block;
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 8px;
  font-size: 0.95rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
  text-align: center;
}

.btn-primary { background: #1a1a1a; color: #fff; }
.btn-primary:hover:not(:disabled) { background: #333; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-block { width: 100%; }

.alert { padding: 0.875rem 1rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.9rem; }
.alert-error { background-color: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.alert-success { background-color: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
.alert-success strong { display: block; margin-bottom: 0.25rem; }
.alert-success p { margin: 0; }

@media (max-width: 480px) { .auth-card { padding: 1.5rem; } }
</style>
