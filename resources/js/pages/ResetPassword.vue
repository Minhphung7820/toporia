<template>
  <div class="auth-page">
    <div class="auth-container">
      <div class="auth-card">
        <h1 class="auth-title">Reset Password</h1>
        <p class="auth-subtitle">Enter your new password below.</p>

        <form @submit.prevent="handleResetPassword" class="auth-form">
          <div v-if="error" class="alert alert-error">
            {{ error }}
          </div>

          <div class="form-group">
            <label for="email">Email</label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              required
              placeholder="your@email.com"
              :disabled="loading || success"
            />
            <span v-if="errors.email" class="error-text">{{ errors.email }}</span>
          </div>

          <div class="form-group">
            <label for="token">Reset Token</label>
            <input
              id="token"
              v-model="form.token"
              type="text"
              required
              placeholder="Enter reset token"
              :disabled="loading || success"
            />
            <span v-if="errors.token" class="error-text">{{ errors.token }}</span>
          </div>

          <div class="form-group">
            <label for="password">New Password</label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              required
              placeholder="At least 8 characters"
              :disabled="loading || success"
            />
            <span v-if="errors.password" class="error-text">{{ errors.password }}</span>
          </div>

          <div class="form-group">
            <label for="password_confirmation">Confirm New Password</label>
            <input
              id="password_confirmation"
              v-model="form.password_confirmation"
              type="password"
              required
              placeholder="Confirm your password"
              :disabled="loading || success"
            />
            <span v-if="errors.password_confirmation" class="error-text">{{ errors.password_confirmation }}</span>
          </div>

          <button v-if="!success" type="submit" class="btn btn-primary btn-block" :disabled="loading">
            <span v-if="loading">Resetting password...</span>
            <span v-else>Reset Password</span>
          </button>

          <div v-else class="success-message">
            <div class="alert alert-success">
              <strong>Password reset successful!</strong>
              <p>You can now login with your new password.</p>
            </div>
            <router-link to="/login" class="btn btn-primary btn-block">
              Go to Login
            </router-link>
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
    const authStore = useAuthStore();
    return { authStore };
  },
  data() {
    return {
      form: {
        email: '',
        token: '',
        password: '',
        password_confirmation: '',
      },
      errors: {},
      error: '',
      success: false,
    };
  },
  computed: {
    loading() {
      return this.authStore.loading;
    },
  },
  mounted() {
    // Get token and email from URL query params
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
        if (result.errors) {
          this.errors = result.errors;
        }
      }
    },
  },
};
</script>

<style scoped>
/* Same styles as Login.vue */
.auth-page {
  min-height: calc(100vh - 200px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem 1rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.auth-container {
  width: 100%;
  max-width: 450px;
}

.auth-card {
  background: white;
  border-radius: 12px;
  padding: 2.5rem;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
}

.auth-title {
  font-size: 2rem;
  font-weight: 700;
  color: #2c3e50;
  margin-bottom: 0.5rem;
  text-align: center;
}

.auth-subtitle {
  color: #666;
  text-align: center;
  margin-bottom: 2rem;
}

.auth-form {
  margin-top: 2rem;
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  color: #2c3e50;
  font-weight: 500;
}

.form-group input {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  font-size: 1rem;
  transition: border-color 0.3s;
  box-sizing: border-box;
}

.form-group input:focus {
  outline: none;
  border-color: #667eea;
}

.form-group input:disabled {
  background-color: #f5f5f5;
  cursor: not-allowed;
}

.error-text {
  display: block;
  color: #e74c3c;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.btn {
  padding: 0.75rem 2rem;
  border: none;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-block {
  width: 100%;
}

.alert {
  padding: 1rem;
  border-radius: 8px;
  margin-bottom: 1.5rem;
}

.alert-error {
  background-color: #fee;
  color: #c33;
  border: 1px solid #fcc;
}

.alert-success {
  background-color: #efe;
  color: #3c3;
  border: 1px solid #cfc;
}

.alert-success strong {
  display: block;
  margin-bottom: 0.5rem;
}

.success-message {
  margin-top: 1rem;
}

@media (max-width: 480px) {
  .auth-card {
    padding: 2rem 1.5rem;
  }

  .auth-title {
    font-size: 1.75rem;
  }
}
</style>

