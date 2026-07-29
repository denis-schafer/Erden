<template>
    <div class="hairsalon-finances p-3">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Finanzas</h4>
            <div class="d-flex gap-2">
                <input type="date" v-model="startDate" class="form-control form-control-sm" style="width:140px" @change="loadData">
                <input type="date" v-model="endDate" class="form-control form-control-sm" style="width:140px" @change="loadData">
                <button class="btn btn-success btn-sm" @click="openIncomeForm"><i class="bi bi-plus"></i> Nuevo Ingreso</button>
                <button class="btn btn-danger btn-sm" @click="openExpenseForm"><i class="bi bi-plus"></i> Nuevo Gasto</button>
            </div>
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
                <div v-for="m in summary.by_method" :key="m.payment_method" class="d-flex justify-content-between small mt-1"><span>{{ methodLabel(m.payment_method) }}</span><span>${{ formatNumber(m.total) }}</span></div>
                <div class="d-flex justify-content-between small mt-1 border-top pt-1 fw-bold"><span>Ing. Total Bruto</span><span>${{ formatNumber(summary.total_gross_income) }}</span></div></div></div></div>
        </div>
        <div class="row mb-3">
            <div class="col-12"><div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <h6 class="mb-0">Ingresos vs Gastos por Día</h6>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="showChart" v-model="chartVisible" @change="saveChartSettings">
                        </div>
                    </div>
                    <div v-if="chartVisible" class="d-flex gap-3">
                        <label class="form-check form-check-inline mb-0 small" style="cursor:pointer">
                            <input class="form-check-input" type="checkbox" v-model="showIncomeLine" @change="saveChartSettings">
                            <span style="color:#198754">&#9632; Ingresos</span>
                        </label>
                        <label class="form-check form-check-inline mb-0 small" style="cursor:pointer">
                            <input class="form-check-input" type="checkbox" v-model="showExpenseLine" @change="saveChartSettings">
                            <span style="color:#dc3545">&#9632; Gastos</span>
                        </label>
                        <label class="form-check form-check-inline mb-0 small" style="cursor:pointer">
                            <input class="form-check-input" type="checkbox" v-model="showBalanceLine" @change="saveChartSettings">
                            <span style="color:#0d6efd">&#9632; Balance</span>
                        </label>
                    </div>
                </div>
                <div v-if="chartVisible" class="card-body" style="height:320px">
                    <Line v-if="dailyChartData.labels.length" :data="dailyChartData" :options="dailyChartOptions" />
                    <div v-else class="text-center text-muted py-5">Sin datos para el período</div>
                </div>
            </div></div>
        </div>
        <div v-if="loading" class="text-center py-5"><div class="spinner-border"></div></div>
        <div v-else>
            <DataTable :data="displayMovements" :columns="columns" :per-page="15">
                <template #rowActions="{ row }">
                    <button class="btn btn-sm btn-outline-info" :disabled="btnLoading['detail-'+row.id]" @click="openDetail(row)">
                        <i v-if="!btnLoading['detail-'+row.id]" class="bi bi-eye"></i>
                        <span v-else class="spinner-border spinner-border-sm"></span>
                    </button>
                    <button v-if="isAdmin" class="btn btn-sm btn-outline-danger ms-1" @click="confirmDelete(row)"><i class="bi bi-trash"></i></button>
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
                <button type="submit" class="btn btn-danger btn-sm" :disabled="savingExpense">{{ savingExpense ? 'Guardando...' : 'Guardar' }}</button>
            </div></form></div></div></div>
        <div v-if="showExpenseForm" class="modal-backdrop fade show"></div>

        <!-- Income Modal -->
        <div v-if="showIncomeForm" class="modal d-block"><div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Nuevo Ingreso</h5><button class="btn-close" @click="showIncomeForm = false"></button></div>
            <form @submit.prevent="saveIncome"><div class="modal-body">
                <div class="mb-2"><label class="form-label">Concepto</label><input v-model="incomeForm.concept" class="form-control form-control-sm" required></div>
                <div class="mb-2"><label class="form-label">Monto</label><input v-model.number="incomeForm.amount" class="form-control form-control-sm" type="number" step="0.01" min="0" required></div>
                <div class="mb-2"><label class="form-label">Método de Pago</label>
                    <select v-model="incomeForm.payment_method" class="form-select form-select-sm"><option value="cash">Efectivo</option><option value="transfer">Transferencia</option><option value="mercadopago">MercadoPago</option><option value="other">Otro</option></select></div>
                <div class="mb-2"><label class="form-label">Notas</label><textarea v-model="incomeForm.notes" class="form-control form-control-sm" rows="2"></textarea></div>
            </div><div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" @click="showIncomeForm = false">Cancelar</button>
                <button type="submit" class="btn btn-success btn-sm" :disabled="savingIncome">{{ savingIncome ? 'Guardando...' : 'Guardar' }}</button>
            </div></form></div></div></div>
        <div v-if="showIncomeForm" class="modal-backdrop fade show"></div>

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

        <!-- Delete Confirm Modal -->
        <div v-if="showDeleteConfirm" class="modal d-block"><div class="modal-dialog modal-sm"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Eliminar</h5><button class="btn-close" @click="showDeleteConfirm = false"></button></div>
            <div class="modal-body">
                <p>¿Estás seguro de eliminar este movimiento?</p>
                <p class="small text-muted">{{ deleteTarget?.type_display }} - {{ deleteTarget?.concept }} - ${{ formatNumber(deleteTarget?.amount) }}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" @click="showDeleteConfirm = false">Cancelar</button>
                <button type="button" class="btn btn-danger btn-sm" @click="deleteMovement" :disabled="deleting">{{ deleting ? 'Eliminando...' : 'Eliminar' }}</button>
            </div></div></div></div>
        <div v-if="showDeleteConfirm" class="modal-backdrop fade show"></div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '../../../stores/auth';
