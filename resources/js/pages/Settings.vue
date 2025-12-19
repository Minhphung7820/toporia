<template>
  <div class="settings-page">
    <div class="settings-container">
      <!-- Page Header -->
      <header class="settings-header">
        <h1>Account Settings</h1>
        <p>Manage your profile information and account security</p>
      </header>

      <!-- Settings Navigation -->
      <nav class="settings-nav">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          :class="['nav-item', { active: activeTab === tab.id }]"
          @click="setActiveTab(tab.id)"
        >
          <component :is="tab.icon" class="nav-icon" />
          <span>{{ tab.label }}</span>
        </button>
      </nav>

      <!-- Settings Content -->
      <div class="settings-content">
        <!-- Profile Tab -->
        <div v-show="activeTab === 'profile'" class="tab-content">
          <!-- Avatar Section -->
          <section class="settings-section">
            <div class="section-header">
              <h2>Profile Photo</h2>
              <p>Upload a photo to personalize your account</p>
            </div>
            <div class="avatar-upload">
              <div class="avatar-preview">
                <img
                  v-if="user?.avatar"
                  :src="user.avatar"
                  :alt="user.name"
                  class="avatar-image"
                />
                <div v-else class="avatar-placeholder">
                  {{ getInitials(user?.name || 'U') }}
                </div>
                <div class="avatar-overlay" @click="triggerFileInput">
                  <CameraIcon class="overlay-icon" />
                </div>
              </div>
              <div class="avatar-actions">
                <input
                  ref="fileInput"
                  type="file"
                  accept="image/jpeg,image/png,image/gif,image/webp"
                  class="hidden"
                  @change="handleAvatarChange"
                />
                <button class="btn btn-secondary" @click="triggerFileInput" :disabled="avatarLoading">
                  <UploadIcon class="btn-icon" />
                  {{ avatarLoading ? 'Uploading...' : 'Upload Photo' }}
                </button>
                <button
                  v-if="user?.avatar"
                  class="btn btn-ghost"
                  @click="handleRemoveAvatar"
                  :disabled="avatarLoading"
                >
                  <TrashIcon class="btn-icon" />
                  Remove
                </button>
              </div>
              <p class="avatar-hint">JPG, PNG, GIF or WebP. Max 2MB.</p>
            </div>
          </section>

          <!-- Profile Info Section -->
          <section class="settings-section">
            <div class="section-header">
              <h2>Personal Information</h2>
              <p>Update your personal details</p>
            </div>

            <form @submit.prevent="handleUpdateProfile" class="profile-form">
              <div v-if="profileMessage" :class="['alert', profileSuccess ? 'alert-success' : 'alert-error']">
                {{ profileMessage }}
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label for="name">Full Name</label>
                  <input
                    id="name"
                    v-model="profileForm.name"
                    type="text"
                    placeholder="Your full name"
                    :disabled="profileLoading"
                  />
                  <span v-if="profileErrors.name" class="error-text">{{ profileErrors.name }}</span>
                </div>
                <div class="form-group">
                  <label for="email">Email Address</label>
                  <input
                    id="email"
                    :value="user?.email"
                    type="email"
                    disabled
                    class="disabled-input"
                  />
                  <span class="hint-text">Email cannot be changed</span>
                </div>
              </div>

              <div class="form-group">
                <label for="bio">Bio</label>
                <textarea
                  id="bio"
                  v-model="profileForm.bio"
                  placeholder="Tell us a little about yourself..."
                  rows="4"
                  :disabled="profileLoading"
                  maxlength="500"
                ></textarea>
                <div class="char-counter">{{ (profileForm.bio || '').length }}/500</div>
                <span v-if="profileErrors.bio" class="error-text">{{ profileErrors.bio }}</span>
              </div>

              <div class="form-group">
                <label for="website">Website</label>
                <div class="input-with-icon">
                  <GlobeIcon class="input-icon" />
                  <input
                    id="website"
                    v-model="profileForm.website"
                    type="text"
                    placeholder="https://yourwebsite.com"
                    :disabled="profileLoading"
                  />
                </div>
                <span v-if="profileErrors.website" class="error-text">{{ profileErrors.website }}</span>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn btn-primary" :disabled="profileLoading">
                  {{ profileLoading ? 'Saving...' : 'Save Changes' }}
                </button>
              </div>
            </form>
          </section>
        </div>

        <!-- Security Tab -->
        <div v-show="activeTab === 'security'" class="tab-content">
          <section class="settings-section">
            <div class="section-header">
              <h2>Change Password</h2>
              <p>Update your password to keep your account secure</p>
            </div>

            <form @submit.prevent="handleChangePassword" class="password-form">
              <div v-if="passwordMessage" :class="['alert', passwordSuccess ? 'alert-success' : 'alert-error']">
                {{ passwordMessage }}
              </div>

              <div class="form-group">
                <label for="current_password">Current Password</label>
                <div class="input-with-icon">
                  <LockIcon class="input-icon" />
                  <input
                    id="current_password"
                    v-model="passwordForm.current_password"
                    :type="showCurrentPassword ? 'text' : 'password'"
                    placeholder="Enter current password"
                    :disabled="passwordLoading"
                  />
                  <button type="button" class="toggle-password" @click="showCurrentPassword = !showCurrentPassword">
                    <EyeIcon v-if="!showCurrentPassword" />
                    <EyeOffIcon v-else />
                  </button>
                </div>
                <span v-if="passwordErrors.current_password" class="error-text">{{ passwordErrors.current_password }}</span>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label for="new_password">New Password</label>
                  <div class="input-with-icon">
                    <LockIcon class="input-icon" />
                    <input
                      id="new_password"
                      v-model="passwordForm.password"
                      :type="showNewPassword ? 'text' : 'password'"
                      placeholder="At least 8 characters"
                      :disabled="passwordLoading"
                    />
                    <button type="button" class="toggle-password" @click="showNewPassword = !showNewPassword">
                      <EyeIcon v-if="!showNewPassword" />
                      <EyeOffIcon v-else />
                    </button>
                  </div>
                  <span v-if="passwordErrors.password" class="error-text">{{ passwordErrors.password }}</span>
                </div>
                <div class="form-group">
                  <label for="confirm_password">Confirm New Password</label>
                  <div class="input-with-icon">
                    <LockIcon class="input-icon" />
                    <input
                      id="confirm_password"
                      v-model="passwordForm.password_confirmation"
                      :type="showConfirmPassword ? 'text' : 'password'"
                      placeholder="Confirm new password"
                      :disabled="passwordLoading"
                    />
                    <button type="button" class="toggle-password" @click="showConfirmPassword = !showConfirmPassword">
                      <EyeIcon v-if="!showConfirmPassword" />
                      <EyeOffIcon v-else />
                    </button>
                  </div>
                  <span v-if="passwordErrors.password_confirmation" class="error-text">{{ passwordErrors.password_confirmation }}</span>
                </div>
              </div>

              <!-- Password Strength Indicator -->
              <div v-if="passwordForm.password" class="password-strength">
                <div class="strength-bar">
                  <div :class="['strength-fill', passwordStrength.class]" :style="{ width: passwordStrength.width }"></div>
                </div>
                <span :class="['strength-text', passwordStrength.class]">{{ passwordStrength.label }}</span>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn btn-primary" :disabled="passwordLoading">
                  {{ passwordLoading ? 'Updating...' : 'Update Password' }}
                </button>
              </div>
            </form>
          </section>

          <!-- Account Info -->
          <section class="settings-section account-info">
            <div class="section-header">
              <h2>Account Information</h2>
            </div>
            <div class="info-grid">
              <div class="info-item">
                <span class="info-label">Account Type</span>
                <span class="info-value">
                  <span :class="['role-badge', user?.role]">{{ formatRole(user?.role) }}</span>
                </span>
              </div>
              <div class="info-item">
                <span class="info-label">Member Since</span>
                <span class="info-value">{{ formatDate(user?.created_at) }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Email Verified</span>
                <span class="info-value">
                  <span v-if="user?.email_verified_at" class="verified">
                    <CheckCircleIcon class="verify-icon" /> Verified
                  </span>
                  <span v-else class="not-verified">
                    <AlertCircleIcon class="verify-icon" /> Not verified
                  </span>
                </span>
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, h } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

