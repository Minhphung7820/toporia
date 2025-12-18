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
          <li v-if="user"><router-link to="/change-password" class="nav-link" @click="closeMenu">Settings</router-link></li>
          <li v-if="!user"><router-link to="/login" class="nav-link" @click="closeMenu">Login</router-link></li>
          <li v-if="!user"><router-link to="/register" class="nav-link nav-link-primary" @click="closeMenu">Get Started</router-link></li>
          <li v-if="user" class="user-menu">
            <span class="user-name">{{ user.name }}</span>
            <button @click="handleLogout" class="btn-logout">Logout</button>
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
    return { isMenuOpen: false };
  },
  computed: {
    currentYear() { return new Date().getFullYear(); },
    user() { return this.authStore.user; },
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
  methods: {
    toggleMenu() { this.isMenuOpen = !this.isMenuOpen; },
    closeMenu() { this.isMenuOpen = false; },
    async handleLogout() {
      await this.authStore.logout();
      this.closeMenu();
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

.user-menu { display: flex; align-items: center; gap: 0.75rem; }
.user-name { color: #1a1a1a; font-weight: 500; font-size: 0.9rem; }

.btn-logout {
  background: transparent;
  color: #666;
  border: 1px solid #ddd;
  padding: 0.4rem 0.75rem;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.85rem;
  font-weight: 500;
}
.btn-logout:hover { background: #f5f5f5; color: #1a1a1a; }

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

  .user-menu {
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
    padding-top: 1rem;
    margin-top: 0.5rem;
    border-top: 1px solid #e5e5e5;
  }

  .btn-logout { width: 100%; padding: 0.75rem; }

  .menu-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.3);
    z-index: 999;
  }

  body.menu-open { overflow: hidden; }
}
</style>
