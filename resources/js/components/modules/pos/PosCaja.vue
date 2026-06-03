<template>
    <div class="pos-container">
        <div class="pos-body row g-0">
            <div class="col-9 pos-products-area">
                <div class="pos-categories-bar">
                    <button 
                        v-for="category in categories" 
                        :key="category.id"
                        class="category-tab"
                        :class="{ 'active': selectedCategory === category.id }"
                        @click="selectedCategory = category.id"
                    >
                        {{ category.name }}
                    </button>
                </div>

                <div class="pos-products">
                    <div 
                        v-for="product in filteredProducts" 
                        :key="product.id"
                        class="product-card"
                        :class="{ 'disabled': !product.enable, 'in-cart': getCartQuantity(product.id) > 0 }"
                        @click="addToCart(product)"
                    >
                        <div class="product-card-content">
                            <div class="product-name">{{ product.name }}</div>
                            <div class="product-price">${{ Number(product.amount).toFixed(2) }}</div>
                            <div v-if="!product.enable" class="product-no-stock">Sin stock</div>
                        </div>
                        <div class="product-actions" v-if="getCartQuantity(product.id) > 0">
                            <button 
                                class="btn btn-sm btn-outline-danger btn-action"
                                @click.stop="decrementFromProduct(product)"
                            >
                                <i class="bi bi-dash-lg"></i>
                            </button>
                            <span class="product-qty-badge">{{ getCartQuantity(product.id) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-3 pos-cart">
                <div class="cart-header d-flex justify-content-between align-items-center">
                    <span>
                        <span v-if="user">Pedido: {{ user.name }}</span>
                        <span v-else>Pedido Actual</span>
                    </span>
                    <button v-if="cart.length > 0" class="btn btn-sm btn-outline-danger" @click="clearCart">
                        Limpiar
                    </button>
                </div>
                <div class="cart-items">
                    <div v-for="item in cart" :key="item.id" class="cart-item">
                        <div class="cart-item-info">
                            <div class="cart-item-name">{{ item.name }}</div>
                            <div class="cart-item-price">${{ Number(item.amount).toFixed(2) }}</div>
                        </div>
                        <div class="cart-item-actions">
                            <button class="btn btn-sm btn-outline-secondary" @click="decrementQty(item)">-</button>
                            <span class="cart-item-qty">{{ item.qty }}</span>
                            <button class="btn btn-sm btn-outline-secondary" @click="incrementQty(item)">+</button>
                            <button class="btn btn-sm btn-outline-danger ms-2" @click="removeFromCart(item)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div v-if="cart.length === 0" class="text-center text-muted py-4">
                        Sin productos agregados
                    </div>
                </div>
                <div class="cart-footer">
                    <div class="cart-total">
                        <span>Total:</span>
                        <span class="total-amount">${{ Number(cartTotal).toFixed(2) }}</span>
                    </div>
                    <button 
                        class="btn w-100 btn-lg" 
                        :class="enablePrint ? 'btn-primary' : 'btn-success'"
                        :disabled="cart.length === 0 || isSaving"
                        @click="createOrder"
                    >
                        <i :class="enablePrint ? 'bi bi-printer me-2' : 'bi bi-check-circle me-2'"></i>
                        {{ isSaving ? 'Guardando...' : (enablePrint ? 'Imprimir Pedido' : 'Generar Pedido') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Fullscreen Modal -->
        <div v-if="showFullscreen" class="pos-fullscreen-modal">
            <div class="pos-fullscreen-content">
                <div class="pos-fullscreen-header">
                    <h5 class="mb-0">Caja</h5>
                    <div class="d-flex gap-2 align-items-center">
                        <div class="zoom-controls">
                            <input 
                                type="range" 
                                class="zoom-slider"
                                min="0.5" 
                                max="3" 
                                step="0.1" 
                                v-model="fontSizeScale"
                            >
                            <span class="zoom-level">{{ Math.round(fontSizeScale * 100) }}%</span>
                        </div>
                        <button class="btn btn-outline-primary" @click="showOrdersModal = true">
                            <i class="bi bi-receipt me-2"></i>Órdenes
                        </button>
                        <button class="btn btn-outline-secondary" @click="showFullscreen = false">
                            <i class="bi bi-x-lg"></i> Cerrar
                        </button>
                    </div>
                </div>
                <div class="pos-fullscreen-body">
                    <div class="row g-0 h-100">
                        <div class="col-12 pos-products-area">
                            <div class="pos-categories-bar">
                                <button 
                                    v-for="category in categories" 
                                    :key="category.id"
                                    class="category-tab"
                                    :class="{ 'active': selectedCategory === category.id }"
                                    @click="selectedCategory = category.id"
                                >
                                    {{ category.name }}
                                </button>
                            </div>
                            <div class="pos-products" :style="productsGridStyle">
                                <div 
                                    v-for="product in filteredProducts" 
                                    :key="product.id"
                                    class="product-card"
                                    :class="{ 'disabled': !product.enable, 'in-cart': getCartQuantity(product.id) > 0 }"
                                    :style="{ '--card-font-scale': fontSizeScale }"
                                    @click="addToCart(product)"
                                >
                                    <div class="product-card-content">
                                        <div class="product-name">{{ product.name }}</div>
                                        <div class="product-price">${{ Number(product.amount).toFixed(2) }}</div>
                                        <div v-if="!product.enable" class="product-no-stock">Sin stock</div>
                                    </div>
                                    <div class="product-actions" v-if="getCartQuantity(product.id) > 0">
                                        <button 
                                            class="btn btn-sm btn-outline-danger btn-action"
                                            @click.stop="decrementFromProduct(product)"
                                        >
                                            <i class="bi bi-dash-lg"></i>
                                        </button>
                                        <span class="product-qty-badge">{{ getCartQuantity(product.id) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="pos-fullscreen-footer">
                    <div class="d-flex align-items-center justify-content-between w-100 gap-2">
                        <button 
                            class="btn btn-primary flex-grow-1 btn-lg" 
                            @click="showCartModal = true"
                        >
                            <i class="bi bi-cart3 me-2"></i>Ver Pedido ({{ cart.length }})
                        </button>
                        <div class="pos-fullscreen-total flex-grow-1 text-center">
                            <span class="total-label">Total:</span>
                            <span class="total-amount">${{ Number(cartTotal).toFixed(2) }}</span>
                        </div>
                        <button 
                            class="btn flex-grow-1 btn-lg" 
                            :class="enablePrint ? 'btn-primary' : 'btn-success'"
                            :disabled="cart.length === 0 || isSaving"
                            @click="createOrder"
                        >
                            <i :class="enablePrint ? 'bi bi-printer me-2' : 'bi bi-check-circle me-2'"></i>
                            {{ isSaving ? 'Guardando...' : (enablePrint ? 'Imprimir Pedido' : 'Guardar Pedido') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cart Modal (for fullscreen mode) -->
        <div v-if="showCartModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5); z-index: 1060;" @click.self="showCartModal = false">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-height: 90vh;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <span v-if="user">Pedido: {{ user.name }}</span>
                            <span v-else>Pedido Actual</span>
                        </h5>
                        <button type="button" class="btn-close" @click="showCartModal = false"></button>
                    </div>
                    <div class="modal-body">
                        <div v-if="cart.length > 0">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th class="text-end">Precio</th>
                                        <th class="text-center">Cantidad</th>
                                        <th class="text-end">Subtotal</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in cart" :key="item.id">
                                        <td>{{ item.name }}</td>
                                        <td class="text-end">${{ Number(item.amount).toFixed(2) }}</td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center justify-content-center gap-2">
                                                <button class="btn btn-sm btn-outline-secondary" @click="decrementQty(item)">-</button>
                                                <span class="cart-item-qty">{{ item.qty }}</span>
                                                <button class="btn btn-sm btn-outline-secondary" @click="incrementQty(item)">+</button>
                                            </div>
                                        </td>
                                        <td class="text-end">${{ Number(item.amount * item.qty).toFixed(2) }}</td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-danger" @click="removeFromCart(item)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3" class="text-end">Total:</th>
                                        <th class="text-end">${{ Number(cartTotal).toFixed(2) }}</th>
                                        <th>
                                            <button v-if="cart.length > 0" class="btn btn-sm btn-outline-danger" @click="clearCart">
                                                Limpiar
                                            </button>
                                        </th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div v-else class="text-center text-muted py-4">
                            Sin productos agregados
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="showCartModal = false">Cerrar</button>
                        <button 
                            type="button" 
                            class="btn"
                            :class="enablePrint ? 'btn-primary' : 'btn-success'"
                            @click="createOrderFromCartModal"
                            :disabled="cart.length === 0 || isSaving"
                        >
                            <i :class="enablePrint ? 'bi bi-printer me-2' : 'bi bi-check-circle me-2'"></i>
                            {{ isSaving ? 'Guardando...' : (enablePrint ? 'Imprimir Pedido' : 'Guardar Pedido') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders Modal -->
        <div v-if="showOrdersModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5); z-index: 1060;" @click.self="showOrdersModal = false">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-height: 90vh;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Órdenes</h5>
                        <button type="button" class="btn-close" @click="showOrdersModal = false"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive position-relative">
                            <div v-if="loadingOrders" class="loading-overlay">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                            </div>
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                        <th>Pagado</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="order in orders" :key="order.id" :class="{ 'table-row-inactive': order.status_id === 2 }">
                                        <td>#{{ order.id }}</td>
                                        <td>${{ Number(order.total).toFixed(2) }}</td>
                                        <td>
                                            <span :class="order.status_id === 2 ? 'badge bg-secondary' : 'badge bg-info'">{{ order.status_name }}</span>
                                        </td>
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
                                                <button 
                                                    v-if="canViewQR"
                                                    class="btn btn-sm btn-success" 
                                                    :disabled="loadingActions[order.id + '_qr'] || order.paid || order.status_id === 3"
                                                    @click="viewOrderQRFromCaja(order)"
                                                    title="Ver QR">
                                                    <span v-if="loadingActions[order.id + '_qr']" class="spinner-border spinner-border-sm"></span>
                                                    <i v-else class="bi bi-qr-code"></i>
                                                </button>
                                                <button 
                                                    class="btn btn-sm btn-danger" 
                                                    :disabled="loadingActions[order.id + '_delete']"
                                                    @click="deleteOrder(order)"
                                                    title="Eliminar">
                                                    <span v-if="loadingActions[order.id + '_delete']" class="spinner-border spinner-border-sm"></span>
                                                    <i v-else class="bi bi-trash-fill"></i>
                                                </button>
                                            </div>
                                            <span v-else class="text-muted">Anulado</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="orders.length === 0 && !loadingOrders" class="text-center text-muted py-4">
                            No hay órdenes
                        </div>
                        <div v-if="ordersLastPage > 1" class="d-flex justify-content-between align-items-center mt-2">
                            <small class="text-muted">{{ ordersTotal }} órdenes</small>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item" :class="{ disabled: ordersPage === 1 }">
                                        <button class="page-link" @click="goToOrdersPage(1)" :disabled="ordersPage === 1">&laquo;</button>
                                    </li>
                                    <li class="page-item" :class="{ disabled: ordersPage === 1 }">
                                        <button class="page-link" @click="goToOrdersPage(ordersPage - 1)" :disabled="ordersPage === 1">&lsaquo;</button>
                                    </li>
                                    <li v-for="p in visibleOrdersPages" :key="p" class="page-item" :class="{ active: p === ordersPage }">
                                        <button class="page-link" @click="goToOrdersPage(p)">{{ p }}</button>
                                    </li>
                                    <li class="page-item" :class="{ disabled: ordersPage === ordersLastPage }">
                                        <button class="page-link" @click="goToOrdersPage(ordersPage + 1)" :disabled="ordersPage === ordersLastPage">&rsaquo;</button>
                                    </li>
                                    <li class="page-item" :class="{ disabled: ordersPage === ordersLastPage }">
                                        <button class="page-link" @click="goToOrdersPage(ordersLastPage)" :disabled="ordersPage === ordersLastPage">&raquo;</button>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Detail Modal -->
        <div v-if="showOrderDetail" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5); z-index: 9999;" @click.self="showOrderDetail = false">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Pedido #{{ selectedOrder?.id }}</h5>
                        <button type="button" class="btn-close" @click="showOrderDetail = false"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">Detalles del Pedido</h6>
                                        <div>
                                            <span class="badge bg-info me-2">{{ selectedOrder?.status_name }}</span>
                                            <span :class="selectedOrder?.paid ? 'badge bg-success' : 'badge bg-warning'">
                                                {{ selectedOrder?.paid ? 'Pagado' : 'Pendiente' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Producto</th>
                                                    <th class="text-end">Precio</th>
                                                    <th class="text-center">Cantidad</th>
                                                    <th class="text-end">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="item in orderItems" :key="item.id">
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
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
<div class="modal-footer">
                        <button v-if="enablePrint" type="button" class="btn btn-primary" @click="reprintOrder()">
                            <i class="bi bi-printer"></i> Reimprimir
                        </button>
                        <button type="button" class="btn btn-secondary" @click="showOrderDetail = false">cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmModal ref="confirmModal" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, inject, watch } from 'vue';
import api from '../../../services/api';
import { toast as toastify } from '../../../utils/toast';
import ConfirmModal from '../../../components/ConfirmModal.vue';
import { useAuthStore } from '../../../stores/auth';
import { useCache } from '../../../composables/useCache';

const authStore = useAuthStore();
const { fetch, refresh } = useCache();

// WebSocket listener for OrderPaid - refresh orders in modal automatically
let orderPaidListenerSetup = false;
const setupOrderPaidListener = () => {
    if (orderPaidListenerSetup || !window.Echo || !authStore.user?.id) return;
    
    const operatorId = authStore.user.id;
    
    window.Echo.channel(`user.${operatorId}`)
        .listen('.OrderPaid', (data) => {
            if (showOrdersModal.value) {
                loadOrders();
            }
        });
    
    orderPaidListenerSetup = true;
};

const user = ref(null);
const categories = ref([]);
const products = ref([]);
const selectedCategory = ref(null);
const cart = ref([]);
const isMobile = ref(window.innerWidth < 768);

// QR permission check
const canViewQR = computed(() => {
    return authStore.user?.mercadopago_qr_enabled;
});

// Filter orders for non-admin users
const isAdmin = computed(() => authStore.isGlobalAdmin || authStore.user?.role_id === 1);
const currentUserId = computed(() => authStore.user?.id);

const withLoading = async (orderId, action, cb) => {
    const key = orderId + '_' + action;
    loadingActions.value = { ...loadingActions.value, [key]: true };
    try {
        await cb();
    } finally {
        loadingActions.value = { ...loadingActions.value, [key]: false };
    }
};

// Config
const enablePrint = ref(false);
const confirmModal = ref(null);
const isSaving = ref(false);

// Modal states
const showFullscreen = ref(false);
const showCartModal = ref(false);
const showOrdersModal = ref(false);
const showOrderDetail = ref(false);

// Zoom controls
const fontSizeScale = ref(1);

const decreaseScale = () => {
    if (fontSizeScale.value > 0.5) {
        fontSizeScale.value = Math.round((fontSizeScale.value - 0.1) * 10) / 10;
    }
};

const increaseScale = () => {
    if (fontSizeScale.value < 3) {
        fontSizeScale.value = Math.round((fontSizeScale.value + 0.1) * 10) / 10;
    }
};

const productsGridStyle = computed(() => {
    if (!showFullscreen.value) return {};
    const scale = fontSizeScale.value;
    return {
        gridTemplateColumns: `repeat(auto-fill, minmax(${140 * scale}px, 1fr))`,
        gap: `${0.5 * scale}rem`
    };
});

// Orders data
const orders = ref([]);
const selectedOrder = ref(null);
const orderItems = ref([]);
const ordersPage = ref(1);
const ordersLastPage = ref(1);
const ordersTotal = ref(0);
const loadingOrders = ref(false);
const loadingActions = ref({});

const visibleOrdersPages = computed(() => {
    const pages = [];
    const start = Math.max(1, ordersPage.value - 2);
    const end = Math.min(ordersLastPage.value, ordersPage.value + 2);
    for (let i = start; i <= end; i++) {
        pages.push(i);
    }
    return pages;
});

const handleResize = () => {
    isMobile.value = window.innerWidth < 768;
};

const handleOpenFullscreen = () => {
    showFullscreen.value = true;
    loadOrders();
};

const handleConfigUpdate = (event) => {
    if (event.name === 'enable_print') {
        enablePrint.value = event.value === 'true' || event.value === true || event.value === '1' || event.value === 1;
    }
};

const handleUserSettingsUpdated = (event) => {
    enablePrint.value = event.enable_print === 1 || event.enable_print === true || event.enable_print === '1' || event.enable_print === 'true';
};

const handleProductUpdate = (event) => {
    const productIndex = products.value.findIndex(p => p.id === event.id);
    if (productIndex !== -1) {
        products.value[productIndex].enable = event.enable;
    }
};

const handleUserDisabled = (event) => {
    if (user.value && user.value.id === event.id) {
        toastify.error('Tu cuenta ha sido deshabilitada. Serás redirigido al login.');
        window.location.href = '/pos/login';
    }
};

onMounted(() => {
    window.addEventListener('resize', handleResize);
    window.addEventListener('open-pos-fullscreen', handleOpenFullscreen);
    window.addEventListener('pos-config-updated', (e) => handleConfigUpdate(e.detail));
    window.addEventListener('pos-product-updated', (e) => handleProductUpdate(e.detail));
    window.addEventListener('pos-user-disabled', (e) => handleUserDisabled(e.detail));
    window.addEventListener('pos-user-settings-updated', (e) => handleUserSettingsUpdated(e.detail));
    window.addEventListener('open-order-detail', () => {
        showOrdersModal.value = false;
    });
    window.closeOrdersModal = () => {
        showOrdersModal.value = false;
    };
    loadData();
    // Setup WebSocket listener for OrderPaid events
    setTimeout(setupOrderPaidListener, 1000);
});

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
    window.removeEventListener('open-pos-fullscreen', handleOpenFullscreen);
    window.removeEventListener('pos-config-updated', handleConfigUpdate);
    window.removeEventListener('pos-product-updated', handleProductUpdate);
    window.removeEventListener('pos-user-disabled', handleUserDisabled);
    window.removeEventListener('pos-user-settings-updated', handleUserSettingsUpdated);
});

const loadConfig = async () => {
    try {
        const userResponse = await api.get('/pos/user/current');
        const currentUser = userResponse.data;
        enablePrint.value = currentUser.enable_print === 1 || currentUser.enable_print === true || currentUser.enable_print === '1' || currentUser.enable_print === 'true';
    } catch (error) {
        enablePrint.value = false;
    }
};

const filteredProducts = computed(() => {
    if (!selectedCategory.value) return products.value;
    return products.value.filter(p => p.category_id === selectedCategory.value);
});

const cartTotal = computed(() => {
    return cart.value.reduce((sum, item) => sum + (item.amount * item.qty), 0);
});

const getCartQuantity = (productId) => {
    const item = cart.value.find(i => i.id === productId);
    return item ? item.qty : 0;
};

const addToCart = (product) => {
    if (!product.enable) return;
    
    const existing = cart.value.find(i => i.id === product.id);
    if (existing) {
        existing.qty++;
    } else {
        cart.value.push({
            id: product.id,
            name: product.name,
            amount: product.amount,
            qty: 1
        });
    }
};

const decrementFromProduct = (product) => {
    const item = cart.value.find(i => i.id === product.id);
    if (item) {
        if (item.qty > 1) {
            item.qty--;
        } else {
            removeFromCart(item);
        }
    }
};

const incrementQty = (item) => {
    item.qty++;
};

const decrementQty = (item) => {
    if (item.qty > 1) {
        item.qty--;
    } else {
        removeFromCart(item);
    }
};

const removeFromCart = (item) => {
    const index = cart.value.findIndex(i => i.id === item.id);
    if (index > -1) {
        cart.value.splice(index, 1);
    }
};

const clearCart = () => {
    cart.value = [];
};

const createOrder = async () => {
    if (cart.value.length === 0 || isSaving.value) return;

    isSaving.value = true;

    const items = cart.value.map(item => ({
        product_id: item.id,
        name: item.name,
        amount: item.amount,
        qty: item.qty
    }));

    try {
        await api.post('/pos/orders', {
            detail: { items, timestamp: new Date().toISOString() },
            total: cartTotal.value,
            operator_id: user.value.id,
            status_id: 1,
            paid: false
        });

        cart.value = [];
        toastify.success('Pedido generado correctamente');
        window.dispatchEvent(new CustomEvent('pos-order-created'));
    } catch (error) {
        toastify.error('Error al generar pedido: ' + (error.response?.data?.message || 'Error desconocido'));
    } finally {
        isSaving.value = false;
    }
};

const loadData = async () => {
    try {
        const [categoriesData, productsData, userRes] = await Promise.all([
            fetch('categories', () => api.get('/pos/categories').then(r => r.data)),
            fetch('products', () => api.get('/pos/products').then(r => r.data)),
            api.get('/pos/user/current'),
        ]);

        categories.value = categoriesData.filter(c => c.enable);
        products.value = productsData;
        user.value = userRes.data;

        const currentUser = userRes.data;
        enablePrint.value = currentUser.enable_print === 1 || currentUser.enable_print === true || currentUser.enable_print === '1' || currentUser.enable_print === 'true';

        const defaultCategory = categories.value.find(c => c.default);
        selectedCategory.value = defaultCategory ? defaultCategory.id : categories.value[0]?.id;
    } catch (error) {
        // Error loading data
    }
};

const loadCategories = async () => {
    try {
        const data = await refresh('categories', () => api.get('/pos/categories').then(r => r.data));
        categories.value = data.filter(c => c.enable);
        const defaultCategory = categories.value.find(c => c.default);
        selectedCategory.value = defaultCategory ? defaultCategory.id : categories.value[0]?.id;
    } catch (error) {
        // Error loading categories
    }
};

const loadProducts = async () => {
    try {
        products.value = await refresh('products', () => api.get('/pos/products').then(r => r.data));
    } catch (error) {
        // Error loading products
    }
};

const removeDisabledProductsFromCart = () => {
    const disabledProducts = products.value.filter(p => !p.enable);
    const disabledProductIds = disabledProducts.map(p => p.id);
    const initialCount = cart.value.length;
    cart.value = cart.value.filter(item => !disabledProductIds.includes(item.id));
    return initialCount - cart.value.length;
};

watch(() => showOrdersModal.value, (newVal) => {
    if (newVal) {
        loadOrders();
    }
});

window.addEventListener('pos-order-created', () => {
    if (showOrdersModal.value) {
        loadOrders();
    }
});

window.addEventListener('pos-order-updated', () => {
    if (showOrdersModal.value) {
        loadOrders();
    }
});

window.addEventListener('pos-category-changed', async () => {
    try {
        const data = await refresh('categories', () => api.get('/pos/categories').then(r => r.data));
        categories.value = data.filter(c => c.enable);
        const defaultCategory = categories.value.find(c => c.default);
        selectedCategory.value = defaultCategory ? defaultCategory.id : categories.value[0]?.id;
    } catch (error) {
    }
});

window.addEventListener('pos-product-changed', async (event) => {
    try {
        products.value = await refresh('products', () => api.get('/pos/products').then(r => r.data));
    } catch (error) {
    }
    setTimeout(() => {
        const removedCount = removeDisabledProductsFromCart();
        if (removedCount > 0) {
            toastify.info(`${removedCount} producto(s) deshabilitado(s) fueron eliminados del pedido`);
        }
    }, 100);
});

window.addEventListener('pos-product-reordered', async () => {
    try {
        products.value = await refresh('products', () => api.get('/pos/products').then(r => r.data));
    } catch (error) {
    }
});

const goToOrdersPage = (p) => {
    if (p < 1 || p > ordersLastPage.value || loadingOrders.value) return;
    ordersPage.value = p;
    loadOrders();
};

const loadOrders = async () => {
    loadingOrders.value = true;
    try {
        const params = { page: ordersPage.value, per_page: 10 };
        if (!isAdmin.value) {
            params.operator_id = currentUserId.value;
        }
        const response = await api.get('/pos/orders', { params });
        orders.value = response.data.data;
        ordersPage.value = response.data.current_page;
        ordersLastPage.value = response.data.last_page;
        ordersTotal.value = response.data.total;
    } catch (error) {
    } finally {
        loadingOrders.value = false;
    }
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
    showOrdersModal.value = false;
};

const reprintOrder = async (order = null) => {
    const orderToPrint = order || selectedOrder.value;
    if (!orderToPrint) {
        toastify.error('No hay orden para reimprimir');
        return;
    }
    const doReprint = async () => {
        await api.post(`/pos/orders/${orderToPrint.id}/reprint`);
        toastify.success('Ticket reimpreso exitosamente');
        showOrderDetail.value = false;
    };
    if (order) {
        await withLoading(order.id, 'reprint', doReprint);
    } else {
        try {
            await doReprint();
        } catch (error) {
            toastify.error('Error al reimprimir: ' + (error.response?.data?.message || 'Error desconocido'));
        }
    }
};

const viewOrderQRFromCaja = async (order) => {
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
                    await loadOrders();
                    toastify.success('Pedido eliminado');
                    window.dispatchEvent(new CustomEvent('pos-order-deleted'));
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
                    await loadOrders();
                    toastify.success(order.paid ? 'Pago desmarcado' : 'Pedido marcado como pagado');
                    window.dispatchEvent(new CustomEvent('pos-order-updated'));
                } catch (error) {
                    toastify.error('Error: ' + (error.response?.data?.message || 'Error desconocido'));
                }
            });
        }
    });
};

const createOrderFromDetail = async () => {
    await createOrder();
    showOrderDetail.value = false;
};

const createOrderFromCartModal = async () => {
    await createOrder();
    showCartModal.value = false;
};

const formatDate = (date) => {
    return new Date(date).toLocaleString();
};

const openFullscreen = () => {
    showFullscreen.value = true;
    loadOrders();
};

defineExpose({ openFullscreen });
</script>

<style scoped>
.pos-container {
    height: 100%;
    display: flex;
    flex-direction: column;
    background: #f8f9fa;
    overflow: hidden;
    box-sizing: border-box;
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}

.pos-header {
    display: flex;
    align-items: center;
    padding: 0.5rem 1rem;
    background: white;
    border-bottom: 1px solid #dee2e6;
    flex-shrink: 0;
}

.pos-categories-bar {
    display: flex;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: white;
    border-bottom: 1px solid #dee2e6;
    overflow-x: auto;
    flex-shrink: 0;
}

.category-tab {
    flex: 1;
    padding: 0.75rem 1rem;
    border: 1px solid #dee2e6;
    background: #f8f9fa;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s;
    text-align: center;
}

.category-tab:hover {
    background: #e9ecef;
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

.page-link {
    cursor: pointer;
}

.category-tab.active {
    background: #0d6efd;
    color: white;
    border-color: #0d6efd;
}

.pos-body {
    flex: 1;
    overflow: hidden;
    min-height: 0;
}

.pos-products-area {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-height: 0;
}

.pos-products-area .pos-categories-bar {
    flex-shrink: 0;
}

.pos-products {
    flex: 1;
    padding: 0.75rem;
    overflow-y: auto;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 0.5rem;
    align-content: start;
}

.product-card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 1rem 0.75rem;
    background: white;
    border-radius: 0.5rem;
    border: 2px solid #adb5bd;
    transition: all 0.2s;
    cursor: pointer;
}

.product-card .product-name {
    font-size: calc(1.1rem * var(--card-font-scale, 1));
}

.product-card .product-price {
    font-size: calc(1.25rem * var(--card-font-scale, 1));
}

.product-card .product-no-stock {
    font-size: calc(0.875rem * var(--card-font-scale, 1));
}

.product-card .product-actions {
    min-height: calc(36px * var(--card-font-scale, 1));
}

.product-card .btn-action {
    height: calc(36px * var(--card-font-scale, 1));
    font-size: calc(1.1rem * var(--card-font-scale, 1));
}

.product-card .product-qty-badge {
    font-size: calc(1.1rem * var(--card-font-scale, 1));
}

.product-card.in-cart {
    border-color: #0d6efd;
    background: #f0f7ff;
}

.product-card:hover {
    border-color: #0d6efd;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.product-card.disabled {
    opacity: 0.85;
    cursor: not-allowed;
    background: #e9ecef;
}

.product-card.disabled .product-card-content {
    opacity: 0.7;
}

.product-no-stock {
    color: #dc3545;
    font-size: 0.75rem;
    font-weight: bold;
    margin-top: 4px;
}

.product-card-content {
    text-align: center;
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.product-name {
    font-weight: 600;
    font-size: 1.1rem;
    color: #212529;
    line-height: 1.2;
}

.product-price {
    color: #0d6efd;
    font-weight: 700;
    font-size: 1.25rem;
    margin-top: 0.25rem;
}

.product-no-stock {
    color: #dc3545;
    font-size: 0.875rem;
    font-weight: 600;
    margin-top: 0.25rem;
}

.product-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0.5rem;
    border-top: 1px solid #e9ecef;
    margin-top: 0.5rem;
    min-height: 36px;
}

.btn-action {
    width: 50%;
    height: 36px;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.product-qty-badge {
    width: 50%;
    text-align: center;
    font-weight: 700;
    font-size: 1.1rem;
    color: #212529;
    background: white;
    border-radius: 0.25rem;
    padding: 0.25rem;
}

.pos-cart {
    background: white;
    border-left: 1px solid #dee2e6;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.cart-header {
    flex-shrink: 0;
    padding: 0.75rem;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.cart-items {
    flex: 1;
    overflow-y: auto;
    padding: 0.5rem;
}

.cart-footer {
    flex-shrink: 0;
    padding: 1rem;
    border-top: 1px solid #dee2e6;
    background: white;
}

.cart-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem;
    border-bottom: 1px solid #f0f0f0;
}

.cart-item-info {
    flex: 1;
}

.cart-item-name {
    font-size: 0.875rem;
    font-weight: 500;
}

.cart-item-price {
    font-size: 0.75rem;
    color: #6c757d;
}

.cart-item-actions {
    display: flex;
    align-items: center;
}

.cart-item-qty {
    width: 30px;
    text-align: center;
    font-weight: 500;
}

.cart-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    font-size: 1.25rem;
    font-weight: bold;
}

.total-amount {
    color: #0d6efd;
}

@media (max-width: 768px) {
    .pos-cart {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        width: 100%;
        height: 50vh;
        z-index: 100;
    }
    
    .pos-products {
        padding-bottom: 55vh;
    }
}

.pos-fullscreen-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: white;
    z-index: 1050;
    display: flex;
    flex-direction: column;
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}

.pos-fullscreen-content {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.pos-fullscreen-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: white;
    border-bottom: 1px solid #dee2e6;
}

.zoom-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: #f8f9fa;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    border: 1px solid #dee2e6;
    min-width: 160px;
}

.zoom-slider {
    width: 100px;
    cursor: pointer;
}

.zoom-level {
    min-width: 45px;
    text-align: center;
    font-weight: 600;
    font-size: 0.875rem;
}

.pos-fullscreen-body {
    flex: 1;
    overflow: hidden;
}

.pos-fullscreen-footer {
    padding: 1rem;
    background: white;
    border-top: 1px solid #dee2e6;
}

.pos-fullscreen-total {
    padding: 0.5rem 1rem;
    background: #f8f9fa;
    border-radius: 0.5rem;
    border: 1px solid #dee2e6;
}

.pos-fullscreen-total .total-label {
    font-size: 1rem;
    color: #6c757d;
    margin-right: 0.5rem;
}

.pos-fullscreen-total .total-amount {
    font-size: 1.5rem;
    font-weight: 700;
    color: #0d6efd;
}

.pos-fullscreen-body .pos-products-area,
.pos-fullscreen-body .pos-products {
    height: 100%;
}

.table-row-inactive {
    background-color: #f8d7da !important;
    opacity: 0.7;
}
</style>
