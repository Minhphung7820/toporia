<template>
  <AdminLayout>
    <div class="profile-page">
      <div class="page-header">
        <div>
          <h1>My Profile</h1>
          <p>Manage your personal information and security settings</p>
        </div>
      </div>

      <!-- Profile Navigation -->
      <nav class="profile-nav">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          :class="['nav-tab', { active: activeTab === tab.id }]"
          @click="setActiveTab(tab.id)"
        >
          <component :is="tab.icon" />
          <span>{{ tab.label }}</span>
        </button>
      </nav>

      <div class="profile-content">
        <!-- Profile Tab -->
        <div v-show="activeTab === 'profile'" class="tab-content">
          <!-- Avatar Section -->
          <section class="content-section">
            <div class="section-header">
              <h2>Profile Photo</h2>
              <p>Upload a photo to personalize your account</p>
            </div>
            <div class="avatar-section">
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
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z" />
                    <circle cx="12" cy="13" r="4" />
                  </svg>
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
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                    <polyline points="17 8 12 3 7 8" />
                    <line x1="12" y1="3" x2="12" y2="15" />
                  </svg>
                  {{ avatarLoading ? 'Uploading...' : 'Upload Photo' }}
                </button>
                <button
                  v-if="user?.avatar"
                  class="btn btn-ghost"
                  @click="handleRemoveAvatar"
                  :disabled="avatarLoading"
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6" />
                    <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
                  </svg>
                  Remove
                </button>
              </div>
              <p class="avatar-hint">JPG, PNG, GIF or WebP. Max 2MB.</p>
            </div>
          </section>

          <!-- Profile Info Section -->
          <section class="content-section">
            <div class="section-header">
              <h2>Personal Information</h2>
              <p>Update your personal details</p>
            </div>

            <form @submit.prevent="handleUpdateProfile" class="profile-form">
              <div v-if="profileMessage" :class="['alert', profileSuccess ? 'alert-success' : 'alert-error']">
                {{ profileMessage }}
              </div>

              <div class="form-grid">
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
                  <span class="hint-text">Email cannot be changed for security reasons</span>
                </div>
              </div>

              <div class="form-group">
                <label for="bio">Bio</label>
                <textarea
                  id="bio"
                  v-model="profileForm.bio"
                  placeholder="Tell us about yourself..."
                  rows="4"
                  maxlength="500"
                  :disabled="profileLoading"
                ></textarea>
                <div class="textarea-footer">
                  <span v-if="profileErrors.bio" class="error-text">{{ profileErrors.bio }}</span>
                  <span class="char-count">{{ (profileForm.bio || '').length }}/500</span>
                </div>
              </div>

              <div class="form-group">
                <label for="website">Website</label>
                <input
                  id="website"
                  v-model="profileForm.website"
                  type="url"
                  placeholder="https://yourwebsite.com"
                  :disabled="profileLoading"
                />
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
          <section class="content-section">
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
                <div class="password-input">
                  <input
                    id="current_password"
                    v-model="passwordForm.current_password"
                    :type="showCurrentPassword ? 'text' : 'password'"
                    placeholder="Enter current password"
                    :disabled="passwordLoading"
                  />
                  <button
                    type="button"
                    class="password-toggle"
                    @click="showCurrentPassword = !showCurrentPassword"
                  >
                    <svg v-if="showCurrentPassword" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24" />
                      <line x1="1" y1="1" x2="23" y2="23" />
                    </svg>
                    <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                      <circle cx="12" cy="12" r="3" />
                    </svg>
                  </button>
                </div>
                <span v-if="passwordErrors.current_password" class="error-text">{{ passwordErrors.current_password }}</span>
              </div>

              <div class="form-group">
                <label for="new_password">New Password</label>
                <div class="password-input">
                  <input
                    id="new_password"
                    v-model="passwordForm.new_password"
                    :type="showNewPassword ? 'text' : 'password'"
                    placeholder="Enter new password"
                    :disabled="passwordLoading"
                  />
                  <button
                    type="button"
                    class="password-toggle"
                    @click="showNewPassword = !showNewPassword"
                  >
                    <svg v-if="showNewPassword" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24" />
                      <line x1="1" y1="1" x2="23" y2="23" />
                    </svg>
                    <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                      <circle cx="12" cy="12" r="3" />
                    </svg>
                  </button>
                </div>
                <span v-if="passwordErrors.new_password" class="error-text">{{ passwordErrors.new_password }}</span>

                <!-- Password Strength Indicator -->
                <div v-if="passwordForm.new_password" class="password-strength">
                  <div class="strength-bar">
                    <div :class="['strength-fill', passwordStrength.class]" :style="{ width: passwordStrength.width }"></div>
                  </div>
                  <span :class="['strength-text', passwordStrength.class]">{{ passwordStrength.label }}</span>
                </div>
              </div>

              <div class="form-group">
                <label for="new_password_confirmation">Confirm New Password</label>
                <div class="password-input">
                  <input
                    id="new_password_confirmation"
                    v-model="passwordForm.new_password_confirmation"
                    :type="showConfirmPassword ? 'text' : 'password'"
                    placeholder="Confirm new password"
                    :disabled="passwordLoading"
                  />
                  <button
                    type="button"
                    class="password-toggle"
                    @click="showConfirmPassword = !showConfirmPassword"
                  >
                    <svg v-if="showConfirmPassword" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24" />
                      <line x1="1" y1="1" x2="23" y2="23" />
                    </svg>
                    <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                      <circle cx="12" cy="12" r="3" />
                    </svg>
                  </button>
                </div>
                <span v-if="passwordErrors.new_password_confirmation" class="error-text">{{ passwordErrors.new_password_confirmation }}</span>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn btn-primary" :disabled="passwordLoading">
                  {{ passwordLoading ? 'Updating...' : 'Update Password' }}
                </button>
              </div>
            </form>
          </section>

          <!-- Account Info Section -->
          <section class="content-section account-info">
            <div class="section-header">
              <h2>Account Information</h2>
              <p>Your account details</p>
            </div>
            <div class="info-grid">
              <div class="info-item">
                <span class="info-label">Role</span>
                <span :class="['info-value', 'role-badge', `role-${user?.role}`]">
                  {{ formatRole(user?.role) }}
                </span>
              </div>
              <div class="info-item">
                <span class="info-label">Member Since</span>
                <span class="info-value">{{ formatDate(user?.created_at) }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Email Verified</span>
                <span :class="['info-value', user?.email_verified_at ? 'verified' : 'not-verified']">
                  {{ user?.email_verified_at ? 'Verified' : 'Not Verified' }}
                </span>
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, reactive, onMounted, watch, h } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminLayout from '../components/layout/AdminLayout.vue';
import { useAuthStore } from '../../stores/auth';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

