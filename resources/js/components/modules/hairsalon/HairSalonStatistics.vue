<template>
    <div class="hairsalon-statistics-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Estadísticas</h4>
            <div class="d-flex gap-2">
                <input type="date" v-model="startDate" class="form-control form-control-sm" style="width:140px">
                <input type="date" v-model="endDate" class="form-control form-control-sm" style="width:140px">
                <button class="btn btn-sm btn-primary" :disabled="loading" @click="loadData">
                    <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                    <i v-else class="bi bi-calculator me-1"></i>Calcular
                </button>
                <button class="btn btn-sm btn-success" :disabled="exporting" @click="exportData">
                    <span v-if="exporting" class="spinner-border spinner-border-sm me-1"></span>
                    <i v-else class="bi bi-download me-1"></i>Exportar
                </button>
            </div>
        </div>

        <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>

        <div v-else>
            <div class="row mb-4">
                <div class="col-md-3"><div class="card stat-card"><div class="card-body">
                    <h6 class="text-muted">Total Trabajos</h6><h2 class="mb-0">{{ stats.total_jobs || 0 }}</h2>
                </div></div></div>
                <div class="col-md-3"><div class="card stat-card"><div class="card-body">
                    <h6 class="text-muted">Ventas Totales</h6><h2 class="mb-0">${{ formatNumber(stats.total_income) }}</h2>
                </div></div></div>
                <div class="col-md-3"><div class="card stat-card"><div class="card-body">
                    <h6 class="text-muted">Ingreso Promedio</h6><h2 class="mb-0">${{ formatNumber(stats.avg_ticket) }}</h2>
                </div></div></div>
            </div>

            <div class="row mb-4">
                <div class="col-md-8"><div class="card h-100"><div class="card-header"><h6 class="mb-0">Ventas por Período</h6></div>
                    <div class="card-body">
                        <div v-if="salesChartData.labels && salesChartData.labels.length" style="height:300px">
                            <Bar :data="salesChartData" :options="barOptions" />
                        </div><p v-else class="text-muted text-center py-4">Sin datos</p>
                    </div>
                </div></div>
                <div class="col-md-4"><div class="card h-100"><div class="card-header"><h6 class="mb-0">Top Servicios</h6></div>
                    <div class="card-body p-0">
                        <div v-if="stats.top_services && stats.top_services.length" class="table-responsive" style="max-height:300px;overflow-y:auto">
                            <table class="table table-sm table-hover mb-0"><thead><tr><th>Servicio</th><th class="text-center">Cant.</th><th class="text-end">Total</th></tr></thead>
                                <tbody><tr v-for="s in stats.top_services" :key="s.name">
                                    <td>{{ s.name }}</td><td class="text-center">{{ s.count }}</td><td class="text-end">${{ formatNumber(s.total) }}</td>
                                </tr></tbody>
                            </table>
                        </div><p v-else class="text-muted text-center py-4">Sin datos</p>
                    </div>
                </div></div>
            </div>

            <div class="row mb-4" v-if="stats.top_services && stats.top_services.length">
                <div class="col-md-6 offset-md-3"><div class="card"><div class="card-header"><h6 class="mb-0">Distribución de Servicios</h6></div>
                    <div class="card-body"><div style="height:300px">
                        <Doughnut :data="servicesChartData" :options="doughnutOptions" />
                    </div></div>
                </div></div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6"><div class="card h-100"><div class="card-header"><h6 class="mb-0">Por Operador</h6></div>
                    <div class="card-body p-0">
                        <div v-if="stats.by_operator && stats.by_operator.length" class="table-responsive" style="max-height:300px;overflow-y:auto">
                            <table class="table table-sm table-hover mb-0"><thead><tr><th>Operador</th><th class="text-center">Trabajos</th><th class="text-end">Total</th></tr></thead>
                                <tbody><tr v-for="o in stats.by_operator" :key="o.name">
                                    <td>{{ o.name }}</td><td class="text-center">{{ o.count }}</td><td class="text-end">${{ formatNumber(o.total) }}</td>
                                </tr></tbody>
                            </table>
                        </div><p v-else class="text-muted text-center py-4">Sin datos</p>
                    </div>
                </div></div>
                <div class="col-md-6"><div class="card h-100"><div class="card-header"><h6 class="mb-0">Por Método de Pago</h6></div>
                    <div class="card-body p-0">
                        <div v-if="stats.by_payment_method && stats.by_payment_method.length" class="table-responsive" style="max-height:300px;overflow-y:auto">
                            <table class="table table-sm table-hover mb-0"><thead><tr><th>Método</th><th class="text-center">Cantidad</th><th class="text-end">Total</th></tr></thead>
                                <tbody><tr v-for="m in stats.by_payment_method" :key="m.payment_method">
                                    <td>{{ methodLabel(m.payment_method) }}</td><td class="text-center">{{ m.count }}</td><td class="text-end">${{ formatNumber(m.total) }}</td>
                                </tr></tbody>
                            </table>
                        </div><p v-else class="text-muted text-center py-4">Sin datos</p>
                    </div>
                </div></div>
            </div>

            <div class="row mb-4" v-if="serviceLineData.labels.length">
                <div class="col-12"><div class="card"><div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Servicios por Hora</h6>
                    <div class="position-relative" v-if="serviceIntervalData.products && serviceIntervalData.products.length">
                        <button class="btn btn-sm btn-outline-secondary" @click="showSvcFilter = !showSvcFilter">
                            <i class="bi bi-funnel me-1"></i>Filtrar ({{ selectedSvcs.length }}/{{ serviceIntervalData.products.length }})
                        </button>
                        <div v-if="showSvcFilter" class="filter-dropdown" @click.stop>
                            <label class="d-flex align-items-center gap-2 px-3 py-1 border-bottom">
                                <input type="checkbox" :checked="allSvcsSelected" @change="toggleAllSvcs">
                                <span class="small fw-semibold">Todos</span>
                            </label>
                            <label v-for="p in serviceIntervalData.products" :key="p.name" class="d-flex align-items-center gap-2 px-3 py-1">
                                <input type="checkbox" :checked="selectedSvcs.includes(p.name)" @change="toggleSvc(p.name)">
                                <span class="small">{{ p.name }}</span>
                            </label>
                        </div>
                    </div>
                </div><div class="card-body"><div style="height:350px">
                    <Line :data="serviceLineData" :options="serviceLineOptions" />
                </div></div></div></div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Bar, Doughnut, Line } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, PointElement, LineElement, Title, Tooltip, Legend, ArcElement, Filler } from 'chart.js';
