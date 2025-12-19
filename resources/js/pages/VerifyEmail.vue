<template>
  <div class="auth-page">
    <div class="auth-container">
      <div class="auth-card">
        <!-- Loading state -->
        <div v-if="loading" class="verification-loading">
          <div class="spinner"></div>
          <p>Verifying your email...</p>
        </div>

        <!-- Success state -->
        <div v-else-if="success" class="verification-result">
          <div class="result-icon success-icon">✓</div>
          <h1 class="result-title">Email Verified!</h1>
          <p class="result-message">Your email has been verified successfully. You can now log in to your account.</p>
          <router-link to="/login" class="btn btn-primary btn-block">
            Go to Login
          </router-link>
        </div>

        <!-- Already verified state -->
        <div v-else-if="alreadyVerified" class="verification-result">
          <div class="result-icon info-icon">i</div>
          <h1 class="result-title">Already Verified</h1>
          <p class="result-message">This email has already been verified. You can log in to your account.</p>
          <router-link to="/login" class="btn btn-primary btn-block">
            Go to Login
          </router-link>
        </div>

        <!-- Error state -->
        <div v-else class="verification-result">
          <div class="result-icon error-icon">✕</div>
          <h1 class="result-title">Verification Failed</h1>
          <p class="result-message">{{ errorMessage }}</p>
          <div class="error-actions">
            <router-link to="/login" class="btn btn-outline btn-block">
              Back to Login
            </router-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'VerifyEmail',
  data() {
    return {
      loading: true,
      success: false,
      alreadyVerified: false,
      errorMessage: 'Unable to verify email. The link may be invalid or expired.',
    };
  },
  async mounted() {
    await this.verifyEmail();
  },
  methods: {
    async verifyEmail() {
      const { id, hash } = this.$route.params;
      const expires = this.$route.query.expires;

      if (!id || !hash) {
        this.loading = false;
        this.errorMessage = 'Invalid verification link.';
        return;
      }

      try {
        const response = await axios.get(`/api/auth/verify-email/${id}/${hash}`, {
          params: { expires },
        });

        this.loading = false;
        if (response.data.success) {
          this.success = true;
        } else if (response.data.error === 'already_verified') {
          this.alreadyVerified = true;
        } else {
          this.errorMessage = response.data.message || 'Verification failed.';
        }
      } catch (error) {
        this.loading = false;
        const data = error.response?.data;

        if (data?.error === 'already_verified') {
          this.alreadyVerified = true;
        } else if (data?.error === 'expired') {
          this.errorMessage = 'This verification link has expired. Please request a new one from the login page.';
        } else if (data?.error === 'invalid') {
          this.errorMessage = 'This verification link is invalid.';
        } else if (data?.error === 'not_found') {
          this.errorMessage = 'User not found.';
        } else {
          this.errorMessage = data?.message || 'An error occurred while verifying your email.';
        }
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
  text-align: center;
}

.verification-loading {
  padding: 2rem 0;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e5e5e5;
  border-top-color: #1a1a1a;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.verification-loading p {
  color: #666;
  margin: 0;
}

.verification-result {
  padding: 1rem 0;
}

.result-icon {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  font-weight: bold;
  margin: 0 auto 1.5rem;
}

.success-icon {
  background: #22c55e;
  color: #fff;
}

.info-icon {
  background: #3b82f6;
  color: #fff;
}

.error-icon {
  background: #ef4444;
  color: #fff;
}

.result-title {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1a1a1a;
  margin-bottom: 0.75rem;
}

.result-message {
  color: #666;
  line-height: 1.6;
  margin-bottom: 1.5rem;
}

.btn {
  display: inline-block;
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

.btn-primary {
  background: #1a1a1a;
  color: #fff;
}

.btn-primary:hover {
  background: #333;
}

.btn-outline {
  background: transparent;
  color: #1a1a1a;
  border: 1px solid #ddd;
}

.btn-outline:hover {
  background: #f5f5f5;
}

.btn-block {
  width: 100%;
}

.error-actions {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

@media (max-width: 480px) {
  .auth-card {
    padding: 1.5rem;
  }
}
</style>