// Icons as functional components
const UserIcon = () => h('svg', { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
  h('path', { d: 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2' }),
  h('circle', { cx: 12, cy: 7, r: 4 })
]);

const ShieldIcon = () => h('svg', { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
  h('path', { d: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z' })
]);

const CameraIcon = () => h('svg', { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
  h('path', { d: 'M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z' }),
  h('circle', { cx: 12, cy: 13, r: 4 })
]);

const UploadIcon = () => h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
  h('path', { d: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4' }),
  h('polyline', { points: '17 8 12 3 7 8' }),
  h('line', { x1: 12, y1: 3, x2: 12, y2: 15 })
]);

const TrashIcon = () => h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
  h('polyline', { points: '3 6 5 6 21 6' }),
  h('path', { d: 'M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2' })
]);

const GlobeIcon = () => h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
  h('circle', { cx: 12, cy: 12, r: 10 }),
  h('line', { x1: 2, y1: 12, x2: 22, y2: 12 }),
  h('path', { d: 'M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z' })
]);

const LockIcon = () => h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
  h('rect', { x: 3, y: 11, width: 18, height: 11, rx: 2, ry: 2 }),
  h('path', { d: 'M7 11V7a5 5 0 0 1 10 0v4' })
]);

const EyeIcon = () => h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
  h('path', { d: 'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z' }),
  h('circle', { cx: 12, cy: 12, r: 3 })
]);

