<template>
  <div id="app">
    <nav class="navbar">
      <div class="container">
        <div class="nav-brand">
          <router-link to="/" class="nav-logo">
            <svg viewBox="0 0 32 32" fill="none" class="logo-icon">
              <rect width="32" height="32" rx="8" fill="#1a1a1a"/>
              <path d="M8 12h16M8 16h12M8 20h8" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
            </svg>
            Toporia
          </router-link>
        </div>

        <button class="hamburger" :class="{ active: isMenuOpen }" @click="toggleMenu" aria-label="Toggle menu">
          <span></span>
          <span></span>
          <span></span>
        </button>

        <div v-if="isMenuOpen" class="menu-overlay" @click="closeMenu"></div>

        <ul class="nav-menu" :class="{ active: isMenuOpen }">
          <li><router-link to="/" class="nav-link" @click="closeMenu">Home</router-link></li>
          <li><router-link to="/blog" class="nav-link" @click="closeMenu">Blog</router-link></li>
          <li><router-link to="/about" class="nav-link" @click="closeMenu">About</router-link></li>

          <!-- Guest actions -->
          <li v-if="!user"><router-link to="/login" class="nav-link" @click="closeMenu">Login</router-link></li>
          <li v-if="!user"><router-link to="/register" class="nav-link nav-link-primary" @click="closeMenu">Get Started</router-link></li>

          <!-- User dropdown -->
          <li v-if="user" class="user-dropdown" ref="userDropdown">
            <button class="user-trigger" @click="toggleUserMenu">
              <div class="user-avatar" :style="avatarStyle">
                <img v-if="user.avatar" :src="user.avatar" :alt="user.name" />
                <span v-else class="avatar-initials">{{ userInitials }}</span>
              </div>
              <span class="user-name">{{ user.name }}</span>
              <svg class="dropdown-arrow" :class="{ open: isUserMenuOpen }" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
            </button>

            <transition name="dropdown">
              <div v-if="isUserMenuOpen" class="dropdown-menu">
                <div class="dropdown-header">
                  <div class="dropdown-avatar" :style="avatarStyle">
                    <img v-if="user.avatar" :src="user.avatar" :alt="user.name" />
                    <span v-else class="avatar-initials">{{ userInitials }}</span>
                  </div>
                  <div class="dropdown-user-info">
                    <span class="dropdown-user-name">{{ user.name }}</span>
                    <span class="dropdown-user-email">{{ user.email }}</span>
                  </div>
                </div>

                <div class="dropdown-divider"></div>

                <router-link v-if="hasAdminAccess" to="/admin" class="dropdown-item" @click="closeUserMenu">
                  <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm0 6a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1v-2zm0 6a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1v-2z" clip-rule="evenodd" />
                  </svg>
                  Dashboard
                </router-link>

                <router-link to="/settings" class="dropdown-item" @click="closeUserMenu">
                  <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                  </svg>
                  Settings
                </router-link>

                <div class="dropdown-divider"></div>

                <button class="dropdown-item dropdown-item-danger" @click="handleLogout">
                  <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd" />
                  </svg>
                  Sign out
                </button>
              </div>
            </transition>
          </li>
        </ul>
      </div>
    </nav>

    <main class="main-content">
      <router-view />
    </main>

    <footer class="footer">
      <div class="container">
        <p>&copy; {{ currentYear }} Toporia Framework</p>
      </div>
    </footer>
  </div>
</template>

<script>
import { useAuthStore } from './stores/auth';

export default {
  name: 'App',
  data() {
    return {
      isMenuOpen: false,
      isUserMenuOpen: false,
    };
  },
  computed: {
    currentYear() { return new Date().getFullYear(); },
    user() { return this.authStore.user; },
    hasAdminAccess() { return this.authStore.hasAdminAccess; },
    userInitials() {
      if (!this.user?.name) return '?';
      return this.user.name
        .split(' ')
        .map(word => word[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
    },
    avatarStyle() {
      if (this.user?.avatar) return {};
      // Generate consistent color from name
      const colors = [
        '#3b82f6', '#8b5cf6', '#ec4899', '#ef4444', '#f97316',
        '#eab308', '#22c55e', '#14b8a6', '#06b6d4', '#6366f1',
      ];
      const name = this.user?.name || '';
      const hash = name.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
      return { backgroundColor: colors[hash % colors.length] };
    },
  },
  setup() {
    const authStore = useAuthStore();
    authStore.initialize();
    return { authStore };
  },
  watch: {
    isMenuOpen(val) {
      document.body.classList[val ? 'add' : 'remove']('menu-open');
    },
  },
  mounted() {
    document.addEventListener('click', this.handleOutsideClick);
  },
  beforeUnmount() {
    document.removeEventListener('click', this.handleOutsideClick);
  },
  methods: {
    toggleMenu() { this.isMenuOpen = !this.isMenuOpen; },
    closeMenu() { this.isMenuOpen = false; },
    toggleUserMenu() { this.isUserMenuOpen = !this.isUserMenuOpen; },
    closeUserMenu() { this.isUserMenuOpen = false; },
    handleOutsideClick(e) {
      const dropdown = this.$refs.userDropdown;
      if (dropdown && !dropdown.contains(e.target)) {
        this.isUserMenuOpen = false;
      }
    },
    async handleLogout() {
      await this.authStore.logout();
      this.closeMenu();
      this.closeUserMenu();
      this.$router.push('/login');
    },
  },
};
</script>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  line-height: 1.6;
  color: #1a1a1a;
  background-color: #fafafa;
}

#app {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.container {
  width: 100%;
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 2rem;
}

