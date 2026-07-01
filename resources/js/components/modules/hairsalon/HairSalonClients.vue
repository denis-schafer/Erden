<template>
    <div class="hairsalon-clients p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Clientes</h4>
            <button class="btn btn-primary btn-sm" @click="openForm()"><i class="bi bi-plus"></i> Nuevo</button>
        </div>
        <div v-if="loading" class="text-center py-5"><div class="spinner-border"></div></div>
        <div v-else>
            <DataTable :data="clients" :columns="columns" :per-page="15">
                <template #rowActions="{ row }">
                    <button class="btn btn-sm btn-outline-info me-1" @click="openHistory(row)" title="Historial"><i class="bi bi-clock-history"></i></button>
                    <button class="btn btn-sm btn-outline-primary me-1" @click="openForm(row)"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger" @click="confirmDelete(row)"><i class="bi bi-trash"></i></button>
                </template>
            </DataTable>
        </div>

        <!-- Client Form Modal -->
        <div v-if="showModal" class="modal d-block"><div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">{{ editing ? 'Editar' : 'Nuevo' }} Cliente</h5><button class="btn-close" @click="showModal = false"></button></div>
            <form @submit.prevent="saveClient"><div class="modal-body">
                <div class="mb-2"><label class="form-label">Nombre</label><input v-model="form.name" class="form-control form-control-sm" required></div>
                <div class="mb-2"><label class="form-label">Teléfono</label><input v-model="form.phone" class="form-control form-control-sm"></div>
                <div class="mb-2"><label class="form-label">Email</label><input v-model="form.email" class="form-control form-control-sm" type="email"></div>
                <div class="mb-2"><label class="form-label">Dirección</label><input v-model="form.address" class="form-control form-control-sm"></div>
                <div class="mb-2"><label class="form-label">Notas</label><textarea v-model="form.notes" class="form-control form-control-sm" rows="2"></textarea></div>
            </div><div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" @click="showModal = false">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ saving ? 'Guardando...' : 'Guardar' }}</button>
            </div></form>
        </div></div></div>
        <div v-if="showModal" class="modal-backdrop fade show"></div>

        <!-- History Modal -->
        <div v-if="showHistory" class="modal d-block"><div class="modal-dialog modal-lg"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Historial de {{ historyClient?.name }}</h5><button class="btn-close" @click="showHistory = false"></button></div>
            <div class="modal-body">
                <div v-if="historyLoading" class="text-center py-3"><div class="spinner-border"></div></div>
                <div v-else-if="!historyJobs.data || !historyJobs.data.length" class="text-center text-muted py-3">Sin trabajos registrados</div>
                <div v-else class="table-responsive">
                    <table class="table table-sm"><thead><tr><th>Fecha</th><th>Servicios</th><th>Total</th><th>Método</th><th>Operador</th><th></th></tr></thead>
                        <tbody><tr v-for="j in historyJobs.data" :key="j.id">
                            <td class="text-nowrap">{{ formatDate(j.created_at) }}</td>
                            <td>{{ j.service_names || '-' }}</td>
                            <td class="text-end">${{ formatNumber(j.total) }}</td>
                            <td>{{ methodLabel(j.payment_method) }}</td>
                            <td>{{ j.operator_name }}</td>
                            <td><button class="btn btn-sm btn-outline-info" @click="openJobDetail(j.id)"><i class="bi bi-eye"></i></button></td>
                        </tr></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary btn-sm" @click="showHistory = false">Cerrar</button></div>
        </div></div></div>
        <div v-if="showHistory" class="modal-backdrop fade show"></div>

        <!-- Job Detail Modal -->
        <div v-if="showJobDetail" class="modal d-block"><div class="modal-dialog modal-lg"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Detalle del Trabajo</h5><button class="btn-close" @click="showJobDetail = false"></button></div>
            <div class="modal-body">
                <div v-if="jobDetailLoading" class="text-center py-3"><div class="spinner-border"></div></div>
                <template v-else-if="jobDetail">
                    <table class="table table-sm table-borderless mb-3">
                        <tr><td class="fw-bold" style="width:120px">Fecha</td><td>{{ formatDate(jobDetail.job.created_at) }}</td></tr>
                        <tr><td class="fw-bold">Cliente</td><td>{{ jobDetail.job.client_name }} <small class="text-muted">{{ jobDetail.job.client_phone }}</small></td></tr>
                        <tr><td class="fw-bold">Operador</td><td>{{ jobDetail.job.operator_name }}</td></tr>
                        <tr><td class="fw-bold">Método</td><td>{{ methodLabel(jobDetail.job.payment_method) }}</td></tr>
                        <tr><td class="fw-bold">Notas</td><td>{{ jobDetail.job.notes || 'Sin notas' }}</td></tr>
                    </table>
                    <h6>Servicios</h6>
                    <table class="table table-sm mb-3"><thead><tr><th>Servicio</th><th class="text-end">Precio</th></tr></thead>
                        <tbody><tr v-for="s in jobDetail.services" :key="s.name"><td>{{ s.name }}</td><td class="text-end">${{ formatNumber(s.price) }}</td></tr></tbody>
                    </table>
                    <h6 v-if="jobDetail.deductions && jobDetail.deductions.length">Productos utilizados</h6>
                    <table v-if="jobDetail.deductions && jobDetail.deductions.length" class="table table-sm mb-3"><thead><tr><th>Producto</th><th class="text-end">Cantidad</th></tr></thead>
                        <tbody><tr v-for="d in jobDetail.deductions" :key="d.name || d.id"><td>{{ d.name }}</td><td class="text-end">{{ d.quantity }}</td></tr></tbody>
                    </table>
                </template>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary btn-sm" @click="showJobDetail = false">Cerrar</button></div>
        </div></div></div>
        <div v-if="showJobDetail" class="modal-backdrop fade show"></div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted, inject } from 'vue';
