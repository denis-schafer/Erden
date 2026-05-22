<template>
    <div class="pos-orders-container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Órdenes</h5>
            <div class="d-flex gap-2">
                <select v-model="statusFilter" class="form-select form-select-sm" style="width: auto;" @change="goToPage(1)">
                    <option value="">Todos</option>
                    <option value="1">Pendiente</option>
                    <option value="2">En Proceso</option>
                    <option value="3">Completado</option>
                    <option value="inactivo">Anulado</option>
                </select>
            </div>
        </div>

        <div class="table-responsive position-relative">
            <div v-if="loading" class="loading-overlay">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            </div>
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
                    <tr v-for="order in orders" :key="order.id" :class="{ 'table-row-inactive': order.status_id === 2 }">
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
                                <button class="btn btn-sm btn-primary" :disabled="loadingActions[order.id + '_view']" @click="viewOrderDetail(order)" title="Ver">
                                    <span v-if="loadingActions[order.id + '_view']" class="spinner-border spinner-border-sm me-1"></span>
                                    <i v-else class="bi bi-eye-fill"></i>
                                </button>
                                <button v-if="order.status_id !== 2 && (isAdmin || !order.paid)"
                                    class="btn btn-sm"
                                    :class="order.paid ? 'btn-warning' : 'btn-success'"
                                    :disabled="loadingActions[order.id + '_togglePaid']"
                                    @click="togglePaid(order)"
                                    :title="order.paid ? 'Desmarcar como pagado' : 'Marcar como Pagado'">
                                    <span v-if="loadingActions[order.id + '_togglePaid']" class="spinner-border spinner-border-sm"></span>
                                    <i v-else class="bi" :class="order.paid ? 'bi-arrow-counterclockwise' : 'bi-cash'"></i>
                                </button>
                                <button class="btn btn-sm btn-secondary" :disabled="loadingActions[order.id + '_reprint']" @click="reprintOrder(order)" title="Reimprimir">
                                    <span v-if="loadingActions[order.id + '_reprint']" class="spinner-border spinner-border-sm"></span>
                                    <i v-else class="bi bi-printer-fill"></i>
                                </button>
                                <button v-if="hasMercadoQr" class="btn btn-sm btn-success" :disabled="loadingActions[order.id + '_qr'] || order.paid || order.status_id === 3" @click="viewOrderQR(order)" title="Ver QR">
                                    <span v-if="loadingActions[order.id + '_qr']" class="spinner-border spinner-border-sm"></span>
                                    <i v-else class="bi bi-qr-code"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" :disabled="loadingActions[order.id + '_delete']" @click="deleteOrder(order)" title="Eliminar">
                                    <span v-if="loadingActions[order.id + '_delete']" class="spinner-border spinner-border-sm"></span>
                                    <i v-else class="bi bi-trash-fill"></i>
                                </button>
                            </div>
                            <span v-else class="text-muted">Anulado</span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="orders.length === 0 && !loading" class="text-center text-muted py-4">
                No hay órdenes
            </div>
        </div>

        <div v-if="lastPage > 1" class="d-flex justify-content-between align-items-center mt-2">
            <small class="text-muted">{{ total }} órdenes</small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item" :class="{ disabled: page === 1 }">
                        <button class="page-link" @click="goToPage(1)" :disabled="page === 1">&laquo;</button>
                    </li>
                    <li class="page-item" :class="{ disabled: page === 1 }">
                        <button class="page-link" @click="goToPage(page - 1)" :disabled="page === 1">&lsaquo;</button>
                    </li>
                    <li v-for="p in visiblePages" :key="p" class="page-item" :class="{ active: p === page }">
                        <button class="page-link" @click="goToPage(p)">{{ p }}</button>
                    </li>
                    <li class="page-item" :class="{ disabled: page === lastPage }">
                        <button class="page-link" @click="goToPage(page + 1)" :disabled="page === lastPage">&rsaquo;</button>
                    </li>
                    <li class="page-item" :class="{ disabled: page === lastPage }">
                        <button class="page-link" @click="goToPage(lastPage)" :disabled="page === lastPage">&raquo;</button>
                    </li>
                </ul>
            </nav>
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

const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(false);
const loadingActions = ref({});

const visiblePages = computed(() => {
    const pages = [];
    const start = Math.max(1, page.value - 2);
    const end = Math.min(lastPage.value, page.value + 2);
    for (let i = start; i <= end; i++) {
        pages.push(i);
    }
    return pages;
});