.navbar {
  background: #fff;
  border-bottom: 1px solid #e5e5e5;
  padding: 0.75rem 0;
  position: sticky;
  top: 0;
  z-index: 100;
}

.navbar .container {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.nav-logo {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 1.25rem;
  font-weight: 600;
  color: #1a1a1a;
  text-decoration: none;
}

.nav-logo:hover { opacity: 0.8; }
.logo-icon { width: 32px; height: 32px; }

.hamburger {
  display: none;
  flex-direction: column;
  justify-content: space-around;
  width: 1.75rem;
  height: 1.75rem;
  background: transparent;
  border: none;
  cursor: pointer;
  z-index: 1001;
}

.hamburger span {
  width: 1.75rem;
  height: 2px;
  background: #1a1a1a;
  border-radius: 2px;
  transition: all 0.3s;
}

.hamburger.active span:nth-child(1) { transform: rotate(45deg) translate(6px, 6px); }
.hamburger.active span:nth-child(2) { opacity: 0; }
.hamburger.active span:nth-child(3) { transform: rotate(-45deg) translate(6px, -6px); }

.nav-menu {
  display: flex;
  list-style: none;
  gap: 0.25rem;
  align-items: center;
}

.nav-link {
  color: #666;
  text-decoration: none;
  font-weight: 500;
  font-size: 0.9rem;
  padding: 0.5rem 0.875rem;
  border-radius: 6px;
  transition: all 0.2s;
}

.nav-link:hover { color: #1a1a1a; background: #f5f5f5; }
.nav-link.router-link-active { color: #1a1a1a; background: #f0f0f0; }

.nav-link-primary {
  background: #1a1a1a !important;
  color: #fff !important;
}
.nav-link-primary:hover { background: #333 !important; }

/* User Dropdown */
.user-dropdown {
  position: relative;
}

.user-trigger {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0.625rem 0.375rem 0.375rem;
  background: transparent;
  border: 1px solid transparent;
  border-radius: 9999px;
  cursor: pointer;
  transition: all 0.2s;
}

.user-trigger:hover {
  background: #f5f5f5;
  border-color: #e5e5e5;
}

.user-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.user-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-initials {
  font-size: 0.75rem;
  font-weight: 600;
  color: #fff;
}

.user-name {
  color: #1a1a1a;
  font-weight: 500;
  font-size: 0.875rem;
  max-width: 120px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dropdown-arrow {
  width: 16px;
  height: 16px;
  color: #666;
  transition: transform 0.2s;
}

.dropdown-arrow.open {
  transform: rotate(180deg);
}

.dropdown-menu {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 280px;
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 12px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
  overflow: hidden;
  z-index: 1000;
}

.dropdown-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem;
  background: #fafafa;
}

.dropdown-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.dropdown-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.dropdown-avatar .avatar-initials {
  font-size: 1rem;
}

.dropdown-user-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.dropdown-user-name {
  font-weight: 600;
  color: #1a1a1a;
  font-size: 0.9rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dropdown-user-email {
  color: #666;
  font-size: 0.8rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dropdown-divider {
  height: 1px;
  background: #e5e5e5;
  margin: 0;
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  color: #333;
  font-size: 0.875rem;
  font-weight: 500;
  text-decoration: none;
  background: none;
  border: none;
  width: 100%;
  cursor: pointer;
  transition: background 0.15s;
}

.dropdown-item:hover {
  background: #f5f5f5;
}

.dropdown-item svg {
  width: 18px;
  height: 18px;
  color: #666;
  flex-shrink: 0;
}

.dropdown-item-danger {
  color: #dc2626;
}

.dropdown-item-danger svg {
  color: #dc2626;
}

.dropdown-item-danger:hover {
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

.main-content { flex: 1; }

.footer {
  background: #fff;
  border-top: 1px solid #e5e5e5;
  color: #999;
  text-align: center;
  padding: 1.25rem 0;
  font-size: 0.875rem;
}

@media (max-width: 768px) {
  .hamburger { display: flex; }

  .nav-menu {
    position: fixed;
    top: 0;
    right: -100%;
    width: 280px;
    height: 100vh;
    background: #fff;
    flex-direction: column;
    padding: 5rem 1.5rem 2rem;
    gap: 0.25rem;
    box-shadow: -5px 0 15px rgba(0,0,0,0.1);
    transition: right 0.3s;
    z-index: 1000;
    align-items: stretch;
    overflow-y: auto;
  }

  .nav-menu.active { right: 0; }
  .nav-menu li { width: 100%; }

  .nav-link {
    display: block;
    padding: 0.875rem 1rem;
    font-size: 1rem;
    border-radius: 8px;
  }

  .nav-link-primary { text-align: center; margin-top: 0.5rem; }

  /* Mobile user dropdown */
  .user-dropdown {
    border-top: 1px solid #e5e5e5;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
  }

  .user-trigger {
    width: 100%;
    padding: 0.75rem;
    border-radius: 8px;
    justify-content: flex-start;
  }

  .user-trigger:hover {
    background: #f5f5f5;
  }

  .user-name {
    flex: 1;
    max-width: none;
    text-align: left;
  }

  .dropdown-menu {
    position: static;
    width: 100%;
    margin-top: 0.5rem;
    box-shadow: none;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
  }

  .dropdown-header {
    padding: 0.875rem;
  }

  .dropdown-avatar {
    width: 40px;
    height: 40px;
  }

  .menu-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.3);
    z-index: 999;
  }

  body.menu-open { overflow: hidden; }
}
</style>
