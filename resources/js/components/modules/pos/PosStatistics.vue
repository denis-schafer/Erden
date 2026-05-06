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
                <button class="btn btn-sm btn-primary" @click="loadData">
                    <i class="bi bi-calculator me-1"></i>Calcular
                </button>
                <button class="btn btn-sm btn-success" @click="exportData">
                    <i class="bi bi-download me-1"></i>Descargar Excel
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
                                <Line :data="salesChartData" :options="lineOptions" />
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
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { Line, Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler, ArcElement } from 'chart.js';
import * as XLSX from 'xlsx';
import api from '../../../services/api';
import { toast as toastify } from '../../../utils/toast';
import { useAuthStore } from '../../../stores/auth';

const authStore = useAuthStore();

const isAdmin = computed(() => {
    return authStore.user?.role_id === 1;
});

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler, ArcElement);

const loading = ref(true);
const stats = ref({});
const salesChartData = ref({ labels: [], datasets: [] });
const topProducts = ref({});
const users = ref([]);
const period = ref('day');

const startDate = ref(new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]);
const endDate = ref(new Date().toISOString().split('T')[0]);
const selectedUserId = ref(null);
const selectedStatus = ref(null);

const lineOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false }
    },
    scales: {
        y: { beginAtZero: true }
    }
};

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'right' }
    }
};

const productsChartData = computed(() => {
    if (!topProducts.value.products || !topProducts.value.products.length) {
        return { labels: [], datasets: [] };
    }
    
    const products = topProducts.value.products.slice(0, 10);
    const colors = [
        '#0d6efd', '#198754', '#dc3545', '#ffc107', '#6f42c1',
        '#20c997', '#fd7e14', '#6610f2', '#e83e8c', '#17a2b8'
    ];
    
    return {
        labels: products.map(p => p.name),
        datasets: [{
            data: products.map(p => p.quantity),
            backgroundColor: colors.slice(0, products.length),
            borderWidth: 1
        }]
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

        const [statsRes, salesRes, productsRes] = await Promise.all([
            api.get('/pos/statistics/summary', { params }),
            api.get('/pos/statistics/sales-by-period', { ...params, period: period.value }),
            api.get('/pos/statistics/top-products', { params })
        ]);

        stats.value = statsRes.data;
        topProducts.value = productsRes.data;

        // Process sales data for chart - group by 10 minute intervals
        const salesByInterval = {};
        salesRes.data.forEach(s => {
            const date = new Date(s.date);
            date.setMinutes(Math.floor(date.getMinutes() / 10) * 10, 0, 0);
            const intervalKey = date.toISOString().slice(0, 16);
            
            if (!salesByInterval[intervalKey]) {
                salesByInterval[intervalKey] = { total: 0, count: 0 };
            }
            salesByInterval[intervalKey].total += parseFloat(s.total);
            salesByInterval[intervalKey].count += s.orders;
        });
        
        const labels = Object.keys(salesByInterval).map(key => {
            const d = new Date(key + ':00');
            return d.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
        });
        const totals = Object.values(salesByInterval).map(s => s.total);

        salesChartData.value = {
            labels: labels,
            datasets: [{
                label: 'Ventas',
                data: totals,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                fill: true,
                tension: 0.3
            }]
        };

    } catch (error) {
        console.error('Error loading statistics:', error);
        toastify.error('Error al cargar estadísticas');
    } finally {
        loading.value = false;
    }
};

const loadSalesByPeriod = () => {
    const params = {
        start_date: startDate.value,
        end_date: endDate.value,
        period: period.value
    };
    if (selectedUserId.value) params.user_id = selectedUserId.value;
    if (selectedStatus.value) params.status_id = selectedStatus.value;

    api.get('/pos/statistics/sales-by-period', { params })
        .then(res => {
            const salesByInterval = {};
            res.data.forEach(s => {
                const date = new Date(s.date);
                date.setMinutes(Math.floor(date.getMinutes() / 10) * 10, 0, 0);
                const intervalKey = date.toISOString().slice(0, 16);
                
                if (!salesByInterval[intervalKey]) {
                    salesByInterval[intervalKey] = { total: 0, count: 0 };
                }
                salesByInterval[intervalKey].total += parseFloat(s.total);
                salesByInterval[intervalKey].count += s.orders;
            });
            
            const labels = Object.keys(salesByInterval).map(key => {
                const d = new Date(key + ':00');
                return d.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
            });
            const totals = Object.values(salesByInterval).map(s => s.total);
            
            salesChartData.value = {
                labels: labels,
                datasets: [{
                    label: 'Ventas',
                    data: totals,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            };
        })
        .catch(error => console.error('Error loading sales by period:', error));
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
        console.error('Error loading users:', error);
    }
};

const exportData = async () => {
    try {
        const params = {
            start_date: startDate.value,
            end_date: endDate.value,
        };
        if (selectedUserId.value) params.user_id = selectedUserId.value;
        if (selectedStatus.value) params.status_id = selectedStatus.value;

        const response = await api.get('/pos/statistics/export', { params });
        const data = response.data;
        
        const wb = XLSX.utils.book_new();
        
        // Sheet 1: Resumen
        const summarySheet = XLSX.utils.aoa_to_sheet(data.summary);
        XLSX.utils.book_append_sheet(wb, summarySheet, 'Resumen');
        
        // Sheet 2: Pedidos
        if (data.orders && data.orders.rows.length > 0) {
            const orderData = [data.orders.headers, ...data.orders.rows];
            const orderSheet = XLSX.utils.aoa_to_sheet(orderData);
            XLSX.utils.book_append_sheet(wb, orderSheet, 'Pedidos');
        }
        
        // Sheet 3: Productos
        if (data.products && data.products.rows.length > 0) {
            const productData = [data.products.headers, ...data.products.rows];
            const productSheet = XLSX.utils.aoa_to_sheet(productData);
            XLSX.utils.book_append_sheet(wb, productSheet, 'Productos');
        }
        
        const fileName = `estadisticas_${data.start_date}_${data.end_date}.xlsx`;
        XLSX.writeFile(wb, fileName);
        
        toastify.success('Archivo Excel generado');
    } catch (error) {
        console.error('Error exporting:', error);
        toastify.error('Error al exportar');
    }
};

onMounted(() => {
    loadUsers();
    if (!isAdmin.value && authStore.user?.id) {
        selectedUserId.value = authStore.user.id;
    }
    loadData();
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
</style>
