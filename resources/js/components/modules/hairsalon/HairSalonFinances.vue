<template>
    <div class="hairsalon-finances p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Finanzas</h4>
            <button class="btn btn-danger btn-sm" @click="openExpenseForm"><i class="bi bi-plus"></i> Nuevo Gasto</button>
        </div>
        <div class="row g-3 mb-3">
            <div class="col"><div class="card border-success"><div class="card-body text-center py-2">
                <small class="text-muted">Ingresos</small><h5 class="text-success mb-0">${{ formatNumber(summary.income) }}</h5></div></div></div>
            <div class="col"><div class="card border-danger"><div class="card-body text-center py-2">
                <small class="text-muted">Gastos</small><h5 class="text-danger mb-0">${{ formatNumber(summary.expenses) }}</h5></div></div></div>
            <div class="col"><div class="card border-primary"><div class="card-body text-center py-2">
                <small class="text-muted">Balance</small><h5 class="text-primary mb-0">${{ formatNumber(summary.balance) }}</h5></div></div></div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-4"><div class="card"><div class="card-body text-center py-2">
                <h6 class="mb-0">Por Método de Pago</h6>
                <div v-for="m in summary.by_method" :key="m.payment_method" class="d-flex justify-content-between small mt-1"><span>{{ methodLabel(m.payment_method) }}</span><span>${{ formatNumber(m.total) }}</span></div></div></div></div>
            <div class="col-md-8"><div class="d-flex gap-2 mb-2">
                <input class="form-control form-control-sm" type="date" v-model="startDate"><input class="form-control form-control-sm" type="date" v-model="endDate">
                <button class="btn btn-outline-primary btn-sm" @click="refreshData">Filtrar</button></div></div>
        </div>
        <div v-if="loading" class="text-center py-5"><div class="spinner-border"></div></div>
        <div v-else>
            <DataTable :data="displayMovements" :columns="columns" :per-page="15">
                <template #rowActions="{ row }">
                    <button class="btn btn-sm btn-outline-info" @click="openDetail(row)"><i class="bi bi-eye"></i></button>
                </template>
            </DataTable>
        </div>

        <!-- Expense Modal -->
        <div v-if="showExpenseForm" class="modal d-block"><div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Nuevo Gasto</h5><button class="btn-close" @click="showExpenseForm = false"></button></div>
            <form @submit.prevent="saveExpense"><div class="modal-body">
                <div class="mb-2"><label class="form-label">Concepto</label><input v-model="expenseForm.concept" class="form-control form-control-sm" required></div>
                <div class="mb-2"><label class="form-label">Monto</label><input v-model.number="expenseForm.amount" class="form-control form-control-sm" type="number" step="0.01" min="0" required></div>
                <div class="mb-2"><label class="form-label">Método de Pago</label>
                    <select v-model="expenseForm.payment_method" class="form-select form-select-sm"><option value="cash">Efectivo</option><option value="transfer">Transferencia</option><option value="mercadopago">MercadoPago</option><option value="other">Otro</option></select></div>
                <div class="mb-2"><label class="form-label">Notas</label><textarea v-model="expenseForm.notes" class="form-control form-control-sm" rows="2"></textarea></div>
            </div><div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" @click="showExpenseForm = false">Cancelar</button>
                <button type="submit" class="btn btn-danger btn-sm">{{ savingExpense ? 'Guardando...' : 'Guardar' }}</button>
            </div></form></div></div></div>
        <div v-if="showExpenseForm" class="modal-backdrop fade show"></div>

        <!-- Detail Modal -->
        <div v-if="showDetail" class="modal d-block"><div class="modal-dialog modal-lg"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Detalle del Movimiento</h5><button class="btn-close" @click="showDetail = false"></button></div>
            <div class="modal-body">
                <div v-if="detailLoading" class="text-center py-3"><div class="spinner-border"></div></div>
                <template v-else-if="detailMov">
                    <table class="table table-sm table-borderless mb-3">
                        <tr><td class="fw-bold" style="width:130px">Fecha</td><td>{{ detailMov.created_at }}</td></tr>
                        <tr><td class="fw-bold">Tipo</td><td>{{ detailMov.type === 'income' ? 'Ingreso' : 'Gasto' }}</td></tr>
                        <tr><td class="fw-bold">Concepto</td><td>{{ detailMov.concept }}</td></tr>
                        <tr><td class="fw-bold">Método</td><td>{{ methodLabel(detailMov.payment_method) }}</td></tr>
                        <tr><td class="fw-bold">Monto</td><td class="fw-bold">${{ formatNumber(detailMov.amount) }}</td></tr>
                        <tr><td class="fw-bold">Operador</td><td>{{ detailMov.operator_name }}</td></tr>
                    </table>
                    <template v-if="detailMov.detail">
                        <h6 v-if="detailMov.detail.client_name">Cliente</h6>
                        <table v-if="detailMov.detail.client_name" class="table table-sm table-borderless mb-3"><tr><td style="width:130px" class="fw-bold">Nombre</td><td>{{ detailMov.detail.client_name }}</td></tr></table>
                        <h6 v-if="detailMov.detail.services && detailMov.detail.services.length">Servicios</h6>
                        <table v-if="detailMov.detail.services && detailMov.detail.services.length" class="table table-sm mb-3"><thead><tr><th>Servicio</th><th class="text-end">Precio</th></tr></thead><tbody><tr v-for="s in detailMov.detail.services" :key="s.id"><td>{{ s.name }}</td><td class="text-end">${{ formatNumber(s.price) }}</td></tr></tbody></table>
                        <div v-if="detailMov.detail.discount > 0" class="mb-2"><small class="text-muted">Descuento: ${{ formatNumber(detailMov.detail.discount) }}</small></div>
                        <h6 v-if="detailMov.detail.deductions && detailMov.detail.deductions.length">Productos descontados</h6>
                        <table v-if="detailMov.detail.deductions && detailMov.detail.deductions.length" class="table table-sm mb-3"><thead><tr><th>Producto</th><th class="text-end">Cantidad</th></tr></thead><tbody><tr v-for="d in detailMov.detail.deductions" :key="d.id"><td>{{ d.name }}</td><td class="text-end">{{ d.quantity }}</td></tr></tbody></table>
                        <div v-if="detailMov.detail.notes" class="mb-2"><small class="text-muted">Notas: {{ detailMov.detail.notes }}</small></div>
                    </template>
                </template>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary btn-sm" @click="showDetail = false">Cerrar</button></div>
        </div></div></div>
        <div v-if="showDetail" class="modal-backdrop fade show"></div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import api from '../../../services/api';