import api from '../../../services/api';
import { toast } from '../../../utils/toast';

ChartJS.register(CategoryScale, LinearScale, BarElement, PointElement, LineElement, Title, Tooltip, Legend, ArcElement, Filler);

const loading = ref(true);
const exporting = ref(false);
const stats = ref({});
const salesChartData = ref({ labels: [], datasets: [] });
const serviceIntervalData = ref({ intervals: [], products: [] });
const showSvcFilter = ref(false);
const selectedSvcs = ref([]);

const startDate = ref(new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]);
const endDate = ref(new Date().toISOString().split('T')[0]);

const productQtyColors = ['#0d6efd','#dc3545','#198754','#ffc107','#0dcaf0','#6f42c1','#fd7e14','#20c997','#e83e8c','#17a2b8','#6610f2','#d63384','#14b8a6','#f97316','#84cc16','#06b6d4','#a855f7','#ec4899','#f59e0b','#8b5cf6'];

const getBarColors = (counts) => {
    const min = Math.min(...counts), max = Math.max(...counts);
    if (max === min) return counts.map(() => 'hsl(50,100%,55%)');
    return counts.map(c => { const r = max === min ? 0 : (c-min)/(max-min); return `hsl(${Math.round(50-r*50)},100%,${Math.round(60-r*10)}%)`; });
};

const servicesChartData = computed(() => {
    if (!stats.value.top_services || !stats.value.top_services.length) return { labels: [], datasets: [] };
    const svcs = stats.value.top_services;
    return { labels: svcs.map(s => s.name), datasets: [{ data: svcs.map(s => s.count), backgroundColor: svcs.map((_,i) => `hsl(${(i*360)/svcs.length},70%,60%)`), borderWidth: 1 }] };
});

