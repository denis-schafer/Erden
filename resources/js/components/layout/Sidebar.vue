<template>
    <aside class="sidebar" :class="{ 'show': isVisible, 'collapsed': isCollapsed }" v-show="isVisible">
        <div class="sidebar-header p-3 border-bottom border-secondary d-flex justify-content-between align-items-center">
            <div style="width: 120px;" v-html="sidebarLogoSvg"></div>
            <button v-if="isMobile" class="btn-close btn-close-white" @click="$emit('close')" aria-label="Close"></button>
        </div>
        <div v-if="testModeEnabled" class="test-mode-banner"><i class="bi bi-bug-fill me-1"></i> MODO TEST</div>
        <nav class="sidebar-nav" ref="sidebarNavRef">
            <div v-for="(module, idx) in visibleModules" :key="module.id"
                class="sidebar-item-wrapper"
                :class="{ 'drag-over-top': dragOverIndex === idx, 'drag-over-bottom': dragOverIndex === idx + 1 }"
                :data-index="idx"
                @dragover.prevent="onDragOver($event, idx)"
                @dragleave="onDragLeave"
                @drop.prevent="onDrop($event, idx)">
                <div v-if="!module.children || module.children.length === 0"
                    class="nav-link"
                    :class="{ active: currentView === module.route, 'dragging': dragIndex === idx }"
                    draggable="true"
                    @dragstart="onDragStart(idx)"
                    @dragend="onDragEnd"
                    @click="$emit('navigate', module.route)"
                >
                    <span v-if="module.icon" class="me-2"><i :class="module.icon"></i></span>
                    <span class="flex-grow-1">{{ module.name }}</span>
                    <span v-if="dragEnabled && dragIndex !== idx" class="drag-handle"><i class="bi bi-grip-vertical"></i></span>
                </div>
                <div v-else class="nav-group">
                    <div class="nav-link" @click="toggleGroup(module.id)">
                        <span v-if="module.icon" class="me-2"><i :class="module.icon"></i></span>
                        {{ module.name }}
                        <span class="float-end">{{ expandedGroups.includes(module.id) ? '▼' : '▶' }}</span>
                    </div>
                    <div v-if="expandedGroups.includes(module.id)" class="nav-group-items">
                        <div v-for="child in module.children" :key="child.id"
                            class="nav-link ps-4"
                            :class="{ active: currentView === child.route }"
                            @click="$emit('navigate', child.route)"
                        >{{ child.name }}</div>
                    </div>
                </div>
            </div>
        </nav>
    </aside>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useAuthStore } from '../../stores/auth';
import api from '../../services/api';

const props = defineProps({
    isOpen: { type: Boolean, default: false },
    isMobile: { type: Boolean, default: false },
    isCollapsed: { type: Boolean, default: false }
});

const emit = defineEmits(['navigate', 'close']);
const authStore = useAuthStore();
const expandedGroups = ref([]);
const sidebarLogoSvg = ref('');
const testModeEnabled = ref(false);
const dragEnabled = ref(false);
const sidebarNavRef = ref(null);

// Drag state
const dragIndex = ref(null);
const dragOverIndex = ref(null);

const loadTestModeStatus = async () => {
    try { const r = await api.get('/pos/test-mode/status'); testModeEnabled.value = r.data?.enabled || false; } catch { testModeEnabled.value = false; }
};

const loadSidebarLogo = async () => {
    try { const r = await fetch('/img/logo.svg'); sidebarLogoSvg.value = await r.text(); } catch (e) { console.error('Error loading sidebar logo:', e); }
};

const hexToRgbStr = (hex) => {
    const m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return m ? `${parseInt(m[1],16)}, ${parseInt(m[2],16)}, ${parseInt(m[3],16)}` : '255, 255, 255';
};

const themeConfig = { bg: '#212529', accent: '#6c757d' };

let themeStyleEl = null;

const applyNavStyles = () => {
    const nav = sidebarNavRef.value;
    if (!nav) return;
    const bg = themeConfig.bg;
    const accent = themeConfig.accent;
    const ar = hexToRgbStr(accent);
    // Inject a dynamic style tag that uses the configured colors with !important
    if (!themeStyleEl) {
        themeStyleEl = document.createElement('style');
        themeStyleEl.id = 'sidebar-dynamic-theme';
        document.head.appendChild(themeStyleEl);
    }
    themeStyleEl.textContent = `
        .sidebar { background-color: ${bg} !important; }
        .sidebar-header { background-color: ${bg} !important; filter: brightness(0.85) !important; }
        .sidebar-nav .nav-link { color: rgba(255,255,255,0.85) !important; background-color: rgba(0,0,0,0.12) !important; }
        .sidebar-nav .nav-link:hover { background-color: rgba(${ar}, 0.25) !important; color: white !important; }
        .sidebar-nav .nav-link.active { background-color: rgba(${ar}, 0.35) !important; color: white !important; border-left: 3px solid ${accent} !important; }
    `;
};

