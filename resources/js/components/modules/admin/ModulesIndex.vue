<template>
    <div class="admin-modules-container">
        <div class="row">
            <!-- Sección 1: Gestión de Módulos -->
            <div class="col-md-6">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="h4 mb-0">Gestión de Módulos</h2>
                </div>

                <div class="card">
                    <div class="card-body">
                        <DataTable 
                            :data="modules" 
                            :columns="columns"
                            :per-page="10"
                        >
                    <template #actions>
                        <button 
                            @click="showModal = true" 
                            class="btn btn-primary btn-circle"
                            title="Nuevo"
                        >
                            <i class="bi bi-plus"></i>
                        </button>
                    </template>
                            <template #rowActions="{ row }">
                                <ActionMenu :actions="getModuleActions(row)" />
                            </template>
                        </DataTable>
                    </div>
                </div>
            </div>

            <!-- Sección 2: Asignación por Empresa (solo visible en DB padre) -->
            <div class="col-md-6" v-if="authStore.isParentDb">
                <div class="mb-4">
                    <h2 class="h4 mb-3">Asignar Módulos por Empresa</h2>
                    
                    <div class="mb-3">
                        <label class="form-label">Seleccionar Empresa:</label>
                        <select v-model="selectedCompanyId" @change="loadCompanyModules" class="form-select">
                            <option value="">Seleccionar empresa...</option>
                            <option v-for="company in companies" :key="company.id" :value="company.id">
                                {{ company.name }}
                            </option>
                        </select>
                    </div>

                    <div v-if="selectedCompanyId && availableModules.length > 0">
                        <div class="mb-3 d-flex align-items-center">
                            <button @click="toggleAllModules" class="btn btn-outline-secondary btn-sm me-2">
                                {{ allSelected ? 'Deseleccionar todos' : 'Seleccionar todos' }}
                            </button>
                            <span class="text-muted small">
                                ({{ selectedModuleIds.length }} de {{ availableModules.length }} seleccionados)
                            </span>
                        </div>

                        <div class="card">
                            <div class="card-body module-checklist">
                                <div 
                                    v-for="module in availableModules" 
                                    :key="module.id" 
                                    class="form-check"
                                >
                                    <input 
                                        class="form-check-input" 
                                        type="checkbox" 
                                        :id="'module-' + module.id"
                                        :checked="selectedModuleIds.includes(module.id)"
                                        @change="toggleModule(module.id)"
                                    >
                                    <label class="form-check-label" :for="'module-' + module.id">
                                        {{ module.name }}
                                        <span v-if="module.route === 'dashboard' || module.route === 'menu'" class="badge bg-secondary ms-1">
                                            Obligatorio
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <button @click="saveCompanyModules" class="btn btn-primary mt-3" :disabled="saving">
                            {{ saving ? 'Guardando...' : 'Guardar asignación' }}
                        </button>
                    </div>

                    <div v-else-if="selectedCompanyId" class="alert alert-info">
                        No hay módulos disponibles para asignar.
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para crear/editar módulo -->
        <div v-if="showModal" class="modal d-block" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ editingModule ? 'Editar' : 'Nuevo' }} Módulo</h5>
                        <button @click="closeModal" type="button" class="btn-close"></button>
                    </div>
                    <form @submit.prevent="saveModule">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Nombre</label>
                                <input v-model="form.name" type="text" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Ruta</label>
                                <input v-model="form.route" type="text" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Ícono</label>
                                <input v-model="form.icon" type="text" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descripción</label>
                                <textarea v-model="form.description" class="form-control"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">¿Es especial?</label>
                                <select v-model="form.is_special" class="form-select">
                                    <option :value="false">No</option>
                                    <option :value="true">Sí</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Parent ID</label>
                                <select v-model="form.parent_id" class="form-select">
                                    <option :value="null">Sin padre</option>
                                    <option v-for="m in modules" :key="m.id" :value="m.id">
                                        {{ m.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Orden</label>
                                <input v-model.number="form.order" type="number" class="form-control">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button @click="closeModal" type="button" class="btn btn-secondary">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div v-if="showModal" class="modal-backdrop fade show"></div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted, computed, inject } from 'vue';
import { useAuthStore } from '../../../stores/auth';
import { toast } from '../../../utils/toast';
import api from '../../../services/api';
import DataTable from '../../../components/common/DataTable.vue';
import ActionMenu from '../../../components/common/ActionMenu.vue';

const authStore = useAuthStore();
const confirmDialog = inject('confirmDialog', null);

const modules = ref([]);
const companies = ref([]);
const selectedCompanyId = ref('');
const assignedModules = ref([]);
const showModal = ref(false);
const editingModule = ref(null);
const saving = ref(false);

const columns = [
    { key: 'id', label: 'ID' },
    { key: 'name', label: 'Nombre' },
    { key: 'route', label: 'Ruta' }
];

const getModuleActions = (module) => {
    const actions = [];
    
    actions.push({
        icon: 'bi bi-pencil',
        title: 'Editar',
        class: 'btn btn-sm btn-outline-primary',
        handler: () => editModule(module)
    });
    
    actions.push({
        icon: 'bi bi-trash',
        title: 'Eliminar',
        class: 'btn btn-sm btn-outline-danger',
        handler: () => deleteModule(module.id)
    });
    
    return actions;
};

const form = reactive({
    name: '',
    route: '',
    icon: '',
    description: '',
    is_special: false,
    parent_id: null,
    order: 0
});

const availableModules = computed(() => {
    return modules.value.filter(m => m.route !== 'menu');
});

const selectedModuleIds = computed({
    get: () => assignedModules.value,
    set: (val) => { assignedModules.value = val; }
});

const allSelected = computed(() => {
    return availableModules.value.length > 0 && 
           availableModules.value.every(m => selectedModuleIds.value.includes(m.id));
});

const loadModules = async () => {
    const response = await api.get('/admin/modules');
    modules.value = response.data;
};

const loadCompanies = async () => {
    const response = await api.get('/admin/companies');
    companies.value = response.data;
};

const loadCompanyModules = async () => {
    if (!selectedCompanyId.value) {
        assignedModules.value = [];
        return;
    }
    
    try {
        const response = await api.get(`/admin/companies/${selectedCompanyId.value}/modules`);
        assignedModules.value = response.data.assigned_modules || [];
    } catch (error) {
        console.error('Error loading company modules:', error);
        assignedModules.value = [];
    }
};

const toggleModule = (moduleId) => {
    const index = selectedModuleIds.value.indexOf(moduleId);
    if (index === -1) {
        selectedModuleIds.value.push(moduleId);
    } else {
        selectedModuleIds.value.splice(index, 1);
    }
};

const toggleAllModules = () => {
    if (allSelected.value) {
        selectedModuleIds.value = [];
    } else {
        selectedModuleIds.value = availableModules.value.map(m => m.id);
    }
};

const saveCompanyModules = async () => {
    saving.value = true;
    try {
        await api.put(`/admin/companies/${selectedCompanyId.value}/modules`, {
            module_ids: selectedModuleIds.value
        });
        toast.success('Asignación guardada correctamente');
    } catch (error) {
        toast.error(error.response?.data?.message || 'Error al guardar asignación');
    } finally {
        saving.value = false;
    }
};

const editModule = (module) => {
    editingModule.value = module;
    form.name = module.name;
    form.route = module.route;
    form.icon = module.icon || '';
    form.description = module.description || '';
    form.is_special = module.is_special;
    form.parent_id = module.parent_id;
    form.order = module.order;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingModule.value = null;
    form.name = '';
    form.route = '';
    form.icon = '';
    form.description = '';
    form.is_special = false;
    form.parent_id = null;
    form.order = 0;
};

const saveModule = async () => {
    try {
        if (editingModule.value) {
            await api.put(`/admin/modules/${editingModule.value.id}`, form);
        } else {
            await api.post('/admin/modules', form);
        }
        closeModal();
        loadModules();
    } catch (error) {
        toast.error(error.response?.data?.message || 'Error al guardar');
    }
};

const deleteModule = async (id) => {
    let confirmed = true;
    if (confirmDialog.value) {
        confirmed = await confirmDialog.value.open({
            title: 'Eliminar Módulo',
            message: '¿Está seguro de eliminar este módulo?',
            confirmText: 'Eliminar',
            confirmClass: 'btn-danger'
        });
    }
    
    if (confirmed) {
        try {
            await api.delete(`/admin/modules/${id}`);
            loadModules();
            toast.success('Módulo eliminado correctamente');
        } catch (error) {
            toast.error(error.response?.data?.message || 'Error al eliminar');
        }
    }
};

onMounted(() => {
    loadModules();
    if (authStore.isParentDb) {
        loadCompanies();
    }
    window.addEventListener('company-updated', loadCompanies);
});

onUnmounted(() => {
    window.removeEventListener('company-updated', loadCompanies);
});
</script>

<style scoped>
.module-checklist {
    max-height: 300px;
    overflow-y: auto;
}

.module-checklist .form-check {
    margin-bottom: 0.5rem;
}
</style>