<template>
    <div class="portal-layout">
        <QuotaPortalLogin
            v-if="!isAuthenticated"
            @login-success="handleLoginSuccess"
        />
        <QuotaPartnerDashboard
            v-else
            @logout="handleLogout"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import QuotaPortalLogin from '../modules/quota-admin/QuotaPortalLogin.vue';
import QuotaPartnerDashboard from '../modules/quota-admin/QuotaPartnerDashboard.vue';

const token = ref(null);
const user = ref(null);
const companyDb = ref(null);

const isAuthenticated = computed(() => token.value && user.value);

const handleLoginSuccess = (data) => {
    token.value = data.token;
    user.value = data.user;
    companyDb.value = data.company_db;
    localStorage.setItem('quota_token', data.token);
    localStorage.setItem('quota_user', JSON.stringify(data.user));
    localStorage.setItem('quota_company_db', data.company_db);
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
    }
});
</script>

<style scoped>
.portal-layout {
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
</style>