const applyThemeFromConfigs = (configs) => {
    dragEnabled.value = (configs.find(c => c.name === 'sidebar_drag_drop')?.value === '1');
    themeConfig.bg = configs.find(c => c.name === 'primary_color')?.value || '#212529';
    themeConfig.accent = configs.find(c => c.name === 'secondary_color')?.value || '#6c757d';
    document.documentElement.style.setProperty('--sidebar-bg', themeConfig.bg);
    document.documentElement.style.setProperty('--sidebar-accent', themeConfig.accent);
    document.documentElement.style.setProperty('--bs-primary', themeConfig.accent);
    const ar = hexToRgbStr(themeConfig.accent);
    document.documentElement.style.setProperty('--bs-primary-rgb', ar);
    
    const logo = configs.find(c => c.name === 'logo')?.value;
    if (logo) sidebarLogoSvg.value = `<img src="${logo}" alt="Logo" style="max-width:120px;max-height:40px;">`;
    else loadSidebarLogo();
    
    const bgImage = configs.find(c => c.name === 'background_image')?.value;
    if (bgImage) document.documentElement.style.setProperty('--bg-image', `url(${bgImage})`);
    else document.documentElement.style.removeProperty('--bg-image');
    
    applyNavStyles();
};

const getModuleFromView = (view) => {
    if (view.startsWith('hairsalon')) return 'hairsalon';
    if (view.startsWith('quota')) return 'quota';
    if (view.startsWith('pos')) return 'pos';
    return null;
};

const endpointForModule = (mod) => {
    if (mod === 'hairsalon') return '/hairsalon/config';
    if (mod === 'quota') return '/quota/config';
    if (mod === 'pos') return '/pos/configs';
    return null;
};

const loadTheme = async () => {
    const mod = getModuleFromView(currentView.value);
    const endpoints = mod ? [endpointForModule(mod)] : ['/hairsalon/config', '/quota/config', '/pos/configs'];
    for (const ep of endpoints) {
        try {
            const r = await api.get(ep);
            applyThemeFromConfigs(r.data || []);
            return;
        } catch {}
    }
    dragEnabled.value = false;
    applyNavStyles();
};

onMounted(() => {
    loadSidebarLogo();
    loadTestModeStatus();
    setTimeout(() => {
        if (currentView.value === 'dashboard') loadTheme();
    }, 1000);
});

nextTick(() => { setTimeout(applyNavStyles, 100); });

const isVisible = computed(() => {
    if (!hasMenuModule.value) return false;
    if (props.isMobile) return props.isOpen;
    return true;
});

const currentView = ref('dashboard');
let lastModule = null;
window.addEventListener('view-changed', (e) => {
    const newView = e.detail;
    currentView.value = newView;
    const mod = getModuleFromView(newView);
    if (mod && mod !== lastModule) {
        lastModule = mod;
        loadTheme();
    }
});

const hasMenuModule = computed(() => authStore.modules.some(m => m.route === 'menu'));

const visibleModules = computed(() => {
    if (!hasMenuModule.value) return [];
    let modules = authStore.modules.filter(m => m.route !== 'menu');
    if (!authStore.isParentDb) {
        modules = modules.filter(m =>
            m.route !== 'admin-modules' && m.route !== 'admin-companies' &&
            m.route !== 'pos-admin' && m.route !== 'pos' &&
            m.route !== 'dashboard' && m.route !== 'users'
        );
        modules = modules.map(m => {
            if (m.route === 'pos-users') return { ...m, name: 'Usuarios', route: 'pos-users' };
            return m;
        });
        const hasPosUsers = modules.some(m => m.route === 'pos-users');
        modules = modules.map(m => {
            if (m.route === 'hairsalon-users') return { ...m, name: hasPosUsers ? 'Usuarios Peluquería' : 'Usuarios', route: 'hairsalon-users' };
            return m;
        });
        const userHasMercadoQr = authStore.user?.mercadopago_qr_enabled;
        if (userHasMercadoQr !== true && userHasMercadoQr !== 1 && userHasMercadoQr !== '1') {
            modules = modules.filter(m => m.route !== 'pos-qr');
        }
    }
    const hasFullPosAccess = authStore.permissions.includes('pos-users_read') || authStore.permissions.includes('pos-config_read');
    const hasFullHairSalonAccess = authStore.permissions.includes('hairsalon-config_read') || authStore.permissions.includes('hairsalon-users_read');
    if (authStore.isGlobalAdmin || hasFullPosAccess || hasFullHairSalonAccess) return modules;
    return modules.filter(m => {
        if (m.route.startsWith('pos-')) return authStore.permissions.includes(`${m.route}_read`);
        if (m.route.startsWith('hairsalon-')) return authStore.permissions.includes(`${m.route}_read`);
        return authStore.permissions.includes(`${m.route}_read`);
    });
});

