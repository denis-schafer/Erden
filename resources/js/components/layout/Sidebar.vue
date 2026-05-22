<template>
    <aside class="sidebar" :class="{ 'show': isVisible, 'collapsed': isCollapsed }" v-show="isVisible">
        <div class="sidebar-header p-3 border-bottom border-secondary d-flex justify-content-between align-items-center">
            <div style="width: 120px;" v-html="sidebarLogoSvg"></div>
            <button 
                v-if="isMobile" 
                class="btn-close btn-close-white" 
                @click="$emit('close')"
                aria-label="Close"
            ></button>
        </div>
        <div v-if="testModeEnabled" class="test-mode-banner">
            <i class="bi bi-bug-fill me-1"></i>
            MODO TEST
        </div>
        <nav class="sidebar-nav">
            <template v-for="module in visibleModules" :key="module.id">
                <div 
                    v-if="!module.children || module.children.length === 0"
                    class="nav-link"
                    :class="{ active: currentView === module.route }"
                    @click="$emit('navigate', module.route)"
                >
                    <span v-if="module.icon" class="me-2"><i :class="module.icon"></i></span>
                    {{ module.name }}
                </div>
                <div v-else class="nav-group">
                    <div class="nav-link" @click="toggleGroup(module.id)">
                        <span v-if="module.icon" class="me-2"><i :class="module.icon"></i></span>
                        {{ module.name }}
                        <span class="float-end">{{ expandedGroups.includes(module.id) ? '▼' : '▶' }}</span>
                    </div>
                    <div v-if="expandedGroups.includes(module.id)" class="nav-group-items">
                        <div 
                            v-for="child in module.children" 
                            :key="child.id"
                            class="nav-link ps-4"
                            :class="{ active: currentView === child.route }"
                            @click="$emit('navigate', child.route)"
                        >
                            {{ child.name }}
                        </div>
                    </div>
                </div>
            </template>
        </nav>
    </aside>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '../../stores/auth';
import api from '../../services/api';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false
    },
    isMobile: {
        type: Boolean,
        default: false
    },
    isCollapsed: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['navigate', 'close']);

const authStore = useAuthStore();
const expandedGroups = ref([]);
const sidebarLogoSvg = ref('');
const testModeEnabled = ref(false);

const loadTestModeStatus = async () => {
    try {
        const response = await api.get('/pos/test-mode/status');
        testModeEnabled.value = response.data?.enabled || false;
    } catch {
        testModeEnabled.value = false;
    }
};

const loadSidebarLogo = async () => {
    try {
        const response = await fetch('/img/logo.svg');
        const text = await response.text();
        sidebarLogoSvg.value = text;
    } catch (e) {
        console.error('Error loading sidebar logo:', e);
    }
};

onMounted(() => {
    loadSidebarLogo();
    loadTestModeStatus();
});

const isVisible = computed(() => {
    if (!hasMenuModule.value) return false;
    if (props.isMobile) return props.isOpen;
    return true; // Desktop: always show (can be collapsed)
});

const currentView = ref('dashboard');

window.addEventListener('view-changed', (e) => {
    currentView.value = e.detail;
});

const hasMenuModule = computed(() => {
    return authStore.modules.some(m => m.route === 'menu');
});

const visibleModules = computed(() => {
    if (!hasMenuModule.value) {
        return [];
    }
    
    let modules = authStore.modules.filter(m => m.route !== 'menu');
    
    // Filter out admin modules and POS module for child DBs only
    if (!authStore.isParentDb) {
        modules = modules.filter(m => 
            m.route !== 'admin-modules' && 
            m.route !== 'admin-companies' &&
            m.route !== 'pos-admin' &&
            m.route !== 'pos' &&
            m.route !== 'dashboard' && // Filter global dashboard for POS users
            m.route !== 'users' // Filter global users for POS users
        );
        
        // Rename "Usuarios POS" to "Usuarios" for cleaner UI
        modules = modules.map(m => {
            if (m.route === 'pos-users') {
                return { ...m, name: 'Usuarios', route: 'pos-users' };
            }
            return m;
        });
        
        // Filter QR module - only show if user has mercadopago_qr_enabled
        const userHasMercadoQr = authStore.user?.mercadopago_qr_enabled;
        if (userHasMercadoQr !== true && userHasMercadoQr !== 1 && userHasMercadoQr !== '1') {
            modules = modules.filter(m => m.route !== 'pos-qr');
        }
    }
    
    // Count POS permissions - more than 4 means full access (admin)
    const posPermissions = authStore.permissions.filter(p => p.startsWith('pos-')).length;
    
    // If user has pos-users_read or pos-config_read = admin (manage users/config)
    const hasFullPosAccess = authStore.permissions.includes('pos-users_read') || 
                          authStore.permissions.includes('pos-config_read');
    
    // Admin can see all modules, Cashier only sees basic POS modules
    if (authStore.isGlobalAdmin || hasFullPosAccess) {
        return modules;
    }
    
    // Non-admin: show modules based on explicit read permissions
    return modules.filter(m => {
        if (m.route.startsWith('pos-')) {
            const perm = `${m.route}_read`;
            return authStore.permissions.includes(perm);
        }
        const permission = `${m.route}_read`;
        return authStore.permissions.includes(permission);
    });
});

const toggleGroup = (id) => {
    const index = expandedGroups.value.indexOf(id);
    if (index === -1) {
        expandedGroups.value.push(id);
    } else {
        expandedGroups.value.splice(index, 1);
    }
};

// Listen for user settings updates via WebSocket
onMounted(() => {
    window.addEventListener('pos-user-settings-updated', (event) => {
        const data = event.detail;
        const currentUserId = authStore.user?.id;
        
        // Only update if it's our own user
        if (data.id === currentUserId && data.mercadopago_qr_enabled !== undefined) {
            authStore.updateUser({
                mercadopago_qr_enabled: data.mercadopago_qr_enabled
            });
            
            // If QR was disabled, close the QR tab if open
            if (!data.mercadopago_qr_enabled) {
                window.dispatchEvent(new CustomEvent('close-pos-qr-module'));
            }
        }
    });

    // Listen for test mode changes via WebSocket
    if (window.Echo) {
        window.Echo.channel('configs')
            .listen('.ConfigUpdated', (data) => {
                if (data?.name === 'test_mode') {
                    testModeEnabled.value = data.value === '1';
                }
            });
    }
});


</script>

<style scoped>
.sidebar {
    width: 250px;
    background-color: #212529;
    height: 100%;
    overflow-y: auto;
    flex-shrink: 0;
    transition: width 0.3s ease;
}

.sidebar.collapsed {
    width: 0;
    overflow: hidden;
}

.sidebar.show {
    transform: translateX(0);
}

.sidebar-header {
    background-color: #1a1d20;
}

.nav-link {
    color: rgba(255,255,255,0.8);
    padding: 0.75rem 1rem;
    display: block;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s;
}

.nav-link:hover {
    color: white;
    background-color: rgba(255,255,255,0.1);
}

.nav-link.active {
    background-color: rgba(255,255,255,0.15);
    color: white;
}

.nav-group-items .nav-link {
    padding-left: 2rem;
    font-size: 0.9rem;
}

.nav-group .nav-link {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.test-mode-banner {
    background-color: #ffc107;
    color: #212529;
    text-align: center;
    padding: 4px 8px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
}

@media (min-width: 992px) {
    .sidebar {
        transform: translateX(0);
    }
}
</style>