const loadData = async () => {
    loading.value = true;
    try {
        const params = { page: page.value, per_page: 10 };
        if (statusFilter.value) {
            params.status = statusFilter.value;
        }
        if (isCashier.value) {
            params.operator_id = currentUserId.value;
        }
        const response = await api.get('/pos/orders', { params });
        orders.value = response.data.data;
        page.value = response.data.current_page;
        lastPage.value = response.data.last_page;
        total.value = response.data.total;
    } catch (error) {
    } finally {
        loading.value = false;
    }
};

const goToPage = (p) => {
    if (p < 1 || p > lastPage.value || loading.value) return;
    page.value = p;
    loadData();
};

const withLoading = async (orderId, action, cb) => {
    const key = orderId + '_' + action;
    loadingActions.value = { ...loadingActions.value, [key]: true };
    try {
        await cb();
    } finally {
        loadingActions.value = { ...loadingActions.value, [key]: false };
    }
};

// WebSocket listener for OrderPaid - refresh orders automatically
let orderPaidHandler = null;
const setupOrderPaidListener = () => {
    if (!window.Echo || !authStore.user?.id) return;
    
    const operatorId = authStore.user.id;
    
    window.Echo.channel(`user.${operatorId}`)
        .listen('.OrderPaid', (data) => {
            console.log('[PosOrders] OrderPaid received:', JSON.stringify({ orderId: data.order?.id }));
            loadData();
        });
};

const handleOrderCreated = () => { loadData(); };
const handleOrderUpdated = () => { loadData(); };
const handleOrderDeleted = () => { loadData(); };

const hasMercadoQr = computed(() => {
    return authStore.user?.mercadopago_qr_enabled;
});

const isAdmin = computed(() => authStore.isGlobalAdmin || authStore.user?.role_id === 1);
const currentUserId = computed(() => authStore.user?.id);
const isCashier = computed(() => {
    return !authStore.isGlobalAdmin && !authStore.permissions?.includes('pos-users_read') && !authStore.permissions?.includes('pos-categories_read');
});

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
    await withLoading(order.id, 'reprint', async () => {
        try {
            await api.post(`/pos/orders/${order.id}/reprint`);
            toastify.success('Ticket reimpreso exitosamente');
        } catch (error) {
            toastify.error('Error al reimprimir: ' + (error.response?.data?.message || 'Error desconocido'));
        }
    });
};

const deleteOrder = (order) => {
    confirmModal.value.open({
        title: 'Eliminar Pedido',
        message: `¿Estás seguro de eliminar el pedido #${order.id}?`,
        confirmText: 'Eliminar',
        type: 'danger',
        onConfirm: async () => {
            await withLoading(order.id, 'delete', async () => {
                try {
                    await api.delete(`/pos/orders/${order.id}`);
                    await loadData();
                    toastify.success('Pedido eliminado');
                } catch (error) {
                    toastify.error('Error al eliminar pedido: ' + (error.response?.data?.message || 'Error desconocido'));
                }
            });
        }
    });
};

const togglePaid = (order) => {
    const isAdminVal = isAdmin.value;
    const title = isAdminVal
        ? (order.paid ? 'Desmarcar como Pagado' : 'Marcar como Pagado')
        : 'Marcar como Pagado';
    const message = isAdminVal
        ? `¿Estás seguro de ${order.paid ? 'desmarcar' : 'marcar'} el pago del pedido #${order.id}?`
        : `¿Estás seguro de marcar el pedido #${order.id} como pagado?`;

    confirmModal.value.open({
        title,
        message,
        confirmText: 'Confirmar',
        type: order.paid ? 'warning' : 'success',
        onConfirm: async () => {
            await withLoading(order.id, 'togglePaid', async () => {
                try {
                    await api.post(`/pos/orders/${order.id}/toggle-paid`);
                    await loadData();
                    toastify.success(order.paid ? 'Pago desmarcado' : 'Pedido marcado como pagado');
                } catch (error) {
                    toastify.error('Error: ' + (error.response?.data?.message || 'Error desconocido'));
                }
            });
        }
    });
};

const viewOrderQR = async (order) => {
    await withLoading(order.id, 'qr', async () => {
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
            toastify.error('Error al generar QR');
        }
    });
};

onMounted(() => {
    loadData();
    window.addEventListener('pos-order-created', handleOrderCreated);
    window.addEventListener('pos-order-updated', handleOrderUpdated);
    window.addEventListener('pos-order-deleted', handleOrderDeleted);
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

.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255,255,255,0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
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

.pagination {
    gap: 0;
}

.page-link {
    cursor: pointer;
}
</style>
