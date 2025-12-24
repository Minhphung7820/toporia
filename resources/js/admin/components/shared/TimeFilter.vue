<template>
  <div class="time-filter" v-click-outside="close">
    <div class="filter-trigger" @click="toggle">
      <svg class="clock-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10" />
        <polyline points="12 6 12 12 16 14" />
      </svg>
      <span class="filter-label">{{ selectedLabel }}</span>
      <button
        v-if="modelValue && clearable"
        @click.stop="clearFilter"
        class="clear-btn"
        type="button"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18" />
          <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
      </button>
      <span class="chevron-icon" :class="{ rotated: isOpen }">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="6 9 12 15 18 9" />
        </svg>
      </span>
    </div>

    <Transition name="dropdown">
      <div v-if="isOpen" class="filter-panel">
        <!-- Preset Options -->
        <div class="preset-section">
          <div class="section-label">Quick Filters</div>
          <div class="preset-grid">
            <button
              v-for="preset in presets"
              :key="preset.value"
              @click="selectPreset(preset)"
              :class="['preset-btn', { active: modelValue === preset.value }]"
              type="button"
            >
              <svg v-if="preset.icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <component :is="preset.icon" />
              </svg>
              <span>{{ preset.label }}</span>
            </button>
          </div>
        </div>

        <!-- Custom Date Range -->
        <div class="custom-section">
          <div class="section-label">Custom Range</div>
          <div class="date-inputs">
            <div class="date-input-group">
              <label>From</label>
              <input
                v-model="customRange.from"
                type="date"
                class="date-input"
                :max="customRange.to || today"
              />
            </div>
            <div class="date-separator">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="5" y1="12" x2="19" y2="12" />
                <polyline points="12 5 19 12 12 19" />
              </svg>
            </div>
            <div class="date-input-group">
              <label>To</label>
              <input
                v-model="customRange.to"
                type="date"
                class="date-input"
                :min="customRange.from"
                :max="today"
              />
            </div>
          </div>
          <button
            @click="applyCustomRange"
            :disabled="!isValidCustomRange"
            class="apply-btn"
            type="button"
          >
            Apply Custom Range
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  modelValue: {
    type: String,
    default: '1h', // Default to "last 1 hour"
  },
  clearable: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(['update:modelValue', 'change', 'open']);

// State
const isOpen = ref(false);
const customRange = ref({
  from: '',
  to: '',
});

// Today's date in YYYY-MM-DD format
const today = computed(() => {
  const date = new Date();
  return date.toISOString().split('T')[0];
});

// Preset time ranges
const presets = [
  {
    value: '1h',
    label: 'Last Hour',
    icon: 'path',
  },
  {
    value: '5h',
    label: 'Last 5 Hours',
    icon: 'path',
  },
  {
    value: '24h',
    label: 'Last 24 Hours',
    icon: 'path',
  },
  {
    value: '72h',
    label: 'Last 3 Days',
    icon: 'path',
  },
  {
    value: '7d',
    label: 'Last Week',
    icon: 'path',
  },
  {
    value: 'older',
    label: 'Older',
    icon: 'path',
  },
];

// Computed
const selectedLabel = computed(() => {
  // Check if it's a preset
  const preset = presets.find(p => p.value === props.modelValue);
  if (preset) return preset.label;

  // Check if it's a custom range (format: YYYY-MM-DD_YYYY-MM-DD)
  if (props.modelValue && props.modelValue.includes('_')) {
    const [from, to] = props.modelValue.split('_');
    return `${formatDate(from)} - ${formatDate(to)}`;
  }

  // Default
  return 'Last Hour';
});

const isValidCustomRange = computed(() => {
  return customRange.value.from && customRange.value.to &&
         customRange.value.from <= customRange.value.to;
});

// Methods
const toggle = () => {
  const willOpen = !isOpen.value;
  if (willOpen) {
    emit('open');
  }
  isOpen.value = willOpen;
  if (!isOpen.value) {
    // Reset custom range inputs when closing
    customRange.value = { from: '', to: '' };
  }
};

const close = () => {
  isOpen.value = false;
  customRange.value = { from: '', to: '' };
};

const selectPreset = (preset) => {
  emit('update:modelValue', preset.value);
  emit('change', { type: 'preset', value: preset.value });
  close();
};

const applyCustomRange = () => {
  if (!isValidCustomRange.value) return;

  const rangeValue = `${customRange.value.from}_${customRange.value.to}`;
  emit('update:modelValue', rangeValue);
  emit('change', {
    type: 'custom',
    value: rangeValue,
    from: customRange.value.from,
    to: customRange.value.to,
  });
  close();
};

const clearFilter = () => {
  emit('update:modelValue', null);
  emit('change', null);
};

const formatDate = (dateStr) => {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};