import { Line } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend } from 'chart.js';
import api from '../../../services/api';
import DataTable from '../../../components/common/DataTable.vue';
import { useCache } from '../../../composables/useCache';
import { toast } from '../../../utils/toast';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend);

const authStore = useAuthStore();
const isAdmin = computed(() => authStore.user?.role_id === 1);

const { refresh } = useCache();
const loading = ref(true);
const movements = ref([]);
const summary = ref({ income: 0, expenses: 0, balance: 0, by_method: [] });
const startDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0]);
const endDate = ref(new Date().toISOString().split('T')[0]);
const showExpenseForm = ref(false);
const savingExpense = ref(false);
const expenseForm = reactive({ concept: '', amount: 0, payment_method: 'cash', notes: '' });
const showIncomeForm = ref(false);
const savingIncome = ref(false);
const incomeForm = reactive({ concept: '', amount: 0, payment_method: 'cash', notes: '' });
const showDetail = ref(false);
const detailMov = ref(null);
const detailLoading = ref(false);
const btnLoading = ref({});
const showDeleteConfirm = ref(false);
const deleteTarget = ref(null);
const deleting = ref(false);

const columns = [
    { key: 'created_at', label: 'Fecha' },
    { key: 'type_display', label: 'Tipo' },
    { key: 'concept', label: 'Concepto' },
    { key: 'client_name', label: 'Cliente' },
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

const displayMovements = computed(() => {
    return movements.value.map(m => ({
        ...m,
        type_display: m.type === 'income' ? 'Ingreso' : 'Egreso',
        method_display: methodLabel(m.payment_method),
    }));
});

const dailyChartData = computed(() => {
    const data = summary.value.daily_totals || [];
    const map = {};
    data.forEach(d => { map[d.date] = d; });
    
    const allDates = [];
    const start = new Date(startDate.value + 'T12:00:00');
    const end = new Date(endDate.value + 'T12:00:00');
    for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
        const ds = d.toISOString().split('T')[0];
        const entry = map[ds] || { income: 0, expense: 0 };
        allDates.push({ date: ds, income: entry.income || 0, expense: entry.expense || 0 });
    }
    
    if (!allDates.length) return { labels: [], datasets: [] };
    return {
        labels: allDates.map(d => new Date(d.date + 'T12:00:00').toLocaleDateString('es-AR', { day:'2-digit', month:'short' })),
        datasets: [
            { label: 'Ingresos', data: allDates.map(d => d.income), borderColor: '#198754', backgroundColor: 'transparent', tension: 0.3, pointRadius: 2, hidden: !showIncomeLine.value },
            { label: 'Gastos', data: allDates.map(d => d.expense), borderColor: '#dc3545', backgroundColor: 'transparent', tension: 0.3, pointRadius: 2, hidden: !showExpenseLine.value },
            { label: 'Balance', data: allDates.map(d => d.income - d.expense), borderColor: '#0d6efd', backgroundColor: 'transparent', tension: 0.3, pointRadius: 2, borderDash: [5,5], hidden: !showBalanceLine.value },
        ],
    };
});