// Icons as render functions
const UserIcon = () => h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
  h('path', { d: 'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2' }),
  h('circle', { cx: 12, cy: 7, r: 4 })
]);

const ShieldIcon = () => h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
  h('path', { d: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z' })
]);

// Valid tabs
const validTabs = ['profile', 'security'];

// State - Initialize from URL or default to 'profile'
const getInitialTab = () => {
  const tabFromUrl = route.query.tab;
  return validTabs.includes(tabFromUrl) ? tabFromUrl : 'profile';
};

const activeTab = ref(getInitialTab());
const fileInput = ref(null);

// Sync tab changes to URL
const setActiveTab = (tabId) => {
  activeTab.value = tabId;
  router.replace({ query: { ...route.query, tab: tabId } });
};

// Profile form
const profileForm = reactive({
  name: '',
  bio: '',
  website: '',
});
const profileLoading = ref(false);
const profileMessage = ref('');
const profileSuccess = ref(false);
const profileErrors = reactive({});

// Password form
const passwordForm = reactive({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
});
const passwordLoading = ref(false);
const passwordMessage = ref('');
const passwordSuccess = ref(false);
const passwordErrors = reactive({});
const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

// Avatar
const avatarLoading = ref(false);

// Tabs config
const tabs = [
  { id: 'profile', label: 'Profile', icon: UserIcon },
  { id: 'security', label: 'Security', icon: ShieldIcon },
];

// Computed
const user = computed(() => authStore.user);

const passwordStrength = computed(() => {
  const password = passwordForm.new_password;
  if (!password) return { label: '', class: '', width: '0%' };

  let score = 0;
  if (password.length >= 8) score++;
  if (password.length >= 12) score++;
  if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
  if (/\d/.test(password)) score++;
  if (/[^a-zA-Z0-9]/.test(password)) score++;

  if (score <= 1) return { label: 'Weak', class: 'weak', width: '25%' };
  if (score === 2) return { label: 'Fair', class: 'fair', width: '50%' };
  if (score === 3) return { label: 'Good', class: 'good', width: '75%' };
  return { label: 'Strong', class: 'strong', width: '100%' };
});

