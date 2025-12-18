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
  if (val >= 1000000) return (val / 1000000).toFixed(1) + 'M';
  if (val >= 1000) return (val / 1000).toFixed(1) + 'K';
  return val.toString();
};

const formatLabel = (label) => {
  if (!label) return '';
  // For date labels, show short format
  if (label.includes('-')) {
    const date = new Date(label);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  }
  return label.slice(0, 3);
};
</script>

<style scoped>
.chart-container {
  height: 250px;
}

.chart-loading,
.chart-empty {
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #9ca3af;
}

.chart-skeleton {
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 8px;
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
  opacity: 0.5;
}

.chart-empty p {
  margin: 0;
  font-size: 14px;
}

.chart-wrapper {
  height: 100%;
  padding-top: 20px;
}

.chart-bars {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  height: 100%;
  gap: 8px;
  padding-bottom: 30px;
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
  max-width: 40px;
  background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
  border-radius: 6px 6px 0 0;
  position: relative;
  transition: height 0.3s ease;
  cursor: pointer;
  min-height: 4px;
}

.chart-bar:hover {
  opacity: 0.9;
}

.bar-value {
  position: absolute;
  top: -24px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
  white-space: nowrap;
  opacity: 0;
  transition: opacity 0.2s ease;
}

.chart-bar:hover .bar-value {
  opacity: 1;
}

.bar-label {
  position: absolute;
  bottom: -24px;
  font-size: 11px;
  color: #9ca3af;
  white-space: nowrap;
}
</style>
