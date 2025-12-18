<template>
  <nav v-if="breadcrumbs.length > 0" class="breadcrumb" aria-label="Breadcrumb">
    <ol class="breadcrumb-list">
      <li v-for="(item, index) in breadcrumbs" :key="index" class="breadcrumb-item">
        <router-link
          v-if="item.path && index < breadcrumbs.length - 1"
          :to="item.path"
          class="breadcrumb-link"
        >
          {{ item.label }}
        </router-link>
        <span v-else class="breadcrumb-current">{{ item.label }}</span>
        <svg
          v-if="index < breadcrumbs.length - 1"
          class="breadcrumb-separator"
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <polyline points="9 18 15 12 9 6" />
        </svg>
      </li>
    </ol>
  </nav>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

const breadcrumbs = computed(() => {
  const items = [{ label: 'Dashboard', path: '/admin' }];

  const pathSegments = route.path.split('/').filter(Boolean);

  // Skip 'admin' segment
  if (pathSegments[0] === 'admin') {
    pathSegments.shift();
  }

  let currentPath = '/admin';

  pathSegments.forEach((segment, index) => {
    currentPath += `/${segment}`;

    // Check if this is an ID or parameter (numeric or UUID)
    const isParam = /^\d+$/.test(segment) || /^[a-f0-9-]{36}$/i.test(segment);

    if (isParam) {
      // Don't add IDs to breadcrumbs, but modify the previous item
      return;
    }

    const label = formatLabel(segment);
    const isLast = index === pathSegments.length - 1;

    items.push({
      label,
      path: isLast ? null : currentPath,
    });
  });

  // Handle special cases for edit/create pages
  if (route.name?.includes('-edit')) {
    items.push({ label: 'Edit', path: null });
  } else if (route.name?.includes('-create')) {
    items.push({ label: 'Create', path: null });
  }

  return items;
});

const formatLabel = (segment) => {
  // Convert kebab-case or snake_case to Title Case
  return segment
    .replace(/[-_]/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase());
};
</script>

<style scoped>
.breadcrumb {
  margin-bottom: 24px;
}

.breadcrumb-list {
  display: flex;
  align-items: center;
  gap: 8px;
  list-style: none;
  margin: 0;
  padding: 0;
  font-size: 14px;
}

.breadcrumb-item {
  display: flex;
  align-items: center;
  gap: 8px;
}

.breadcrumb-link {
  color: #6b7280;
  text-decoration: none;
  transition: color 0.2s ease;
}

.breadcrumb-link:hover {
  color: #4f46e5;
}

.breadcrumb-current {
  color: #1f2937;
  font-weight: 500;
}

.breadcrumb-separator {
  color: #d1d5db;
}
</style>
