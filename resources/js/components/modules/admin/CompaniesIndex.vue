<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Compañías</h2>
        </div>

        <div class="card">
            <div class="card-body">
                <DataTable 
                    :data="companies" 
                    :columns="columns"
                    :per-page="10"
                >
                    <template #actions>
                        <button 
                            @click="showModal = true" 
                            class="btn btn-primary btn-circle"
                            title="Nueva"
                        >
                            <i class="bi bi-plus"></i>
                        </button>
                    </template>
                    <template #rowActions="{ row }">
                        <ActionMenu :actions="getCompanyActions(row)" />
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- Modal de Company -->
        <div v-if="showModal" class="modal d-block" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Nueva Compañía</h5>
                        <button @click="closeModal" type="button" class="btn-close"></button>
                    </div>
                    <form @submit.prevent="saveCompany">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Nombre</label>
                                <input v-model="form.name" type="text" class="form-control" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button @click="closeModal" type="button" class="btn btn-secondary">Cancelar</button>
                            <button type="submit" class="btn btn-primary" :disabled="saving">
                                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                                Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div v-if="showModal" class="modal-backdrop fade show"></div>

        <!-- Modal de Editar Status -->
        <div v-if="showStatusModal" class="modal d-block" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Cambiar Estado</h5>
                        <button @click="closeStatusModal" type="button" class="btn-close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Estado</label>
                            <select v-model="form.status_id" class="form-select">
                                <option v-for="status in statuses" :key="status.id" :value="status.id">
                                    {{ status.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button @click="closeStatusModal" type="button" class="btn btn-secondary">Cancelar</button>
                        <button @click="updateStatus" class="btn btn-primary" :disabled="saving">
                            <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                            Guardar
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="showStatusModal" class="modal-backdrop fade show"></div>

        <!-- Modal de Confirmación para Eliminar -->
        <div v-if="showDeleteModal" class="modal d-block" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirmar Eliminación</h5>
                        <button @click="showDeleteModal = false" type="button" class="btn-close"></button>
                    </div>
                    <div class="modal-body">
                        <p>¿Está seguro de eliminar la compañía <strong>{{ deletingCompany?.name }}</strong>?</p>
                        <p class="text-muted small">Esto también eliminará la base de datos "{{ deletingCompany?.db }}".</p>
                    </div>
                    <div class="modal-footer">
                        <button @click="showDeleteModal = false" type="button" class="btn btn-secondary">Cancelar</button>
                        <button @click="confirmDelete" class="btn btn-danger">
                            <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                            Eliminar
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="showDeleteModal" class="modal-backdrop fade show"></div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useAuthStore } from '../../../stores/auth';
import api from '../../../services/api';
import DataTable from '../../../components/common/DataTable.vue';
import ActionMenu from '../../../components/common/ActionMenu.vue';

const authStore = useAuthStore();

const companies = ref([]);
const statuses = ref([]);
const showModal = ref(false);
const showStatusModal = ref(false);
const showDeleteModal = ref(false);
const deletingCompany = ref(null);
const saving = ref(false);
const editingCompany = ref(null);

const columns = [
    { key: 'id', label: 'ID' },
    { key: 'name', label: 'Nombre' },
    { key: 'db', label: 'DB' },
    { key: 'status.name', label: 'Estado' }
];

const form = reactive({
    name: '',
    status_id: 1
});

const loadData = async () => {
    try {
        const [companiesRes, statusesRes] = await Promise.all([
            api.get('/companies'),
            api.get('/companies/statuses')
        ]);
        companies.value = companiesRes.data.map(c => ({
            ...c,
            'status.name': c.status?.name || 'Sin estado'
        }));
        statuses.value = statusesRes.data;
    } catch (error) {
        console.error('Error loading data:', error);
    }
};

const getCompanyActions = (company) => {
    const actions = [];

    actions.push({
        icon: 'bi bi-toggle-on',
        title: 'Cambiar Estado',
        class: 'btn btn-sm btn-outline-secondary',
        handler: () => openStatusModal(company)
    });

    actions.push({
        icon: 'bi bi-trash',
        title: 'Eliminar',
        class: 'btn btn-sm btn-outline-danger',
        handler: () => deleteCompany(company)
    });

    return actions;
};

const openStatusModal = (company) => {
    editingCompany.value = company;
    form.status_id = company.status_id;
    showStatusModal.value = true;
};

const closeStatusModal = () => {
    showStatusModal.value = false;
    editingCompany.value = null;
};

const closeModal = () => {
    showModal.value = false;
    form.name = '';
};

const saveCompany = async () => {
    saving.value = true;
    try {
        await api.post('/companies', form);
        closeModal();
        loadData();
        window.dispatchEvent(new CustomEvent('company-updated'));
    } catch (error) {
        console.error('Error saving company:', error);
    } finally {
        saving.value = false;
    }
};

const updateStatus = async () => {
    if (!editingCompany.value) return;
    
    saving.value = true;
    try {
        await api.put(`/companies/${editingCompany.value.id}`, {
            status_id: form.status_id
        });
        closeStatusModal();
        loadData();
    } catch (error) {
        console.error('Error updating status:', error);
    } finally {
        saving.value = false;
    }
};

const deleteCompany = async (company) => {
    deletingCompany.value = company;
    showDeleteModal.value = true;
};

const confirmDelete = async () => {
    if (!deletingCompany.value) return;
    
    try {
        await api.delete(`/companies/${deletingCompany.value.id}`);
        showDeleteModal.value = false;
        deletingCompany.value = null;
        loadData();
    } catch (error) {
        console.error('Error deleting company:', error);
    }
};

onMounted(loadData);
</script>