<template>
    <header class="topbar">
        <div style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 0 0.5rem;">
            <div class="d-flex align-items-center">
                <button 
                    v-if="hasMenuModule" 
                    class="hamburger-btn me-2" 
                    @click="$emit('toggle-sidebar')"
                >
                    <i class="bi bi-list"></i>
                </button>
                <h6 class="mb-0 topbar-title">{{ currentPageTitle }}</h6>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="text-end topbar-user-info">
                    <div class="fw-semibold">{{ authStore.user?.name || authStore.user?.username }}</div>
                    <small class="text-muted">{{ authStore.company?.name }}</small>
                </div>
                <div class="dropdown">
                    <button class="btn btn-link p-0 text-decoration-none" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle fs-4 text-dark"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <button class="dropdown-item" @click="$emit('open-profile')">
                                <i class="bi bi-person me-2"></i>Perfil
                            </button>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button class="dropdown-item text-danger" @click="handleLogout">
                                <i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </header>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '../../stores/auth';

const props = defineProps({
    hasMenuModule: Boolean,
    sidebarCollapsed: Boolean
});

const emit = defineEmits(['toggle-sidebar', 'open-profile']);

const authStore = useAuthStore();

const currentPage = ref('dashboard');

const titles = {
    'dashboard': 'Dashboard',
    'users': 'Usuarios',
    'roles': 'Roles',
    'admin-modules': 'Admin Módulos'
};

const currentPageTitle = ref('Erden');

const updateTitle = () => {
    currentPageTitle.value = titles[currentPage.value] || 'Erden';
};

const handleViewChanged = (e) => {
    currentPage.value = e.detail;
    updateTitle();
};

onMounted(() => {
    updateTitle();
    window.addEventListener('view-changed', handleViewChanged);
});

onUnmounted(() => {
    window.removeEventListener('view-changed', handleViewChanged);
});

const handleLogout = async () => {
    authStore.clearAuth();
    window.location.reload();
};
</script>

<style scoped>
.topbar {
    height: 56px;
    display: flex;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 100;
    background: white;
    border-bottom: 1px solid #dee2e6;
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}

.hamburger-btn {
    display: inline-block;
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #212529;
    cursor: pointer;
    padding: 0.25rem;
    margin-right: 0.5rem;
}

.dropdown-toggle::after {
    display: none;
}
</style>