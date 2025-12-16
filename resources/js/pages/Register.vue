<template>
  <div class="auth-page">
    <div class="auth-container">
      <div class="auth-card">
        <h1 class="auth-title">Create account</h1>
        <p class="auth-subtitle">Start your journey with Toporia</p>

        <form @submit.prevent="handleRegister" class="auth-form">
          <div v-if="error" class="alert alert-error">{{ error }}</div>

          <div class="form-group">
            <label for="name">Full Name</label>
            <input id="name" v-model="form.name" type="text" required placeholder="John Doe" :disabled="loading" />
            <span v-if="errors.name" class="error-text">{{ errors.name }}</span>
          </div>

          <div class="form-group">
            <label for="email">Email</label>
            <input id="email" v-model="form.email" type="email" required placeholder="you@example.com" :disabled="loading" />
            <span v-if="errors.email" class="error-text">{{ errors.email }}</span>
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <input id="password" v-model="form.password" type="password" required placeholder="At least 8 characters" :disabled="loading" />
            <span v-if="errors.password" class="error-text">{{ errors.password }}</span>
          </div>

          <div class="form-group">
            <label for="password_confirmation">Confirm Password</label>
            <input id="password_confirmation" v-model="form.password_confirmation" type="password" required placeholder="Confirm password" :disabled="loading" />
            <span v-if="errors.password_confirmation" class="error-text">{{ errors.password_confirmation }}</span>
          </div>

          <button type="submit" class="btn btn-primary btn-block" :disabled="loading">
            {{ loading ? 'Creating account...' : 'Create account' }}
          </button>
        </form>

        <div class="auth-footer">
          <p>Already have an account? <router-link to="/login" class="auth-link">Sign in</router-link></p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { useAuthStore } from '../stores/auth';

export default {
  name: 'Register',
  setup() {
    return { authStore: useAuthStore() };
  },
  data() {
    return {
      form: { name: '', email: '', password: '', password_confirmation: '' },
      errors: {},
      error: '',
    };
  },
  computed: {
    loading() { return this.authStore.loading; },
  },
  methods: {
    async handleRegister() {
      this.error = '';
      this.errors = {};
      const result = await this.authStore.register(this.form);
      if (result.success) {
        this.$router.push('/');
      } else {
        this.error = result.message || 'Registration failed';
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
.btn-block { width: 100%; }

.alert { padding: 0.875rem 1rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.9rem; }
.alert-error { background-color: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

.auth-footer { margin-top: 1.5rem; text-align: center; color: #666; font-size: 0.9rem; }
.auth-link { color: #1a1a1a; text-decoration: none; font-weight: 500; }
.auth-link:hover { text-decoration: underline; }

@media (max-width: 480px) { .auth-card { padding: 1.5rem; } }
</style>
