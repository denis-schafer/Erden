<template>
    <div class="quota-payments p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Pagos</h4>
            <button class="btn btn-outline-info btn-sm" @click="loadBalance">
                <i class="bi bi-wallet2"></i> Balance Cajeros
            </button>
        </div>

        <div class="row mb-3">
            <div class="col-md-3">
                <input class="form-control form-control-sm" v-model="filters.search" placeholder="Buscar..." @input="onFilterChange">
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" v-model="filters.payment_method" @change="onFilterChange">
                    <option value="">Todos</option>
                    <option value="cash">Efectivo</option>
                    <option value="digital">Digital</option>
                    <option value="mercadopago">MercadoPago</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" v-model="filters.rendered" @change="onFilterChange">
                    <option value="">Todos</option>
                    <option value="false">No Rendidos</option>
                    <option value="true">Rendidos</option>
                </select>
            </div>
        </div>

        <div v-if="showBalance" class="card mb-4">
            <div class="card-header d-flex justify-content-between">
                <span>Balance por Cajero</span>
                <button class="btn btn-sm btn-outline-secondary" @click="showBalance = false">Cerrar</button>
            </div>
            <div class="card-body">
                <div v-if="balanceLoading" class="text-center"><div class="spinner-border spinner-border-sm"></div></div>
                <div v-else>
                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">Cobrado vs Rendido por Cajero</h6>
                                    <div style="height: 250px;">
                                        <Bar v-if="cashierBarData.labels.length" :data="cashierBarData" :options="barOptions" />
                                        <p v-else class="text-muted text-center py-4">Sin datos</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">Total por Método</h6>
                                    <div style="height: 250px;">
                                        <Doughnut v-if="methodDoughnutData.labels.length" :data="methodDoughnutData" :options="doughnutOptions" />
                                        <p v-else class="text-muted text-center py-4">Sin datos</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h6>Efectivo</h6>
                    <table class="table table-sm">
                        <thead><tr><th>Cajero</th><th>Cobrado</th><th>Rendido</th><th>Pendiente</th></tr></thead>
                        <tbody>
                            <tr v-for="c in balanceData.by_cashier?.cash" :key="c.id">
                                <td>{{ c.name }}</td>
                                <td>${{ formatNumber(c.total_collected) }}</td>
                                <td>${{ formatNumber(c.total_rendered) }}</td>
                                <td class="text-danger">${{ formatNumber(c.total_collected - c.total_rendered) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <h6>Digital</h6>
                    <table class="table table-sm">
                        <thead><tr><th>Cajero</th><th>Cobrado</th><th>Rendido</th><th>Pendiente</th></tr></thead>
                        <tbody>
                            <tr v-for="c in balanceData.by_cashier?.digital" :key="c.id">
                                <td>{{ c.name }}</td>
                                <td>${{ formatNumber(c.total_collected) }}</td>
                                <td>${{ formatNumber(c.total_rendered) }}</td>
                                <td class="text-danger">${{ formatNumber(c.total_collected - c.total_rendered) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="loading" class="text-center py-5"><div class="spinner-border"></div></div>
        <template v-else>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th class="sortable" @click="sortBy('id')">
                                ID <span v-if="sortField === 'id'" class="sort-indicator">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                            </th>
                            <th class="sortable" @click="sortBy('partner_name')">
                                Socio <span v-if="sortField === 'partner_name'" class="sort-indicator">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                            </th>
                            <th class="sortable" @click="sortBy('dni')">
                                DNI <span v-if="sortField === 'dni'" class="sort-indicator">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                            </th>
                            <th class="sortable" @click="sortBy('total_amount')">
                                Monto <span v-if="sortField === 'total_amount'" class="sort-indicator">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                            </th>
                            <th class="sortable" @click="sortBy('payment_method')">
                                Método <span v-if="sortField === 'payment_method'" class="sort-indicator">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                            </th>
                            <th class="sortable" @click="sortBy('paid_by_name')">
                                Cobrado por <span v-if="sortField === 'paid_by_name'" class="sort-indicator">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                            </th>
                            <th class="sortable" @click="sortBy('paid_at')">
                                Fecha <span v-if="sortField === 'paid_at'" class="sort-indicator">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                            </th>
                            <th>Rendido</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in payments.data" :key="p.id">
                            <td>{{ p.id }}</td>
                            <td>{{ p.partner_name }}</td>
                            <td>{{ p.dni }}</td>
                            <td>${{ formatNumber(p.total_amount) }}</td>
                            <td>{{ p.payment_method }}</td>
                            <td>{{ p.paid_by_name || '-' }}</td>
                            <td>{{ p.paid_at }}</td>
                            <td>
                                <i v-if="p.rendered" class="bi bi-check-circle-fill text-success"></i>
                                <span v-else class="text-muted">No</span>
                            </td>
                            <td>
                                <button v-if="!p.rendered" class="btn btn-sm btn-outline-success"
                                    @click="openRenderConfirm(p)">
                                    <i class="bi bi-check2-square"></i> Rendir
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!payments.data?.length">
                            <td colspan="9" class="text-center text-muted">No hay pagos</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <nav v-if="payments.last_page > 1">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item" :class="{ disabled: payments.current_page === 1 }">
                            <button class="page-link" @click="changePage(payments.current_page - 1)">Anterior</button>
                        </li>
                        <li class="page-item" :class="{ active: page === payments.current_page }" v-for="page in payments.last_page" :key="page">
                            <button class="page-link" @click="changePage(page)">{{ page }}</button>
                        </li>
                        <li class="page-item" :class="{ disabled: payments.current_page === payments.last_page }">
                            <button class="page-link" @click="changePage(payments.current_page + 1)">Siguiente</button>
                        </li>
                    </ul>
                </nav>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <span class="text-muted small">Mostrar:</span>
                    <select class="form-select form-select-sm" style="width: auto;" v-model.number="perPage" @change="onPerPageChange">
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>
            </div>
        </template>
    </div>
    <ConfirmModal ref="confirmModal" />
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Bar, Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend, ArcElement } from 'chart.js';
import axios from 'axios';
import { toast } from '../../../utils/toast';
import ConfirmModal from '../../../components/ConfirmModal.vue';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend, ArcElement);

const confirmModal = ref(null);

const payments = ref({ data: [], current_page: 1, last_page: 1 });
const loading = ref(true);
const showBalance = ref(false);
const balanceLoading = ref(false);
const balanceData = ref({});
const filters = ref({ search: '', payment_method: '', rendered: '' });
const perPage = ref(10);
const currentPage = ref(1);
const sortField = ref('');
const sortDir = ref('desc');

const formatNumber = (n) => parseFloat(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 });

const barColors = ['#0d6efd', '#198754', '#ffc107', '#dc3545'];

const barOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 8, font: { size: 10 } } },
        tooltip: {
            callbacks: {
                label: (ctx) => `$${Number(ctx.parsed.y).toLocaleString('es-AR', { minimumFractionDigits: 2 })}`
            }
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: (value) => '$' + Number(value).toLocaleString('es-AR', { minimumFractionDigits: 0 })
            }
        },
        x: {
            ticks: { font: { size: 10 } }
        }
    }
};

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 8, font: { size: 10 } } }
    }
};

