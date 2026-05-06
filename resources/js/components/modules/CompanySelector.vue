<template>
    <div class="company-selector-container">
        <div class="company-selector-box">
            <div class="text-center mb-4">
                <h1 class="h4 mb-3">Seleccionar Empresa</h1>
                <p class="text-muted">Elija la empresa con la que desea trabajar</p>
            </div>
            
            <div class="dropdown">
                <button 
                    class="btn btn-outline-secondary dropdown-toggle w-100 text-start d-flex justify-content-between align-items-center"
                    type="button" 
                    data-bs-toggle="dropdown" 
                    aria-expanded="false"
                >
                    <span v-if="selectedCompanyName">{{ selectedCompanyName }}</span>
                    <span v-else class="text-muted">Seleccionar empresa</span>
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
            
            <button @click="logout" class="btn btn-outline-secondary w-100 mt-3">
                Cerrar Sesión
            </button>
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
    background-color: #f8f9fa;
}

.company-selector-box {
    width: 100%;
    max-width: 400px;
    padding: 2rem;
    background: white;
    border-radius: 0.5rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
}

.dropdown-menu {
    width: 100% !important;
    max-height: 300px;
    overflow: hidden;
}

.dropdown-scroll {
    max-height: 250px;
    overflow-y: auto;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.company-name {
    font-weight: 500;
}

.company-db {
    color: #6c757d;
    font-size: 0.875rem;
}
</style>