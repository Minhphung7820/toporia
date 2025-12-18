<template>
  <div class="stat-card" :class="[color, { loading }]">
    <div class="stat-header">
      <div class="stat-icon">
        <svg v-if="icon === 'posts'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
          <path d="M14 2v6h6"/>
          <line x1="16" y1="13" x2="8" y2="13"/>
          <line x1="16" y1="17" x2="8" y2="17"/>
        </svg>
        <svg v-else-if="icon === 'views'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
          <circle cx="12" cy="12" r="3"/>
        </svg>
        <svg v-else-if="icon === 'comments'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
        </svg>
        <svg v-else-if="icon === 'users'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 00-3-3.87"/>
          <path d="M16 3.13a4 4 0 010 7.75"/>
        </svg>
      </div>
      <span class="stat-title">{{ title }}</span>
    </div>
    <div class="stat-body">
      <div v-if="loading" class="stat-value-skeleton"></div>
      <div v-else class="stat-value">{{ formatValue(value) }}</div>
      <div v-if="!loading && change !== undefined && change !== null" class="stat-change" :class="{ positive: change >= 0, negative: change < 0 }">
        <svg v-if="change >= 0" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <polyline points="18 15 12 9 6 15"/>
        </svg>
        <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
        <span>{{ Math.abs(change) }}%</span>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    required: true,
  },
  value: {
    type: [Number, String],
    default: 0,
  },
  change: {
    type: Number,
    default: null,
  },
  icon: {
    type: String,
    default: 'posts',
  },
  color: {
    type: String,
    default: 'blue',
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const formatValue = (val) => {
  if (typeof val === 'string') return val;
  if (val >= 1000000000) return (val / 1000000000).toFixed(1) + 'B';
  if (val >= 1000000) return (val / 1000000).toFixed(1) + 'M';
  if (val >= 1000) return (val / 1000).toFixed(1) + 'K';
  return val.toLocaleString();
};
</script>

<style scoped>
.stat-card {
  background: #fff;
  border-radius: 16px;
  padding: 20px 24px;
  border: 1px solid #e2e8f0;
  transition: all 0.2s ease;
}

.stat-card:hover {
  border-color: #cbd5e1;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.stat-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}

.stat-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-card.blue .stat-icon {
  background: #eff6ff;
  color: #2563eb;
}

.stat-card.green .stat-icon {
  background: #ecfdf5;
  color: #059669;
}

.stat-card.purple .stat-icon {
  background: #f5f3ff;
  color: #7c3aed;
}

.stat-card.orange .stat-icon {
  background: #fff7ed;
  color: #ea580c;
}

.stat-title {
  font-size: 13px;
  font-weight: 500;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.025em;
}

.stat-body {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
}

.stat-value {
  font-size: 32px;
  font-weight: 700;
  color: #0f172a;
  line-height: 1;
  letter-spacing: -0.025em;
}

.stat-change {
  display: flex;
  align-items: center;
  gap: 2px;
  font-size: 13px;
  font-weight: 600;
  padding: 4px 8px;
  border-radius: 6px;
}

.stat-change.positive {
  color: #059669;
  background: #ecfdf5;
}

.stat-change.negative {
  color: #dc2626;
  background: #fef2f2;
}

/* Loading skeleton */
.stat-value-skeleton {
  width: 80px;
  height: 32px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 6px;
}

@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}
</style>
