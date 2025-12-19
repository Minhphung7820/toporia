<template>
  <header class="admin-header" :class="{ 'sidebar-collapsed': sidebarCollapsed }">
    <div class="header-left">
      <button class="menu-toggle" @click="$emit('toggle-sidebar')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="3" y1="12" x2="21" y2="12" />
          <line x1="3" y1="6" x2="21" y2="6" />
          <line x1="3" y1="18" x2="21" y2="18" />
        </svg>
      </button>

      <div class="search-box">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        <input
          type="text"
          v-model="searchQuery"
          placeholder="Search..."
          @keyup.enter="handleSearch"
        />
      </div>
    </div>

    <div class="header-right">
      <router-link to="/admin/comments/pending" class="header-btn" title="Notifications">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
          <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9" />
          <path d="M13.73 21a2 2 0 01-3.46 0" />
        </svg>
        <span v-if="notificationCount > 0" class="badge">{{ notificationCount > 99 ? '99+' : notificationCount }}</span>
      </router-link>

      <a href="/" target="_blank" class="header-btn" title="View Site">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
          <path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6" />
          <polyline points="15 3 21 3 21 9" />
          <line x1="10" y1="14" x2="21" y2="3" />
        </svg>
      </a>

      <div class="user-menu" ref="userMenuRef">
        <button class="user-btn" @click="toggleUserMenu">
          <div class="user-avatar">
            <img v-if="user?.avatar" :src="user.avatar" :alt="user.name" />
            <span v-else>{{ userInitials }}</span>
          </div>
          <span class="user-name">{{ user?.name || 'Admin' }}</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 12 15 18 9" />
          </svg>
        </button>

        <Transition name="dropdown">
          <div v-if="showUserMenu" class="user-dropdown">
            <div class="dropdown-header">
              <strong>{{ user?.name }}</strong>
              <span>{{ user?.email }}</span>
            </div>
            <div class="dropdown-divider"></div>
            <router-link to="/admin/profile" class="dropdown-item" @click="showUserMenu = false">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" />
                <circle cx="12" cy="7" r="4" />
              </svg>
              Profile
            </router-link>
            <router-link to="/admin/settings" class="dropdown-item" @click="showUserMenu = false">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                <circle cx="12" cy="12" r="3" />
                <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z" />
              </svg>
              Settings
            </router-link>
            <div class="dropdown-divider"></div>
            <button class="dropdown-item logout" @click="handleLogout">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" />
                <polyline points="16 17 21 12 16 7" />
                <line x1="21" y1="12" x2="9" y2="12" />
              </svg>
              Logout
            </button>
          </div>
        </Transition>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '../../../stores/auth';
import { useCommentsStore } from '../../stores/comments';
import { useFeedbackStore } from '../../stores/feedback';

defineProps({
  sidebarCollapsed: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['toggle-sidebar']);

const authStore = useAuthStore();
const commentsStore = useCommentsStore();
const feedbackStore = useFeedbackStore();

const searchQuery = ref('');
const showUserMenu = ref(false);
const userMenuRef = ref(null);

const user = computed(() => authStore.user);
const userInitials = computed(() => {
  if (!user.value?.name) return 'A';
  return user.value.name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
});

const notificationCount = computed(() => {
  return (commentsStore.pendingCount || 0) + (feedbackStore.pendingCount || 0);
});

const toggleUserMenu = () => {
  showUserMenu.value = !showUserMenu.value;
};

const handleSearch = () => {
  if (searchQuery.value.trim()) {
    console.log('Search:', searchQuery.value);
  }
};

const handleLogout = async () => {
  await authStore.logout();
  window.location.href = '/login';
};

const handleClickOutside = (event) => {
  if (userMenuRef.value && !userMenuRef.value.contains(event.target)) {
    showUserMenu.value = false;
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<style scoped>
.admin-header {
  height: 72px;
  background: #fff;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 28px;
  position: fixed;
  top: 0;
  right: 0;
  left: 260px;
  z-index: 100;
  transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.admin-header.sidebar-collapsed {
  left: 72px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.menu-toggle {
  display: none;
  padding: 10px;
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
  border-radius: 10px;
  transition: all 0.2s ease;
}

.menu-toggle:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.search-box {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  min-width: 280px;
  transition: all 0.2s ease;
}

.search-box:focus-within {
  border-color: #2563eb;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.search-box svg {
  color: #94a3b8;
  flex-shrink: 0;
}

.search-box input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font-size: 14px;
  color: #0f172a;
}

.search-box input::placeholder {
  color: #94a3b8;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 6px;
}

.header-btn {
  position: relative;
  padding: 10px;
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
  border-radius: 10px;
  text-decoration: none;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.header-btn:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.header-btn .badge {
  position: absolute;
  top: 4px;
  right: 4px;
  background: #dc2626;
  color: #fff;
  font-size: 10px;
  font-weight: 600;
  padding: 2px 5px;
  border-radius: 10px;
  min-width: 18px;
  text-align: center;
  line-height: 1.2;
}

.user-menu {
  position: relative;
  margin-left: 8px;
}

.user-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 12px 6px 6px;
  background: transparent;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.user-btn:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
}

.user-avatar {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 12px;
  font-weight: 600;
  overflow: hidden;
}

.user-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.user-name {
  font-size: 14px;
  font-weight: 500;
  color: #0f172a;
}

.user-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
  min-width: 220px;
  overflow: hidden;
  z-index: 1000;
}

.dropdown-header {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  background: #f8fafc;
}

.dropdown-header strong {
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
}

.dropdown-header span {
  font-size: 12px;
  color: #64748b;
}

.dropdown-divider {
  height: 1px;
  background: #e2e8f0;
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  color: #334155;
  text-decoration: none;
  font-size: 14px;
  background: transparent;
  border: none;
  width: 100%;
  cursor: pointer;
  transition: all 0.2s ease;
}

.dropdown-item:hover {
  background: #f8fafc;
}

.dropdown-item.logout {
  color: #dc2626;
}

.dropdown-item.logout:hover {
  background: #fef2f2;
}

/* Dropdown animation */
.dropdown-enter-active,
.dropdown-leave-active {
  transition: all 0.2s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

@media (max-width: 1024px) {
  .admin-header {
    left: 0;
    padding: 0 20px;
  }

  .admin-header.sidebar-collapsed {
    left: 0;
  }

  .menu-toggle {
    display: flex;
  }

  .search-box {
    display: none;
  }
}

@media (max-width: 768px) {
  .admin-header {
    height: 56px;
    padding: 0 12px;
  }

  .header-left {
    gap: 8px;
  }

  .menu-toggle {
    padding: 8px;
  }

  .header-right {
    gap: 2px;
  }

  .header-btn {
    padding: 8px;
  }

  .user-name {
    display: none;
  }

  .user-btn {
    padding: 4px;
    border: none;
    gap: 0;
  }

  .user-btn svg {
    display: none;
  }

  .user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 8px;
  }

  .user-dropdown {
    right: -8px;
    min-width: 200px;
  }
}

@media (max-width: 480px) {
  .admin-header {
    padding: 0 8px;
  }

  .header-btn {
    padding: 6px;
  }

  .menu-toggle {
    padding: 6px;
  }

  .menu-toggle svg {
    width: 20px;
    height: 20px;
  }
}
</style>
