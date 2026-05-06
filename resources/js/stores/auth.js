import { defineStore } from 'pinia';
import api from '../services/api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: JSON.parse(localStorage.getItem('user') || 'null'),
        permissions: JSON.parse(localStorage.getItem('permissions') || '[]'),
        modules: JSON.parse(localStorage.getItem('modules') || '[]'),
        company: JSON.parse(localStorage.getItem('company') || 'null'),
        token: localStorage.getItem('token') || null,
        isGlobalAdmin: localStorage.getItem('isGlobalAdmin') === 'true',
        isParentDb: localStorage.getItem('isParentDb') === 'true',
        companyDb: localStorage.getItem('companyDb') || null
    }),

    getters: {
        isAuthenticated: (state) => !!state.token,
        hasPermission: (state) => (permission) => {
            if (state.isGlobalAdmin) return true;
            return state.permissions.includes(permission);
        },
        hasModule: (state) => (route) => {
            const permission = `${route}_read`;
            if (state.isGlobalAdmin) return true;
            return state.permissions.includes(permission);
        }
    },

    actions: {
        async login(credentials) {
            const response = await api.post('/login', credentials);
            return response.data;
        },

        async selectCompany(companyId) {
            const response = await api.post('/select-company', { company_id: companyId });
            this.setAuth(response.data);
            return response.data;
        },

        async logout() {
            await api.post('/logout');
            this.clearAuth();
        },

        setAuth(data) {
            this.user = data.user;
            this.permissions = data.permissions || [];
            this.modules = data.modules || [];
            this.company = data.company || null;
            this.token = data.token;
            this.isGlobalAdmin = data.is_global_admin || false;
            this.isParentDb = data.is_parent_db || false;
            
            const companyDb = data.company?.db || null;

            localStorage.setItem('user', JSON.stringify(this.user));
            localStorage.setItem('permissions', JSON.stringify(this.permissions));
            localStorage.setItem('modules', JSON.stringify(this.modules));
            localStorage.setItem('company', JSON.stringify(this.company));
            localStorage.setItem('token', this.token);
            localStorage.setItem('isGlobalAdmin', this.isGlobalAdmin.toString());
            localStorage.setItem('isParentDb', this.isParentDb.toString());
            localStorage.setItem('companyDb', companyDb);
            
            const sessionLifetime = 1440;
            const expiryTime = Date.now() + (sessionLifetime * 60 * 1000);
            localStorage.setItem('session_expiry', expiryTime.toString());
        },

        clearAuth() {
            this.user = null;
            this.permissions = [];
            this.modules = [];
            this.company = null;
            this.token = null;
            this.isGlobalAdmin = false;
            this.isParentDb = false;
            this.companyDb = null;

            localStorage.removeItem('user');
            localStorage.removeItem('permissions');
            localStorage.removeItem('modules');
            localStorage.removeItem('company');
            localStorage.removeItem('token');
            localStorage.removeItem('isGlobalAdmin');
            localStorage.removeItem('isParentDb');
            localStorage.removeItem('companyDb');
            localStorage.removeItem('session_expiry');
        },
        
        updateUser(updates) {
            this.user = { ...this.user, ...updates };
            localStorage.setItem('user', JSON.stringify(this.user));
        },

        async fetchSession() {
            try {
                const response = await api.get('/session');
                if (response.data.user) {
                    this.setAuth(response.data);
                }
                return response.data;
            } catch (error) {
                this.clearAuth();
                throw error;
            }
        }
    }
});