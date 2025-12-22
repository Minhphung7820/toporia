/**
 * Admin API Service
 *
 * Handles all admin-related API calls.
 */

import http from '../../services/http';

/**
 * Dashboard API
 */
export const dashboard = {
  statistics() {
    return http.get('/admin/dashboard/statistics');
  },
  activity(limit = 10) {
    return http.get('/admin/dashboard/activity', { params: { limit } });
  },
  popularPosts(limit = 5) {
    return http.get('/admin/dashboard/popular-posts', { params: { limit } });
  },
  recentComments(limit = 5) {
    return http.get('/admin/dashboard/recent-comments', { params: { limit } });
  },
  charts(period = 'week') {
    return http.get('/admin/dashboard/charts', { params: { period } });
  },
  quickStats() {
    return http.get('/admin/dashboard/quick-stats');
  },
};

/**
 * Posts Admin API
 */
export const posts = {
  list(params = {}) {
    return http.get('/admin/posts', { params });
  },
  get(id) {
    return http.get(`/admin/posts/${id}`);
  },
  create(data) {
    return http.post('/admin/posts', data);
  },
  update(id, data) {
    return http.put(`/admin/posts/${id}`, data);
  },
  delete(id) {
    return http.delete(`/admin/posts/${id}`);
  },
  publish(id) {
    return http.post(`/admin/posts/${id}/publish`);
  },
  unpublish(id) {
    return http.post(`/admin/posts/${id}/unpublish`);
  },
  schedule(id, scheduledAt) {
    return http.post(`/admin/posts/${id}/schedule`, { scheduled_at: scheduledAt });
  },
  toggleFeatured(id) {
    return http.post(`/admin/posts/${id}/toggle-featured`);
  },
};

/**
 * Comments Admin API
 */
export const comments = {
  list(params = {}) {
    return http.get('/admin/comments', { params });
  },
  listCursor(params = {}) {
    return http.get('/admin/comments', { params: { ...params, pagination: 'cursor' } });
  },
  pending(params = {}) {
    return http.get('/admin/comments/pending', { params });
  },
  statistics() {
    return http.get('/admin/comments/statistics');
  },
  approve(id) {
    return http.post(`/admin/comments/${id}/approve`);
  },
  reject(id) {
    return http.post(`/admin/comments/${id}/reject`);
  },
  bulkApprove(ids) {
    return http.post('/admin/comments/bulk-approve', { ids });
  },
  bulkReject(ids) {
    return http.post('/admin/comments/bulk-reject', { ids });
  },
  bulkDelete(ids) {
    return http.post('/admin/comments/bulk-delete', { ids });
  },
  delete(id) {
    return http.delete(`/admin/comments/${id}`);
  },
};

/**
 * Categories Admin API
 */
export const categories = {
  list(params = {}) {
    return http.get('/admin/categories', { params });
  },
  selectOptions(excludeId = null) {
    return http.get('/admin/categories/select', { params: { exclude: excludeId } });
  },
  get(id) {
    return http.get(`/admin/categories/${id}`);
  },
  create(data) {
    return http.post('/admin/categories', data);
  },
  update(id, data) {
    return http.put(`/admin/categories/${id}`, data);
  },
  delete(id) {
    return http.delete(`/admin/categories/${id}`);
  },
  toggleActive(id) {
    return http.post(`/admin/categories/${id}/toggle-active`);
  },
  reorder(order) {
    return http.post('/admin/categories/reorder', { order });
  },
};

/**
 * Tags Admin API
 */
export const tags = {
  list(params = {}) {
    return http.get('/admin/tags', { params });
  },
  statistics() {
    return http.get('/admin/tags/statistics');
  },
  get(id) {
    return http.get(`/admin/tags/${id}`);
  },
  create(data) {
    return http.post('/admin/tags', data);
  },
  update(id, data) {
    return http.put(`/admin/tags/${id}`, data);
  },
  delete(id) {
    return http.delete(`/admin/tags/${id}`);
  },
  bulkDelete(ids) {
    return http.post('/admin/tags/bulk-delete', { ids });
  },
  merge(targetTagId, sourceTagIds) {
    return http.post('/admin/tags/merge', { target_tag_id: targetTagId, source_tag_ids: sourceTagIds });
  },
  cleanup() {
    return http.post('/admin/tags/cleanup');
  },
};

/**
 * Users Admin API
 */
export const users = {
  list(params = {}) {
    return http.get('/admin/users', { params });
  },
  statistics() {
    return http.get('/admin/users/statistics');
  },
  get(id) {
    return http.get(`/admin/users/${id}`);
  },
  create(data) {
    return http.post('/admin/users', data);
  },
  update(id, data) {
    return http.put(`/admin/users/${id}`, data);
  },
  delete(id) {
    return http.delete(`/admin/users/${id}`);
  },
  changeRole(id, role) {
    return http.post(`/admin/users/${id}/role`, { role });
  },
};

/**
 * Settings Admin API
 */
export const settings = {
  list() {
    return http.get('/admin/settings');
  },
  groups() {
    return http.get('/admin/settings/groups');
  },
  byGroup(group) {
    return http.get(`/admin/settings/group/${group}`);
  },
  create(data) {
    return http.post('/admin/settings', data);
  },
  update(key, value) {
    return http.put(`/admin/settings/${key}`, { value });
  },
  updateBatch(settings) {
    return http.put('/admin/settings', { settings });
  },
  delete(key) {
    return http.delete(`/admin/settings/${key}`);
  },
  reset(group = null) {
    return http.post('/admin/settings/reset', { group });
  },
};

/**
 * Feedback Admin API
 */
export const feedback = {
  list(params = {}) {
    return http.get('/admin/feedback', { params });
  },
  pending(params = {}) {
    return http.get('/admin/feedback/pending', { params });
  },
  statistics() {
    return http.get('/admin/feedback/statistics');
  },
  myAssigned(params = {}) {
    return http.get('/admin/feedback/my-assigned', { params });
  },
  get(id) {
    return http.get(`/admin/feedback/${id}`);
  },
  updateStatus(id, status) {
    return http.post(`/admin/feedback/${id}/status`, { status });
  },
  updatePriority(id, priority) {
    return http.post(`/admin/feedback/${id}/priority`, { priority });
  },
  assign(id, assigneeId) {
    return http.post(`/admin/feedback/${id}/assign`, { assignee_id: assigneeId });
  },
  addNotes(id, notes) {
    return http.post(`/admin/feedback/${id}/notes`, { notes });
  },
  delete(id) {
    return http.delete(`/admin/feedback/${id}`);
  },
  bulkDelete(ids) {
    return http.post('/admin/feedback/bulk-delete', { ids });
  },
};

export default {
  dashboard,
  posts,
  comments,
  categories,
  tags,
  users,
  settings,
  feedback,
};
