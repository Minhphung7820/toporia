import { createRouter, createWebHistory } from 'vue-router';
import Home from '../pages/Home.vue';
import About from '../pages/About.vue';
import Login from '../pages/Login.vue';
import Register from '../pages/Register.vue';
import ForgotPassword from '../pages/ForgotPassword.vue';
import ResetPassword from '../pages/ResetPassword.vue';
import ChangePassword from '../pages/ChangePassword.vue';

// Error pages
import Error403 from '../pages/errors/Error403.vue';
import Error404 from '../pages/errors/Error404.vue';
import Error500 from '../pages/errors/Error500.vue';

import { useAuthStore } from '../stores/auth';

const routes = [
    {
        path: '/',
        name: 'home',
        component: Home,
    },
    {
        path: '/about',
        name: 'about',
        component: About,
    },
    {
        path: '/login',
        name: 'login',
        component: Login,
        meta: { requiresGuest: true },
    },
    {
        path: '/register',
        name: 'register',
        component: Register,
        meta: { requiresGuest: true },
    },
    {
        path: '/forgot-password',
        name: 'forgot-password',
        component: ForgotPassword,
        meta: { requiresGuest: true },
    },
    {
        path: '/reset-password',
        name: 'reset-password',
        component: ResetPassword,
        meta: { requiresGuest: true },
    },
    {
        path: '/change-password',
        name: 'change-password',
        component: ChangePassword,
        meta: { requiresAuth: true },
    },

    // Error pages
    {
        path: '/error/403',
        name: 'error-403',
        component: Error403,
    },
    {
        path: '/error/404',
        name: 'error-404',
        component: Error404,
    },
    {
        path: '/error/500',
        name: 'error-500',
        component: Error500,
    },

    // Catch-all 404 route (must be last)
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: Error404,
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// Navigation guard
router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore();

    // Ensure auth is initialized
    if (!authStore.initialized) {
        await authStore.initialize();
    }

    // Check if route requires authentication
    if (to.meta.requiresAuth) {
        if (authStore.isAuthenticated) {
            next();
        } else {
            next({ name: 'login', query: { redirect: to.fullPath } });
        }
    }
    // Check if route requires guest (not authenticated)
    else if (to.meta.requiresGuest) {
        if (authStore.isAuthenticated) {
            next({ name: 'home' }); // Already logged in, redirect to home
        } else {
            next();
        }
    } else {
        next();
    }
});

export default router;
