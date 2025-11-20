<template>
  <div class="page-container">
    <div class="content-container">
      <div class="card">
        <h1 class="page-title">Change Password</h1>
        <p class="page-subtitle">Update your account password.</p>

        <form @submit.prevent="handleChangePassword" class="form">
          <div v-if="error" class="alert alert-error">
            {{ error }}
          </div>

          <div v-if="success" class="alert alert-success">
            {{ successMessage }}
          </div>

          <div class="form-group">
            <label for="current_password">Current Password</label>
            <input
              id="current_password"
              v-model="form.current_password"
              type="password"
              required
              placeholder="Enter current password"
              :disabled="loading"
            />
            <span v-if="errors.current_password" class="error-text">{{ errors.current_password }}</span>
          </div>

          <div class="form-group">
            <label for="password">New Password</label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              required
              placeholder="At least 8 characters"
              :disabled="loading"
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
              :disabled="loading"
            />
            <span v-if="errors.password_confirmation" class="error-text">{{ errors.password_confirmation }}</span>
          </div>

          <button type="submit" class="btn btn-primary" :disabled="loading">
            <span v-if="loading">Changing password...</span>
            <span v-else>Change Password</span>
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
    const authStore = useAuthStore();
    return { authStore };
  },
  data() {
    return {
      form: {
        current_password: '',
        password: '',
        password_confirmation: '',
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
    async handleChangePassword() {
      this.error = '';
      this.errors = {};
      this.success = false;

      const result = await this.authStore.changePassword(this.form);

      if (result.success) {
        this.success = true;
        this.successMessage = result.message || 'Password changed successfully';
        this.form = {
          current_password: '',
          password: '',
          password_confirmation: '',
        };
      } else {
        this.error = result.message || 'Failed to change password';
        if (result.errors) {
          this.errors = result.errors;
        }
      }
    },
  },
};
</script>

<style scoped>
.page-container {
  min-height: calc(100vh - 200px);
  padding: 2rem 1rem;
}

.content-container {
  max-width: 600px;
  margin: 0 auto;
}

.card {
  background: white;
  border-radius: 12px;
  padding: 2.5rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.page-title {
  font-size: 2rem;
  font-weight: 700;
  color: #2c3e50;
  margin-bottom: 0.5rem;
}

.page-subtitle {
  color: #666;
  margin-bottom: 2rem;
}

.form {
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

@media (max-width: 480px) {
  .card {
    padding: 2rem 1.5rem;
  }

  .page-title {
    font-size: 1.75rem;
  }
}
</style>

