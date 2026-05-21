<template>
    <div class="pos-statistics-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Estadísticas</h4>
            <div class="d-flex gap-2">
                <select v-if="isAdmin" v-model="selectedUserId" class="form-select form-select-sm" style="width: 150px;">
                    <option :value="null">Todos</option>
                    <option v-for="user in users" :key="user.id" :value="user.id">
                        {{ user.name }}
                    </option>
                </select>
                <select v-model="selectedStatus" class="form-select form-select-sm" style="width: 140px;">
                    <option :value="null">Todos los estados</option>
                    <option value="1">Pendiente</option>
                    <option value="2">En Proceso</option>
                    <option value="3">Completado</option>
                    <option value="4">Cancelado</option>
                </select>
                <input type="date" v-model="startDate" class="form-control form-control-sm" style="width: 140px;" @change="loadData">
                <input type="date" v-model="endDate" class="form-control form-control-sm" style="width: 140px;" @change="loadData">
                <button class="btn btn-sm btn-primary" :disabled="loading" @click="loadData">
                    <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                    <i v-else class="bi bi-calculator me-1"></i>Calcular
                </button>
                <button class="btn btn-sm btn-success" :disabled="exporting" @click="exportData">
                    <span v-if="exporting" class="spinner-border spinner-border-sm me-1"></span>
                    <i v-else class="bi bi-download me-1"></i>Descargar Excel
                </button>
            </div>
        </div>

        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary"></div>
        </div>

        <div v-else>
            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <h6 class="text-muted">Total Pedidos</h6>
                            <h2 class="mb-0">{{ stats.total_orders || 0 }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <h6 class="text-muted">Ventas Totales</h6>
                            <h2 class="mb-0">${{ Number(stats.total_sales || 0).toFixed(2) }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <h6 class="text-muted">Ticket Promedio</h6>
                            <h2 class="mb-0">${{ Number(stats.avg_order || 0).toFixed(2) }}</h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Canceled Orders -->
            <div v-if="stats.canceled_orders > 0" class="row mb-4">
                <div class="col-md-6">
                    <div class="card stat-card-canceled">
                        <div class="card-body">
                            <h6 class="text-muted">Pedidos Cancelados</h6>
                            <h2 class="mb-0">{{ stats.canceled_orders }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card stat-card-canceled">
                        <div class="card-body">
                            <h6 class="text-muted">Monto Cancelado</h6>
                            <h2 class="mb-0">${{ Number(stats.canceled_amount || 0).toFixed(2) }}</h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales Chart and Top Products -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Ventas por Período (Intervalos de 10 min)</h6>
                        </div>
                        <div class="card-body">
                            <div v-if="salesChartData.labels && salesChartData.labels.length" style="height: 300px;">
                                <Bar :data="salesChartData" :options="barOptions" />
                            </div>
                            <p v-else class="text-muted text-center py-4">Sin datos para el gráfico</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="mb-0">Top Productos</h6>
                        </div>
                        <div class="card-body p-0">
                            <div v-if="topProducts.products && topProducts.products.length" class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th class="text-center">Cant.</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="product in topProducts.products" :key="product.name">
                                            <td>{{ product.name }}</td>
                                            <td class="text-center">{{ product.quantity }}</td>
                                            <td class="text-end">${{ Number(product.amount).toFixed(2) }}</td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th>Total</th>
                                            <th class="text-center">{{ topProducts.total_items || 0 }}</th>
                                            <th class="text-end">${{ Number(topProducts.total_amount || 0).toFixed(2) }}</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <p v-else class="text-muted text-center py-4">Sin datos</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Doughnut Chart -->
            <div class="row mb-4" v-if="topProducts.products && topProducts.products.length">
                <div class="col-md-6 offset-md-3">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Distribución de Productos (Cantidades)</h6>
                        </div>
                        <div class="card-body">
                            <div style="height: 300px;">
                                <Doughnut :data="productsChartData" :options="doughnutOptions" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Products Quantity by Interval Line Chart -->
            <div class="row mb-4" v-if="productLineData.labels.length">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Productos Vendidos cada 10 min</h6>
                            <div class="position-relative" v-if="productIntervalData.products.length">
                                <button class="btn btn-sm btn-outline-secondary" @click="showProductFilter = !showProductFilter">
                                    <i class="bi bi-funnel me-1"></i>Filtrar ({{ selectedProducts.length }}/{{ productIntervalData.products.length }})
                                </button>
                                <div v-if="showProductFilter" class="product-filter-dropdown" @click.stop>
                                    <label class="d-flex align-items-center gap-2 px-3 py-1 product-filter-item border-bottom">
                                        <input type="checkbox" :checked="allSelected" @change="toggleAll">
                                        <span class="small fw-semibold">Todos</span>
                                    </label>
                                    <label v-for="p in productIntervalData.products" :key="p.name" class="d-flex align-items-center gap-2 px-3 py-1 product-filter-item">
                                        <input type="checkbox" :checked="selectedProducts.includes(p.name)" @change="toggleProduct(p.name)">
                                        <span class="small">{{ p.name }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div style="height: 350px;">
                                <Line :data="productLineData" :options="productLineOptions" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Bar, Line, Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, PointElement, LineElement, Title, Tooltip, Legend, ArcElement, Filler } from 'chart.js';
import api from '../../../services/api';
import { toast as toastify } from '../../../utils/toast';
import { useAuthStore } from '../../../stores/auth';

const authStore = useAuthStore();

const isAdmin = computed(() => {
    return authStore.user?.role_id === 1;
});

ChartJS.register(CategoryScale, LinearScale, BarElement, PointElement, LineElement, Title, Tooltip, Legend, ArcElement, Filler);

const loading = ref(true);
const exporting = ref(false);
const stats = ref({});
const salesChartData = ref({ labels: [], datasets: [] });
const topProducts = ref({});
const productIntervalData = ref({ intervals: [], products: [] });
const users = ref([]);

const productQtyColors = [
    '#0d6efd', '#dc3545', '#198754', '#ffc107', '#0dcaf0',
    '#6f42c1', '#fd7e14', '#20c997', '#e83e8c', '#17a2b8',
    '#6610f2', '#d63384', '#14b8a6', '#f97316', '#84cc16',
    '#06b6d4', '#a855f7', '#ec4899', '#f59e0b', '#8b5cf6'
];

const startDate = ref(new Date(Date.now() - 3 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]);
const endDate = ref(new Date().toISOString().split('T')[0]);
const selectedUserId = ref(null);
const selectedStatus = ref(null);
const showProductFilter = ref(false);
const selectedProducts = ref([]);

const toggleProduct = (name) => {
    const idx = selectedProducts.value.indexOf(name);
    if (idx >= 0) {
        selectedProducts.value = selectedProducts.value.filter(n => n !== name);
    } else {
        selectedProducts.value = [...selectedProducts.value, name];
    }
};

const toggleAll = () => {
    if (selectedProducts.value.length === productIntervalData.value.products.length) {
        selectedProducts.value = [];
    } else {
        selectedProducts.value = productIntervalData.value.products.map(p => p.name);
    }
};

const allSelected = computed(() => {
    if (!productIntervalData.value.products.length) return false;
    return selectedProducts.value.length === productIntervalData.value.products.length;
});

const barOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx) => `${ctx.parsed.y} pedidos`
            }
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: { stepSize: 1 }
        }
    }
};

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'right' }
    }
};

