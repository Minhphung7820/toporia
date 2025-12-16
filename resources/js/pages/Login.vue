<template>
  <div class="auth-page">
    <div class="auth-container">
      <div class="auth-card">
        <h1 class="auth-title">Welcome back</h1>
        <p class="auth-subtitle">Sign in to your account</p>

        <form @submit.prevent="handleLogin" class="auth-form">
          <div v-if="error" class="alert alert-error">{{ error }}</div>

          <div class="form-group">
            <label for="email">Email</label>
            <input id="email" v-model="form.email" type="email" required placeholder="you@example.com" :disabled="loading" />
            <span v-if="errors.email" class="error-text">{{ errors.email }}</span>
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <input id="password" v-model="form.password" type="password" required placeholder="Enter your password" :disabled="loading" />
            <span v-if="errors.password" class="error-text">{{ errors.password }}</span>
          </div>

          <div class="form-group checkbox-group">
            <label class="checkbox-label">
              <input v-model="form.remember" type="checkbox" :disabled="loading" />
              <span>Remember me</span>
            </label>
            <router-link to="/forgot-password" class="forgot-link">Forgot password?</router-link>
          </div>

          <button type="submit" class="btn btn-primary btn-block" :disabled="loading">
            {{ loading ? 'Signing in...' : 'Sign in' }}
          </button>
        </form>

        <div class="auth-footer">
          <p>Don't have an account? <router-link to="/register" class="auth-link">Sign up</router-link></p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { useAuthStore } from '../stores/auth';

export default {
  name: 'Login',
  setup() {
    return { authStore: useAuthStore() };
  },
  data() {
    return {
      form: { email: '', password: '', remember: false },
      errors: {},
      error: '',
    };
  },
  computed: {
    loading() { return this.authStore.loading; },
  },
  methods: {
    async handleLogin() {
      this.error = '';
      this.errors = {};
      const result = await this.authStore.login(this.form.email, this.form.password, this.form.remember);
      if (result.success) {
        this.$router.push(this.$route.query.redirect || '/');
      } else {
        this.error = result.message || 'Login failed';
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

.auth-container {
  width: 100%;
  max-width: 400px;
}

.auth-card {
  background: #fff;
  border-radius: 12px;
  padding: 2.5rem;
  border: 1px solid #e5e5e5;
}

.auth-title {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1a1a1a;
  margin-bottom: 0.25rem;
}

.auth-subtitle {
  color: #666;
  margin-bottom: 1.5rem;
  font-size: 0.9rem;
}

.form-group {
  margin-bottom: 1.25rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  color: #1a1a1a;
  font-weight: 500;
  font-size: 0.9rem;
}

.form-group input[type="email"],
.form-group input[type="password"],
.form-group input[type="text"] {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 0.95rem;
  transition: border-color 0.2s;
  background: #fff;
}

.form-group input:focus {
  outline: none;
  border-color: #1a1a1a;
}

.form-group input:disabled {
  background-color: #f5f5f5;
  cursor: not-allowed;
}

.error-text {
  display: block;
  color: #dc2626;
  font-size: 0.8rem;
  margin-top: 0.25rem;
}

.checkbox-group {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-size: 0.9rem;
  color: #666;
}

.checkbox-label input[type="checkbox"] {
  width: 1rem;
  height: 1rem;
  cursor: pointer;
}

.forgot-link {
  color: #666;
  text-decoration: none;
  font-size: 0.875rem;
}

.forgot-link:hover {
  color: #1a1a1a;
}

.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 8px;
  font-size: 0.95rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary {
  background: #1a1a1a;
  color: #fff;
}

.btn-primary:hover:not(:disabled) {
  background: #333;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-block {
  width: 100%;
}

.alert {
  padding: 0.875rem 1rem;
  border-radius: 8px;
  margin-bottom: 1.25rem;
  font-size: 0.9rem;
}

.alert-error {
  background-color: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.auth-footer {
  margin-top: 1.5rem;
  text-align: center;
  color: #666;
  font-size: 0.9rem;
}

.auth-link {
  color: #1a1a1a;
  text-decoration: none;
  font-weight: 500;
}

.auth-link:hover {
  text-decoration: underline;
}

@media (max-width: 480px) {
  .auth-card {
    padding: 1.5rem;
  }
}
</style>