const toggleGroup = (id) => {
    const idx = expandedGroups.value.indexOf(id);
    if (idx === -1) expandedGroups.value.push(id);
    else expandedGroups.value.splice(idx, 1);
};

// Drag & Drop
const onDragStart = (idx) => { if (!dragEnabled.value) return; dragIndex.value = idx; };
const onDragOver = (e, idx) => {
    if (!dragEnabled.value || dragIndex.value === null) return;
    e.dataTransfer.dropEffect = 'move';
    const rect = e.currentTarget.getBoundingClientRect();
    const midY = rect.top + rect.height / 2;
    dragOverIndex.value = e.clientY < midY ? idx : idx + 1;
};
const onDragLeave = () => { if (!dragEnabled.value) return; dragOverIndex.value = null; };
const onDrop = async (e, idx) => {
    if (!dragEnabled.value || dragIndex.value === null) return;
    const fromIdx = dragIndex.value;
    const toIdx = dragOverIndex.value !== null ? dragOverIndex.value : idx;
    dragIndex.value = null;
    dragOverIndex.value = null;
    if (fromIdx === toIdx || fromIdx === null) return;
    const modules = [...visibleModules.value];
    const item = modules.splice(fromIdx, 1)[0];
    const targetIdx = toIdx > fromIdx ? toIdx - 1 : toIdx;
    modules.splice(targetIdx, 0, item);
    const orderData = modules.map((m, i) => ({ route: m.route, order: i }));
    try {
        await api.post('/user/modules/reorder', { modules: orderData });
        const ordered = orderData.map(o => authStore.modules.find(m => m.route === o.route)).filter(Boolean);
        const others = authStore.modules.filter(m => !ordered.includes(m));
        authStore.modules = [...others.filter(m => m.route === 'menu'), ...ordered, ...others.filter(m => m.route !== 'menu' && !ordered.includes(m))];
    } catch (e) { /* silent */ }
};
const onDragEnd = () => { dragIndex.value = null; dragOverIndex.value = null; };

onMounted(() => {
    window.addEventListener('pos-user-settings-updated', (event) => {
        const data = event.detail;
        const currentUserId = authStore.user?.id;
        if (data.id === currentUserId && data.mercadopago_qr_enabled !== undefined) {
            authStore.updateUser({ mercadopago_qr_enabled: data.mercadopago_qr_enabled });
            if (!data.mercadopago_qr_enabled) window.dispatchEvent(new CustomEvent('close-pos-qr-module'));
        }
    });
    if (window.Echo) {
        window.Echo.channel('configs').listen('.ConfigUpdated', (data) => {
            if (data?.name === 'test_mode') testModeEnabled.value = data.value === '1';
        });
    }
    window.addEventListener('hairsalon-config-updated', () => { loadTheme(); });
    window.addEventListener('quota-config-updated', () => { loadTheme(); });
    window.addEventListener('pos-config-updated', () => { loadTheme(); });
});
</script>

<style scoped>
.sidebar { width: 250px; background-color: #212529; height: 100%; overflow-y: auto; flex-shrink: 0; transition: width 0.3s ease; user-select: none; -webkit-user-select: none; }
.sidebar.collapsed { width: 0; overflow: hidden; }
.sidebar.show { transform: translateX(0); }
.sidebar-header { background-color: #212529; filter: brightness(0.85); }
.test-mode-banner { background-color: #ffc107; color: #212529; text-align: center; padding: 4px 8px; font-size: 0.75rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
.nav-link { display: flex; align-items: center; padding: 0.75rem 1rem; text-decoration: none; cursor: pointer; transition: all 0.2s; color: rgba(255,255,255,0.8); }
.nav-group-items .nav-link { padding-left: 2rem; font-size: 0.9rem; }
.nav-group .nav-link { display: flex; justify-content: space-between; align-items: center; }
.sidebar-item-wrapper { position: relative; }
.sidebar-item-wrapper.drag-over-top::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: #0d6efd; z-index: 10; }
.sidebar-item-wrapper.drag-over-bottom::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 3px; background: #0d6efd; z-index: 10; }
.nav-link.dragging { opacity: 0.4; }
.drag-handle { color: rgba(255,255,255,0.3); font-size: 0.8rem; cursor: grab; margin-left: auto; }
.nav-link:hover .drag-handle { color: rgba(255,255,255,0.6); }
@media (min-width: 992px) { .sidebar { transform: translateX(0); } }
</style>
