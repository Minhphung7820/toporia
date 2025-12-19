import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../../stores/auth';

// Lazy load admin pages for better performance
const Dashboard = () => import('../pages/Dashboard.vue');
const Profile = () => import('../pages/Profile.vue');
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
  // Routes accessible by both admin and moderator
  {
    path: '/admin',
    name: 'admin-dashboard',
    component: Dashboard,
    meta: { title: 'Dashboard', roles: ['admin', 'moderator'] },
  },

  // Profile - accessible by both admin and moderator
  {
    path: '/admin/profile',
    name: 'admin-profile',
    component: Profile,
    meta: { title: 'My Profile', roles: ['admin', 'moderator'] },
  },

  // Posts - accessible by both admin and moderator
  {
    path: '/admin/posts',
    name: 'admin-posts',
    component: PostsIndex,
    meta: { title: 'Posts', roles: ['admin', 'moderator'] },
  },
  {
    path: '/admin/posts/create',
    name: 'admin-posts-create',
    component: PostsCreate,
    meta: { title: 'Create Post', roles: ['admin', 'moderator'] },
  },
  {
    path: '/admin/posts/:id/edit',
    name: 'admin-posts-edit',
    component: PostsEdit,
    meta: { title: 'Edit Post', roles: ['admin', 'moderator'] },
  },

  // Comments - accessible by both admin and moderator
  {
    path: '/admin/comments',
    name: 'admin-comments',
    component: CommentsIndex,
    meta: { title: 'Comments', roles: ['admin', 'moderator'] },
  },
  {
    path: '/admin/comments/pending',
    name: 'admin-comments-pending',
    component: CommentsPending,
    meta: { title: 'Pending Comments', roles: ['admin', 'moderator'] },
  },

  // Categories - Admin only
  {
    path: '/admin/categories',
    name: 'admin-categories',
    component: CategoriesIndex,
    meta: { title: 'Categories', roles: ['admin'] },
  },
  {
    path: '/admin/categories/create',
    name: 'admin-categories-create',
    component: CategoriesForm,
    meta: { title: 'Create Category', roles: ['admin'] },
  },
  {
    path: '/admin/categories/:id/edit',
    name: 'admin-categories-edit',
    component: CategoriesForm,
    meta: { title: 'Edit Category', roles: ['admin'] },
  },

  // Tags - Admin only
  {
    path: '/admin/tags',
    name: 'admin-tags',
    component: TagsIndex,
    meta: { title: 'Tags', roles: ['admin'] },
  },

  // Users - Admin only
  {
    path: '/admin/users',
    name: 'admin-users',
    component: UsersIndex,
    meta: { title: 'Users', roles: ['admin'] },
  },
  {
    path: '/admin/users/create',
    name: 'admin-users-create',
    component: UsersForm,
    meta: { title: 'Create User', roles: ['admin'] },
  },
  {
    path: '/admin/users/:id/edit',
    name: 'admin-users-edit',
    component: UsersForm,
    meta: { title: 'Edit User', roles: ['admin'] },
  },

  // Settings - Admin only
  {
    path: '/admin/settings',
    name: 'admin-settings',
    component: SettingsIndex,
    meta: { title: 'Settings', roles: ['admin'] },
  },

  // Feedback - Admin only
  {
    path: '/admin/feedback',
    name: 'admin-feedback',
    component: FeedbackIndex,
    meta: { title: 'Feedback', roles: ['admin'] },
  },
  {
    path: '/admin/feedback/:id',
    name: 'admin-feedback-show',
    component: FeedbackShow,
    meta: { title: 'Feedback Details', roles: ['admin'] },
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

  // Check route-specific role requirements
  const allowedRoles = to.meta.roles || ['admin', 'moderator'];
  const userRole = authStore.userRole;

  if (!allowedRoles.includes(userRole)) {
    // User doesn't have permission for this specific route
    // Redirect moderator to dashboard instead of 403 (better UX)
    if (authStore.isModerator) {
      next({ name: 'admin-dashboard' });
      return;
    }
    window.location.href = '/error/403';
    return;
  }

  next();
});

// Export auth check status for use in components
export const isAuthChecked = () => authChecked;

export default router;