const EyeOffIcon = () => h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
  h('path', { d: 'M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24' }),
  h('line', { x1: 1, y1: 1, x2: 23, y2: 23 })
]);

const CheckCircleIcon = () => h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
  h('path', { d: 'M22 11.08V12a10 10 0 1 1-5.93-9.14' }),
  h('polyline', { points: '22 4 12 14.01 9 11.01' })
]);

const AlertCircleIcon = () => h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
  h('circle', { cx: 12, cy: 12, r: 10 }),
  h('line', { x1: 12, y1: 8, x2: 12, y2: 12 }),
  h('line', { x1: 12, y1: 16, x2: 12.01, y2: 16 })
]);

// Tabs
const tabs = [
  { id: 'profile', label: 'Profile', icon: UserIcon },
  { id: 'security', label: 'Security', icon: ShieldIcon },
];

// Valid tabs
const validTabs = ['profile', 'security'];

// Initialize from URL or default to 'profile'
const getInitialTab = () => {
  const tabFromUrl = route.query.tab;
  return validTabs.includes(tabFromUrl) ? tabFromUrl : 'profile';
};

const activeTab = ref(getInitialTab());

// Sync tab changes to URL
const setActiveTab = (tabId) => {
  activeTab.value = tabId;
  router.replace({ query: { ...route.query, tab: tabId } });
};

// User data
const user = computed(() => authStore.user);
const fileInput = ref(null);

// Profile form
const profileForm = ref({
  name: '',
  bio: '',
  website: '',
});
const profileLoading = ref(false);
const profileMessage = ref('');
const profileSuccess = ref(false);
const profileErrors = ref({});

// Avatar
const avatarLoading = ref(false);

// Password form
const passwordForm = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
});
const passwordLoading = ref(false);
const passwordMessage = ref('');
const passwordSuccess = ref(false);
const passwordErrors = ref({});
const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

// Password strength
const passwordStrength = computed(() => {
  const password = passwordForm.value.password;
  if (!password) return { label: '', class: '', width: '0%' };

  let score = 0;
  if (password.length >= 8) score++;
  if (password.length >= 12) score++;
  if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
  if (/\d/.test(password)) score++;
  if (/[^a-zA-Z0-9]/.test(password)) score++;

  if (score <= 1) return { label: 'Weak', class: 'weak', width: '20%' };
  if (score <= 2) return { label: 'Fair', class: 'fair', width: '40%' };
  if (score <= 3) return { label: 'Good', class: 'good', width: '60%' };
  if (score <= 4) return { label: 'Strong', class: 'strong', width: '80%' };
  return { label: 'Very Strong', class: 'very-strong', width: '100%' };
});

// Initialize form with user data
const initializeForm = () => {
  if (user.value) {
    profileForm.value = {
      name: user.value.name || '',
      bio: user.value.bio || '',
      website: user.value.website || '',
    };
  }
};

watch(user, initializeForm, { immediate: true });

onMounted(() => {
  initializeForm();
});

// Methods
const getInitials = (name) => {
  return name
    .split(' ')
    .map(word => word[0])
    .join('')
    .toUpperCase()
    .substring(0, 2);
};

const formatRole = (role) => {
  const roles = {
    admin: 'Administrator',
    moderator: 'Moderator',
    user: 'Member',
  };
  return roles[role] || role;
};

const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
};

const triggerFileInput = () => {
  fileInput.value?.click();
};

const handleAvatarChange = async (e) => {
  const file = e.target.files?.[0];
  if (!file) return;

  avatarLoading.value = true;
  const result = await authStore.updateAvatar(file);
  avatarLoading.value = false;

  if (!result.success) {
    profileMessage.value = result.message || 'Failed to upload avatar';
    profileSuccess.value = false;
  } else {
    profileMessage.value = 'Avatar updated successfully';
    profileSuccess.value = true;
  }

  // Clear file input
  e.target.value = '';

  // Clear message after 3 seconds
  setTimeout(() => {
    profileMessage.value = '';
  }, 3000);
};

