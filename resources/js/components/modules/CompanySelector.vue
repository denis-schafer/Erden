<template>
    <div class="company-selector-container">
        <div class="smoke-bubble bubble-1"></div>
        <div class="smoke-bubble bubble-2"></div>

        <svg class="waves" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320" preserveAspectRatio="none">
            <path class="wave-1" fill="rgba(255,255,255,0.04)" d="M0,224L48,213.3C96,203,192,181,288,181.3C384,181,480,203,576,218.7C672,235,768,245,864,229.3C960,213,1056,171,1152,154.7C1248,139,1344,149,1392,154.7L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
        </svg>

        <div class="selector-card">
            <div class="selector-header">
                <div class="selector-logo" v-html="logoSvg"></div>
            </div>
            <div class="selector-body">
                <p class="selector-subtitle">Seleccione la empresa con la que desea trabajar</p>

                <div class="dropdown">
                    <button
                        class="btn-selector-dropdown"
                        type="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                    >
                        <span v-if="selectedCompanyName" class="selected-text">{{ selectedCompanyName }}</span>
                        <span v-else class="placeholder-text">Seleccionar empresa</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu w-100">
                        <div class="px-3 py-2">
                            <input
                                v-model="searchQuery"
                                type="text"
                                class="form-control form-control-sm"
                                placeholder="Buscar por nombre o código..."
                                @click.stop
                            >
                        </div>
                        <div class="dropdown-divider"></div>
                        <div class="dropdown-scroll">
                            <button
                                v-for="company in filteredCompanies"
                                :key="company.id"
                                class="dropdown-item"
                                :class="{ 'active': selectedCompany === company.id }"
                                type="button"
                                @click="selectCompany(company.id)"
                            >
                                <span class="company-name">{{ company.name }}</span>
                                <span class="company-db">({{ company.db }})</span>
                                <span v-if="company.is_global" class="badge bg-primary ms-1">Global</span>
                            </button>
                            <div v-if="filteredCompanies.length === 0" class="px-3 py-2 text-muted small">
                                No se encontraron empresas
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="error" class="alert alert-danger mt-3">{{ error }}</div>

                <button @click="logout" class="btn-logout">
                    <i class="bi bi-box-arrow-left me-2"></i>
                    Cerrar Sesión
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { toast as toastify } from '../../utils/toast';
import api from '../../services/api';

const props = defineProps({
    initialCompanies: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['company-selected', 'logout']);

const authStore = useAuthStore();

const companies = ref(props.initialCompanies);
const selectedCompany = ref('');
const loading = ref(false);
const error = ref('');
const searchQuery = ref('');
const logoSvg = ref('');

const filteredCompanies = computed(() => {
    if (!searchQuery.value) return companies.value;

    const query = searchQuery.value.toLowerCase();
    return companies.value.filter(company =>
        (company.name && company.name.toLowerCase().includes(query)) ||
        (company.db && company.db.toLowerCase().includes(query))
    );
});

const selectedCompanyName = computed(() => {
    if (!selectedCompany.value) return '';
    const company = companies.value.find(c => c.id === selectedCompany.value);
    return company ? `${company.name} (${company.db})` : '';
});

onMounted(async () => {
    if (companies.value.length === 0) {
        try {
            const response = await api.get('/companies');
            companies.value = response.data;
        } catch (err) {
            error.value = 'Error al cargar empresas';
            toastify.error('Error al cargar empresas');
        }
    }
    try {
        const resp = await fetch('/img/logo.svg');
        logoSvg.value = await resp.text();
    } catch (e) {
        console.warn('Failed to load logo SVG:', e);
    }
});

const selectCompany = async (companyId) => {
    selectedCompany.value = companyId;
    searchQuery.value = '';
    loading.value = true;
    error.value = '';

    try {
        await authStore.selectCompany(companyId);
        emit('company-selected');
        toastify.success('Empresa seleccionada correctamente');
    } catch (err) {
        error.value = err.response?.data?.message || 'Error al seleccionar empresa';
        toastify.error(error.value);
    } finally {
        loading.value = false;
    }
};

const logout = async () => {
    await authStore.logout();
    emit('logout');
};
</script>

<style scoped>
.company-selector-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
    background: linear-gradient(135deg, #0d2137 0%, #1a6d91 50%, #2391c1 100%);
    background-size: 400% 400%;
    animation: gradientShift 20s ease infinite;
}

@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    25% { background-position: 100% 0%; }
    50% { background-position: 100% 100%; }
    75% { background-position: 0% 100%; }
    100% { background-position: 0% 50%; }
}