// Methods
const getInitials = (name) => {
  if (!name) return 'U';
  return name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
};

const formatRole = (role) => {
  if (!role) return 'User';
  return role.charAt(0).toUpperCase() + role.slice(1);
};

const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
};

const triggerFileInput = () => {
  fileInput.value?.click();
};

const handleAvatarChange = async (event) => {
  const file = event.target.files?.[0];
  if (!file) return;

  // Validate file size (max 2MB)
  if (file.size > 2 * 1024 * 1024) {
    alert('File too large. Maximum size is 2MB.');
    return;
  }

  avatarLoading.value = true;
  const result = await authStore.updateAvatar(file);
  avatarLoading.value = false;

  if (!result.success) {
    alert(result.message || 'Failed to upload avatar');
  }

  // Reset file input
  if (fileInput.value) {
    fileInput.value.value = '';
  }
};

const handleRemoveAvatar = async () => {
  if (!confirm('Are you sure you want to remove your avatar?')) return;

  avatarLoading.value = true;
  const result = await authStore.removeAvatar();
  avatarLoading.value = false;

  if (!result.success) {
    alert(result.message || 'Failed to remove avatar');
  }
};

const handleUpdateProfile = async () => {
  // Reset state
  profileMessage.value = '';
  profileSuccess.value = false;
  Object.keys(profileErrors).forEach((key) => delete profileErrors[key]);

  profileLoading.value = true;
  const result = await authStore.updateProfile({
    name: profileForm.name,
    bio: profileForm.bio,
    website: profileForm.website,
  });
  profileLoading.value = false;

  if (result.success) {
    profileSuccess.value = true;
    profileMessage.value = result.message || 'Profile updated successfully';
  } else {
    profileSuccess.value = false;
    profileMessage.value = result.message || 'Failed to update profile';
    if (result.errors) {
      Object.assign(profileErrors, result.errors);
    }
  }
};

const handleChangePassword = async () => {
  // Reset state
  passwordMessage.value = '';
  passwordSuccess.value = false;
  Object.keys(passwordErrors).forEach((key) => delete passwordErrors[key]);

  // Basic validation
  if (passwordForm.new_password !== passwordForm.new_password_confirmation) {
    passwordErrors.new_password_confirmation = 'Passwords do not match';
    return;
  }

  passwordLoading.value = true;
  const result = await authStore.changePassword({
    current_password: passwordForm.current_password,
    new_password: passwordForm.new_password,
    new_password_confirmation: passwordForm.new_password_confirmation,
  });
  passwordLoading.value = false;

  if (result.success) {
    passwordSuccess.value = true;
    passwordMessage.value = result.message || 'Password changed successfully';
    // Clear form
    passwordForm.current_password = '';
    passwordForm.new_password = '';
    passwordForm.new_password_confirmation = '';
  } else {
    passwordSuccess.value = false;
    passwordMessage.value = result.message || 'Failed to change password';
    if (result.errors) {
      Object.assign(passwordErrors, result.errors);
    }
  }
};

// Initialize form with user data
watch(
  () => authStore.user,
  (newUser) => {
    if (newUser) {
      profileForm.name = newUser.name || '';
      profileForm.bio = newUser.bio || '';
      profileForm.website = newUser.website || '';
    }
  },
  { immediate: true }
);

onMounted(() => {
  if (authStore.user) {
    profileForm.name = authStore.user.name || '';
    profileForm.bio = authStore.user.bio || '';
    profileForm.website = authStore.user.website || '';
  }
});
</script>

<style scoped>
.profile-page {
  max-width: 800px;
}

.page-header {
  margin-bottom: 24px;
}