const chartVisible = ref(localStorage.getItem('finances_chart_visible') !== '0');
const showIncomeLine = ref(localStorage.getItem('finances_chart_income') !== '0');
const showExpenseLine = ref(localStorage.getItem('finances_chart_expense') !== '0');
const showBalanceLine = ref(localStorage.getItem('finances_chart_balance') !== '0');

const saveChartSettings = () => {
    localStorage.setItem('finances_chart_visible', chartVisible.value ? '1' : '0');
    localStorage.setItem('finances_chart_income', showIncomeLine.value ? '1' : '0');
    localStorage.setItem('finances_chart_expense', showExpenseLine.value ? '1' : '0');
    localStorage.setItem('finances_chart_balance', showBalanceLine.value ? '1' : '0');
};

const dailyChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { ticks: { callback: v => '$' + Number(v || 0).toLocaleString('es-AR') } } },
};

const methodLabel = (m) => ({ cash: 'Efectivo', transfer: 'Transferencia', mercadopago: 'MercadoPago', other: 'Otro' }[m] || m);
const formatNumber = (n) => Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const openExpenseForm = () => { expenseForm.concept = ''; expenseForm.amount = 0; expenseForm.payment_method = 'cash'; expenseForm.notes = ''; showExpenseForm.value = true; };

const saveExpense = async () => {
    savingExpense.value = true;
    try { await api.post('/hairsalon/finances/expenses', expenseForm); toast.success('Gasto registrado'); showExpenseForm.value = false; await loadData(); }
    catch (e) { toast.error(e.response?.data?.message || 'Error'); }
    finally { savingExpense.value = false; }
};

const openIncomeForm = () => { incomeForm.concept = ''; incomeForm.amount = 0; incomeForm.payment_method = 'cash'; incomeForm.notes = ''; showIncomeForm.value = true; };

const saveIncome = async () => {
    savingIncome.value = true;
    try { await api.post('/hairsalon/finances/incomes', incomeForm); toast.success('Ingreso registrado'); showIncomeForm.value = false; await loadData(); }
    catch (e) { toast.error(e.response?.data?.message || 'Error'); }
    finally { savingIncome.value = false; }
};

const openDetail = async (movement) => {
    showDetail.value = true; detailLoading.value = true; detailMov.value = null;
    btnLoading.value['detail-' + movement.id] = true;
    try { const res = await api.get('/hairsalon/finances/' + movement.id); detailMov.value = res.data; }
    catch (e) { toast.error('Error al cargar detalle'); }
    finally { detailLoading.value = false; btnLoading.value['detail-' + movement.id] = false; }
};

const confirmDelete = (movement) => {
    deleteTarget.value = movement;
    showDeleteConfirm.value = true;
};

const deleteMovement = async () => {
    if (!deleteTarget.value) return;
    deleting.value = true;
    try {
        await api.delete('/hairsalon/finances/' + deleteTarget.value.id);
        toast.success('Movimiento eliminado');
        showDeleteConfirm.value = false;
        deleteTarget.value = null;
        await loadData();
    } catch (e) { toast.error(e.response?.data?.message || 'Error al eliminar'); }
    finally { deleting.value = false; }
};

const handleJobCreated = () => { loadData(); };
onMounted(() => { loadData(); window.addEventListener('hairsalon-job-created', handleJobCreated); });
onUnmounted(() => { window.removeEventListener('hairsalon-job-created', handleJobCreated); });
</script>
