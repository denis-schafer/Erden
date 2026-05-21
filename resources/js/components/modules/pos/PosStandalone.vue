<template>
    <div class="pos-standalone">
        <PosLogin 
            v-if="!isAuthenticated"
            :company-db="companyDb"
            :company-name="companyName"
            @login-success="handleLoginSuccess"
        />
        <PosCaja 
            v-else-if="currentView === 'caja'"
            @order-created="handleOrderCreated"
        />
        <PosAdmin 
            v-else-if="currentView === 'admin'"
            @back="logout"
        />
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import PosLogin from './PosLogin.vue';
import PosCaja from './PosCaja.vue';
import PosAdmin from './PosAdmin.vue';

const props = defineProps({
    companyDb: {
        type: String,
        required: true
    },
    companyName: {
        type: String,
        default: 'POS'
    },
    initialView: {
        type: String,
        default: 'caja'
    }
});

const isAuthenticated = ref(false);
const currentView = ref(props.initialView);
const user = ref(null);

onMounted(() => {
    const posUser = localStorage.getItem('pos_user');
    if (posUser) {
        const userData = JSON.parse(posUser);
        isAuthenticated.value = true;
        user.value = userData;
        currentView.value = userData.role_name === 'admin' ? 'admin' : 'caja';
    }
});

const handleLoginSuccess = (data) => {
    user.value = data.user;
    isAuthenticated.value = true;
    
    if (data.user.role_name === 'admin') {
        currentView.value = 'admin';
    } else {
        currentView.value = 'caja';
    }
};

const handleOrderCreated = () => {
    // Order created
};

const logout = () => {
    localStorage.removeItem('pos_token');
    localStorage.removeItem('pos_user');
    isAuthenticated.value = false;
    currentView.value = 'caja';
    user.value = null;
};

onMounted(() => {
    const posUser = localStorage.getItem('pos_user');
    if (posUser) {
        const userData = JSON.parse(posUser);
        isAuthenticated.value = true;
        user.value = userData;
        currentView.value = userData.role_name === 'admin' ? 'admin' : 'caja';
    }
});
</script>

<style scoped>
.pos-standalone {
    min-height: 100vh;
    background: linear-gradient(135deg, #0a1520 0%, #152d42 50%, #1a4a6e 100%);
}
</style>
