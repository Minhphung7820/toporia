<template>
  <div class="auth-page">
    <div class="auth-container">
      <div class="auth-card">
        <h1 class="auth-title">Forgot Password</h1>
        <p class="auth-subtitle">Enter your email address and we'll send you a link to reset your password.</p>

        <form @submit.prevent="handleForgotPassword" class="auth-form" v-if="!success">
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
              :disabled="loading"
            />
            <span v-if="errors.email" class="error-text">{{ errors.email }}</span>
          </div>

          <button type="submit" class="btn btn-primary btn-block" :disabled="loading">
            <span v-if="loading">Sending...</span>
            <span v-else>Send Reset Link</span>
          </button>
        </form>

        <div v-else class="success-message">
          <div class="alert alert-success">
            <strong>Check your email!</strong>
            <p>{{ successMessage }}</p>
          </div>
        </div>

        <div class="auth-footer">
          <p>
            Remember your password?
            <router-link to="/login" class="auth-link">Login</router-link>
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { useAuthStore } from '../stores/auth';

export default {
  name: 'ForgotPassword',
  setup() {
    const authStore = useAuthStore();
    return { authStore };
  },
  data() {
    return {
      form: {
        email: '',
      },
      errors: {},
      error: '',
      success: false,
      successMessage: '',
    };
  },
  computed: {
    loading() {
      return this.authStore.loading;
    },
  },
  methods: {
    async handleForgotPassword() {
      this.error = '';
      this.errors = {};

      const result = await this.authStore.forgotPassword(this.form.email);

      if (result.success) {
        this.success = true;
        this.successMessage = result.message || 'If the email exists, a password reset link has been sent.';
      } else {
        this.error = result.message || 'Failed to send reset link';
        if (result.errors) {
          this.errors = result.errors;
        }
      }
    },
  },
};
</script>

<style scoped>
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

.form-group input[type="email"] {
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
  margin-top: 2rem;
}

.auth-footer {
  margin-top: 2rem;
  text-align: center;
  color: #666;
}

.auth-link {
  color: #667eea;
  text-decoration: none;
  font-weight: 600;
  transition: color 0.3s;
}

.auth-link:hover {
  color: #764ba2;
  text-decoration: underline;
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

