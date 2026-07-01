<template>
    <div class="hairsalon-dashboard-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Dashboard</h4>
            <div class="d-flex gap-2">
                <input type="date" v-model="startDate" class="form-control form-control-sm" style="width:140px" @change="loadData">
                <input type="date" v-model="endDate" class="form-control form-control-sm" style="width:140px" @change="loadData">
            </div>
        </div>

        <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>

        <div v-else>
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stat-card"><div class="card-body">
                        <h6 class="text-muted">Clientes</h6>
                        <h2 class="mb-0">{{ stats.total_clients }}</h2>
                    </div></div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card"><div class="card-body">
                        <h6 class="text-muted">Trabajos (período)</h6>
                        <h2 class="mb-0">{{ stats.period_jobs }}</h2>
                    </div></div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card"><div class="card-body">
                        <h6 class="text-muted">Ingresos (período)</h6>
                        <h2 class="mb-0">${{ formatNumber(stats.period_income) }}</h2>
                    </div></div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card"><div class="card-body">
                        <h6 class="text-muted">Ingreso Promedio</h6>
                        <h2 class="mb-0">${{ formatNumber(stats.period_avg) }}</h2>
                    </div></div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100"><div class="card-header"><h6 class="mb-0">Trabajos por Día</h6></div>
                        <div class="card-body">
                            <Bar v-if="trendData.labels.length" :data="trendData" :options="barOptions" />
                            <p v-else class="text-muted text-center">Sin datos</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100"><div class="card-header"><h6 class="mb-0">Métodos de Pago</h6></div>
                        <div class="card-body">
                            <Doughnut v-if="methodData.labels.length" :data="methodData" :options="doughnutOptions" />
                            <p v-else class="text-muted text-center">Sin datos</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card h-100"><div class="card-header"><h6 class="mb-0">Servicios Más Vendidos</h6></div>
                        <div class="card-body">
                            <table class="table table-sm"><thead><tr><th>Servicio</th><th class="text-center">Cantidad</th><th class="text-end">Total</th></tr></thead>
                                <tbody><tr v-for="s in stats.top_services" :key="s.name">
                                    <td>{{ s.name }}</td><td class="text-center">{{ s.count }}</td><td class="text-end">${{ formatNumber(s.total) }}</td>
                                </tr><tr v-if="!stats.top_services || !stats.top_services.length"><td colspan="3" class="text-muted text-center">Sin datos</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100"><div class="card-header"><h6 class="mb-0">Distribución</h6></div>
                        <div class="card-body">
                            <Doughnut v-if="servicesDoughnut.labels.length" :data="servicesDoughnut" :options="doughnutOptions" />
                            <p v-else class="text-muted text-center">Sin datos</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="card"><div class="card-header"><h6 class="mb-0">Últimos Trabajos</h6></div>
                        <div class="card-body">
                            <table class="table table-sm"><thead><tr><th>Cliente</th><th>Operador</th><th>Total</th><th>Método</th><th>Hora</th></tr></thead>
                                <tbody><tr v-for="j in stats.recent_jobs" :key="j.id">
                                    <td>{{ j.client_name }}</td><td>{{ j.operator_name }}</td>
                                    <td>${{ formatNumber(j.total) }}</td><td>{{ methodLabel(j.payment_method) }}</td>
                                    <td>{{ formatDate(j.created_at) }}</td>
                                </tr><tr v-if="!stats.recent_jobs || !stats.recent_jobs.length"><td colspan="5" class="text-muted text-center">Sin datos</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Doughnut, Bar } from 'vue-chartjs';
import { Chart as ChartJS, ArcElement, Tooltip, Legend, CategoryScale, LinearScale, BarElement, Title } from 'chart.js';
import api from '../../../services/api';
import { useAuthStore } from '../../../stores/auth';

ChartJS.register(ArcElement, Tooltip, Legend, CategoryScale, LinearScale, BarElement, Title);

const authStore = useAuthStore();
const loading = ref(true);
const stats = ref({});
const startDate = ref(new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]);
const endDate = ref(new Date().toISOString().split('T')[0]);

const barColors = ['#0d6efd', '#dc3545', '#198754', '#ffc107', '#0dcaf0', '#6f42c1', '#fd7e14', '#20c997', '#e83e8c', '#6c757d'];

const trendData = computed(() => {
    const days = stats.value.jobs_by_day || [];
    return {
        labels: days.map(d => d.date),
        datasets: [{
            label: 'Trabajos',
            data: days.map(d => d.count),
            backgroundColor: barColors.slice(0, days.length),
            borderRadius: 4
        }]
    };
});

const methodData = computed(() => {
    const methods = stats.value.by_method || [];
    return {
        labels: methods.map(m => methodLabel(m.payment_method)),
        datasets: [{ data: methods.map(m => m.count), backgroundColor: barColors.slice(0, methods.length) }]
    };
});

const servicesDoughnut = computed(() => {
    const svcs = (stats.value.top_services || []).slice(0, 8);
    return {
        labels: svcs.map(s => s.name),
        datasets: [{ data: svcs.map(s => s.count), backgroundColor: barColors.slice(0, svcs.length), borderWidth: 1 }]
    };
});

const doughnutOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } };
const barOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } };

const methodLabel = (m) => ({ cash: 'Efectivo', transfer: 'Transferencia', mercadopago: 'MercadoPago', other: 'Otro' }[m] || m);
const formatNumber = (n) => Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const formatDate = (d) => { if (!d) return '-'; const dt = new Date(d); return dt.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }); };

const loadData = async () => {
    loading.value = true;
    try {
        const res = await api.get('/hairsalon/dashboard', { params: { start_date: startDate.value, end_date: endDate.value } });
        stats.value = res.data;
    } finally { loading.value = false; }
};

const handleJobCreated = () => { loadData(); };

onMounted(() => { loadData(); window.addEventListener('hairsalon-job-created', handleJobCreated); });
onUnmounted(() => { window.removeEventListener('hairsalon-job-created', handleJobCreated); });
</script>

<style scoped>
.hairsalon-dashboard-container { height: 100%; padding: 1rem; overflow-y: auto; background-color: #f8f9fa; background-image: var(--bg-image, none); background-position: center; background-size: cover; background-repeat: no-repeat; background-attachment: fixed; }
.stat-card { border-left: 4px solid #0d6efd; }
.stat-card h2 { color: #0d6efd; }
.card { box-shadow: 0 2px 4px rgba(0,0,0,.1); }
.card-header { background: #fff; border-bottom: 1px solid #e9ecef; font-weight: 600; }
</style>
