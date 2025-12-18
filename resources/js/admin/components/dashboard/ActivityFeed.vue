<template>
  <div class="activity-feed">
    <div v-if="loading" class="activity-loading">
      <div v-for="i in 5" :key="i" class="activity-skeleton">
        <div class="skeleton-icon"></div>
        <div class="skeleton-content">
          <div class="skeleton-line short"></div>
          <div class="skeleton-line"></div>
        </div>
      </div>
    </div>
    <div v-else-if="items.length === 0" class="activity-empty">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <circle cx="12" cy="12" r="10"/>
        <path d="M12 6v6l4 2"/>
      </svg>
      <p>No recent activity</p>
    </div>
    <div v-else class="activity-list">
      <div
        v-for="item in items"
        :key="item.id"
        class="activity-item"
      >
        <div class="activity-icon" :class="getIconClass(item)">
          <component :is="getIcon(item)" />
        </div>
        <div class="activity-content">
          <p class="activity-text">
            <span class="activity-user">{{ item.user?.name || 'System' }}</span>
            <span class="activity-action" :class="getActionClass(item.action)">{{ getActionLabel(item.action) }}</span>
            <span class="activity-target">{{ getTargetName(item) }}</span>
          </p>
          <span class="activity-time">{{ formatTime(item.created_at) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { h } from 'vue';

defineProps({
  items: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

// Icons as render functions
const PostIcon = () => h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
  h('path', { d: 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z' }),
  h('path', { d: 'M14 2v6h6' }),
]);

const CategoryIcon = () => h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
  h('path', { d: 'M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z' }),
]);

const CommentIcon = () => h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
  h('path', { d: 'M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z' }),
]);

const UserIcon = () => h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
  h('path', { d: 'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2' }),
  h('circle', { cx: 12, cy: 7, r: 4 }),
]);

const TagIcon = () => h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
  h('path', { d: 'M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z' }),
  h('line', { x1: 7, y1: 7, x2: '7.01', y2: 7 }),
]);

const FeedbackIcon = () => h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
  h('path', { d: 'M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z' }),
]);

const DefaultIcon = () => h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
  h('circle', { cx: 12, cy: 12, r: 10 }),
  h('line', { x1: 12, y1: 8, x2: 12, y2: 12 }),
  h('line', { x1: 12, y1: 16, x2: '12.01', y2: 16 }),
]);

const getIcon = (item) => {
  const modelType = item.model_type?.toLowerCase() || '';
  const icons = {
    post: PostIcon,
    category: CategoryIcon,
    comment: CommentIcon,
    user: UserIcon,
    tag: TagIcon,
    feedback: FeedbackIcon,
  };
  return icons[modelType] || DefaultIcon;
};

const getIconClass = (item) => {
  const modelType = item.model_type?.toLowerCase() || '';
  const classes = {
    post: 'blue',
    category: 'purple',
    comment: 'green',
    user: 'indigo',
    tag: 'amber',
    feedback: 'orange',
  };
  return classes[modelType] || 'gray';
};

const getActionLabel = (action) => {
  const labels = {
    create: 'created',
    update: 'updated',
    delete: 'deleted',
    publish: 'published',
    unpublish: 'unpublished',
    approve: 'approved',
    reject: 'rejected',
    restore: 'restored',
  };
  return labels[action] || action;
};

const getActionClass = (action) => {
  const classes = {
    create: 'action-create',
    publish: 'action-publish',
    update: 'action-update',
    delete: 'action-delete',
    unpublish: 'action-unpublish',
    approve: 'action-approve',
    reject: 'action-reject',
  };
  return classes[action] || '';
};

const getTargetName = (item) => {
  // Extract target name from description
  // Format: "Action model_type: Target Name" -> extract "Target Name"
  if (item.description) {
    const match = item.description.match(/:\s*(.+)$/);
    if (match) {
      return match[1];
    }
  }
  return item.model_type || 'item';
};

const formatTime = (date) => {
  if (!date) return '';
  const now = new Date();
  const past = new Date(date);
  const diffMs = now - past;
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 1) return 'just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays < 7) return `${diffDays}d ago`;
  return past.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};
</script>

<style scoped>
.activity-feed {
  max-height: 320px;
  overflow-y: auto;
  padding-right: 4px;
  margin-right: -4px;
}

/* Custom scrollbar */
.activity-feed::-webkit-scrollbar {
  width: 6px;
}

.activity-feed::-webkit-scrollbar-track {
  background: transparent;
}

.activity-feed::-webkit-scrollbar-thumb {
  background: #e2e8f0;
  border-radius: 3px;
}

.activity-feed::-webkit-scrollbar-thumb:hover {
  background: #cbd5e1;
}

/* Firefox scrollbar */
.activity-feed {
  scrollbar-width: thin;
  scrollbar-color: #e2e8f0 transparent;
}

.activity-loading {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.activity-skeleton {
  display: flex;
  gap: 12px;
  align-items: flex-start;
}

.skeleton-icon {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  flex-shrink: 0;
}

.skeleton-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding-top: 2px;
}

.skeleton-line {
  height: 10px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 4px;
}

.skeleton-line.short {
  width: 70%;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.activity-empty {
  text-align: center;
  padding: 32px 16px;
  color: #94a3b8;
}

.activity-empty svg {
  margin-bottom: 12px;
  opacity: 0.5;
}

.activity-empty p {
  margin: 0;
  font-size: 13px;
}

.activity-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.activity-item {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  padding: 8px 10px;
  border-radius: 8px;
  transition: background-color 0.15s ease;
}

.activity-item:hover {
  background: #f8fafc;
}

.activity-icon {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.activity-icon.blue {
  background: #eff6ff;
  color: #2563eb;
}

.activity-icon.green {
  background: #ecfdf5;
  color: #059669;
}

.activity-icon.purple {
  background: #f5f3ff;
  color: #7c3aed;
}

.activity-icon.indigo {
  background: #eef2ff;
  color: #4f46e5;
}

.activity-icon.orange {
  background: #fff7ed;
  color: #ea580c;
}

.activity-icon.amber {
  background: #fffbeb;
  color: #d97706;
}

.activity-icon.gray {
  background: #f1f5f9;
  color: #64748b;
}

.activity-content {
  flex: 1;
  min-width: 0;
}

.activity-text {
  font-size: 13px;
  color: #475569;
  margin: 0 0 3px 0;
  line-height: 1.5;
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  align-items: baseline;
}

.activity-user {
  font-weight: 600;
  color: #1e293b;
}

.activity-action {
  color: #64748b;
}

.activity-action.action-create,
.activity-action.action-publish {
  color: #059669;
}

.activity-action.action-update {
  color: #2563eb;
}

.activity-action.action-delete,
.activity-action.action-reject {
  color: #dc2626;
}

.activity-action.action-unpublish {
  color: #d97706;
}

.activity-action.action-approve {
  color: #059669;
}

.activity-target {
  color: #334155;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 180px;
}

.activity-time {
  font-size: 11px;
  color: #94a3b8;
}
</style>
