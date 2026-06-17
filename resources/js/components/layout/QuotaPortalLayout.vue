<template>
    <div class="portal-layout" :style="layoutStyle">
        <img v-if="portalConfig.bg" :src="portalConfig.bg" class="portal-bg-img">
        <QuotaPortalLogin
            v-if="!isAuthenticated"
            :initial-company-name="companyName"
            :initial-dni="dni"
            @login-success="handleLoginSuccess"
        />
        <QuotaPartnerDashboard
            v-else
            :portal-config="portalConfig"
            @logout="handleLogout"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import QuotaPortalLogin from '../modules/quota-admin/QuotaPortalLogin.vue';
import QuotaPartnerDashboard from '../modules/quota-admin/QuotaPartnerDashboard.vue';

const props = defineProps({
    companyName: { type: String, default: '' },
    dni: { type: String, default: '' },
});

const token = ref(null);
const user = ref(null);
const companyDb = ref(null);
const portalConfig = ref({});

const isAuthenticated = computed(() => token.value && user.value);

const layoutStyle = computed(() => {
    const primary = portalConfig.value.primary_color || '#667eea';
    const secondary = portalConfig.value.secondary_color || '#764ba2';
    return {
        '--portal-primary': primary,
        '--portal-secondary': secondary,
        background: `linear-gradient(135deg, ${primary} 0%, ${secondary} 100%)`,
    };
});

const handleLoginSuccess = (data) => {
    token.value = data.token;
    user.value = data.user;
    companyDb.value = data.company_db;
    localStorage.setItem('quota_token', data.token);
    localStorage.setItem('quota_user', JSON.stringify(data.user));
    localStorage.setItem('quota_company_db', data.company_db);
    const savedConfig = localStorage.getItem('portal_config');
    if (savedConfig) portalConfig.value = JSON.parse(savedConfig);
};

const handleLogout = () => {
    token.value = null;
    user.value = null;
    companyDb.value = null;
    localStorage.removeItem('quota_token');
    localStorage.removeItem('quota_user');
    localStorage.removeItem('quota_company_db');
};

onMounted(() => {
    const savedToken = localStorage.getItem('quota_token');
    const savedUser = localStorage.getItem('quota_user');
    const savedCompanyDb = localStorage.getItem('quota_company_db');
    if (savedToken && savedUser && savedCompanyDb) {
        token.value = savedToken;
        user.value = JSON.parse(savedUser);
        companyDb.value = savedCompanyDb;
        const savedConfig = localStorage.getItem('portal_config');
        if (savedConfig) portalConfig.value = JSON.parse(savedConfig);
    }
});
</script>

<style scoped>
.portal-layout {
    position: relative;
    min-height: 100vh;
    min-height: 100dvh;
}
.portal-bg-img {
    position: fixed;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    z-index: 0;
    pointer-events: none;
}
.portal-layout > :not(.portal-bg-img) {
    position: relative;
    z-index: 1;
}
</style>
