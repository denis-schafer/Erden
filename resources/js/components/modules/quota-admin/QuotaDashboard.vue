<template>
    <div class="quota-dashboard p-3">
        <h4 class="mb-4">Dashboard de Cuotas</h4>
        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border"></div>
        </div>
        <template v-else>
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-primary">
                        <div class="card-body text-center">
                            <h5 class="card-title text-primary">{{ stats.total_partners }}</h5>
                            <p class="card-text text-muted">Total Socios</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-success">
                        <div class="card-body text-center">
                            <h5 class="card-title text-success">{{ stats.paid_quotas }}</h5>
                            <p class="card-text text-muted">Cuotas Pagadas</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-warning">
                        <div class="card-body text-center">
                            <h5 class="card-title text-warning">{{ stats.pending_quotas }}</h5>
                            <p class="card-text text-muted">Cuotas Pendientes</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-info">
                        <div class="card-body text-center">
                            <h5 class="card-title text-info">{{ stats.payment_rate }}%</h5>
                            <p class="card-text text-muted">Tasa de Pago</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">Resumen Financiero</div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Cobrado:</span>
                                <strong>${{ formatNumber(stats.total_collected) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Rendido:</span>
                                <strong>${{ formatNumber(stats.total_rendered) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Pendiente de Rendir:</span>
                                <strong class="text-danger">${{ formatNumber(stats.pending_rendered) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">Pendiente por Método</div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Efectivo:</span>
                                <strong>${{ formatNumber(stats.pending_cash) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Digital:</span>
                                <strong>${{ formatNumber(stats.pending_digital) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">Cuotas por Estado</div>
                        <div class="card-body">
                            <div style="height: 260px;">
                                <Doughnut v-if="statusChartData.labels.length" :data="statusChartData" :options="doughnutOptions" />
                                <p v-else class="text-muted text-center py-4">Sin datos</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">Distribución por Método de Pago</div>
                        <div class="card-body">
                            <div style="height: 260px;">
                                <Doughnut v-if="methodChartData.labels.length" :data="methodChartData" :options="methodDoughnutOptions" />
                                <p v-else class="text-muted text-center py-4">Sin datos</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">Cobranza Mensual (últimos 12 meses)</div>
                <div class="card-body">
                    <div style="height: 300px;">
                        <Bar v-if="monthlyChartData.labels.length" :data="monthlyChartData" :options="barOptions" />
                        <p v-else class="text-muted text-center py-4">Sin datos</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Últimos Pagos</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Socio</th>
                                <th>DNI</th>
                                <th>Monto</th>
                                <th>Método</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in stats.recent_payments" :key="p.id">
                                <td>{{ p.partner_name }}</td>
                                <td>{{ p.dni }}</td>
                                <td>${{ formatNumber(p.total_amount) }}</td>
                                <td>{{ p.payment_method }}</td>
                                <td>{{ p.paid_at }}</td>
                            </tr>
                            <tr v-if="!stats.recent_payments?.length">
                                <td colspan="5" class="text-center text-muted">Sin pagos recientes</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Doughnut, Bar } from 'vue-chartjs';
import { Chart as ChartJS, ArcElement, Tooltip, Legend, CategoryScale, LinearScale, BarElement, Title } from 'chart.js';
import axios from 'axios';

ChartJS.register(ArcElement, Tooltip, Legend, CategoryScale, LinearScale, BarElement, Title);

const loading = ref(true);
const stats = ref({});

const formatNumber = (n) => {
    return parseFloat(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 });
};

const barColors = ['#0d6efd', '#dc3545', '#198754', '#ffc107', '#0dcaf0', '#6f42c1', '#fd7e14', '#20c997', '#e83e8c', '#6c757d'];

const getMonthName = (m) => {
    const names = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    return names[m - 1] || '';
};

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom' }
    }
};

const methodDoughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 8, font: { size: 10 } } }
    }
};

const barOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
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
        }
    }
};

const statusChartData = computed(() => {
    return {
        labels: ['Pagadas', 'Pendientes'],
        datasets: [{
            data: [stats.value.paid_quotas || 0, stats.value.pending_quotas || 0],
            backgroundColor: ['#198754', '#ffc107'],
            borderWidth: 1
        }]
    };
});

const methodChartData = computed(() => {
    const methods = stats.value.payment_by_method || [];
    const labels = methods.map(m => {
        const names = { cash: 'Efectivo', digital: 'Digital', mercadopago: 'MercadoPago' };
        return names[m.payment_method] || m.payment_method;
    });
    const totals = methods.map(m => Number(m.total));
    return {
        labels,
        datasets: [{
            data: totals,
            backgroundColor: barColors.slice(0, labels.length),
            borderWidth: 1
        }]
    };
});

const monthlyChartData = computed(() => {
    const collection = [...(stats.value.monthly_collection || [])].reverse();
    const labels = collection.map(m => `${getMonthName(m.month)} ${m.year}`);
    const totals = collection.map(m => Number(m.total));
    return {
        labels,
        datasets: [{
            label: 'Cobrado',
            data: totals,
            backgroundColor: '#0d6efd',
            borderRadius: 4
        }]
    };
});

onMounted(async () => {
    try {
        const { data } = await axios.get('/quota/dashboard');
        stats.value = data;
    } catch (e) {
        console.error('Error loading dashboard:', e);
    } finally {
        loading.value = false;
    }
});
</script>

<style scoped>
.card-header { background: #fff; border-bottom: 1px solid #e9ecef; font-weight: 600; }
</style>
