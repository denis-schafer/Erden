<template>
    <div id="app" :class="{ 'portal-mode': isPortalRoute }">
        <ConfirmationDialog ref="confirmDialog" />
        <Toast />
        <QuotaPortalLayout v-if="isPortalRoute" />
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
import { ref, computed, onMounted, provide } from 'vue';
import { useAuthStore } from './stores/auth';
import Login from './components/modules/Login.vue';
import CompanySelector from './components/modules/CompanySelector.vue';
import MainLayout from './components/layout/MainLayout.vue';
import QuotaPortalLayout from './components/layout/QuotaPortalLayout.vue';
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

const handleLoginSuccess = () => {
    showCompanySelector.value = false;
};

const handleShowCompanySelector = (companyList) => {
    companies.value = companyList;
    showCompanySelector.value = true;
};

const handleCompanySelected = () => {
    showCompanySelector.value = false;
};

    const handleLogout = () => {
        showCompanySelector.value = false;
    };

    const isPortalRoute = computed(() => {
        return window.location.pathname === '/asociados';
    });

    onMounted(() => {
        if (isPortalRoute.value) return;

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
        }
    });
</script>
