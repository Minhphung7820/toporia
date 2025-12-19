import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../../stores/auth';

// Lazy load admin pages for better performance
const Dashboard = () => import('../pages/Dashboard.vue');
const PostsIndex = () => import('../pages/posts/Index.vue');
const PostsCreate = () => import('../pages/posts/Create.vue');
const PostsEdit = () => import('../pages/posts/Edit.vue');
const CommentsIndex = () => import('../pages/comments/Index.vue');
const CommentsPending = () => import('../pages/comments/Pending.vue');
const CategoriesIndex = () => import('../pages/categories/Index.vue');
const CategoriesForm = () => import('../pages/categories/Form.vue');
const TagsIndex = () => import('../pages/tags/Index.vue');
const UsersIndex = () => import('../pages/users/Index.vue');
const UsersForm = () => import('../pages/users/Form.vue');
const SettingsIndex = () => import('../pages/settings/Index.vue');
const FeedbackIndex = () => import('../pages/feedback/Index.vue');
const FeedbackShow = () => import('../pages/feedback/Show.vue');

const routes = [
  {
    path: '/admin',
    name: 'admin-dashboard',
    component: Dashboard,
    meta: { title: 'Dashboard' },
  },

  // Posts
  {
    path: '/admin/posts',
    name: 'admin-posts',
    component: PostsIndex,
    meta: { title: 'Posts' },
  },
  {
    path: '/admin/posts/create',
    name: 'admin-posts-create',
    component: PostsCreate,
    meta: { title: 'Create Post' },
  },
  {
    path: '/admin/posts/:id/edit',
    name: 'admin-posts-edit',
    component: PostsEdit,
    meta: { title: 'Edit Post' },
  },

  // Comments
  {
    path: '/admin/comments',
    name: 'admin-comments',
    component: CommentsIndex,
    meta: { title: 'Comments' },
  },
  {
    path: '/admin/comments/pending',
    name: 'admin-comments-pending',
    component: CommentsPending,
    meta: { title: 'Pending Comments' },
  },

  // Categories
  {
    path: '/admin/categories',
    name: 'admin-categories',
    component: CategoriesIndex,
    meta: { title: 'Categories' },
  },
  {
    path: '/admin/categories/create',
    name: 'admin-categories-create',
    component: CategoriesForm,
    meta: { title: 'Create Category' },
  },
  {
    path: '/admin/categories/:id/edit',
    name: 'admin-categories-edit',
    component: CategoriesForm,
    meta: { title: 'Edit Category' },
  },

  // Tags
  {
    path: '/admin/tags',
    name: 'admin-tags',
    component: TagsIndex,
    meta: { title: 'Tags' },
  },

  // Users
  {
    path: '/admin/users',
    name: 'admin-users',
    component: UsersIndex,
    meta: { title: 'Users' },
  },
  {
    path: '/admin/users/create',
    name: 'admin-users-create',
    component: UsersForm,
    meta: { title: 'Create User' },
  },
  {
    path: '/admin/users/:id/edit',
    name: 'admin-users-edit',
    component: UsersForm,
    meta: { title: 'Edit User' },
  },

  // Settings
  {
    path: '/admin/settings',
    name: 'admin-settings',
    component: SettingsIndex,
    meta: { title: 'Settings' },
  },

  // Feedback
  {
    path: '/admin/feedback',
    name: 'admin-feedback',
    component: FeedbackIndex,
    meta: { title: 'Feedback' },
  },
  {
    path: '/admin/feedback/:id',
    name: 'admin-feedback-show',
    component: FeedbackShow,
    meta: { title: 'Feedback Details' },
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

// Auth check flag - prevents rendering admin UI before auth check completes
let authChecked = false;

// Navigation guard - check auth AND role BEFORE rendering any admin page
router.beforeEach(async (to, from, next) => {
  // Update page title
  document.title = to.meta.title ? `${to.meta.title} - Admin` : 'Admin';

  const authStore = useAuthStore();

  // Initialize auth if not already done
  if (!authStore.initialized) {
    await authStore.initialize();
  }

  authChecked = true;

  // Check authentication - redirect to login
  if (!authStore.isAuthenticated) {
    window.location.href = `/login?redirect=${encodeURIComponent(to.fullPath)}`;
    return;
  }

  // Check admin role (admin or editor) - redirect to 403 error page
  if (!authStore.hasAdminAccess) {
    window.location.href = '/error/403';
    return;
  }

  next();
});

// Export auth check status for use in components
export const isAuthChecked = () => authChecked;

export default router;