import DataTable from '../../../components/common/DataTable.vue';
import { useCache } from '../../../composables/useCache';
import { toast } from '../../../utils/toast';

const { refresh } = useCache();
const loading = ref(true);
const movements = ref([]);
const summary = ref({ income: 0, expenses: 0, balance: 0, by_method: [] });
const startDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0]);
const endDate = ref(new Date().toISOString().split('T')[0]);
const showExpenseForm = ref(false);
const savingExpense = ref(false);
const expenseForm = reactive({ concept: '', amount: 0, payment_method: 'cash', notes: '' });
const showDetail = ref(false);
const detailMov = ref(null);
const detailLoading = ref(false);

const columns = [
    { key: 'created_at', label: 'Fecha' },
    { key: 'type_display', label: 'Tipo' },
    { key: 'concept', label: 'Concepto' },
    { key: 'method_display', label: 'Método' },
    { key: 'amount', label: 'Monto' },
    { key: 'operator_name', label: 'Operador' },
];

const loadData = async () => {
    loading.value = true;
    try {
        const [mov, sum] = await Promise.all([
            api.get('/hairsalon/finances', { params: { start_date: startDate.value, end_date: endDate.value, per_page: 500 } }).then(r => r.data),
            api.get('/hairsalon/finances/summary', { params: { start_date: startDate.value, end_date: endDate.value } }).then(r => r.data),
        ]);
        movements.value = mov.data || [];
        summary.value = sum;
    } finally { loading.value = false; }
};

const refreshData = async () => {
    loading.value = true;
    try {
        const [mov, sum] = await Promise.all([
            api.get('/hairsalon/finances', { params: { start_date: startDate.value, end_date: endDate.value, per_page: 500 } }).then(r => r.data),
            api.get('/hairsalon/finances/summary', { params: { start_date: startDate.value, end_date: endDate.value } }).then(r => r.data),
        ]);
        movements.value = mov.data || [];
        summary.value = sum;
    } finally { loading.value = false; }
};

const displayMovements = computed(() => {
    return movements.value.map(m => ({
        ...m,
        type_display: m.type === 'income' ? 'Ingreso' : 'Egreso',
        method_display: methodLabel(m.payment_method),
    }));
});

const methodLabel = (m) => ({ cash: 'Efectivo', transfer: 'Transferencia', mercadopago: 'MercadoPago', other: 'Otro' }[m] || m);
const formatNumber = (n) => Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const openExpenseForm = () => { expenseForm.concept = ''; expenseForm.amount = 0; expenseForm.payment_method = 'cash'; expenseForm.notes = ''; showExpenseForm.value = true; };

const saveExpense = async () => {
    savingExpense.value = true;
    try { await api.post('/hairsalon/finances/expenses', expenseForm); toast.success('Gasto registrado'); showExpenseForm.value = false; await refreshData(); }
    catch (e) { toast.error(e.response?.data?.message || 'Error'); }
    finally { savingExpense.value = false; }
};

const openDetail = async (movement) => {
    showDetail.value = true; detailLoading.value = true; detailMov.value = null;
    try { const res = await api.get('/hairsalon/finances/' + movement.id); detailMov.value = res.data; }
    catch (e) { toast.error('Error al cargar detalle'); }
    finally { detailLoading.value = false; }
};

const handleJobCreated = () => { refreshData(); };
onMounted(() => { loadData(); window.addEventListener('hairsalon-job-created', handleJobCreated); });
onUnmounted(() => { window.removeEventListener('hairsalon-job-created', handleJobCreated); });
</script>
