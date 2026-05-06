<template>
    <div class="pos-orders-container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Órdenes</h5>
            <div class="d-flex gap-2">
                <select v-model="statusFilter" class="form-select form-select-sm" style="width: auto;">
                    <option value="">Todos</option>
                    <option value="1">Pendiente</option>
                    <option value="2">En Proceso</option>
                    <option value="3">Completado</option>
                    <option value="inactivo">Anulado</option>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Operador</th>
                        <th>Pagado</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="order in filteredByUser" :key="order.id" :class="{ 'table-row-inactive': order.status_id === 2 }">
                        <td>#{{ order.id }}</td>
                        <td>{{ order.operator_name }}</td>
                        <td>
                            <span :class="order.paid ? 'badge bg-success' : 'badge bg-warning'">
                                {{ order.paid ? 'Sí' : 'No' }}
                            </span>
                        </td>
                        <td>{{ formatDate(order.created_at) }}</td>
                        <td>
                            <div class="d-flex gap-1" v-if="order.status_id !== 2">
                                <button class="btn btn-sm btn-primary" @click="viewOrderDetail(order)" title="Ver">
                                    <i class="bi bi-eye-fill"></i>
                                </button>
                                <button class="btn btn-sm btn-secondary" @click="reprintOrder(order)" title="Reimprimir">
                                    <i class="bi bi-printer-fill"></i>
                                </button>
                                <button v-if="hasMercadoQr" class="btn btn-sm btn-success" @click="viewOrderQR(order)" title="Ver QR" :disabled="order.paid || order.status_id === 3">
                                    <i class="bi bi-qr-code"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" @click="deleteOrder(order)" title="Eliminar">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            </div>
                            <span v-else class="text-muted">Anulado</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div v-if="orders.length === 0" class="text-center text-muted py-4">
            No hay órdenes
        </div>

        <!-- Order Detail Modal - always on top -->
        <div v-if="showOrderDetail" class="order-detail-modal" @click.self="closeDetail">
            <div class="order-detail-content">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Pedido #{{ selectedOrder?.id }}</h5>
                    <button type="button" class="btn-close" @click="closeDetail"></button>
                </div>
                <div class="mb-3">
                    <span class="badge bg-info me-2">{{ selectedOrder?.status_name }}</span>
                    <span :class="selectedOrder?.paid ? 'badge bg-success' : 'badge bg-warning'">
                        {{ selectedOrder?.paid ? 'Pagado' : 'Pendiente' }}
                    </span>
                </div>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th class="text-end">Precio</th>
                            <th class="text-center">Cant</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in orderItems" :key="item.name">
                            <td>{{ item.name }}</td>
                            <td class="text-end">${{ Number(item.amount).toFixed(2) }}</td>
                            <td class="text-center">{{ item.qty }}</td>
                            <td class="text-end">${{ Number(item.amount * item.qty).toFixed(2) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-end">Total:</th>
                            <th class="text-end">${{ Number(selectedOrder?.total || 0).toFixed(2) }}</th>
                        </tr>
                    </tfoot>
                </table>
                <div class="text-end mt-3">
                    <button type="button" class="btn btn-secondary" @click="closeDetail">Cerrar</button>
                </div>
            </div>
        </div>

        <ConfirmModal ref="confirmModal" />
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useAuthStore } from '../../../stores/auth';
import api from '../../../services/api';
import { toast as toastify } from '../../../utils/toast';
import ConfirmModal from '../../../components/ConfirmModal.vue';

const authStore = useAuthStore();
const orders = ref([]);
const statusFilter = ref(null);
const selectedOrder = ref(null);
const confirmModal = ref(null);
const orderItems = ref([]);
const showOrderDetail = ref(false);

// WebSocket listener for OrderPaid - refresh orders automatically
let orderPaidHandler = null;
const setupOrderPaidListener = () => {
    if (!window.Echo || !authStore.user?.id) return;
    
    const operatorId = authStore.user.id;
    console.log('[PosOrders] Setting up OrderPaid listener for user.' + operatorId);
    
    // Clean up previous listener if exists
    if (window.Echo.leaveChannel) {
        window.Echo.leaveChannel(`user.${operatorId}`);
    }
    
    window.Echo.channel(`user.${operatorId}`)
        .listen('.OrderPaid', (data) => {
            console.log('[PosOrders] OrderPaid received, reloading orders...', data);
            loadData();
        });
};

const handleOrderCreated = () => { loadData(); };
const handleOrderUpdated = () => { loadData(); };
const handleOrderDeleted = () => { loadData(); };

const hasMercadoQr = computed(() => {
    return authStore.user?.mercadopago_qr_enabled;
});

const filteredOrders = computed(() => {
    if (!statusFilter.value) return orders.value;
    return orders.value.filter(o => o.status_id == statusFilter.value);
});

const currentUserId = computed(() => authStore.user?.id);
const isCashier = computed(() => {
    return !authStore.isGlobalAdmin && !authStore.permissions?.includes('pos-users_read') && !authStore.permissions?.includes('pos-categories_read');
});

const filteredByUser = computed(() => {
    if (!isCashier.value) return filteredOrders.value;
    return filteredOrders.value.filter(o => o.operator_id === currentUserId.value);
});

const loadData = async () => {
    try {
        const response = await api.get('/pos/orders');
        orders.value = response.data;
    } catch (error) {
        console.error('Error loading orders:', error);
    }
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString();
};

const viewOrderDetail = (order) => {
    selectedOrder.value = order;
    const detail = order.detail;
    if (typeof detail === 'string') {
        try {
            const parsed = JSON.parse(detail);
            orderItems.value = parsed.items || [];
        } catch (e) {
            orderItems.value = [];
        }
    } else if (detail && detail.items) {
        orderItems.value = detail.items;
    } else {
        orderItems.value = [];
    }
    showOrderDetail.value = true;
};

const closeDetail = () => {
    showOrderDetail.value = false;
    selectedOrder.value = null;
    orderItems.value = [];
};

const reprintOrder = async (order) => {
    try {
        await api.post(`/pos/orders/${order.id}/reprint`);
        toastify.success('Ticket reimpreso exitosamente');
    } catch (error) {
        toastify.error('Error al reimprimir: ' + (error.response?.data?.message || 'Error desconocido'));
    }
};

const deleteOrder = (order) => {
    confirmModal.value.open({
        title: 'Eliminar Pedido',
        message: `¿Estás seguro de eliminar el pedido #${order.id}?`,
        confirmText: 'Eliminar',
        type: 'danger',
        onConfirm: async () => {
            try {
                await api.delete(`/pos/orders/${order.id}`);
                await loadData();
                toastify.success('Pedido eliminado');
            } catch (error) {
                toastify.error('Error al eliminar pedido: ' + (error.response?.data?.message || 'Error desconocido'));
            }
        }
    });
};

const viewOrderQR = async (order) => {
    const targetUserId = order.operator_id;
    const targetUsername = order.operator_username;
    
    try {
        await api.post('/pos/request-qr-order', {
            order_id: order.id,
            username: targetUsername,
            total: order.total,
            target_user_id: targetUserId
        });
        
        window.dispatchEvent(new CustomEvent('open-pos-qr-order', {
            detail: {
                orderId: order.id,
                username: targetUsername,
                total: order.total,
                target_user_id: targetUserId
            }
        }));
        
        toastify.success('QR generado');
    } catch (error) {
        console.error('Error requesting QR:', error);
        toastify.error('Error al generar QR');
    }
};

onMounted(() => {
    loadData();
    window.addEventListener('pos-order-created', handleOrderCreated);
    window.addEventListener('pos-order-updated', handleOrderUpdated);
    window.addEventListener('pos-order-deleted', handleOrderDeleted);
    // Setup WebSocket listener for OrderPaid events
    setTimeout(setupOrderPaidListener, 1000);
});

onUnmounted(() => {
    window.removeEventListener('pos-order-created', handleOrderCreated);
    window.removeEventListener('pos-order-updated', handleOrderUpdated);
    window.removeEventListener('pos-order-deleted', handleOrderDeleted);
});
</script>

<style scoped>
.pos-orders-container {
    height: 100%;
    display: flex;
    flex-direction: column;
    background: #f8f9fa;
    padding: 1rem;
    overflow: auto;
    position: relative;
}

.order-detail-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 99999;
}

.order-detail-content {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.table-row-inactive {
    background-color: #f8d7da !important;
    opacity: 0.7;
}
</style>