const cashierBarData = computed(() => {
    const byCashier = {};
    const allCash = balanceData.value.by_cashier?.cash || [];
    const allDigital = balanceData.value.by_cashier?.digital || [];

    allCash.forEach(c => {
        byCashier[c.name] = { cobrado: Number(c.total_collected), rendido: Number(c.total_rendered) };
    });
    allDigital.forEach(c => {
        if (byCashier[c.name]) {
            byCashier[c.name].cobrado += Number(c.total_collected);
            byCashier[c.name].rendido += Number(c.total_rendered);
        } else {
            byCashier[c.name] = { cobrado: Number(c.total_collected), rendido: Number(c.total_rendered) };
        }
    });

    const labels = Object.keys(byCashier);
    return {
        labels,
        datasets: [
            { label: 'Cobrado', data: labels.map(n => byCashier[n].cobrado), backgroundColor: '#0d6efd', borderRadius: 4 },
            { label: 'Rendido', data: labels.map(n => byCashier[n].rendido), backgroundColor: '#198754', borderRadius: 4 }
        ]
    };
});

const methodDoughnutData = computed(() => {
    const total = balanceData.value.total || {};
    return {
        labels: ['Efectivo', 'Digital'],
        datasets: [{
            data: [Number(total.cash_collected) || 0, Number(total.digital_collected) || 0],
            backgroundColor: ['#20c997', '#0d6efd'],
            borderWidth: 1
        }]
    };
});

