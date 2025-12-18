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
      <p>No recent activity</p>
    </div>
    <div v-else class="activity-list">
      <div
        v-for="item in items"
        :key="item.id"
        class="activity-item"
      >
        <div class="activity-icon" :class="getActivityColor(item.type)">
          <svg v-if="item.type === 'post'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
            <path d="M14 2v6h6"/>
          </svg>
          <svg v-else-if="item.type === 'comment'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
          </svg>
          <svg v-else-if="item.type === 'user'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
          </svg>
          <svg v-else-if="item.type === 'feedback'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/>
          </svg>
          <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
        </div>
        <div class="activity-content">
          <p class="activity-text">
            <strong>{{ item.actor_name }}</strong>
            {{ getActivityAction(item) }}
            <router-link v-if="item.target_url" :to="item.target_url" class="activity-target">
              {{ item.target_title }}
            </router-link>
            <span v-else class="activity-target">{{ item.target_title }}</span>
          </p>
          <span class="activity-time">{{ formatTime(item.created_at) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
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

const getActivityColor = (type) => {
  const colors = {
    post: 'blue',
    comment: 'green',
    user: 'purple',
    feedback: 'orange',
  };
  return colors[type] || 'gray';
};

const getActivityAction = (item) => {
  const actions = {
    post_created: 'published a new post',
    post_updated: 'updated the post',
    post_deleted: 'deleted a post',
    comment_created: 'commented on',
    comment_approved: 'approved a comment on',
    comment_rejected: 'rejected a comment on',
    user_registered: 'registered as a new user',
    user_updated: 'updated their profile',
    feedback_submitted: 'submitted feedback',
    feedback_resolved: 'resolved feedback',
  };
  return actions[item.action] || item.action;
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
  max-height: 350px;
  overflow-y: auto;
}

.activity-loading {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.activity-skeleton {
  display: flex;
  gap: 12px;
}

.skeleton-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #e5e7eb;
  flex-shrink: 0;
}

.skeleton-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.skeleton-line {
  height: 12px;
  background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 4px;
}

.skeleton-line.short {
  width: 60%;
}

@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

.activity-empty {
  text-align: center;
  padding: 32px;
  color: #9ca3af;
}

.activity-empty p {
  margin: 0;
}

.activity-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.activity-item {
  display: flex;
  gap: 12px;
}

.activity-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.activity-icon.blue {
  background: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
}

.activity-icon.green {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.activity-icon.purple {
  background: rgba(139, 92, 246, 0.1);
  color: #8b5cf6;
}

.activity-icon.orange {
  background: rgba(245, 158, 11, 0.1);
  color: #f59e0b;
}

.activity-icon.gray {
  background: rgba(107, 114, 128, 0.1);
  color: #6b7280;
}

.activity-content {
  flex: 1;
  min-width: 0;
}

.activity-text {
  font-size: 14px;
  color: #374151;
  margin: 0 0 4px 0;
  line-height: 1.4;
}

.activity-text strong {
  color: #1f2937;
}

.activity-target {
  color: #4f46e5;
  text-decoration: none;
}

.activity-target:hover {
  text-decoration: underline;
}

.activity-time {
  font-size: 12px;
  color: #9ca3af;
}
</style>
