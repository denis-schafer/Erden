<template>
    <div class="main-layout" :class="{ 'sidebar-hidden': shouldHideSidebar }">
        <Sidebar 
            v-if="hasMenuModule" 
            :class="{ 'collapsed': isSidebarCollapsed, 'show': isSidebarOpen && isMobile }"
            :is-open="isSidebarOpen"
            :is-mobile="isMobile"
            :is-collapsed="isSidebarCollapsed"
            @navigate="handleNavigate"
            @close="closeSidebar"
        />
        <div class="topbar-wrapper">
            <Topbar 
                :sidebar-collapsed="isSidebarCollapsed"
                :has-menu-module="hasMenuModule"
                @toggle-sidebar="toggleSidebar"
                @open-profile="showProfileModal = true"
            />
            <main class="main-content">
                <TabsContainer ref="tabsContainer" />
            </main>
        </div>
        <ProfileModal 
            v-if="showProfileModal" 
            @close="showProfileModal = false"
        />
        <SessionModal @logout="handleLogout" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { toast as toastify } from '../../utils/toast';
import Sidebar from './Sidebar.vue';
import Topbar from './Topbar.vue';
import TabsContainer from './TabsContainer.vue';
import ProfileModal from './ProfileModal.vue';
import SessionModal from '../SessionModal.vue';

const authStore = useAuthStore();
const tabsContainer = ref(null);

const handleLogout = () => {
    toastify.warning('Tu sesión ha sido deshabilitada. Serás redirigido al login.', 8000);
    setTimeout(() => {
        localStorage.clear();
        window.location.href = '/login';
    }, 2000);
};

const isSidebarOpen = ref(true);
const isOverlayVisible = ref(false);
const isSidebarCollapsed = ref(false);
const isMobile = ref(false);
const showProfileModal = ref(false);

// Computed: when sidebar should be hidden (for content padding)
const shouldHideSidebar = computed(() => {
    if (isMobile.value) {
        return !isSidebarOpen.value;
    }
    return isSidebarCollapsed.value;
});

const hasMenuModule = computed(() => {
    return authStore.modules.some(m => m.route === 'menu');
});

const toggleSidebar = () => {
    if (isMobile.value) {
        isSidebarOpen.value = !isSidebarOpen.value;
        isOverlayVisible.value = isSidebarOpen.value;
    } else {
        isSidebarCollapsed.value = !isSidebarCollapsed.value;
    }
};

const closeSidebar = () => {
    if (isMobile.value) {
        isSidebarOpen.value = false;
        isOverlayVisible.value = false;
    }
};

const handleNavigate = (route) => {
    const module = authStore.modules.find(m => m.route === route);
    if (module && tabsContainer.value) {
        tabsContainer.value.openTab(module);
    }
    closeSidebar();
};

const checkMobile = () => {
    isMobile.value = window.innerWidth < 992;
};

onMounted(() => {
    checkMobile();
    window.addEventListener('resize', checkMobile);
    
    window.addEventListener('pos-user-disabled', (event) => {
        const disabledUserId = event.detail.userId;
        const currentUserId = authStore.user?.id;
        
        if (currentUserId && parseInt(disabledUserId) === parseInt(currentUserId)) {
            toastify.warning('Tu sesión ha sido deshabilitada. Serás redirigido al login.', 8000);
            setTimeout(() => {
                localStorage.clear();
                window.location.href = '/login';
            }, 2000);
        }
    });

    window.addEventListener('session-expired', () => {
        toastify.warning('Tu sesión ha expirado. Serás redirigido al login.', 8000);
        setTimeout(() => {
            localStorage.clear();
            window.location.href = '/login';
        }, 2000);
    });
});

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile);
    window.removeEventListener('pos-user-disabled', () => {});
    window.removeEventListener('session-expired', () => {});
});
</script>

<style scoped>
.main-layout {
    position: relative;
    min-height: 100vh;
}

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    height: 100vh;
    background-color: #212529;
    z-index: 1000;
    transform: translateX(0);
    transition: transform 0.3s ease;
    overflow-y: auto;
}

.sidebar.collapsed {
    transform: translateX(-100%);
}

.main-layout.sidebar-hidden .topbar-wrapper {
    padding-left: 0;
}

@media (max-width: 991px) {
    .sidebar {
        transform: translateX(-100%);
    }
    .sidebar.show {
        transform: translateX(0);
    }
}

.topbar-wrapper {
    display: flex;
    flex-direction: column;
    height: 100vh;
    padding-left: 250px;
    transition: padding-left 0.3s ease;
}

.main-layout.sidebar-hidden .topbar-wrapper {
    padding-left: 0;
}

.main-content {
    flex: 1;
    height: 0;
    display: flex;
    flex-direction: column;
}
</style>