import api from '../../../services/api';
import DataTable from '../../../components/common/DataTable.vue';
import { useCache } from '../../../composables/useCache';
import { toast } from '../../../utils/toast';

const { fetch, refresh } = useCache();
const confirmDialog = inject('confirmDialog', null);
const loading = ref(true);
const clients = ref([]);
const showModal = ref(false);
const editing = ref(null);
const saving = ref(false);
const form = reactive({ name: '', phone: '', email: '', address: '', notes: '' });

const showHistory = ref(false);
const historyLoading = ref(false);
const historyClient = ref(null);
const historyJobs = ref({ data: [] });
const showJobDetail = ref(false);
const jobDetailLoading = ref(false);
const jobDetail = ref(null);

const columns = [
    { key: 'name', label: 'Nombre' },
    { key: 'phone', label: 'Teléfono' },
    { key: 'email', label: 'Email' },
];

const loadClients = async () => {
    loading.value = true;
    try {
        const res = await api.get('/hairsalon/clients', { params: { per_page: 500 } });
        clients.value = res.data.data || [];
    } finally { loading.value = false; }
};

const refreshClients = async () => {
    try {
        const res = await api.get('/hairsalon/clients', { params: { per_page: 500 } });
        clients.value = res.data.data || [];
    } finally { /* silent */ }
};

const openForm = (client) => {
    editing.value = client || null;
    form.name = client?.name || '';
    form.phone = client?.phone || '';
    form.email = client?.email || '';
    form.address = client?.address || '';
    form.notes = client?.notes || '';
    showModal.value = true;
};

const saveClient = async () => {
    saving.value = true;
    try {
        if (editing.value) {
            await api.put('/hairsalon/clients/' + editing.value.id, form);
            toast.success('Cliente actualizado');
        } else {
            await api.post('/hairsalon/clients', form);
            toast.success('Cliente creado');
        }
        showModal.value = false;
        refreshClients();
    } catch (e) { toast.error(e.response?.data?.message || 'Error'); }
    finally { saving.value = false; }
};

const confirmDelete = async (client) => {
    if (confirmDialog && confirmDialog.value) {
        const confirmed = await confirmDialog.value.open({
            title: 'Eliminar Cliente',
            message: `¿Está seguro de eliminar a "${client.name}"?`,
            confirmText: 'Eliminar',
            confirmClass: 'btn-danger'
        });
        if (!confirmed) return;
    } else {
        if (!confirm('¿Está seguro de eliminar este cliente?')) return;
    }
    try {
        await api.delete('/hairsalon/clients/' + client.id);
        toast.success('Cliente eliminado');
        refreshClients();
    } catch (e) { toast.error(e.response?.data?.message || 'Error'); }
};

const openHistory = async (client) => {
    historyClient.value = client;
    showHistory.value = true;
    historyLoading.value = true;
    try {
        const res = await api.get('/hairsalon/clients/' + client.id + '/jobs');
        historyJobs.value = res.data;
    } catch (e) { toast.error('Error al cargar historial'); }
    finally { historyLoading.value = false; }
};

const openJobDetail = async (jobId) => {
    showJobDetail.value = true;
    jobDetailLoading.value = true;
    jobDetail.value = null;
    try {
        const res = await api.get('/hairsalon/cashier/' + jobId);
        jobDetail.value = res.data;
    } catch (e) { toast.error('Error al cargar detalle'); }
    finally { jobDetailLoading.value = false; }
};

const formatDate = (d) => {
    if (!d) return '-';
    const dt = new Date(d);
    return dt.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};
const formatNumber = (n) => Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const methodLabel = (m) => ({ cash: 'Efectivo', transfer: 'Transferencia', mercadopago: 'MercadoPago', other: 'Otro' }[m] || m);

const handleClientChanged = () => { refreshClients(); };
onMounted(() => { loadClients(); window.addEventListener('hairsalon-client-changed', handleClientChanged); });
onUnmounted(() => { window.removeEventListener('hairsalon-client-changed', handleClientChanged); });
</script>