// Click outside directive
const vClickOutside = {
  mounted(el, binding) {
    el.clickOutsideEvent = (event) => {
      if (!(el === event.target || el.contains(event.target))) {
        binding.value();
      }
    };
    document.addEventListener('click', el.clickOutsideEvent);
  },
  unmounted(el) {
    document.removeEventListener('click', el.clickOutsideEvent);
  },
};

// Expose close method for parent component
defineExpose({
  close,
});
</script>

<style scoped>
.time-filter {
  position: relative;
  width: 100%;
}

/* Trigger */
.filter-trigger {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s ease;
  height: 42px;
  box-sizing: border-box;
  user-select: none;
}

.filter-trigger:hover {
  border-color: #d1d5db;
  background: #f9fafb;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.filter-trigger:active {
  transform: scale(0.995);
}

.clock-icon {
  color: #6b7280;
  flex-shrink: 0;
}

.filter-label {
  flex: 1;
  color: #111827;
  font-size: 14px;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.clear-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
  background: transparent;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  border-radius: 4px;
  transition: all 0.15s ease;
  flex-shrink: 0;
}

.clear-btn:hover {
  background: #f3f4f6;
  color: #6b7280;
}

.chevron-icon {
  display: flex;
  align-items: center;
  color: #6b7280;
  transition: transform 0.2s ease;
  flex-shrink: 0;
}

.chevron-icon.rotated {
  transform: rotate(180deg);
}

/* Panel */
.filter-panel {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  right: 0;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1),
              0 2px 8px rgba(0, 0, 0, 0.06);
  z-index: 1000;
  padding: 16px;
  min-width: 360px;
  max-width: 400px;
  width: max-content;
}

/* Sections */
.section-label {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 12px;
}

.preset-section {
  padding-bottom: 16px;
  border-bottom: 1px solid #f3f4f6;
  margin-bottom: 16px;
}

/* Preset Grid */
.preset-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
}

.preset-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px 12px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  color: #374151;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
}

.preset-btn:hover {
  background: #f3f4f6;
  border-color: #d1d5db;
}

.preset-btn.active {
  background: #eff6ff;
  border-color: #4f46e5;
  color: #4f46e5;
}

.preset-btn svg {
  width: 14px;
  height: 14px;
  flex-shrink: 0;
}

/* Custom Range */
.custom-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.date-inputs {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  max-width: 100%;
}

.date-input-group {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.date-input-group label {
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
  white-space: nowrap;
}

.date-input {
  width: 100%;
  max-width: 100%;
  padding: 8px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 13px;
  color: #111827;
  outline: none;
  transition: all 0.2s ease;
  box-sizing: border-box;
}

.date-input:focus {
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.date-separator {
  display: flex;
  align-items: center;
  justify-content: center;
  padding-bottom: 8px;
  color: #9ca3af;
}

.apply-btn {
  width: 100%;
  padding: 10px 16px;
  background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
  border: none;
  border-radius: 8px;
  color: white;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
}

.apply-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4);
}

.apply-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

/* Transitions */
.dropdown-enter-active,
.dropdown-leave-active {
  transition: all 0.2s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

/* Responsive */
@media (max-width: 768px) {
  .filter-panel {
    min-width: 320px;
    max-width: calc(100vw - 40px);
    left: 50%;
    right: auto;
    top: calc(100% + 6px);
    transform: translateX(-50%);
  }

  .dropdown-enter-from,
  .dropdown-leave-to {
    opacity: 0;
    transform: translateX(-50%) translateY(-10px);
  }

  .dropdown-enter-active,
  .dropdown-leave-active {
    transition: all 0.2s ease;
  }
}

@media (max-width: 640px) {
  .filter-panel {
    min-width: 280px;
    max-width: calc(100vw - 32px);
    left: 50%;
    right: auto;
    transform: translateX(-50%);
  }

  .dropdown-enter-from,
  .dropdown-leave-to {
    opacity: 0;
    transform: translateX(-50%) translateY(-10px);
  }

  .dropdown-enter-active,
  .dropdown-leave-active {
    transition: all 0.2s ease;
  }

  .preset-grid {
    grid-template-columns: 1fr;
  }

  .date-inputs {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }

  .date-separator {
    transform: rotate(90deg);
    padding: 8px 0;
  }
}

@media (max-width: 480px) {
  .filter-panel {
    min-width: 240px;
    max-width: calc(100vw - 24px);
    left: 50%;
    right: auto;
    transform: translateX(-50%);
    padding: 14px;
  }

  .dropdown-enter-from,
  .dropdown-leave-to {
    opacity: 0;
    transform: translateX(-50%) translateY(-10px);
  }

  .dropdown-enter-active,
  .dropdown-leave-active {
    transition: all 0.2s ease;
  }

  .preset-btn {
    font-size: 12px;
    padding: 8px 12px;
  }

  .date-input {
    font-size: 12px;
    padding: 7px 9px;
  }
}
</style>