const getBarColors = (counts) => {
    const min = Math.min(...counts);
    const max = Math.max(...counts);
    return counts.map(count => {
        if (max === min) return 'hsl(50, 100%, 55%)';
        const ratio = (count - min) / (max - min);
        const hue = Math.round(50 - (ratio * 50));
        const lightness = Math.round(60 - (ratio * 10));
        return `hsl(${hue}, 100%, ${lightness}%)`;
    });
};

const productsChartData = computed(() => {
    if (!topProducts.value.products || !topProducts.value.products.length) {
        return { labels: [], datasets: [] };
    }
    
    const products = topProducts.value.products;
    
    return {
        labels: products.map(p => p.name),
        datasets: [{
            data: products.map(p => p.quantity),
            backgroundColor: products.map((_, i) => `hsl(${(i * 360) / products.length}, 70%, 60%)`),
            borderWidth: 1
        }]
    };
});

const productLineOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                boxWidth: 12,
                padding: 8,
                font: { size: 10 }
            }
        },
        tooltip: {
            callbacks: {
                label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y} unidades`
            }
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: { stepSize: 1 }
        },
        x: {
            ticks: {
                maxRotation: 45,
                font: { size: 9 }
            }
        }
    }
};

const productLineData = computed(() => {
    if (!productIntervalData.value.products.length) {
        return { labels: [], datasets: [] };
    }

    const intervals = productIntervalData.value.intervals.map(i => {
        const d = new Date(i + ':00');
        return d.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
    });

    const sorted = [...productIntervalData.value.products]
        .filter(p => selectedProducts.value.includes(p.name))
        .sort((a, b) => b.data.reduce((s, v) => s + v, 0) - a.data.reduce((s, v) => s + v, 0));

    return {
        labels: intervals,
        datasets: sorted.map((p, i) => ({
            label: p.name,
            data: p.data,
            borderColor: productQtyColors[i % productQtyColors.length],
            backgroundColor: productQtyColors[i % productQtyColors.length] + '33',
            pointBackgroundColor: productQtyColors[i % productQtyColors.length],
            pointRadius: 3,
            tension: 0.3,
            fill: false
        }))
    };
});

const loadData = async () => {
    loading.value = true;
    try {
        const params = {
            start_date: startDate.value,
            end_date: endDate.value,
        };
        if (selectedUserId.value) params.user_id = selectedUserId.value;
        if (selectedStatus.value) params.status_id = selectedStatus.value;

        const [statsRes, salesRes, productsRes, intervalRes] = await Promise.all([
            api.get('/pos/statistics/summary', { params }),
            api.get('/pos/statistics/sales-by-period', { params }),
            api.get('/pos/statistics/top-products', { params }),
            api.get('/pos/statistics/products-by-interval', { params })
        ]);

        stats.value = statsRes.data;
        topProducts.value = productsRes.data;
        productIntervalData.value = intervalRes.data;
selectedProducts.value = intervalRes.data.products.map(p => p.name);

        // Process sales data for chart - group by 10 minute intervals
        const salesByInterval = {};
        salesRes.data.forEach(s => {
            const date = new Date(s.date);
            date.setMinutes(Math.floor(date.getMinutes() / 10) * 10, 0, 0);
            const intervalKey = date.toISOString().slice(0, 16);
            
            if (!salesByInterval[intervalKey]) {
                salesByInterval[intervalKey] = 0;
            }
            salesByInterval[intervalKey]++;
        });
        
        const labels = Object.keys(salesByInterval).map(key => {
            const d = new Date(key + ':00');
            return d.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
        });
        const counts = Object.values(salesByInterval);

        salesChartData.value = {
            labels,
            datasets: [{
                label: 'Pedidos',
                data: counts,
                backgroundColor: getBarColors(counts),
                borderRadius: 4
            }]
        };

    } catch (error) {
        toastify.error('Error al cargar estadísticas');
    } finally {
        loading.value = false;
    }
};

const loadUsers = async () => {
    if (!isAdmin.value) {
        users.value = [];
        return;
    }
    try {
        const response = await api.get('/pos/users');
        users.value = response.data;
    } catch (error) {
    }
};

const refreshStats = async () => {
    const params = {
        start_date: startDate.value,
        end_date: endDate.value,
    };
    if (selectedUserId.value) params.user_id = selectedUserId.value;
    if (selectedStatus.value) params.status_id = selectedStatus.value;

    try {
        const [statsRes, salesRes, productsRes, intervalRes] = await Promise.all([
            api.get('/pos/statistics/summary', { params }),
            api.get('/pos/statistics/sales-by-period', { params }),
            api.get('/pos/statistics/top-products', { params }),
            api.get('/pos/statistics/products-by-interval', { params })
        ]);

        stats.value = statsRes.data;
        topProducts.value = productsRes.data;
        productIntervalData.value = intervalRes.data;
selectedProducts.value = intervalRes.data.products.map(p => p.name);

        const salesByInterval = {};
        salesRes.data.forEach(s => {
            const date = new Date(s.date);
            date.setMinutes(Math.floor(date.getMinutes() / 10) * 10, 0, 0);
            const intervalKey = date.toISOString().slice(0, 16);
            if (!salesByInterval[intervalKey]) {
                salesByInterval[intervalKey] = 0;
            }
            salesByInterval[intervalKey]++;
        });

        const labels = Object.keys(salesByInterval).map(key => {
            const d = new Date(key + ':00');
            return d.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
        });
        const counts = Object.values(salesByInterval);

        salesChartData.value = {
            labels,
            datasets: [{
                label: 'Pedidos',
                data: counts,
                backgroundColor: getBarColors(counts),
                borderRadius: 4
            }]
        };
    } catch (error) {
    }
};

const exportData = async () => {
    exporting.value = true;
    try {
        const params = {
            start_date: startDate.value,
            end_date: endDate.value,
            selected_products: JSON.stringify(selectedProducts.value),
        };
        if (selectedUserId.value) params.user_id = selectedUserId.value;
        if (selectedStatus.value) params.status_id = selectedStatus.value;

        const response = await api.get('/pos/statistics/export', {
            params,
            responseType: 'blob',
        });

        const url = URL.createObjectURL(response.data);
        const a = document.createElement('a');
        a.href = url;
        a.download = `estadisticas_${startDate.value}_${endDate.value}.xlsx`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        toastify.success('Archivo Excel generado');
    } catch (error) {
        toastify.error('Error al exportar');
    } finally {
        exporting.value = false;
    }
};

onMounted(() => {
    loadUsers();
    if (!isAdmin.value && authStore.user?.id) {
        selectedUserId.value = authStore.user.id;
    }
    loadData();
    window.addEventListener('pos-order-created', refreshStats);
    window.addEventListener('pos-order-updated', refreshStats);
    window.addEventListener('pos-order-deleted', refreshStats);
});

onUnmounted(() => {
    window.removeEventListener('pos-order-created', refreshStats);
    window.removeEventListener('pos-order-updated', refreshStats);
    window.removeEventListener('pos-order-deleted', refreshStats);
});
</script>

<style scoped>
.pos-statistics-container {
    height: 100%;
    padding: 1rem;
    overflow-y: auto;
    background: #f8f9fa;
}

.stat-card {
    border-left: 4px solid #0d6efd;
    height: 100%;
}

.stat-card-canceled {
    border-left: 4px solid #dc3545;
    height: 100%;
}

.card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.card-header {
    background: white;
    border-bottom: 1px solid #dee2e6;
    font-weight: 600;
}

.product-legend .legend-color {
    width: 12px;
    height: 12px;
    border-radius: 2px;
    flex-shrink: 0;
    display: inline-block;
}

.product-filter-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    z-index: 1050;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
    max-height: 300px;
    overflow-y: auto;
    width: 280px;
    padding: 0.5rem 0;
}

.product-filter-item:hover {
    background: #f8f9fa;
}
</style>
