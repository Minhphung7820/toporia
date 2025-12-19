/**
 * Auth Store (Pinia)
 *
 * Centralized state management for authentication.
 * Manages user state, loading states, and auth operations.
 */

import { defineStore } from 'pinia';
import { authService } from '../services/auth';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    loading: false,
    initialized: false,
  }),

  getters: {
    /**
     * Check if user is authenticated
     */
    isAuthenticated: (state) => {
      return state.user !== null;
    },

    /**
     * Check if user has admin access (admin or moderator role)
     */
    hasAdminAccess: (state) => {
      const adminRoles = ['admin', 'moderator'];
      return state.user !== null && adminRoles.includes(state.user.role);
    },

    /**
     * Check if user is admin
     */
    isAdmin: (state) => {
      return state.user?.role === 'admin';
    },

    /**
     * Check if user is moderator
     */
    isModerator: (state) => {
      return state.user?.role === 'moderator';
    },

    /**
     * Get user role
     */
    userRole: (state) => {
      return state.user?.role || null;
    },

    /**
     * Get user name
     */
    userName: (state) => {
      return state.user?.name || null;
    },

    /**
     * Get user email
     */
    userEmail: (state) => {
      return state.user?.email || null;
    },
  },

  actions: {
    /**
     * Initialize auth state (check current user)
     * Only runs once on app load
     */
    async initialize() {
      if (this.initialized) {
        return;
      }

      this.loading = true;
      try {
        const result = await authService.getUser();
        if (result.success && result.user) {
          this.user = result.user;
        } else {
          this.user = null;
        }
      } catch (error) {
        console.error('Auth initialization error:', error);
        this.user = null;
      } finally {
        this.loading = false;
        this.initialized = true;
      }
    },

    /**
     * Check current auth status
     * Can be called multiple times to refresh user data
     */
    async checkAuth() {
      this.loading = true;
      try {
        const result = await authService.getUser();
        if (result.success && result.user) {
          this.user = result.user;
        } else {
          this.user = null;
        }
        return { success: result.success, user: this.user };
      } catch (error) {
        console.error('Auth check error:', error);
        this.user = null;
        return { success: false, user: null };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Login user
     */
    async login(email, password, remember = false) {
      this.loading = true;
      try {
        const result = await authService.login(email, password, remember);
        if (result.success && result.user) {
          this.user = result.user;
          return { success: true, user: this.user };
        }
        // Handle unverified email case
        if (result.requires_verification) {
          return {
            success: false,
            requires_verification: true,
            email: result.email,
            message: result.message || 'Please verify your email address',
          };
        }
        return { success: false, message: result.message || 'Login failed' };
      } catch (error) {
        console.error('Login error:', error);
        return { success: false, message: 'An error occurred during login' };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Register new user
     */
    async register(data) {
      this.loading = true;
      try {
        const result = await authService.register(data);
        if (result.success) {
          // Don't set user - registration now requires email verification
          return {
            success: true,
            requires_verification: result.requires_verification || false,
            message: result.message,
          };
        }
        return { success: false, message: result.message || 'Registration failed', errors: result.errors };
      } catch (error) {
        console.error('Registration error:', error);
        return { success: false, message: 'An error occurred during registration' };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Logout user
     */
    async logout() {
      this.loading = true;
      try {
        await authService.logout();
        this.user = null;
        return { success: true };
      } catch (error) {
        console.error('Logout error:', error);
        // Even if logout fails, clear local state
        this.user = null;
        return { success: false, message: 'An error occurred during logout' };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Request password reset
     */
    async forgotPassword(email) {
      this.loading = true;
      try {
        const result = await authService.forgotPassword(email);
        return result;
      } catch (error) {
        console.error('Forgot password error:', error);
        return { success: false, message: 'An error occurred' };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Reset password with token
     */
    async resetPassword(data) {
      this.loading = true;
      try {
        const result = await authService.resetPassword(data);
        return result;
      } catch (error) {
        console.error('Reset password error:', error);
        return { success: false, message: 'An error occurred' };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Change password (authenticated)
     */
    async changePassword(data) {
      this.loading = true;
      try {
        const result = await authService.changePassword(data);
        if (result.success && result.user) {
          this.user = result.user;
        }
        return result;
      } catch (error) {
        console.error('Change password error:', error);
        return { success: false, message: 'An error occurred' };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Update profile (authenticated)
     */
    async updateProfile(data) {
      this.loading = true;
      try {
        const result = await authService.updateProfile(data);
        if (result.success && result.user) {
          this.user = result.user;
        }
        return result;
      } catch (error) {
        console.error('Update profile error:', error);
        return { success: false, message: 'An error occurred' };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Update avatar (authenticated)
     */
    async updateAvatar(file) {
      this.loading = true;
      try {
        const result = await authService.updateAvatar(file);
        if (result.success && result.user) {
          this.user = result.user;
        }
        return result;
      } catch (error) {
        console.error('Update avatar error:', error);
        return { success: false, message: 'An error occurred' };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Remove avatar (authenticated)
     */
    async removeAvatar() {
      this.loading = true;
      try {
        const result = await authService.removeAvatar();
        if (result.success && result.user) {
          this.user = result.user;
        }
        return result;
      } catch (error) {
        console.error('Remove avatar error:', error);
        return { success: false, message: 'An error occurred' };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Clear auth state (for testing or manual logout)
     */
    clearAuth() {
      this.user = null;
      this.initialized = false;
    },
  },
});

