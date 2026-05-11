<template>
    <div class="pos-dashboard-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Dashboard</h4>
            <div class="d-flex gap-2">
                <select v-model="selectedUserId" class="form-select form-select-sm" style="width: 150px;" @change="loadData" v-if="isAdmin">
                    <option :value="null">Todos</option>
                    <option v-for="cashier in cashiers" :key="cashier.id" :value="cashier.id">
                        {{ cashier.name }}
                    </option>
                </select>
                <input type="date" v-model="startDate" class="form-control form-control-sm" style="width: 140px;" @change="loadData">
                <input type="date" v-model="endDate" class="form-control form-control-sm" style="width: 140px;" @change="loadData">
            </div>
        </div>

        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary"></div>
        </div>

        <div v-else>
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card stat-card">
                        <div class="card-body">
                            <h6 class="text-muted">Pedidos</h6>
                            <h2 class="mb-0">{{ stats.total_orders }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card">
                        <div class="card-body">
                            <h6 class="text-muted">Ventas Total</h6>
                            <h2 class="mb-0">${{ Number(stats.total_sales || 0).toFixed(2) }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card">
                        <div class="card-body">
                            <h6 class="text-muted">Promedio/Pedido</h6>
                            <h2 class="mb-0">${{ Number(stats.avg_order || 0).toFixed(2) }}</h2>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="mb-0">Pedidos por Estado</h6>
                        </div>
                        <div class="card-body">
                            <Doughnut v-if="statusData.labels.length" :data="statusData" :options="doughnutOptions" />
                            <p v-else class="text-muted text-center">Sin datos</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="mb-0">Tendencia de Ventas (Últimos 7 días)</h6>
                        </div>
                        <div class="card-body">
                            <Bar v-if="trendData.labels.length" :data="trendData" :options="barOptions" />
                            <p v-else class="text-muted text-center">Sin datos</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Productos Más Vendidos</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="product in topProducts" :key="product.name">
                                <td>{{ product.name }}</td>
                                <td class="text-center">{{ product.qty }}</td>
                                <td class="text-end">${{ Number(product.total).toFixed(2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="topProducts.length === 0" class="text-muted text-center">Sin datos</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, reactive } from 'vue';
import { Doughnut, Bar } from 'vue-chartjs';
import { Chart as ChartJS, ArcElement, Tooltip, Legend, CategoryScale, LinearScale, BarElement, Title } from 'chart.js';
import api from '../../../services/api';
import { useAuthStore } from '../../../stores/auth';

ChartJS.register(ArcElement, Tooltip, Legend, CategoryScale, LinearScale, BarElement, Title);

const authStore = useAuthStore();
const loading = ref(true);
const stats = ref({});
const statusData = reactive({ labels: [], datasets: [] });
const trendData = reactive({ labels: [], datasets: [] });
const topProducts = ref([]);
const cashiers = ref([]);

const startDate = ref(new Date(Date.now() - 2 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]);
const endDate = ref(new Date().toISOString().split('T')[0]);
const selectedUserId = ref(null);

const isAdmin = computed(() => {
    return authStore.user?.role_id === 1;
});

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom' }
    }
};

const barOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false }
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: { stepSize: 1 }
        }
    }
};

const loadData = async () => {
    loading.value = true;
    try {
        const params = {
            start_date: startDate.value,
            end_date: endDate.value,
        };
        if (isAdmin.value && selectedUserId.value) {
            params.user_id = selectedUserId.value;
        }

        const [statsRes, statusRes, trendRes, productsRes] = await Promise.all([
            api.get('/pos/dashboard/stats', { params }),
            api.get('/pos/dashboard/by-status', { params }),
            api.get('/pos/dashboard/sales-trend', { params }),
            api.get('/pos/dashboard/top-products', { params })
        ]);

        stats.value = statsRes.data;

        const statusLabels = statusRes.data.map(s => s.status);
        const statusCounts = statusRes.data.map(s => s.count);
        const statusColors = ['#ffc107', '#17a2b8', '#28a745', '#dc3545', '#6c757d'];
        
        statusData.labels = statusLabels;
        statusData.datasets = [{
            data: statusCounts,
            backgroundColor: statusColors.slice(0, statusLabels.length)
        }];

        const trendLabels = trendRes.data.map(t => t.date);
        const trendOrders = trendRes.data.map(t => t.orders);
        
        trendData.labels = trendLabels;
        trendData.datasets = [{
            label: 'Pedidos',
            data: trendOrders,
            backgroundColor: '#0d6efd',
            borderRadius: 4
        }];

        topProducts.value = productsRes.data;

    } catch (error) {
        console.error('Error loading dashboard:', error);
    } finally {
        loading.value = false;
    }
};

const loadCashiers = async () => {
    if (isAdmin.value) {
        try {
            const res = await api.get('/pos/dashboard/cashiers');
            cashiers.value = res.data;
        } catch (error) {
            console.error('Error loading cashiers:', error);
        }
    }
};

const refreshStats = async () => {
    const params = {
        start_date: startDate.value,
        end_date: endDate.value,
    };
    if (isAdmin.value && selectedUserId.value) {
        params.user_id = selectedUserId.value;
    }

    try {
        const [statsRes, statusRes, trendRes, productsRes] = await Promise.all([
            api.get('/pos/dashboard/stats', { params }),
            api.get('/pos/dashboard/by-status', { params }),
            api.get('/pos/dashboard/sales-trend', { params }),
            api.get('/pos/dashboard/top-products', { params })
        ]);

        stats.value = statsRes.data;

        statusData.labels = statusRes.data.map(s => s.status);
        statusData.datasets = [{
            data: statusRes.data.map(s => s.count),
            backgroundColor: ['#ffc107', '#17a2b8', '#28a745', '#dc3545', '#6c757d'].slice(0, statusRes.data.length)
        }];

        trendData.labels = trendRes.data.map(t => t.date);
        trendData.datasets = [{
            label: 'Pedidos',
            data: trendRes.data.map(t => t.orders),
            backgroundColor: '#0d6efd',
            borderRadius: 4
        }];

        topProducts.value = productsRes.data;
    } catch (error) {
        console.error('Error refreshing dashboard:', error);
    }
};

const handleOrderCreated = () => { refreshStats(); };
const handleOrderUpdated = () => { refreshStats(); };
const handleOrderDeleted = () => { refreshStats(); };

onMounted(() => {
    loadCashiers();
    loadData();
    window.addEventListener('pos-order-created', handleOrderCreated);
    window.addEventListener('pos-order-updated', handleOrderUpdated);
    window.addEventListener('pos-order-deleted', handleOrderDeleted);
});

onUnmounted(() => {
    window.removeEventListener('pos-order-created', handleOrderCreated);
    window.removeEventListener('pos-order-updated', handleOrderUpdated);
    window.removeEventListener('pos-order-deleted', handleOrderDeleted);
});
</script>

<style scoped>
.pos-dashboard-container {
    height: 100%;
    padding: 1rem;
    overflow-y: auto;
    background: #f8f9fa;
}

.stat-card {
    border-left: 4px solid #0d6efd;
}

.stat-card h2 {
    color: #0d6efd;
}

.card-header {
    background: #fff;
    border-bottom: 1px solid #e9ecef;
}
</style>