<template>
    <div class="tabs-container">
        <div class="tabs-header">
            <div class="tabs-list">
                <div 
                    v-for="tab in tabs" 
                    :key="tab.id"
                    class="tab-item"
                    :class="{ 'active': activeTabId === tab.id, 'fixed': tab.fixed }"
                    @click="selectTab(tab.id)"
                >
                    <span class="tab-title">
                        {{ tab.title }}
                        <button 
                            v-if="tab.id === 'pos-caja' || tab.id === 'pos-admin' || tab.id === 'pos-qr'" 
                            class="tab-maximize"
                            @click.stop="openCajaFullscreen"
                            title="Pantalla completa"
                        >
                            <i class="bi bi-arrows-fullscreen"></i>
                        </button>
                    </span>
                    <button 
                        v-if="!tab.fixed" 
                        class="tab-close" 
                        @click.stop="closeTab(tab.id)"
                    >
                        ×
                    </button>
                </div>
            </div>
        </div>
        <div class="tabs-content">
            <div 
                v-for="tab in tabs" 
                :key="tab.id"
                v-show="activeTabId === tab.id"
                class="tab-pane"
            >
            <component :is="getComponent(tab.id)" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, markRaw, onMounted } from 'vue';
import { useAuthStore } from '../../stores/auth';
import Dashboard from '../modules/Dashboard.vue';
import UsersIndex from '../modules/users/Index.vue';
import RolesIndex from '../modules/roles/Index.vue';
import ModulesIndex from '../modules/admin/ModulesIndex.vue';
import CompaniesIndex from '../modules/admin/CompaniesIndex.vue';
import GlobalConfig from '../modules/admin/GlobalConfig.vue';
import PosCaja from '../modules/pos/PosCaja.vue';
import PosCategories from '../modules/pos/PosCategories.vue';
import PosProducts from '../modules/pos/PosProducts.vue';
import PosOrders from '../modules/pos/PosOrders.vue';
import PosUsers from '../modules/pos/PosUsers.vue';
import PosConfig from '../modules/pos/PosConfig.vue';
import PosDashboard from '../modules/pos/PosDashboard.vue';
import PosQR from '../modules/pos/PosQR.vue';
import PosStatistics from '../modules/pos/PosStatistics.vue';
import PosLog from '../modules/pos/PosLog.vue';

const authStore = useAuthStore();

onMounted(() => {
    window.addEventListener('close-pos-qr-module', () => {
        closeTab('pos-qr');
    });
});

const componentMap = {
    'users': markRaw(UsersIndex),
    'roles': markRaw(RolesIndex),
    'admin-modules': markRaw(ModulesIndex),
    'admin-companies': markRaw(CompaniesIndex),
    'config': markRaw(GlobalConfig),
    'dashboard': markRaw(Dashboard),
    'menu': markRaw(Dashboard),
    'pos-dashboard': markRaw(PosDashboard),
    'pos-caja': markRaw(PosCaja),
    'pos-categories': markRaw(PosCategories),
    'pos-products': markRaw(PosProducts),
    'pos-orders': markRaw(PosOrders),
    'pos-users': markRaw(PosUsers),
    'pos-config': markRaw(PosConfig),
    'pos-qr': markRaw(PosQR),
    'pos-statistics': markRaw(PosStatistics),
    'pos-log': markRaw(PosLog)
};

const tabs = ref([
    {
        id: 'dashboard',
        title: 'Dashboard',
        component: markRaw(Dashboard),
        fixed: true
    }
]);

const activeTabId = ref('dashboard');

const getComponent = (tabId) => {
    const tab = tabs.value.find(t => t.id === tabId);
    if (tab && tab.component) {
        if (typeof tab.component === 'string') {
            return componentMap[tab.component] || Dashboard;
        }
        return tab.component;
    }
    return Dashboard;
};

const initPosDashboard = () => {
    if (authStore.modules && authStore.modules.length > 0) {
        const posDashModule = authStore.modules.find(m => m.route === 'pos-dashboard');
        if (posDashModule) {
            tabs.value[0] = {
                id: 'pos-dashboard',
                title: 'Dashboard',
                component: markRaw(PosDashboard),
                fixed: true
            };
            activeTabId.value = 'pos-dashboard';
        }
    }
};

setTimeout(initPosDashboard, 200);

const selectTab = (tabId) => {
    activeTabId.value = tabId;
    window.dispatchEvent(new CustomEvent('view-changed', { detail: tabId }));
};

const openTab = (module) => {
    const existingTab = tabs.value.find(t => t.id === module.route);
    
    if (existingTab) {
        selectTab(module.route);
    } else {
        let comp = componentMap[module.route];
        
        if (!comp) {
            console.warn('Component not found for route:', module.route, 'Using Dashboard');
            comp = markRaw(Dashboard);
        }
        
        tabs.value.push({
            id: module.route,
            title: module.name,
            component: comp,
            fixed: false
        });
        
        selectTab(module.route);
    }
};

const closeTab = (tabId) => {
    const index = tabs.value.findIndex(t => t.id === tabId);
    if (index > -1 && !tabs.value[index].fixed) {
        tabs.value.splice(index, 1);
        
        if (activeTabId.value === tabId) {
            const newActive = tabs.value[tabs.value.length - 1];
            activeTabId.value = newActive.id;
            window.dispatchEvent(new CustomEvent('view-changed', { detail: newActive.id }));
        }
    }
};

const openCajaFullscreen = () => {
    window.dispatchEvent(new CustomEvent('open-pos-fullscreen'));
};

window.openTab = openTab;

defineExpose({ openTab, selectTab });
</script>

<style scoped>
.tabs-container {
    display: flex;
    flex-direction: column;
    height: 100%;
    width: 100%;
    overflow: hidden;
}

.tabs-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 0 0.5rem;
    flex-shrink: 0;
    min-height: 44px;
}

.tabs-list {
    display: flex;
    flex-wrap: wrap;
}

.tab-item {
    display: flex;
    align-items: center;
    padding: 0.5rem 1rem;
    background-color: transparent;
    border: 1px solid transparent;
    border-bottom: none;
    cursor: pointer;
    font-size: 0.875rem;
    color: #6c757d;
    transition: all 0.2s;
    margin-bottom: -1px;
    border-radius: 0.25rem 0.25rem 0 0;
}

.tab-item:hover {
    background-color: #e9ecef;
}

.tab-item.active {
    background-color: white;
    border-color: #dee2e6;
    color: #212529;
}

.tab-title {
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.tab-maximize {
    background: none;
    border: none;
    color: #6c757d;
    padding: 0 0.25rem;
    cursor: pointer;
    font-size: 0.875rem;
    line-height: 1;
}

.tab-maximize:hover {
    color: #0d6efd;
}

.tab-close {
    background: none;
    border: none;
    color: #6c757d;
    font-size: 1rem;
    line-height: 1;
    padding: 0 0 0 0.5rem;
    cursor: pointer;
    opacity: 0.5;
}

.tab-close:hover {
    opacity: 1;
    color: #dc3545;
}

.tabs-content {
    flex: 1;
    min-height: 0;
    background-color: white;
    padding: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.tab-pane {
    flex: 1;
    min-height: 0;
    height: 100%;
    overflow: hidden;
}
</style>
