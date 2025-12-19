<template>
  <div class="admin-layout">
    <Sidebar
      :collapsed="sidebarCollapsed"
      :mobile-open="mobileSidebarOpen"
      @toggle="toggleSidebar"
      @close="closeMobileSidebar"
    />

    <div class="admin-main" :class="{ 'sidebar-collapsed': sidebarCollapsed }">
      <Header
        :sidebar-collapsed="sidebarCollapsed"
        @toggle-sidebar="toggleMobileSidebar"
      />

      <main class="admin-content">
        <Breadcrumb />
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import Sidebar from './Sidebar.vue';
import Header from './Header.vue';
import Breadcrumb from './Breadcrumb.vue';

const route = useRoute();

const sidebarCollapsed = ref(false);
const mobileSidebarOpen = ref(false);
const isMobile = ref(false);

const checkMobile = () => {
  isMobile.value = window.innerWidth <= 1024;
  if (!isMobile.value) {
    mobileSidebarOpen.value = false;
  }
};

const toggleSidebar = () => {
  sidebarCollapsed.value = !sidebarCollapsed.value;
  localStorage.setItem('admin-sidebar-collapsed', sidebarCollapsed.value);
};

const toggleMobileSidebar = () => {
  if (isMobile.value) {
    mobileSidebarOpen.value = !mobileSidebarOpen.value;
  } else {
    toggleSidebar();
  }
};

const closeMobileSidebar = () => {
  mobileSidebarOpen.value = false;
};

// Close mobile sidebar on route change
watch(() => route.path, () => {
  if (isMobile.value) {
    mobileSidebarOpen.value = false;
  }
});

onMounted(() => {
  checkMobile();
  window.addEventListener('resize', checkMobile);

  const saved = localStorage.getItem('admin-sidebar-collapsed');
  if (saved !== null && !isMobile.value) {
    sidebarCollapsed.value = saved === 'true';
  }
});

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile);
});
</script>

<style scoped>
.admin-layout {
  display: flex;
  min-height: 100vh;
  background-color: #f8fafc;
}

.admin-main {
  flex: 1;
  margin-left: 260px;
  transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.admin-main.sidebar-collapsed {
  margin-left: 72px;
}

.admin-content {
  flex: 1;
  padding: 28px 32px;
  margin-top: 72px;
  max-width: 100%;
}

@media (max-width: 1024px) {
  .admin-main {
    margin-left: 0;
  }

  .admin-main.sidebar-collapsed {
    margin-left: 0;
  }

  .admin-content {
    padding: 20px;
    margin-top: 64px;
  }
}

@media (max-width: 768px) {
  .admin-content {
    padding: 16px;
    margin-top: 56px;
  }
}

@media (max-width: 480px) {
  .admin-content {
    padding: 12px;
  }
}
</style>