const serviceLineOptions = {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 8, font: { size: 10 } } }, tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y} unidades` } } },
    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } }, x: { ticks: { maxRotation: 45, font: { size: 9 } } } }
};

const serviceLineData = computed(() => {
    if (!serviceIntervalData.value.products || !serviceIntervalData.value.products.length) return { labels: [], datasets: [] };
    const intervals = (serviceIntervalData.value.intervals || []).map(i => { const d = new Date(i+':00'); return d.toLocaleTimeString('es-AR', { hour:'2-digit', minute:'2-digit' }); });
    const sorted = [...serviceIntervalData.value.products].filter(p => selectedSvcs.value.includes(p.name)).sort((a,b) => b.data.reduce((s,v)=>s+v,0) - a.data.reduce((s,v)=>s+v,0));
    return { labels: intervals, datasets: sorted.map((p,i) => ({ label: p.name, data: p.data, borderColor: productQtyColors[i%productQtyColors.length], backgroundColor: productQtyColors[i%productQtyColors.length]+'33', pointBackgroundColor: productQtyColors[i%productQtyColors.length], pointRadius: 3, tension: 0.3, fill: false })) };
});

const allSvcsSelected = computed(() => serviceIntervalData.value.products?.length > 0 && selectedSvcs.value.length === serviceIntervalData.value.products.length);

const toggleSvc = (name) => { selectedSvcs.value = selectedSvcs.value.includes(name) ? selectedSvcs.value.filter(n => n !== name) : [...selectedSvcs.value, name]; };
const toggleAllSvcs = () => { selectedSvcs.value = selectedSvcs.value.length === serviceIntervalData.value.products?.length ? [] : (serviceIntervalData.value.products || []).map(p => p.name); };

const barOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => `$${Number(ctx.parsed.y).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` } } }, scales: { y: { beginAtZero: true } } };
const doughnutOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } };

const methodLabel = (m) => ({ cash: 'Efectivo', transfer: 'Transferencia', mercadopago: 'MercadoPago', other: 'Otro' }[m] || m);
const formatNumber = (n) => Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const loadData = async () => {
    loading.value = true;
    try {
        const params = { start_date: startDate.value, end_date: endDate.value };
        const [statsRes, salesRes, intervalRes] = await Promise.all([
            api.get('/hairsalon/statistics/summary', { params }),
            api.get('/hairsalon/statistics/sales-by-period', { params }),
            api.get('/hairsalon/statistics/services-by-interval', { params }),
        ]);
        stats.value = statsRes.data;
        serviceIntervalData.value = intervalRes.data;
        selectedSvcs.value = (intervalRes.data.products || []).map(p => p.name);

        const byInterval = {};
        salesRes.data.forEach(s => {
            const d = new Date(s.date);
            d.setMinutes(Math.floor(d.getMinutes() / 10) * 10, 0, 0);
            const key = d.toISOString().slice(0, 16);
            byInterval[key] = (byInterval[key] || 0) + Number(s.amount);
        });
        const labels = Object.keys(byInterval).map(k => { const d = new Date(k + ':00'); return d.toLocaleTimeString('es-AR', { hour:'2-digit', minute:'2-digit' }); });
        const amounts = Object.values(byInterval);
        salesChartData.value = { labels, datasets: [{ label: 'Ventas ($)', data: amounts, backgroundColor: getBarColors(amounts), borderRadius: 4 }] };
    } finally { loading.value = false; }
};

const exportData = async () => {
    exporting.value = true;
    try {
        const res = await api.get('/hairsalon/statistics/export', { params: { start_date: startDate.value, end_date: endDate.value }, responseType: 'blob' });
        const url = URL.createObjectURL(res.data);
        const a = document.createElement('a'); a.href = url; a.download = `estadisticas_${startDate.value}_${endDate.value}.csv`; a.click();
        URL.revokeObjectURL(url);
        toast.success('Archivo exportado');
    } catch (e) { toast.error('Error al exportar'); }
    finally { exporting.value = false; }
};

onMounted(() => { loadData(); });
</script>

<style scoped>
.hairsalon-statistics-container { height: 100%; padding: 1rem; overflow-y: auto; background: #f8f9fa; }
.stat-card { border-left: 4px solid #0d6efd; height: 100%; }
.card { box-shadow: 0 2px 4px rgba(0,0,0,.1); }
.card-header { background: white; border-bottom: 1px solid #dee2e6; font-weight: 600; }
.filter-dropdown { position: absolute; top: 100%; right: 0; z-index: 1050; background: white; border: 1px solid #dee2e6; border-radius: 0.375rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15); max-height: 300px; overflow-y: auto; width: 280px; padding: 0.5rem 0; }
.filter-dropdown label:hover { background: #f8f9fa; }
</style>
