import {createRouter, createWebHistory} from 'vue-router';
import LoginForm from '../components/LoginForm.vue';
import Dashboard from '../components/Dashboard.vue';
import {useAuthStore} from '../stores/auth';

const routes = [
    {
        path: '/',
        redirect: '/login'
    },
    {
        path: '/login',
        name: 'Login',
        component: LoginForm,
        meta: {guest: true}
    },
    {
        path: '/dashboard',
        name: 'Dashboard',
        component: Dashboard,
        meta: {requiresAuth: true}
    }
];

const router = createRouter({
    history: createWebHistory(),
    routes
});

// Navigation guards
router.beforeEach((to, from, next) => {
    const authStore = useAuthStore();

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        // Redirect to login if trying to access protected route without auth
        next('/login');
    } else if (to.meta.guest && authStore.isAuthenticated) {
        // Redirect to dashboard if trying to access guest route while authenticated
        next('/dashboard');
    } else {
        next();
    }
});

export default router;