const loadPayments = async () => {
    loading.value = true;
    try {
        const params = {
            ...filters.value,
            page: currentPage.value,
            per_page: perPage.value,
        };
        if (sortField.value) {
            params.sort_field = sortField.value;
            params.sort_dir = sortDir.value;
        }
        Object.keys(params).forEach(k => { if (!params[k] && k !== 'page' && k !== 'per_page') delete params[k]; });
        const { data } = await axios.get('/quota/payments', { params });
        payments.value = data;
    } catch (e) { console.error(e); }
    finally { loading.value = false; }
};

const changePage = (page) => {
    if (page < 1 || page > payments.value.last_page) return;
    currentPage.value = page;
    loadPayments();
};

const onFilterChange = () => {
    currentPage.value = 1;
    loadPayments();
};

const onPerPageChange = () => {
    currentPage.value = 1;
    loadPayments();
};

const sortBy = (field) => {
    if (sortField.value === field) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDir.value = field === 'paid_at' ? 'desc' : 'asc';
    }
    currentPage.value = 1;
    loadPayments();
};

const loadBalance = async () => {
    showBalance.value = !showBalance.value;
    if (!showBalance.value) return;
    balanceLoading.value = true;
    try {
        const { data } = await axios.get('/quota/payments/cashier-balance');
        balanceData.value = data;
    } catch (e) { console.error(e); }
    finally { balanceLoading.value = false; }
};

const openRenderConfirm = (payment) => {
    if (confirmModal.value) {
        confirmModal.value.open({
            title: 'Rendir Pago',
            message: `¿Rendir pago #${payment.id} por $${formatNumber(payment.total_amount)}?`,
            confirmText: 'Rendir',
            type: 'primary',
            onConfirm: async () => {
                try {
                    await axios.post(`/quota/payments/${payment.id}/render`);
                    loadPayments();
                    toast.success('Pago rendido correctamente');
                } catch (e) {
                    toast.error(e.response?.data?.message || 'Error al rendir');
                }
            }
        });
    }
};

onMounted(() => {
    loadPayments();
    setupEcho();
    window.addEventListener('view-changed', onViewChanged);
});

let echoChannel = null;
let echoRetryTimer = null;

function setupEcho() {
    const companyDb = localStorage.getItem('quota_company_db');
    if (!companyDb) return;
    if (!window.Echo) {
        echoRetryTimer = setTimeout(setupEcho, 500);
        return;
    }
    echoChannel = window.Echo.private('quota.' + companyDb);
    echoChannel.listen('.QuotaRenderedUpdated', (e) => {
        if (!e.payment_id) return;
        for (const p of payments.value.data || []) {
            if (p.id === e.payment_id) {
                p.rendered = e.rendered;
            }
        }
    });
}

function onViewChanged(e) {
    if (e.detail === 'quota-payments') {
        loadPayments();
    }
}

onUnmounted(() => {
    if (echoRetryTimer) clearTimeout(echoRetryTimer);
    if (echoChannel) {
        echoChannel.stopListening('.QuotaRenderedUpdated');
        window.Echo.leaveChannel('quota.' + localStorage.getItem('quota_company_db'));
        echoChannel = null;
    }
    window.removeEventListener('view-changed', onViewChanged);
});
</script>

<style scoped>
.sortable { cursor: pointer; user-select: none; }
.sortable:hover { background-color: rgba(0,0,0,0.05); }
.sort-indicator { font-size: 0.7rem; margin-left: 2px; }
</style>
