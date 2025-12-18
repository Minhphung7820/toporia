<template>
  <div class="chart-container">
    <div v-if="loading" class="chart-loading">
      <div class="chart-skeleton"></div>
    </div>
    <div v-else-if="!data || !data.labels || data.labels.length === 0" class="chart-empty">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <line x1="18" y1="20" x2="18" y2="10" />
        <line x1="12" y1="20" x2="12" y2="4" />
        <line x1="6" y1="20" x2="6" y2="14" />
      </svg>
      <p>No data available</p>
    </div>
    <div v-else class="chart-wrapper">
      <div class="chart-bars">
        <div
          v-for="(value, index) in data.values"
          :key="index"
          class="chart-bar-wrapper"
        >
          <div
            class="chart-bar"
            :style="{ height: getBarHeight(value) + '%' }"
            :title="`${data.labels[index]}: ${value.toLocaleString()} views`"
          >
            <span class="bar-value">{{ formatValue(value) }}</span>
          </div>
          <span class="bar-label">{{ formatLabel(data.labels[index]) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  data: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const maxValue = computed(() => {
  if (!props.data?.values) return 0;
  return Math.max(...props.data.values, 1);
});

const getBarHeight = (value) => {
  return (value / maxValue.value) * 100;
};

const formatValue = (val) => {
  if (val >= 1000000000) return (val / 1000000000).toFixed(1) + 'B';
  if (val >= 1000000) return (val / 1000000).toFixed(1) + 'M';
  if (val >= 1000) return (val / 1000).toFixed(1) + 'K';
  return val.toString();
};

const formatLabel = (label) => {
  if (!label) return '';
  if (label.includes('-')) {
    const date = new Date(label);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  }
  return label.slice(0, 3);
};
</script>

<style scoped>
.chart-container {
  height: 280px;
}

.chart-loading,
.chart-empty {
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
}

.chart-skeleton {
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 12px;
}

@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

.chart-empty svg {
  margin-bottom: 12px;
  opacity: 0.4;
}

.chart-empty p {
  margin: 0;
  font-size: 14px;
}

.chart-wrapper {
  height: 100%;
  padding-top: 28px;
}

.chart-bars {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  height: 100%;
  gap: 10px;
  padding-bottom: 36px;
}

.chart-bar-wrapper {
  flex: 1;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  position: relative;
}

.chart-bar {
  width: 100%;
  max-width: 48px;
  background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
  border-radius: 8px 8px 0 0;
  position: relative;
  transition: all 0.3s ease;
  cursor: pointer;
  min-height: 4px;
}

.chart-bar:hover {
  background: linear-gradient(180deg, #60a5fa 0%, #3b82f6 100%);
  transform: scaleY(1.02);
  transform-origin: bottom;
}

.bar-value {
  position: absolute;
  top: -26px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 12px;
  font-weight: 600;
  color: #475569;
  white-space: nowrap;
  opacity: 0;
  transition: opacity 0.2s ease;
}

.chart-bar:hover .bar-value {
  opacity: 1;
}

.bar-label {
  position: absolute;
  bottom: -28px;
  font-size: 12px;
  color: #64748b;
  white-space: nowrap;
  font-weight: 500;
}

@media (max-width: 768px) {
  .chart-container {
    height: 220px;
  }

  .chart-bars {
    gap: 6px;
  }

  .chart-bar {
    max-width: 32px;
    border-radius: 6px 6px 0 0;
  }

  .bar-label {
    font-size: 10px;
  }
}
</style>
