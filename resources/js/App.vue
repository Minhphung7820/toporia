<template>
  <div id="app">
    <nav class="navbar">
      <div class="container">
        <div class="nav-brand">
          <router-link to="/" class="nav-logo">Toporia</router-link>
        </div>

        <!-- Hamburger Menu Button (Mobile) -->
        <button
          class="hamburger"
          :class="{ active: isMenuOpen }"
          @click="toggleMenu"
          aria-label="Toggle menu"
        >
          <span></span>
          <span></span>
          <span></span>
        </button>

        <!-- Overlay (Mobile) -->
        <div
          v-if="isMenuOpen"
          class="menu-overlay"
          @click="closeMenu"
        ></div>

        <!-- Navigation Menu -->
        <ul class="nav-menu" :class="{ active: isMenuOpen }">
          <li>
            <router-link to="/" class="nav-link" @click="closeMenu">Home</router-link>
          </li>
          <li>
            <router-link to="/about" class="nav-link" @click="closeMenu">About</router-link>
          </li>
          <li v-if="user">
            <router-link to="/change-password" class="nav-link" @click="closeMenu">Change Password</router-link>
          </li>
          <li v-if="!user">
            <router-link to="/login" class="nav-link" @click="closeMenu">Login</router-link>
          </li>
          <li v-if="!user">
            <router-link to="/register" class="nav-link" @click="closeMenu">Register</router-link>
          </li>
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
        <p>&copy; {{ currentYear }} Toporia Framework. All rights reserved.</p>
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
    };
  },
  computed: {
    currentYear() {
      return new Date().getFullYear();
    },
    user() {
      return this.authStore.user;
    },
  },
  setup() {
    const authStore = useAuthStore();

    // Initialize auth on app load
    authStore.initialize();

    return {
      authStore,
    };
  },
  watch: {
    isMenuOpen(newVal) {
      // Prevent body scroll when menu is open
      if (newVal) {
        document.body.classList.add('menu-open');
      } else {
        document.body.classList.remove('menu-open');
      }
    },
  },
  methods: {
    toggleMenu() {
      this.isMenuOpen = !this.isMenuOpen;
    },
    closeMenu() {
      this.isMenuOpen = false;
    },
    async handleLogout() {
      try {
        await this.authStore.logout();
        this.closeMenu();
        this.$router.push('/login');
      } catch (error) {
        console.error('Logout error:', error);
      }
    },
  },
};
</script>

<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  line-height: 1.6;
  color: #333;
  background-color: #f5f5f5;
}

#app {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
}

/* Navbar */
.navbar {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 1rem 0;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.navbar .container {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.nav-logo {
  font-size: 1.5rem;
  font-weight: bold;
  color: white;
  text-decoration: none;
  transition: opacity 0.3s;
}

.nav-logo:hover {
  opacity: 0.8;
}

/* Hamburger Menu Button */
.hamburger {
  display: none;
  flex-direction: column;
  justify-content: space-around;
  width: 2rem;
  height: 2rem;
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 0;
  z-index: 1001;
  transition: all 0.3s ease;
}

.hamburger span {
  width: 2rem;
  height: 3px;
  background: white;
  border-radius: 10px;
  transition: all 0.3s ease;
  transform-origin: center;
}

.hamburger.active span:nth-child(1) {
  transform: rotate(45deg) translate(8px, 8px);
}

.hamburger.active span:nth-child(2) {
  opacity: 0;
  transform: translateX(-20px);
}

.hamburger.active span:nth-child(3) {
  transform: rotate(-45deg) translate(8px, -8px);
}

.hamburger:hover span {
  background: rgba(255, 255, 255, 0.9);
}

.nav-menu {
  display: flex;
  list-style: none;
  gap: 2rem;
  transition: all 0.3s ease;
}

.nav-link {
  color: white;
  text-decoration: none;
  font-weight: 500;
  transition: opacity 0.3s;
  padding: 0.5rem 1rem;
  border-radius: 4px;
}

.nav-link:hover {
  opacity: 0.8;
  background-color: rgba(255, 255, 255, 0.1);
}

.nav-link.router-link-active {
  background-color: rgba(255, 255, 255, 0.2);
  font-weight: 600;
}

.user-menu {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.user-name {
  color: white;
  font-weight: 500;
}

.btn-logout {
  background-color: rgba(255, 255, 255, 0.2);
  color: white;
  border: 1px solid rgba(255, 255, 255, 0.3);
  padding: 0.5rem 1rem;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s;
}

.btn-logout:hover {
  background-color: rgba(255, 255, 255, 0.3);
}

/* Main Content */
.main-content {
  flex: 1;
  padding: 2rem 0;
}

/* Footer */
.footer {
  background-color: #2c3e50;
  color: white;
  text-align: center;
  padding: 1.5rem 0;
  margin-top: auto;
}

/* Page Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Responsive Design */
@media (max-width: 768px) {
  .hamburger {
    display: flex;
  }

  .nav-menu {
    position: fixed;
    top: 0;
    right: -100%;
    width: 280px;
    height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    flex-direction: column;
    padding: 5rem 2rem 2rem;
    gap: 0;
    box-shadow: -5px 0 15px rgba(0, 0, 0, 0.2);
    transition: right 0.3s ease-in-out;
    z-index: 1000;
    overflow-y: auto;
  }

  .nav-menu.active {
    right: 0;
  }

  .nav-menu li {
    width: 100%;
    margin-bottom: 0.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding-bottom: 0.5rem;
  }

  .nav-menu li:last-child {
    border-bottom: none;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
  }

  .nav-link {
    display: block;
    width: 100%;
    padding: 1rem;
    border-radius: 8px;
    font-size: 1.1rem;
    transition: all 0.3s ease;
  }

  .nav-link:hover {
    background-color: rgba(255, 255, 255, 0.15);
    transform: translateX(5px);
  }

  .nav-link.router-link-active {
    background-color: rgba(255, 255, 255, 0.25);
    font-weight: 600;
  }

  .user-menu {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
    width: 100%;
  }

  .user-name {
    font-size: 1.1rem;
    padding: 0.5rem 0;
  }

  .btn-logout {
    width: 100%;
    padding: 0.75rem 1rem;
    font-size: 1rem;
  }

  /* Overlay when menu is open */
  .menu-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
    animation: fadeIn 0.3s ease;
    backdrop-filter: blur(2px);
  }
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

/* Tablet adjustments */
@media (max-width: 1024px) and (min-width: 769px) {
  .nav-menu {
    gap: 1.5rem;
  }

  .nav-link {
    padding: 0.5rem 0.75rem;
    font-size: 0.95rem;
  }
}

/* Prevent body scroll when menu is open on mobile */
@media (max-width: 768px) {
  body.menu-open {
    overflow: hidden;
  }
}
</style>