.smoke-bubble {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    pointer-events: none;
    z-index: 0;
}

.bubble-1 {
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(77,184,232,0.12) 0%, transparent 70%);
    top: -100px;
    right: -80px;
    animation: float1 25s ease-in-out infinite;
}

.bubble-2 {
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
    bottom: -80px;
    left: -60px;
    animation: float2 30s ease-in-out infinite;
}

@keyframes float1 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(-40px, 30px) scale(1.1); }
}

@keyframes float2 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(50px, -40px) scale(0.9); }
}

.waves {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 150px;
    z-index: 0;
    pointer-events: none;
}

.wave-1 {
    animation: waveAnim 10s ease-in-out infinite alternate;
}

@keyframes waveAnim {
    0% { transform: translateX(0); }
    100% { transform: translateX(-40px); }
}

.selector-card {
    width: 100%;
    max-width: 420px;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 25px 80px rgba(0,0,0,0.35);
    animation: cardEnter 0.6s ease-out;
    position: relative;
    z-index: 1;
}

@keyframes cardEnter {
    from { opacity: 0; transform: translateY(40px) scale(0.96); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.selector-header {
    background: linear-gradient(135deg, #0a1628 0%, #1a4a6e 50%, #1a6d91 100%);
    padding: 2.5rem 2rem 2rem;
    text-align: center;
}

.selector-logo {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 40px;
}

.selector-logo svg {
    display: block;
    width: 100%;
    max-width: 160px;
    height: auto;
    margin: 0;
}

.selector-body {
    background: #fff;
    padding: 2rem;
}

.selector-subtitle {
    color: #6c757d;
    font-size: 0.95rem;
    text-align: center;
    margin-bottom: 1.5rem;
}

.btn-selector-dropdown {
    width: 100%;
    height: 48px;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 1rem;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.25s;
    color: #212529;
}

.btn-selector-dropdown:hover {
    border-color: #2391c1;
    background: #fff;
}

.btn-selector-dropdown:focus {
    border-color: #2391c1;
    box-shadow: 0 0 0 4px rgba(35,145,193,0.1);
    outline: none;
}

.placeholder-text {
    color: #adb5bd;
}

.selected-text {
    color: #212529;
    font-weight: 500;
}

.btn-selector-dropdown .bi-chevron-down {
    color: #adb5bd;
    transition: transform 0.2s;
}

.btn-selector-dropdown[aria-expanded="true"] .bi-chevron-down {
    transform: rotate(180deg);
}

.dropdown-menu {
    width: 100% !important;
    max-height: 300px;
    overflow: hidden;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    padding: 0;
}

.dropdown-scroll {
    max-height: 250px;
    overflow-y: auto;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 1rem;
    transition: background 0.15s;
}

.dropdown-item:hover {
    background: #f0f7fb;
}

.dropdown-item.active {
    background: #e3f0f7;
    color: #1a6d91;
}

.company-name {
    font-weight: 500;
}

.company-db {
    color: #6c757d;
    font-size: 0.875rem;
}

.btn-logout {
    width: 100%;
    height: 44px;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    background: #fff;
    color: #6c757d;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.25s;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 1rem;
}

.btn-logout:hover {
    border-color: #dc3545;
    color: #dc3545;
    background: #fff5f5;
}

.alert {
    border-radius: 10px;
    font-size: 0.9rem;
    padding: 0.75rem 1rem;
}
</style>
