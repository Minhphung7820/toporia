<template>
  <aside class="sidebar" :class="{ collapsed, 'mobile-open': mobileOpen }">
    <div class="sidebar-header">
      <router-link to="/admin" class="logo">
        <span class="logo-icon">T</span>
        <span v-if="!collapsed" class="logo-text">Toporia</span>
      </router-link>
      <button v-if="isMobile" class="close-btn" @click="$emit('close')">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18"/>
          <line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section">
        <span v-if="!collapsed" class="nav-section-title">Menu</span>
      </div>
      <ul class="nav-list">
        <li v-for="item in menuItems" :key="item.path" class="nav-item">
          <router-link
            :to="item.path"
            class="nav-link"
            :class="{ active: isActive(item.path) }"
            @click="handleNavClick"
          >
            <span class="nav-icon" v-html="item.icon"></span>
            <span v-if="!collapsed" class="nav-label">{{ item.label }}</span>
            <span v-if="!collapsed && item.badge" class="nav-badge">{{ item.badge }}</span>
          </router-link>
        </li>
      </ul>
    </nav>

    <div class="sidebar-footer">
      <button v-if="!isMobile" class="toggle-btn" @click="$emit('toggle')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path v-if="collapsed" d="M9 18l6-6-6-6" />
          <path v-else d="M15 18l-6-6 6-6" />
        </svg>
        <span v-if="!collapsed" class="toggle-text">Collapse</span>
      </button>
    </div>
  </aside>
  <div v-if="mobileOpen" class="sidebar-overlay" @click="$emit('close')"></div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useCommentsStore } from '../../stores/comments';
import { useFeedbackStore } from '../../stores/feedback';
import { useAuthStore } from '../../../stores/auth';

const props = defineProps({
  collapsed: {
    type: Boolean,
    default: false,
  },
  mobileOpen: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['toggle', 'close']);

const route = useRoute();
const commentsStore = useCommentsStore();
const feedbackStore = useFeedbackStore();
const authStore = useAuthStore();

const isMobile = ref(false);

const checkMobile = () => {
  isMobile.value = window.innerWidth <= 1024;
};

onMounted(() => {
  checkMobile();
  window.addEventListener('resize', checkMobile);
});

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile);
});

const handleNavClick = () => {
  if (isMobile.value && props.mobileOpen) {
    // Close mobile menu after navigation
  }
};

// All menu items with role-based visibility
const allMenuItems = [
  {
    path: '/admin',
    label: 'Dashboard',
    icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>',
    roles: ['admin', 'moderator'], // Both admin and moderator can see
  },
  {
    path: '/admin/posts',
    label: 'Posts',
    icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
    roles: ['admin', 'moderator'],
  },
  {
    path: '/admin/comments',
    label: 'Comments',
    icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>',
    roles: ['admin', 'moderator'],
    getBadge: () => commentsStore.pendingCount > 0 ? commentsStore.pendingCount : null,
  },
  {
    path: '/admin/categories',
    label: 'Categories',
    icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>',
    roles: ['admin'], // Admin only
  },
  {
    path: '/admin/tags',
    label: 'Tags',
    icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>',
    roles: ['admin'], // Admin only
  },
  {
    path: '/admin/users',
    label: 'Users',
    icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>',
    roles: ['admin'], // Admin only
  },
  {
    path: '/admin/feedback',
    label: 'Feedback',
    icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>',
    roles: ['admin'], // Admin only
    getBadge: () => feedbackStore.pendingCount > 0 ? feedbackStore.pendingCount : null,
  },
  {
    path: '/admin/settings',
    label: 'Settings',
    icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>',
    roles: ['admin'], // Admin only
  },
];

// Filter menu items based on user role
const menuItems = computed(() => {
  const userRole = authStore.userRole;
  return allMenuItems
    .filter(item => item.roles.includes(userRole))
    .map(item => ({
      ...item,
      badge: item.getBadge ? item.getBadge() : null,
    }));
});

const isActive = (path) => {
  if (path === '/admin') {
    return route.path === '/admin';
  }
  return route.path.startsWith(path);
};
</script>

<style scoped>
.sidebar {
  width: 260px;
  height: 100vh;
  position: fixed;
  left: 0;
  top: 0;
  background: #0f172a;
  color: #fff;
  display: flex;
  flex-direction: column;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  z-index: 1000;
  border-right: 1px solid #1e293b;
}

.sidebar.collapsed {
  width: 72px;
}

.sidebar-header {
  padding: 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid #1e293b;
  min-height: 72px;
}

.logo {
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
  color: #fff;
}

.logo-icon {
  width: 36px;
  height: 36px;
  background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 18px;
  flex-shrink: 0;
}

.logo-text {
  font-size: 20px;
  font-weight: 600;
  letter-spacing: -0.025em;
}

.close-btn {
  display: none;
  padding: 8px;
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  border-radius: 8px;
}

.close-btn:hover {
  background: #1e293b;
  color: #fff;
}

.sidebar-nav {
  flex: 1;
  padding: 16px 12px;
  overflow-y: auto;
}

.nav-section {
  margin-bottom: 8px;
  padding: 0 8px;
}

.nav-section-title {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #475569;
}

.nav-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.nav-item {
  margin: 2px 0;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 14px;
  color: #94a3b8;
  text-decoration: none;
  border-radius: 10px;
  transition: all 0.2s ease;
  font-weight: 450;
}

.nav-link:hover {
  background: #1e293b;
  color: #e2e8f0;
}

.nav-link.active {
  background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
  color: #fff;
  font-weight: 500;
}

.nav-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 20px;
  height: 20px;
}

.nav-label {
  flex: 1;
  font-size: 14px;
  white-space: nowrap;
}

.nav-badge {
  background: #dc2626;
  color: #fff;
  font-size: 11px;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 10px;
  min-width: 20px;
  text-align: center;
}

.sidebar-footer {
  padding: 16px;
  border-top: 1px solid #1e293b;
}

.toggle-btn {
  width: 100%;
  padding: 10px 14px;
  background: #1e293b;
  border: none;
  border-radius: 10px;
  color: #94a3b8;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  transition: all 0.2s ease;
  font-size: 13px;
  font-weight: 500;
}

.toggle-btn:hover {
  background: #334155;
  color: #fff;
}

.toggle-text {
  font-size: 13px;
}

.sidebar.collapsed .nav-link {
  justify-content: center;
  padding: 11px;
}

.sidebar.collapsed .toggle-btn {
  padding: 10px;
}

.sidebar.collapsed .sidebar-header {
  justify-content: center;
  padding: 20px 12px;
}

.sidebar.collapsed .nav-section {
  display: none;
}

.sidebar-overlay {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 999;
}

/* Mobile styles */
@media (max-width: 1024px) {
  .sidebar {
    transform: translateX(-100%);
    width: 280px;
  }

  .sidebar.mobile-open {
    transform: translateX(0);
  }

  .sidebar.collapsed {
    width: 280px;
    transform: translateX(-100%);
  }

  .sidebar.collapsed.mobile-open {
    transform: translateX(0);
  }

  .sidebar-overlay {
    display: block;
  }

  .close-btn {
    display: flex;
  }

  .toggle-btn {
    display: none;
  }
}
</style>
