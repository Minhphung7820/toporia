import { defineStore } from 'pinia';
import { settings } from '../services/api';

export const useSettingsStore = defineStore('admin-settings', {
  state: () => ({
    items: [],
    groups: [],
    currentGroup: null,
    groupSettings: [],
    loading: false,
    saving: false,
    error: null,
    unsavedChanges: {},
  }),

  getters: {
    hasItems: (state) => state.items.length > 0,
    hasUnsavedChanges: (state) => Object.keys(state.unsavedChanges).length > 0,
    getSettingValue: (state) => (key) => {
      if (state.unsavedChanges[key] !== undefined) {
        return state.unsavedChanges[key];
      }
      const setting = state.items.find((s) => s.key === key);
      return setting?.value ?? null;
    },
  },

  actions: {
    async fetchSettings() {
      this.loading = true;
      this.error = null;
      try {
        const response = await settings.list();
        if (response.data.success) {
          this.items = response.data.data;
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch settings';
      } finally {
        this.loading = false;
      }
    },

    async fetchGroups() {
      try {
        const response = await settings.groups();
        if (response.data.success) {
          this.groups = Array.isArray(response.data.data) ? response.data.data : [];
        }
      } catch (error) {
        console.error('Failed to fetch groups:', error);
        this.groups = [];
      }
    },

    async fetchByGroup(group) {
      this.loading = true;
      this.error = null;
      this.currentGroup = group;
      try {
        const response = await settings.byGroup(group);
        if (response.data.success) {
          this.groupSettings = response.data.data;
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch group settings';
      } finally {
        this.loading = false;
      }
    },

    async updateSetting(key, value) {
      this.saving = true;
      this.error = null;
      try {
        const response = await settings.update(key, value);
        if (response.data.success) {
          const index = this.items.findIndex((s) => s.key === key);
          if (index !== -1) {
            this.items[index] = response.data.data;
          }
          // Also update group settings if loaded
          const groupIndex = this.groupSettings.findIndex((s) => s.key === key);
          if (groupIndex !== -1) {
            this.groupSettings[groupIndex] = response.data.data;
          }
          // Remove from unsaved changes
          delete this.unsavedChanges[key];
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update setting';
        throw error;
      } finally {
        this.saving = false;
      }
    },

    async updateBatch(settingsData) {
      this.saving = true;
      this.error = null;
      try {
        const response = await settings.updateBatch(settingsData);
        if (response.data.success) {
          // Refresh all settings after batch update
          await this.fetchSettings();
          if (this.currentGroup) {
            await this.fetchByGroup(this.currentGroup);
          }
          this.unsavedChanges = {};
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update settings';
        throw error;
      } finally {
        this.saving = false;
      }
    },

    async createSetting(data) {
      this.saving = true;
      this.error = null;
      try {
        const response = await settings.create(data);
        if (response.data.success) {
          this.items.push(response.data.data);
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to create setting';
        throw error;
      } finally {
        this.saving = false;
      }
    },

    async deleteSetting(key) {
      this.error = null;
      try {
        const response = await settings.delete(key);
        if (response.data.success) {
          this.items = this.items.filter((s) => s.key !== key);
          this.groupSettings = this.groupSettings.filter((s) => s.key !== key);
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to delete setting';
        throw error;
      }
    },

    async resetToDefaults(group = null) {
      this.saving = true;
      this.error = null;
      try {
        const response = await settings.reset(group);
        if (response.data.success) {
          await this.fetchSettings();
          if (this.currentGroup) {
            await this.fetchByGroup(this.currentGroup);
          }
          this.unsavedChanges = {};
        }
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to reset settings';
        throw error;
      } finally {
        this.saving = false;
      }
    },

    setUnsavedChange(key, value) {
      this.unsavedChanges[key] = value;
    },

    discardUnsavedChanges() {
      this.unsavedChanges = {};
    },

    async saveAllUnsavedChanges() {
      if (!this.hasUnsavedChanges) return;
      await this.updateBatch(this.unsavedChanges);
    },
  },
});
