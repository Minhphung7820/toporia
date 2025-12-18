<template>
  <div class="admin-layout">
    <Sidebar
      :collapsed="sidebarCollapsed"
      @toggle="toggleSidebar"
    />

    <div class="admin-main" :class="{ 'sidebar-collapsed': sidebarCollapsed }">
      <Header
        :sidebar-collapsed="sidebarCollapsed"
        @toggle-sidebar="toggleSidebar"
      />

      <main class="admin-content">
        <Breadcrumb />
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import Sidebar from './Sidebar.vue';
import Header from './Header.vue';
import Breadcrumb from './Breadcrumb.vue';

const sidebarCollapsed = ref(false);

const toggleSidebar = () => {
  sidebarCollapsed.value = !sidebarCollapsed.value;
  localStorage.setItem('admin-sidebar-collapsed', sidebarCollapsed.value);
};

onMounted(() => {
  const saved = localStorage.getItem('admin-sidebar-collapsed');
  if (saved !== null) {
    sidebarCollapsed.value = saved === 'true';
  }
});
</script>

<style scoped>
.admin-layout {
  display: flex;
  min-height: 100vh;
  background-color: #f5f7fa;
}

.admin-main {
  flex: 1;
  margin-left: 260px;
  transition: margin-left 0.3s ease;
  display: flex;
  flex-direction: column;
}

.admin-main.sidebar-collapsed {
  margin-left: 64px;
}

.admin-content {
  flex: 1;
  padding: 24px;
  margin-top: 64px;
}

@media (max-width: 768px) {
  .admin-main {
    margin-left: 0;
  }

  .admin-main.sidebar-collapsed {
    margin-left: 0;
  }

  .admin-content {
    padding: 16px;
  }
}
</style>