const handleRemoveAvatar = async () => {
  if (!confirm('Are you sure you want to remove your avatar?')) return;

  avatarLoading.value = true;
  const result = await authStore.removeAvatar();
  avatarLoading.value = false;

  if (result.success) {
    profileMessage.value = 'Avatar removed successfully';
    profileSuccess.value = true;
  } else {
    profileMessage.value = result.message || 'Failed to remove avatar';
    profileSuccess.value = false;
  }

  setTimeout(() => {
    profileMessage.value = '';
  }, 3000);
};

const handleUpdateProfile = async () => {
  profileMessage.value = '';
  profileErrors.value = {};
  profileLoading.value = true;

  const result = await authStore.updateProfile(profileForm.value);
  profileLoading.value = false;

  if (result.success) {
    profileMessage.value = 'Profile updated successfully';
    profileSuccess.value = true;
  } else {
    profileMessage.value = result.message || 'Failed to update profile';
    profileSuccess.value = false;
    if (result.errors) {
      profileErrors.value = result.errors;
    }
  }

  setTimeout(() => {
    profileMessage.value = '';
  }, 3000);
};

const handleChangePassword = async () => {
  passwordMessage.value = '';
  passwordErrors.value = {};
  passwordLoading.value = true;

  const result = await authStore.changePassword(passwordForm.value);
  passwordLoading.value = false;

  if (result.success) {
    passwordMessage.value = 'Password changed successfully';
    passwordSuccess.value = true;
    passwordForm.value = {
      current_password: '',
      password: '',
      password_confirmation: '',
    };
  } else {
    passwordMessage.value = result.message || 'Failed to change password';
    passwordSuccess.value = false;
    if (result.errors) {
      passwordErrors.value = result.errors;
    }
  }

  setTimeout(() => {
    passwordMessage.value = '';
  }, 5000);
};
</script>

<style scoped>
.settings-page {
  min-height: calc(100vh - 120px);
  padding: 2rem 1rem;
  background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f2 100%);
}

.settings-container {
  max-width: 800px;
  margin: 0 auto;
}

/* Header */
.settings-header {
  margin-bottom: 2rem;
}

.settings-header h1 {
  font-size: 1.75rem;
  font-weight: 700;
  color: #1a1a1a;
  margin-bottom: 0.25rem;
}

.settings-header p {
  color: #666;
  font-size: 0.95rem;
}

/* Navigation */
.settings-nav {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
  background: #fff;
  padding: 0.5rem;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.25rem;
  border: none;
  background: transparent;
  color: #666;
  font-size: 0.95rem;
  font-weight: 500;
  cursor: pointer;
  border-radius: 8px;
  transition: all 0.2s;
}

.nav-item:hover {
  color: #1a1a1a;
  background: #f5f5f5;
}

.nav-item.active {
  color: #fff;
  background: #1a1a1a;
}

.nav-icon {
  flex-shrink: 0;
}

/* Content */
.settings-content {
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  overflow: hidden;
}

.tab-content {
  padding: 2rem;
}

/* Sections */
.settings-section {
  padding-bottom: 2rem;
  margin-bottom: 2rem;
  border-bottom: 1px solid #eee;
}

.settings-section:last-child {
  padding-bottom: 0;
  margin-bottom: 0;
  border-bottom: none;
}

.section-header {
  margin-bottom: 1.5rem;
}

.section-header h2 {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1a1a1a;
  margin-bottom: 0.25rem;
}

.section-header p {
  color: #666;
  font-size: 0.875rem;
}

/* Avatar Upload */
.avatar-upload {
  display: flex;
  align-items: flex-start;
  gap: 1.5rem;
}

.avatar-preview {
  position: relative;
  width: 100px;
  height: 100px;
  border-radius: 50%;
  overflow: hidden;
  cursor: pointer;
  flex-shrink: 0;
}

.avatar-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-placeholder {
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, #1a1a1a 0%, #333 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  font-weight: 600;
}

.avatar-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.2s;
}

.avatar-preview:hover .avatar-overlay {
  opacity: 1;
}

.overlay-icon {
  color: #fff;
}

.avatar-actions {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.avatar-hint {
  font-size: 0.8rem;
  color: #999;
  margin-top: 0.5rem;
}

/* Forms */
.form-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.25rem;
}

.form-group {
  margin-bottom: 1.25rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #333;
}

.form-group input,
.form-group textarea {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 0.95rem;
  transition: all 0.2s;
  background: #fff;
}

.form-group input:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #1a1a1a;
  box-shadow: 0 0 0 3px rgba(26, 26, 26, 0.1);
}

