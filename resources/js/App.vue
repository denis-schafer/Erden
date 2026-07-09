<template>
    <div id="app" :class="{ 'portal-mode': isPortalRoute, 'academy-mode': isAcademyRoute }">
        <ConfirmationDialog ref="confirmDialog" />
        <Toast />
        <QuotaOAuth v-if="isOAuthRoute" />
        <AcademyPortalLayout v-else-if="isAcademyRoute" :course-slug="academyCourseSlug" :dni="academyDni" />
        <QuotaPortalLayout v-else-if="isPortalRoute" :company-name="portalCompanyName" :dni="portalDni" />
        <template v-else>
            <Login 
                v-if="!isAuthenticated && !showCompanySelector" 
                @login-success="handleLoginSuccess"
                @show-company-selector="handleShowCompanySelector"
            />
            <CompanySelector 
                v-else-if="showCompanySelector" 
                :initial-companies="companies"
                @company-selected="handleCompanySelected"
                @logout="handleLogout"
            />
            <MainLayout v-else />
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, provide, watch } from 'vue';
import { useAuthStore } from './stores/auth';
import api from './services/api';
import Login from './components/modules/Login.vue';
import CompanySelector from './components/modules/CompanySelector.vue';
import MainLayout from './components/layout/MainLayout.vue';
import QuotaPortalLayout from './components/layout/QuotaPortalLayout.vue';
import QuotaOAuth from './components/modules/quota-admin/QuotaOAuth.vue';
import AcademyPortalLayout from './components/modules/academy/portal/AcademyPortalLayout.vue';
import ConfirmationDialog from './components/layout/ConfirmationDialog.vue';
import Toast from './components/layout/Toast.vue';

const authStore = useAuthStore();
const showCompanySelector = ref(false);
const companies = ref([]);
const confirmDialog = ref(null);

provide('confirmDialog', confirmDialog);

const isAuthenticated = computed(() => {
    return authStore.token && authStore.user;
});

const needsCompanySelection = computed(() => {
    return authStore.token && authStore.user && !authStore.company;
});

const resetScroll = () => {
    window.scrollTo(0, 0);
    document.documentElement.scrollTop = 0;
    document.body.scrollTop = 0;
    document.querySelectorAll('.tab-pane, .tabs-content, .main-content, .topbar-wrapper, .main-layout, #app').forEach(el => {
        if (el) el.scrollTop = 0;
    });
};

const handleLoginSuccess = () => {
    resetScroll();
    showCompanySelector.value = false;
    triggerChangelog();
};

const handleShowCompanySelector = (companyList) => {
    companies.value = companyList;
    showCompanySelector.value = true;
};

const handleCompanySelected = () => {
    showCompanySelector.value = false;
    triggerChangelog();
};

const triggerChangelog = () => {
    const modules = authStore.modules.map(m => m.route);
    const detected = [];
    if (modules.some(r => r.startsWith('quota-'))) detected.push('quota');
    if (modules.some(r => r.startsWith('hairsalon-'))) detected.push('hairsalon');
    if (modules.some(r => r.startsWith('pos-'))) detected.push('pos');
    if (modules.some(r => r.startsWith('academy-'))) detected.push('academy');
    const moduleIds = detected.length ? detected.join(',') : '';
    setTimeout(() => {
        window.dispatchEvent(new CustomEvent('check-changelog', { detail: { module: moduleIds } }));
    }, 600);
};

    const handleLogout = () => {
        showCompanySelector.value = false;
    };

    const isPortalRoute = computed(() => {
        return window.location.pathname === '/asociados' || window.location.pathname.startsWith('/asociados/');
    });

    const isAcademyRoute = computed(() => {
        return window.location.pathname === '/curso' || window.location.pathname.startsWith('/curso/');
    });

    const getPathParts = () => window.location.pathname.split('/').filter(Boolean);

    const portalCompanyName = computed(() => getPathParts()[1] || '');

    const portalDni = computed(() => getPathParts()[2] || '');

    const academyCourseSlug = computed(() => {
        const parts = getPathParts();
        return parts[1] || '';
    });

    const academyDni = computed(() => {
        const parts = getPathParts();
        return parts[2] || '';
    });

    const isOAuthRoute = computed(() => {
        return window.location.pathname === '/oauth';
    });

    watch(isAuthenticated, (val) => {
        if (val) {
            window.scrollTo(0, 0);
            document.querySelectorAll('.tab-pane, .tabs-content, .main-content').forEach(el => { if (el) el.scrollTop = 0; });
        }
    });

    onMounted(() => {
        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }
        window.scrollTo(0, 0);

        if (isPortalRoute.value || isAcademyRoute.value || isOAuthRoute.value) return;

        const token = localStorage.getItem('token');
        const user = localStorage.getItem('user');
        const company = localStorage.getItem('company');
        
        if (token && user && company) {
            authStore.user = JSON.parse(user);
            authStore.token = token;
            authStore.company = JSON.parse(company);
            authStore.modules = JSON.parse(localStorage.getItem('modules') || '[]');
            authStore.permissions = JSON.parse(localStorage.getItem('permissions') || '[]');
            authStore.isGlobalAdmin = localStorage.getItem('isGlobalAdmin') === 'true';
            authStore.isParentDb = localStorage.getItem('isParentDb') === 'true';
            
            // Sync fresh module order from server in background
            setTimeout(() => {
                api.get('/session').then(res => {
                    if (res.data?.modules) {
                        authStore.modules = res.data.modules;
                        localStorage.setItem('modules', JSON.stringify(res.data.modules));
                    }
                }).catch(() => {});
            }, 500);
        }
    });
</script>
