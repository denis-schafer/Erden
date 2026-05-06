<template>
    <div class="order-display-container">
        <div class="order-display-card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="bi bi-receipt"></i> Pedido #{{ order?.id }}
                </h4>
                <span v-if="order" :class="getStatusClass(order.status_name)">
                    {{ order.status_name }}
                </span>
            </div>
            
            <div class="card-body">
                <div v-if="loading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
                
                <div v-else-if="error" class="alert alert-danger">
                    {{ error }}
                </div>
                
                <div v-else-if="order">
                    <div class="order-info mb-4">
                        <div class="row">
                            <div class="col-6">
                                <strong>Operador:</strong> {{ order.operator_name }}
                            </div>
                            <div class="col-6 text-end">
                                <strong>Fecha:</strong> {{ formatDate(order.created_at) }}
                            </div>
                        </div>
                    </div>
                    
                    <div class="order-items">
                        <h5 class="border-bottom pb-2">Items del Pedido</h5>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Cant.</th>
                                    <th>Producto</th>
                                    <th class="text-end">Precio</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in order.detail" :key="index">
                                    <td>{{ item.quantity }}</td>
                                    <td>{{ item.name }}</td>
                                    <td class="text-end">${{ formatPrice(item.price) }}</td>
                                    <td class="text-end">${{ formatPrice(item.quantity * item.price) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="order-total mt-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="mb-0">Total:</h3>
                            <h3 class="mb-0 text-success">${{ formatPrice(order.total) }}</h3>
                        </div>
                    </div>
                    
                    <div v-if="order.paid" class="mt-3">
                        <span class="badge bg-success fs-5">
                            <i class="bi bi-check-circle"></i> PAGADO
                        </span>
                    </div>
                    
                    <div class="order-selector mt-4">
                        <label for="orderSelect" class="form-label">Ver otro pedido:</label>
                        <select 
                            id="orderSelect" 
                            class="form-select" 
                            :value="order.id"
                            @change="changeOrder($event.target.value)"
                        >
                            <option v-for="opt in orders" :key="opt.id" :value="opt.id">
                                #{{ opt.id }} - ${{ formatPrice(opt.total) }} - {{ formatDate(opt.created_at) }} ({{ opt.status_name }})
                            </option>
                        </select>
                    </div>
                </div>
                
                <div v-else class="alert alert-warning">
                    No hay pedidos para mostrar
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();
const order = ref(null);
const orders = ref([]);
const loading = ref(true);
const error = ref(null);

const username = route.params.username;
const orderId = route.params.orderId;

const loadOrder = async () => {
    loading.value = true;
    error.value = null;
    
    try {
        let url = `/pos/order-display/${username}`;
        if (orderId && orderId !== 'null') {
            url += `/${orderId}`;
        }
        
        const response = await window.axios.get(url);
        order.value = response.data.order;
        orders.value = response.data.orders || [];
    } catch (err) {
        error.value = err.response?.data?.error || err.message;
        console.error('Error loading order:', err);
    } finally {
        loading.value = false;
    }
};

const changeOrder = (newOrderId) => {
    if (newOrderId) {
        window.location.href = `/pedido/${username}/${newOrderId}`;
    }
};

const formatPrice = (price) => {
    return parseFloat(price || 0).toFixed(2);
};

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const getStatusClass = (status) => {
    const statusMap = {
        'pending': 'badge bg-warning',
        'in_progress': 'badge bg-info',
        'completed': 'badge bg-success',
        'cancelled': 'badge bg-danger',
    };
    return statusMap[status?.toLowerCase()] || 'badge bg-secondary';
};

const handleOrderCreated = (event) => {
    loadOrder();
};

const handleOrderUpdated = (event) => {
    if (order.value && event.detail.order?.id === order.value.id) {
        loadOrder();
    }
};

onMounted(() => {
    loadOrder();
    window.addEventListener('pos-order-created', handleOrderCreated);
    window.addEventListener('pos-order-updated', handleOrderUpdated);
});

onUnmounted(() => {
    window.removeEventListener('pos-order-created', handleOrderCreated);
    window.removeEventListener('pos-order-updated', handleOrderUpdated);
});
</script>

<style scoped>
.order-display-container {
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.order-display-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    max-width: 600px;
    width: 100%;
    overflow: hidden;
}

.card-header {
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-body {
    padding: 20px;
}

.order-items table {
    margin-bottom: 0;
}

.order-items th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.order-total {
    border-top: 3px solid #28a745;
    padding-top: 15px;
}

.badge {
    font-size: 0.9rem;
    padding: 8px 12px;
}

.order-selector select {
    font-size: 1.1rem;
    padding: 10px;
}

@media (max-width: 576px) {
    .order-display-container {
        padding: 10px;
    }
    
    .order-display-card {
        border-radius: 10px;
    }
}
</style>