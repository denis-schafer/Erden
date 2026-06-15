<template>
    <div class="quota-statistics p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Estadísticas</h4>
            <button class="btn btn-outline-success btn-sm" @click="exportData">
                <i class="bi bi-download"></i> Exportar
            </button>
        </div>
        <div v-if="loading" class="text-center py-5"><div class="spinner-border"></div></div>
        <template v-else>
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-header">Socios</div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total:</span><strong>{{ stats.partners?.total }}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Activos:</span><strong class="text-success">{{ stats.partners?.active }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-header">Cuotas</div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Regulares:</span>
                                <span>{{ stats.quotas?.regular?.paid }}/{{ stats.quotas?.regular?.total }} pagadas</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Dchos Pileta:</span>
                                <span>{{ stats.quotas?.pool_fees?.paid }}/{{ stats.quotas?.pool_fees?.total }} pagadas</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-header">Financiero</div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Cobrado:</span>
                                <strong>${{ formatNumber(stats.financial?.total_collected) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Total Pendiente:</span>
                                <strong class="text-danger">${{ formatNumber(stats.financial?.total_pending) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-header">Rendiciones</div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Efectivo rendido:</span>
                                <strong>${{ formatNumber(stats.rendering?.rendered_cash) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Digital rendido:</span>
                                <strong>${{ formatNumber(stats.rendering?.rendered_digital) }}</strong>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between text-danger">
                                <span>Efectivo en caja:</span>
                                <strong>${{ formatNumber(stats.rendering?.cash_in_hand) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between text-danger">
                                <span>Digital pendiente:</span>
                                <strong>${{ formatNumber(stats.rendering?.digital_in_hand) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-8">
                    <div class="card h-100">
                        <div class="card-header">Cobranza Mensual (últimos 12 meses)</div>
                        <div class="card-body">
                            <div style="height: 300px;">
                                <Bar v-if="monthlyChartData.labels.length" :data="monthlyChartData" :options="barOptions" />
                                <p v-else class="text-muted text-center py-4">Sin datos</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header">Cuotas por Tipo y Estado</div>
                        <div class="card-body">
                            <div style="height: 300px;">
                                <Doughnut v-if="quotaTypeChartData.labels.length" :data="quotaTypeChartData" :options="doughnutOptions" />
                                <p v-else class="text-muted text-center py-4">Sin datos</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6 offset-md-3">
                    <div class="card h-100">
                        <div class="card-header">Rendiciones Pendientes</div>
                        <div class="card-body">
                            <div style="height: 260px;">
                                <Doughnut v-if="renderingChartData.labels.length" :data="renderingChartData" :options="renderingDoughnutOptions" />
                                <p v-else class="text-muted text-center py-4">Sin datos</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Bar, Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend, ArcElement } from 'chart.js';
import axios from 'axios';
import { toast } from '../../../utils/toast';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend, ArcElement);

const loading = ref(true);
const stats = ref({});

const formatNumber = (n) => parseFloat(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 });

const getMonthName = (m) => {
    const names = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    return names[m - 1] || '';
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

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom' }
    }
};

const renderingDoughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 8, font: { size: 10 } } }
    }
};

const monthlyChartData = computed(() => {
    const collection = [...(stats.value.monthly_collection || [])].reverse();
    return {
        labels: collection.map(m => `${getMonthName(m.month)} ${m.year}`),
        datasets: [{
            label: 'Cobrado',
            data: collection.map(m => Number(m.total)),
            backgroundColor: '#0d6efd',
            borderRadius: 4
        }]
    };
});

const quotaTypeChartData = computed(() => {
    const r = stats.value.quotas;
    if (!r) return { labels: [], datasets: [] };
    const labels = ['Regulares Pagadas', 'Regulares Pendientes', 'Pileta Pagadas', 'Pileta Pendientes'];
    const data = [
        r.regular?.paid || 0,
        r.regular?.pending || 0,
        r.pool_fees?.paid || 0,
        r.pool_fees?.pending || 0,
    ];
    return {
        labels,
        datasets: [{
            data,
            backgroundColor: ['#198754', '#ffc107', '#0d6efd', '#dc3545'],
            borderWidth: 1
        }]
    };
});

const renderingChartData = computed(() => {
    const rend = stats.value.rendering;
    if (!rend) return { labels: [], datasets: [] };
    const labels = ['Efectivo Rendido', 'Digital Rendido', 'Efectivo Pendiente', 'Digital Pendiente'];
    const data = [
        Number(rend.rendered_cash) || 0,
        Number(rend.rendered_digital) || 0,
        Number(rend.cash_in_hand) || 0,
        Number(rend.digital_in_hand) || 0,
    ];
    return {
        labels,
        datasets: [{
            data,
            backgroundColor: ['#20c997', '#0d6efd', '#fd7e14', '#dc3545'],
            borderWidth: 1
        }]
    };
});

const loadStats = async () => {
    try {
        const { data } = await axios.get('/quota/statistics/summary');
        stats.value = data;
    } catch (e) { console.error(e); }
    finally { loading.value = false; }
};

const exportData = async () => {
    try {
        const { data } = await axios.get('/quota/statistics/export');
        const csv = ['dni,nombre,apellido,telefono,cuotas_totales,cuotas_pagadas,cuotas_pendientes,monto_total,monto_pagado,monto_deuda'];
        data.data.forEach(r => {
            csv.push(`${r.dni},"${r.nombre}","${r.apellido}",${r.telefono},${r.cuotas_totales},${r.cuotas_pagadas},${r.cuotas_pendientes},${r.monto_total},${r.monto_pagado},${r.monto_deuda}`);
        });
        const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = data.filename;
        link.click();
    } catch (e) {
        toast.error('Error al exportar');
    }
};

onMounted(loadStats);
</script>

<style scoped>
.card-header { background: #fff; border-bottom: 1px solid #e9ecef; font-weight: 600; }
</style>
