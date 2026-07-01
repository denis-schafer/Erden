<template>
    <div class="company-selector-container">
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
    padding: 1.5rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.selector-card {
    width: 100%;
    max-width: 420px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    animation: cardEnter 0.5s ease-out;
}

@keyframes cardEnter {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.selector-header {
    background: linear-gradient(135deg, #0a1628 0%, #1a4a6e 50%, #1a6d91 100%);
    padding: 0.5rem 2rem;
    text-align: center;
    line-height: 0;
    border-radius: 12px 12px 0 0;
}

.selector-logo {
    display: flex;
    justify-content: center;
    align-items: center;
}

.selector-logo svg {
    display: block;
    width: 100%;
    max-width: 140px;
    height: auto;
    margin-top: 5px;
}

.selector-body {
    padding: 2rem;
    border-radius: 0 0 12px 12px;
}

.selector-subtitle {
    color: #6c757d;
    font-size: 0.9rem;
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
