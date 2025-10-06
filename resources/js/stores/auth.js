import {defineStore} from 'pinia';
import {authService} from '../services/auth';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: authService.getUser(),
        token: authService.getToken(),
        loading: false,
        error: null
    }),

    getters: {
        isAuthenticated: (state) => !!state.token,
        currentUser: (state) => state.user
    },

    actions: {
        async login(email, password) {
            this.loading = true;
            this.error = null;

            try {
                const {user, token} = await authService.login(email, password);
                this.user = user;
                this.token = token;
                return true;
            } catch (error) {
                this.error = error.response?.data?.message || 'Login failed. Please try again.';
                console.error('Login error:', error);
                return false;
            } finally {
                this.loading = false;
            }
        },

        async logout() {
            this.loading = true;
            try {
                await authService.logout();
            } catch (error) {
                console.error('Logout error:', error);
            } finally {
                this.user = null;
                this.token = null;
                this.loading = false;
            }
        },

        async fetchUser() {
            if (!this.token) return;

            try {
                const user = await authService.whoami();
                this.user = user;
            } catch (error) {
                console.error('Failed to fetch user:', error);
                this.user = null;
                this.token = null;
            }
        },

        clearError() {
            this.error = null;
        }
    }
});