.form-group input:disabled,
.form-group textarea:disabled {
  background: #f5f5f5;
  cursor: not-allowed;
}

.disabled-input {
  background: #f5f5f5 !important;
  color: #999;
}

.form-group textarea {
  resize: vertical;
  min-height: 100px;
}

.char-counter {
  text-align: right;
  font-size: 0.75rem;
  color: #999;
  margin-top: 0.25rem;
}

.hint-text {
  display: block;
  font-size: 0.75rem;
  color: #999;
  margin-top: 0.25rem;
}

.error-text {
  display: block;
  font-size: 0.8rem;
  color: #dc2626;
  margin-top: 0.25rem;
}

/* Input with icon */
.input-with-icon {
  position: relative;
}

.input-with-icon .input-icon {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: #999;
  pointer-events: none;
}

.input-with-icon input {
  padding-left: 2.75rem;
  padding-right: 2.75rem;
}

.toggle-password {
  position: absolute;
  right: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: #999;
  cursor: pointer;
  padding: 0.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.toggle-password:hover {
  color: #666;
}

/* Password Strength */
.password-strength {
  margin-bottom: 1.25rem;
}

.strength-bar {
  height: 4px;
  background: #eee;
  border-radius: 2px;
  overflow: hidden;
  margin-bottom: 0.5rem;
}

.strength-fill {
  height: 100%;
  border-radius: 2px;
  transition: all 0.3s;
}

.strength-fill.weak { background: #dc2626; }
.strength-fill.fair { background: #f59e0b; }
.strength-fill.good { background: #10b981; }
.strength-fill.strong { background: #059669; }
.strength-fill.very-strong { background: #047857; }

.strength-text {
  font-size: 0.75rem;
  font-weight: 500;
}

.strength-text.weak { color: #dc2626; }
.strength-text.fair { color: #f59e0b; }
.strength-text.good { color: #10b981; }
.strength-text.strong { color: #059669; }
.strength-text.very-strong { color: #047857; }

/* Form Actions */
.form-actions {
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid #eee;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.25rem;
  border: none;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  background: #1a1a1a;
  color: #fff;
}

.btn-primary:hover:not(:disabled) {
  background: #333;
}

.btn-secondary {
  background: #f5f5f5;
  color: #1a1a1a;
  border: 1px solid #ddd;
}

.btn-secondary:hover:not(:disabled) {
  background: #eee;
}

.btn-ghost {
  background: transparent;
  color: #666;
}

.btn-ghost:hover:not(:disabled) {
  background: #f5f5f5;
  color: #1a1a1a;
}

.btn-icon {
  flex-shrink: 0;
}

/* Alerts */
.alert {
  padding: 0.875rem 1rem;
  border-radius: 8px;
  margin-bottom: 1.25rem;
  font-size: 0.9rem;
}

.alert-error {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.alert-success {
  background: #f0fdf4;
  color: #16a34a;
  border: 1px solid #bbf7d0;
}

/* Account Info */
.account-info {
  background: #fafafa;
  border-radius: 12px;
  padding: 1.5rem;
  margin-top: 1.5rem;
  border-bottom: none;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.5rem;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.info-label {
  font-size: 0.75rem;
  font-weight: 500;
  color: #999;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.info-value {
  font-size: 0.95rem;
  color: #1a1a1a;
  font-weight: 500;
}

.role-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 500;
}

.role-badge.admin {
  background: #fef3c7;
  color: #92400e;
}

.role-badge.moderator {
  background: #dbeafe;
  color: #1e40af;
}

.role-badge.user {
  background: #f3f4f6;
  color: #374151;
}

.verified {
  color: #16a34a;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.not-verified {
  color: #f59e0b;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.verify-icon {
  flex-shrink: 0;
}

/* Utilities */
.hidden {
  display: none;
}

/* Responsive */
@media (max-width: 768px) {
  .tab-content {
    padding: 1.5rem;
  }

  .form-row {
    grid-template-columns: 1fr;
  }

  .avatar-upload {
    flex-direction: column;
    align-items: center;
    text-align: center;
  }

  .avatar-actions {
    align-items: center;
  }

  .info-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .settings-nav {
    flex-wrap: wrap;
  }

  .nav-item {
    flex: 1;
    justify-content: center;
  }
}

@media (max-width: 480px) {
  .settings-page {
    padding: 1rem 0.75rem;
  }

  .tab-content {
    padding: 1.25rem;
  }

  .settings-header h1 {
    font-size: 1.5rem;
  }

  .avatar-preview {
    width: 80px;
    height: 80px;
  }

  .avatar-placeholder {
    font-size: 1.5rem;
  }
}
</style>