.page-header h1 {
  font-size: 24px;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.page-header p {
  color: #6b7280;
  margin: 0;
}

/* Profile Navigation */
.profile-nav {
  display: flex;
  gap: 4px;
  margin-bottom: 24px;
  padding: 4px;
  background: #f3f4f6;
  border-radius: 10px;
  width: fit-content;
}

.nav-tab {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: transparent;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s ease;
}

.nav-tab:hover {
  color: #1f2937;
}

.nav-tab.active {
  background: #fff;
  color: #1f2937;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

/* Content */
.profile-content {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.tab-content {
  padding: 24px;
}

.content-section {
  padding-bottom: 32px;
  margin-bottom: 32px;
  border-bottom: 1px solid #e5e7eb;
}

.content-section:last-child {
  padding-bottom: 0;
  margin-bottom: 0;
  border-bottom: none;
}

.section-header {
  margin-bottom: 20px;
}

.section-header h2 {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.section-header p {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

/* Avatar Section */
.avatar-section {
  display: flex;
  align-items: flex-start;
  gap: 24px;
}

.avatar-preview {
  position: relative;
  width: 100px;
  height: 100px;
  border-radius: 50%;
  overflow: hidden;
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
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 32px;
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
  cursor: pointer;
  transition: opacity 0.2s ease;
}

.avatar-overlay svg {
  color: #fff;
}

.avatar-preview:hover .avatar-overlay {
  opacity: 1;
}

.avatar-actions {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.avatar-hint {
  font-size: 12px;
  color: #9ca3af;
  margin: 0;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  transition: all 0.2s ease;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
}

.btn-primary:hover:not(:disabled) {
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
  background: #f3f4f6;
  color: #374151;
}

.btn-secondary:hover:not(:disabled) {
  background: #e5e7eb;
}

.btn-ghost {
  background: transparent;
  color: #6b7280;
}

.btn-ghost:hover:not(:disabled) {
  background: #f3f4f6;
  color: #374151;
}

/* Forms */
.profile-form,
.password-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}

.form-group input,
.form-group textarea {
  padding: 10px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  transition: all 0.2s ease;
}

.form-group input:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-group input:disabled,
.form-group textarea:disabled {
  background: #f9fafb;
  color: #9ca3af;
}

.disabled-input {
  background: #f9fafb !important;
  color: #9ca3af !important;
  cursor: not-allowed;
}

.textarea-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.char-count {
  font-size: 12px;
  color: #9ca3af;
}

.hint-text {
  font-size: 12px;
  color: #9ca3af;
}

.error-text {
  font-size: 12px;
  color: #dc2626;
}

.form-actions {
  margin-top: 8px;
}

/* Password Input */
.password-input {
  position: relative;
  display: flex;
  align-items: center;
}

.password-input input {
  flex: 1;
  padding-right: 44px;
}

.password-toggle {
  position: absolute;
  right: 12px;
  background: none;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.password-toggle:hover {
  color: #6b7280;
}

/* Password Strength */
.password-strength {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 8px;
}

.strength-bar {
  flex: 1;
  height: 4px;
  background: #e5e7eb;
  border-radius: 2px;
  overflow: hidden;
}

.strength-fill {
  height: 100%;
  border-radius: 2px;
  transition: all 0.3s ease;
}

.strength-fill.weak {
  background: #dc2626;
}

.strength-fill.fair {
  background: #f59e0b;
}

.strength-fill.good {
  background: #10b981;
}

.strength-fill.strong {
  background: #059669;
}

.strength-text {
  font-size: 12px;
  font-weight: 500;
}

.strength-text.weak {
  color: #dc2626;
}

.strength-text.fair {
  color: #f59e0b;
}

.strength-text.good {
  color: #10b981;
}

.strength-text.strong {
  color: #059669;
}

/* Alerts */
.alert {
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 14px;
}

.alert-success {
  background: #ecfdf5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}

.alert-error {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

/* Account Info */
.account-info {
  border-bottom: none;
  padding-bottom: 0;
  margin-bottom: 0;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.info-label {
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.info-value {
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
}

.role-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  width: fit-content;
}

.role-admin {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
}

.role-moderator {
  background: #dbeafe;
  color: #1e40af;
}

.role-user {
  background: #f3f4f6;
  color: #374151;
}

.verified {
  color: #059669;
}

.not-verified {
  color: #dc2626;
}

.hidden {
  display: none;
}

/* Responsive */
@media (max-width: 768px) {
  .profile-nav {
    width: 100%;
  }

  .nav-tab {
    flex: 1;
    justify-content: center;
  }

  .avatar-section {
    flex-direction: column;
    align-items: center;
    text-align: center;
  }

  .avatar-actions {
    align-items: center;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .info-grid {
    grid-template-columns: 1fr;
  }
}
</style>
