<template>
  <AdminLayout>
    <div class="settings-index">
      <div class="page-header">
        <div>
          <h1>Settings</h1>
          <p>Configure your site settings</p>
        </div>
        <div class="header-actions" v-if="hasUnsavedChanges">
          <button @click="discardChanges" class="btn btn-secondary">Discard</button>
          <button @click="saveAll" class="btn btn-primary" :disabled="saving">
            {{ saving ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </div>

      <div class="settings-layout">
        <div class="settings-sidebar">
          <button
            v-for="group in groups"
            :key="group"
            :class="['group-btn', { active: currentGroup === group }]"
            @click="selectGroup(group)"
          >
            <svg v-if="group === 'general'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="3" />
              <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z" />
            </svg>
            <svg v-else-if="group === 'blog'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
              <polyline points="14 2 14 8 20 8" />
            </svg>
            <svg v-else-if="group === 'seo'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8" />
              <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
            <svg v-else-if="group === 'social'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z" />
            </svg>
            <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
            </svg>
            {{ formatGroupName(group) }}
          </button>
        </div>

        <div class="settings-content">
          <div v-if="loading" class="loading-state">
            <div class="spinner"></div>
            Loading settings...
          </div>

          <div v-else-if="groupSettings.length === 0" class="empty-state">
            <p>No settings in this group</p>
          </div>

          <div v-else class="settings-list">
            <div v-for="setting in groupSettings" :key="setting.key" class="setting-item">
              <div class="setting-info">
                <label :for="setting.key">{{ setting.label || setting.key }}</label>
                <span v-if="setting.description" class="setting-description">{{ setting.description }}</span>
              </div>
              <div class="setting-input">
                <template v-if="setting.type === 'boolean'">
                  <label class="toggle">
                    <input
                      type="checkbox"
                      :checked="getSettingValue(setting.key)"
                      @change="updateSetting(setting.key, $event.target.checked)"
                    />
                    <span class="toggle-slider"></span>
                  </label>
                </template>
                <template v-else-if="setting.type === 'select'">
                  <select
                    :id="setting.key"
                    :value="getSettingValue(setting.key)"
                    @change="updateSetting(setting.key, $event.target.value)"
                  >
                    <option v-for="opt in setting.options" :key="opt.value" :value="opt.value">
                      {{ opt.label }}
                    </option>
                  </select>
                </template>
                <template v-else-if="setting.type === 'textarea'">
                  <textarea
                    :id="setting.key"
                    :value="getSettingValue(setting.key)"
                    @input="updateSetting(setting.key, $event.target.value)"
                    rows="3"
                  ></textarea>
                </template>
                <template v-else>
                  <input
                    :id="setting.key"
                    :type="setting.type || 'text'"
                    :value="getSettingValue(setting.key)"
                    @input="updateSetting(setting.key, $event.target.value)"
                  />
                </template>
              </div>
            </div>
          </div>

          <div class="reset-section">
            <button @click="confirmReset" class="btn btn-danger-outline">
              Reset {{ formatGroupName(currentGroup) }} to Defaults
            </button>
          </div>
        </div>
      </div>

      <ConfirmDialog
        v-if="showResetDialog"
        title="Reset Settings"
        :message="`Reset all ${formatGroupName(currentGroup)} settings to their default values?`"
        confirm-text="Reset"
        confirm-class="danger"
        @confirm="resetSettings"
        @cancel="showResetDialog = false"
      />
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import AdminLayout from '../../components/layout/AdminLayout.vue';
import ConfirmDialog from '../../components/shared/ConfirmDialog.vue';
import { useSettingsStore } from '../../stores/settings';

const store = useSettingsStore();

const currentGroup = ref('general');
const showResetDialog = ref(false);

const groups = computed(() => store.groups);
const groupSettings = computed(() => store.groupSettings);
const loading = computed(() => store.loading);
const saving = computed(() => store.saving);
const hasUnsavedChanges = computed(() => store.hasUnsavedChanges);

const getSettingValue = (key) => store.getSettingValue(key);

const formatGroupName = (group) => {
  if (!group || typeof group !== 'string') return '';
  return group.charAt(0).toUpperCase() + group.slice(1).replace(/_/g, ' ');
};

const selectGroup = async (group) => {
  currentGroup.value = group;
  await store.fetchByGroup(group);
};

const updateSetting = (key, value) => {
  store.setUnsavedChange(key, value);
};

const saveAll = async () => {
  await store.saveAllUnsavedChanges();
};

const discardChanges = () => {
  store.discardUnsavedChanges();
  store.fetchByGroup(currentGroup.value);
};

const confirmReset = () => {
  showResetDialog.value = true;
};

const resetSettings = async () => {
  await store.resetToDefaults(currentGroup.value);
  showResetDialog.value = false;
};

onMounted(async () => {
  await store.fetchGroups();
  if (groups.value.length > 0) {
    currentGroup.value = groups.value[0];
    await store.fetchByGroup(currentGroup.value);
  }
});
</script>

<style scoped>
.settings-index { max-width: 1000px; }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 24px; }
.page-header h1 { font-size: 24px; font-weight: 700; color: #1f2937; margin: 0 0 4px 0; }
.page-header p { color: #6b7280; margin: 0; }
.header-actions { display: flex; gap: 12px; }
.btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; border: none; }
.btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; }
.btn-secondary { background: #f3f4f6; color: #374151; }
.btn-danger-outline { background: transparent; border: 1px solid #fecaca; color: #dc2626; }
.btn-danger-outline:hover { background: #fef2f2; }
.settings-layout { display: grid; grid-template-columns: 200px 1fr; gap: 24px; }
.settings-sidebar { display: flex; flex-direction: column; gap: 4px; }
.group-btn { display: flex; align-items: center; gap: 10px; padding: 12px 16px; background: transparent; border: none; border-radius: 8px; font-size: 14px; font-weight: 500; color: #6b7280; cursor: pointer; text-align: left; transition: all 0.2s ease; }
.group-btn:hover { background: #f3f4f6; color: #1f2937; }
.group-btn.active { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; }
.settings-content { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); }
.loading-state, .empty-state { text-align: center; padding: 48px; color: #9ca3af; }
.spinner { width: 32px; height: 32px; border: 3px solid #e5e7eb; border-top-color: #667eea; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 16px; }
@keyframes spin { to { transform: rotate(360deg); } }
.settings-list { display: flex; flex-direction: column; gap: 24px; }
.setting-item { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; padding-bottom: 24px; border-bottom: 1px solid #e5e7eb; }
.setting-item:last-child { border-bottom: none; padding-bottom: 0; }
.setting-info { flex: 1; }
.setting-info label { display: block; font-weight: 500; color: #1f2937; margin-bottom: 4px; }
.setting-description { font-size: 13px; color: #6b7280; }
.setting-input { width: 280px; flex-shrink: 0; }
.setting-input input, .setting-input select, .setting-input textarea { width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; }
.setting-input input:focus, .setting-input select:focus, .setting-input textarea:focus { outline: none; border-color: #667eea; }
.toggle { position: relative; display: inline-block; width: 48px; height: 26px; }
.toggle input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; inset: 0; background: #e5e7eb; border-radius: 26px; transition: 0.3s; }
.toggle-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: 0.3s; }
.toggle input:checked + .toggle-slider { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.toggle input:checked + .toggle-slider:before { transform: translateX(22px); }
.reset-section { margin-top: 32px; padding-top: 24px; border-top: 1px solid #e5e7eb; }
@media (max-width: 768px) { .settings-layout { grid-template-columns: 1fr; } .settings-sidebar { flex-direction: row; flex-wrap: wrap; } .setting-item { flex-direction: column; } .setting-input { width: 100%; } }
</style>
