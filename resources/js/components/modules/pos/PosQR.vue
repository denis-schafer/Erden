<template>
    <div class="pos-order-display h-100 d-flex flex-column">
        <!-- Header -->
        <div class="order-header">
            <h5 class="mb-0">
                <i class="bi bi-receipt"></i> {{ order ? `Pedido: #${order.id}` : 'QR Payment' }}
            </h5>
            <div class="d-flex gap-2 align-items-center">
                <!-- User selector for admin -->
                <div v-if="isAdmin" class="d-flex align-items-center gap-1">
                    <select v-model="selectedUsername" @change="loadOrder" class="form-select form-select-sm" :disabled="usersLoading">
                        <option value="">Seleccionar usuario</option>
                        <option v-for="user in users" :key="user.username" :value="user.username">
                            {{ user.name || user.username }}
                        </option>
                    </select>
                    <span v-if="usersLoading" class="spinner-border spinner-border-sm text-light"></span>
                </div>

            </div>
        </div>
        
        <!-- Fullscreen Modal -->
        <div v-if="isFullscreen" class="qr-fullscreen-modal">
            <div class="qr-fullscreen-content">
                <div class="qr-fullscreen-header">
                    <h5 class="mb-0">{{ order ? `Pedido: #${order.id}` : 'QR Payment' }}</h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-danger" @click="closeOrder">
                            Cancelar
                        </button>
                        <button class="btn btn-outline-light" @click="toggleFullscreen">
                            Cerrar
                        </button>
                    </div>
                </div>
                <div class="qr-fullscreen-body">
                    <!-- Vista de pago exitoso -->
                    <div v-if="paymentSuccess" class="success-view text-center py-5">
                        <div class="mb-3">
                            <i class="bi bi-check-circle-fill" style="font-size: 4rem; color: #00A650;"></i>
                        </div>
                        <h3 style="color: #00A650; font-weight: 700;">¡Pago Realizado!</h3>
                        <p class="lead text-white">Orden #{{ closedOrderId || order?.id }} pagada correctamente</p>
                    </div>
                    <!-- Publicidad cuando no hay orden -->
                    <div v-else-if="!order" class="advertisement-section">
                        <div class="ad-logo">
                            <div class="ad-logo mb-4" v-html="logoSvg"></div>
                        </div>
                        <div class="ad-text">Desarrollo de Software</div>
                        <div class="ad-footer">
                            Denis Schafer | tel: +54 291 4357999
                        </div>
                    </div>
                    <!-- Orden no disponible para pago -->
                    <div v-else-if="order && order.status_id !== 1" class="order-card text-center">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle fs-2"></i>
                            <h5 class="mt-2">Orden no disponible para pago</h5>
                            <p class="mb-0">Esta orden ya ha sido pagada o cancelada.</p>
                            <small class="text-muted">Status: {{ order.status_name }}</small>
                        </div>
                        <div class="order-info mb-3">
                            <div class="d-flex justify-content-between">
                                <span><strong>#{{ order.id }}</strong></span>
                                <span :class="getStatusClass(order.status_name)">{{ order.status_name }}</span>
                            </div>
                        </div>
                    </div>
                    <!-- Loading -->
                    <div v-else-if="loading" class="d-flex justify-content-center align-items-center h-100">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                    </div>
                    <!-- QR Section -->
                    <div v-else-if="order && order.status_id === 1" class="order-card">
                        <div v-if="qrCode" class="qr-section mb-3 text-center">
                            <div class="qr-label mb-2">Escanea con MercadoPago</div>
                            <img :src="qrCode" alt="QR MercadoPago" class="qr-image" />
                            <div class="qr-total">Total: <span class="text-mp-green">${{ formatPrice(order.total) }}</span></div>
                        </div>
                        <div v-else-if="qrLoading" class="qr-section mb-3 text-center">
                            <div class="spinner-border" style="color: #3483FA;" role="status">
                                <span class="visually-hidden">Generando QR...</span>
                            </div>
                            <div class="small mt-2" style="color: #3483FA;">Generando QR...</div>
                        </div>
                        <div v-else-if="qrError" class="qr-section mb-3 text-center">
                            <div class="text-danger small">{{ qrError }}</div>
                        </div>
                        <div v-if="showManualCheck" class="text-center mb-3">
                            <button @click="manualCheck" :disabled="loadingCheck" class="btn btn-outline-primary btn-sm">
                                <span v-if="loadingCheck" class="spinner-border spinner-border-sm me-1"></span>
                                {{ loadingCheck ? 'Verificando...' : 'Verificar pago manualmente' }}
                            </button>
                        </div>
                        <div class="order-info mb-3">
                            <div class="d-flex justify-content-between">
                                <span><strong>#{{ order.id }}</strong></span>
                                <span :class="getStatusClass(order.status_name)">{{ order.status_name }}</span>
                            </div>
                            <div class="text-muted small">
                                {{ order.operator_name }} - {{ formatDate(order.created_at) }}
                            </div>
                        </div>
                        <div class="order-items mb-3">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Cant.</th>
                                        <th>Producto</th>
                                        <th class="text-end">Precio</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, index) in (order.detail?.items || order.detail || [])" :key="index">
                                        <td>1</td>
                                        <td>{{ item.name }}</td>
                                        <td class="text-end">${{ formatPrice(item.amount) }}</td>
                                        <td class="text-end">${{ formatPrice(item.amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="order-total">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="h4 mb-0">Total:</span>
                                <span class="h3 mb-0 text-success">${{ formatPrice(order.total) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Normal View -->
        <div class="order-content flex-grow-1 overflow-auto p-3" v-else>
            <!-- Vista de pago exitoso -->
            <div v-if="paymentSuccess" class="success-view text-center py-5">
                <div class="mb-3">
                    <i class="bi bi-check-circle-fill" style="font-size: 4rem; color: #00A650;"></i>
                </div>
                <h3 style="color: #00A650; font-weight: 700;">¡Pago Realizado!</h3>
                <p class="lead" style="color: #333;">Orden #{{ closedOrderId || order?.id }} pagada correctamente</p>
            </div>
            
            <!-- Publicidad cuando no hay orden -->
            <div v-else-if="!order" class="advertisement-section h-100 d-flex flex-column justify-content-center align-items-center text-center">
                <div class="ad-logo mb-4">
                    <div class="ad-logo mb-4" v-html="logoSvg"></div>
                </div>
                <div class="ad-text h4 mb-4">Desarrollo de Software</div>
                <div class="ad-footer">
                    Denis Schafer | tel: +54 291 4357999
                </div>
            </div>
            
            <!-- Orden no disponible para pago (status_id !== 1 y no es success) -->
            <div v-else-if="order && order.status_id !== 1" class="order-card text-center">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle fs-2"></i>
                    <h5 class="mt-2">Orden no disponible para pago</h5>
                    <p class="mb-0">Esta orden ya ha sido pagada o cancelada.</p>
                    <small class="text-muted">Status: {{ order.status_name }}</small>
                </div>
                <div class="order-info mb-3">
                    <div class="d-flex justify-content-between">
                        <span><strong>#{{ order.id }}</strong></span>
                        <span :class="getStatusClass(order.status_name)">{{ order.status_name }}</span>
                    </div>
                </div>
            </div>
            
            <!-- Loading -->
            <div v-else-if="loading" class="d-flex justify-content-center align-items-center" style="min-height: 200px;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            </div>
            
            <!-- QR Section para orden pending -->
            <div v-else-if="order && order.status_id === 1" class="order-card">
                <div v-if="error" class="alert alert-danger">{{ error }}</div>
                
                <div v-if="qrCode" class="qr-section mb-3 text-center">
                    <div class="qr-label mb-2">Escanea con MercadoPago</div>
                    <img :src="qrCode" alt="QR MercadoPago" class="qr-image" />
                    <div class="qr-total">Total: <span class="text-mp-green">${{ formatPrice(order.total) }}</span></div>
                </div>
                <div v-else-if="qrLoading" class="qr-section mb-3 text-center">
                    <div class="spinner-border" style="color: #3483FA;" role="status">
                        <span class="visually-hidden">Generando QR...</span>
                    </div>
                    <div class="small mt-2" style="color: #3483FA;">Generando QR...</div>
                </div>
                <div v-else-if="qrError" class="qr-section mb-3 text-center">
                    <div class="text-danger small">{{ qrError }}</div>
                </div>
                
                <div v-if="showManualCheck" class="text-center mb-3">
                    <button @click="manualCheck" :disabled="loadingCheck" class="btn btn-outline-primary">
                        <span v-if="loadingCheck" class="spinner-border spinner-border-sm me-1"></span>
                        {{ loadingCheck ? 'Verificando...' : 'Verificar pago manualmente' }}
                    </button>
                </div>
                
                <div class="order-info mb-3">
                    <div class="d-flex justify-content-between">
                        <span><strong>#{{ order.id }}</strong></span>
                        <span :class="getStatusClass(order.status_name)">{{ order.status_name }}</span>
                    </div>
                    <div class="text-muted small">
                        {{ order.operator_name }} - {{ formatDate(order.created_at) }}
                    </div>
                </div>
                
                <div class="order-items mb-3">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Cant.</th>
                                <th>Producto</th>
                                <th class="text-end">Precio</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in (order.detail?.items || order.detail || [])" :key="index">
                                <td>1</td>
                                <td>{{ item.name }}</td>
                                <td class="text-end">${{ formatPrice(item.amount) }}</td>
                                <td class="text-end">${{ formatPrice(item.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="order-total">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="h4 mb-0">Total:</span>
                        <span class="h3 mb-0 text-success">${{ formatPrice(order.total) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';
import api from '@/services/api';

const authStore = useAuthStore();
const toastStore = useToastStore();
const logoSvg = ref('');

// Cargar el logo desde public
const loadLogo = async () => {
    try {
        const response = await fetch('/img/logo.svg');
        const text = await response.text();
        logoSvg.value = text;
    } catch (e) {

    }
};

const order = ref(null);
const orders = ref([]);
const users = ref([]);
const usersLoading = ref(false);
const loading = ref(true);
const error = ref(null);
const isFullscreen = ref(false);
const isMobile = ref(window.innerWidth < 768);

const qrCode = ref(null);
const qrLoading = ref(false);
const qrError = ref(null);
const paymentSuccess = ref(false);
const successTimeout = ref(null);
const closedOrderId = ref(null);
const paymentPollTimer = ref(null);
const polling = ref(false);
const showManualCheck = ref(false);
const loadingCheck = ref(false);

const selectedUsername = ref('');
const currentUsername = authStore.user?.username;

const isAdmin = computed(() => authStore.isGlobalAdmin);

const username = computed(() => selectedUsername.value || currentUsername);

const isBrowserFullscreen = ref(false);
const wakeLock = ref(null);

const toggleBrowserFullscreen = async () => {
    try {
        if (!document.fullscreenElement && !document.webkitFullscreenElement) {
            // Entrar en fullscreen con navigationUI: "hide"
            const elem = document.documentElement;
            if (elem.requestFullscreen) {
                // Intentar con la opción navigationUI (funciona en algunos navegadores)
                await elem.requestFullscreen({ navigationUI: 'hide' });
            } else if (elem.webkitRequestFullscreen) {
                // Safari/Chrome legacy
                await elem.webkitRequestFullscreen();
            }
            
            // Intentar mantener pantalla activa (Screen Wake Lock)
            if ('wakeLock' in navigator) {
                try {
                    wakeLock.value = await navigator.wakeLock.request('screen');
                } catch (err) {
                }
            }
        } else {
            // Salir de fullscreen
            if (document.exitFullscreen) {
                await document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                await document.webkitExitFullscreen();
            }
            
            // Liberar Wake Lock
            if (wakeLock.value) {
                await wakeLock.value.release();
                wakeLock.value = null;
            }
        }
    } catch (err) {

    }
};

// Listen for fullscreen changes
const handleFullscreenChange = () => {
    isBrowserFullscreen.value = !!document.fullscreenElement || !!document.webkitFullscreenElement;
    
    // Si salimos de fullscreen manualmente, liberar wake lock
    if (!isBrowserFullscreen.value && wakeLock.value) {
        wakeLock.value.release();
        wakeLock.value = null;
    }
};

onMounted(() => {
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
});

window.addEventListener('resize', () => {
    isMobile.value = window.innerWidth < 768;
});

const loadUsers = async () => {
    usersLoading.value = true;
    try {
        const response = await api.get('/pos/users');
        users.value = response.data.users || [];
    } catch (err) {

    } finally {
        usersLoading.value = false;
    }
};

const loadOrder = async () => {
    loading.value = true;
    error.value = null;
    qrCode.value = null;
    qrError.value = null;
    paymentSuccess.value = false;
    closedOrderId.value = null;
    
    try {
        const response = await api.get(`/pos/order-display/${username.value}`);
        order.value = response.data.order;
        orders.value = response.data.orders || [];
        
        // Solo generar QR si la orden está en status pending (status_id = 1)
        if (order.value && order.value.id) {
            if (order.value.status_id === 1) {
                generateQR();
            } else {
                // Orden no disponible para pago
                qrCode.value = null;
            }
        }
    } catch (err) {
        error.value = err.response?.data?.error || 'Error al cargar pedido';
    } finally {
        loading.value = false;
    }
};

const generateQR = async () => {
    if (!order.value || !order.value.id) return;
    if (qrLoading.value || qrCode.value) return;
    
    qrLoading.value = true;
    qrError.value = null;
    qrCode.value = null;
    
    try {
        const companyDb = localStorage.getItem('companyDb');
        const userId = order.value.operator_id || authStore.user?.id;
        
        const response = await api.post('/pos/generate-mp-qr', {
            order_id: order.value.id,
            amount: order.value.total,
            description: `Pedido #${order.value.id}`,
            company_db: companyDb,
            user_id: userId
        });
        
        if (response.data.success) {
            qrCode.value = response.data.qr_base64;
            startPaymentPolling();
        } else {
            qrError.value = response.data.message || 'Error al generar QR';
        }
    } catch (err) {
        qrError.value = err.response?.data?.message || 'Error al generar QR';

    } finally {
        qrLoading.value = false;
    }
};

const toggleFullscreen = () => {
    isFullscreen.value = false;
};

const closeOrder = () => {
    stopPaymentPolling();
    order.value = null;
    qrCode.value = null;
    closedOrderId.value = null;
    paymentSuccess.value = false;
};

const showPaymentSuccess = () => {
    stopPaymentPolling();
    if (paymentSuccess.value) return;
    const currentOrderId = order.value?.id;
    if (!currentOrderId) return;
    console.log('[PosQR] showPaymentSuccess called, orderId:', currentOrderId);
    
    paymentSuccess.value = true;
    qrCode.value = null;
    closedOrderId.value = currentOrderId;
    
    toastStore.success(`¡Pago completado! Orden #${currentOrderId}`);
    
    window.dispatchEvent(new CustomEvent('pos-order-updated'));
    
    setTimeout(() => {
        closeOrder();
    }, 3000);
};

const POLL_MAX_MS = 180000; // 3 minutos
const POLL_INTERVAL_MS = 1000; // 1 segundo

const startPaymentPolling = () => {
    stopPaymentPolling();
    const orderId = order.value?.id;
    if (!orderId) return;

    const startTime = Date.now();
    polling.value = true;
    showManualCheck.value = false;

    paymentPollTimer.value = setInterval(async () => {
        if (Date.now() - startTime > POLL_MAX_MS) {
            stopPaymentPolling();
            showManualCheck.value = true;
            toastStore.info('Tiempo de espera agotado. Verifique el pago manualmente.');
            return;
        }

        try {
            const res = await api.get(`/pos/orders/${orderId}/payment-status`);
            if (res.data.paid) {
                stopPaymentPolling();
                if (paymentSuccess.value) return;
                showPaymentSuccess();
            }
        } catch (err) {
            console.error('[PosQR] Payment poll error:', err);
        }
    }, POLL_INTERVAL_MS);
};

const stopPaymentPolling = () => {
    polling.value = false;
    showManualCheck.value = false;
    if (paymentPollTimer.value) {
        clearInterval(paymentPollTimer.value);
        paymentPollTimer.value = null;
    }
};

const manualCheck = async () => {
    if (!order.value?.id) return;
    loadingCheck.value = true;
    try {
        const res = await api.get(`/pos/orders/${order.value.id}/payment-status`);
        if (res.data.paid) {
            showPaymentSuccess();
        } else {
            toastStore.info('Pago no detectado. Intente nuevamente o verifique en el módulo Órdenes.');
        }
    } catch (err) {
        toastStore.error('Error al verificar pago.');
    } finally {
        loadingCheck.value = false;
    }
};

// Listen for when admin disables QR for user - close the order and show advertisement
window.addEventListener('pos-user-disabled', (e) => {
    const currentUserId = authStore.user?.id;
    if (e.detail.userId === currentUserId) {
        closeOrder();
    }
});

const formatPrice = (price) => {
    return parseFloat(price || 0).toFixed(2);
};

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const getStatusClass = (status) => {
    const statusMap = {
        'completed': 'badge bg-success',
        'cancelled': 'badge bg-danger',
        'in_progress': 'badge bg-info',
        'pending': 'badge bg-warning',
    };
    return statusMap[status?.toLowerCase()] || 'badge bg-secondary';
};

const getItemValue = (item, field) => {
    if (item && typeof item === 'object') {
        return item[field] ?? null;
    }
    return null;
};

const handleOrderCreated = (event) => {
    if (closedOrderId.value || !event.detail) return;
    const { operator_id } = event.detail;
    if (!operator_id) return;

    const currentOperatorId = order.value?.operator_id || authStore.user?.id;
    if (!currentOperatorId || operator_id !== currentOperatorId) return;

    loadOrder();
};

const handleOrderUpdated = (event) => {
    if (!order.value || !event.detail) return;
    const updated = event.detail;

    // Si es un pago directo (toggle manual desde caja/admin sin pasar por MP)
    if (updated.status_id === 3 && updated.paid && updated.id === order.value.id) {
        order.value = { ...order.value, ...updated };
        showPaymentSuccess();
        return;
    }

    // Otros cambios - recargar si es la misma orden
    if (updated.id === order.value.id) {
        loadOrder();
    }
};
const handleOpenFullscreen = () => {
    isFullscreen.value = true;
};

const handleOpenSpecificOrder = async (event) => {
    stopPaymentPolling();
    const { orderId, username: targetUsername, total } = event.detail;
    loading.value = true;
    error.value = null;
    qrCode.value = null;
    paymentSuccess.value = false;
    closedOrderId.value = null;
    
    try {
        const response = await api.get(`/pos/order-display/${targetUsername}/${orderId}`);
        order.value = response.data.order;
        
        // Solo generar QR si la orden está en status pending (status_id = 1)
        if (order.value && order.value.id && order.value.status_id === 1) {
            generateQR();
        } else if (order.value && order.value.status_id !== 1) {
            qrCode.value = null;
            error.value = null;
        }
    } catch (err) {
        error.value = err.response?.data?.error || 'Error al cargar pedido';
    } finally {
        loading.value = false;
    }
};

const handleQRUpdated = (event) => {
    const currentUserId = authStore.user?.id;
    const { target_user_id, order_id, username, total } = event.detail;
    
    if (target_user_id === currentUserId) {
        handleOpenSpecificOrder({
            detail: {
                orderId: order_id,
                username: username,
                total: total
            }
        });
    }
};

onMounted(() => {
    if (isAdmin.value) {
        loadUsers();
    }
    // Cargar logo para publicidad
    loadLogo();
    // No cargar orden por defecto - mostrar publicidad
    // Las órdenes se cargan automáticamente vía WebSocket (pos-order-created)
    
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
    
    window.addEventListener('pos-order-created', handleOrderCreated);
    window.addEventListener('pos-order-updated', handleOrderUpdated);
    window.addEventListener('open-pos-fullscreen', handleOpenFullscreen);
    window.addEventListener('open-pos-qr-order', handleOpenSpecificOrder);
    window.addEventListener('pos-qr-updated', handleQRUpdated);
    window.addEventListener('pos-user-disabled', closeOrder);
    
    // Suscribirse al canal del operador cuando se monte el componente
    // Esto funciona igual que en PosCaja - suscripción directa
    const setupWebSocket = () => {
        console.log('[PosQR] setupWebSocket called, Echo:', !!window.Echo, 'order:', order.value?.id, 'authStore.user:', authStore.user?.id);
        if (!window.Echo) {
            console.log('[PosQR] Echo not ready, retrying in 500ms');
            setTimeout(setupWebSocket, 500);
            return;
        }
        
        // Usar el operator_id de la orden actual, o del usuario autenticado
        const operatorId = order.value?.operator_id || authStore.user?.id;
        if (!operatorId) {
            console.log('[PosQR] No operatorId available, skipping subscription');
            return;
        }
        
        console.log('[PosQR] Subscribing to user.' + operatorId);
        window.Echo.channel(`user.${operatorId}`)
            .listen('.OrderPaid', (data) => {
                console.log('[PosQR] OrderPaid received:', JSON.stringify({ orderId: data.order?.id, currentOrderId: order.value?.id, closedOrderId: closedOrderId.value }));
                // Evitar procesar si la orden ya se cerró o es una orden diferente
                if (!data.order || closedOrderId.value === data.order.id) {
                    console.log('[PosQR] Ignoring OrderPaid - order already closed or different', { hasOrder: !!data.order, closedMatch: closedOrderId.value === data.order?.id });
                    return;
                }
                // Call showPaymentSuccess directly - order is already updated
                if (data.order?.id === order.value?.id) {
                    if (paymentSuccess.value) return;
                    order.value = data.order;
                    console.log('[PosQR] Calling showPaymentSuccess');
                    showPaymentSuccess();
                } else {
                    console.log('[PosQR] Order ID mismatch', { eventOrderId: data.order?.id, currentOrderId: order.value?.id });
                }
            });
    };
    
    // Configurar WebSocket después de un breve retraso para asegurar que Echo esté listo
    setTimeout(setupWebSocket, 1000);
});

onUnmounted(() => {
    document.removeEventListener('fullscreenchange', handleFullscreenChange);
    document.removeEventListener('webkitfullscreenchange', handleFullscreenChange);
    window.removeEventListener('resize', () => {
        isMobile.value = window.innerWidth < 768;
    });
    window.removeEventListener('pos-order-created', handleOrderCreated);
    window.removeEventListener('pos-order-updated', handleOrderUpdated);
    window.removeEventListener('open-pos-fullscreen', handleOpenFullscreen);
    window.removeEventListener('open-pos-qr-order', handleOpenSpecificOrder);
    window.removeEventListener('pos-qr-updated', handleQRUpdated);
    window.removeEventListener('pos-user-disabled', closeOrder);
    
    // Limpiar canal Echo
    if (window.Echo && order.value?.operator_id) {
        window.Echo.leaveChannel(`user.${order.value.operator_id}`);
    }
    
    // Limpiar timeout de éxito
    if (successTimeout.value) {
        clearTimeout(successTimeout.value);
    }
    
    stopPaymentPolling();
});

onUnmounted(() => {
    document.removeEventListener('fullscreenchange', handleFullscreenChange);
    document.removeEventListener('webkitfullscreenchange', handleFullscreenChange);
    window.removeEventListener('resize', () => {
        isMobile.value = window.innerWidth < 768;
    });
    window.removeEventListener('pos-order-created', handleOrderCreated);
    window.removeEventListener('pos-order-updated', handleOrderUpdated);
    window.removeEventListener('open-pos-fullscreen', handleOpenFullscreen);
    window.removeEventListener('open-pos-qr-order', handleOpenSpecificOrder);
    window.removeEventListener('pos-qr-updated', handleQRUpdated);
    window.removeEventListener('pos-user-disabled', closeOrder);
    
    // Limpiar canal Echo
    if (window.Echo && order.value?.operator_id) {
        window.Echo.leaveChannel(`user.${order.value.operator_id}`);
    }
});
</script>

<style scoped>
.pos-order-display {
    background: #ebebeb;
    min-height: 100%;
}

.order-header {
    background: #3483FA;
    color: white;
    padding: 10px 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.order-header .form-select {
    background-color: rgba(255,255,255,0.15);
    color: white;
    border-color: rgba(255,255,255,0.3);
}

.order-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12);
    border-left: 4px solid #3483FA;
}

.order-items table {
    font-size: 0.85rem;
}

.order-items {
    max-height: 200px;
    overflow-y: auto;
}

.order-items thead th {
    color: #666;
    font-weight: 600;
    border-bottom: 2px solid #eee;
}

.order-total {
    border-top: 2px solid #3483FA;
    padding-top: 12px;
    margin-top: 4px;
}

.order-total .h4 {
    color: #333;
    font-weight: 600;
}

/* Fullscreen Modal Styles - same light aesthetic as normal view */
.qr-fullscreen-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: #ebebeb;
    z-index: 9999;
    display: flex;
    flex-direction: column;
}

.qr-fullscreen-content {
    display: flex;
    flex-direction: column;
    height: 100%;
    max-width: 800px;
    margin: 0 auto;
    width: 100%;
    background: #fff;
}

.qr-fullscreen-header {
    background: #3483FA;
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.qr-fullscreen-body {
    flex: 1;
    overflow: auto;
    padding: 20px;
    background: #ebebeb;
}

.qr-fullscreen-body .order-items {
    max-height: 200px;
    overflow-y: auto;
}

.qr-fullscreen-body .order-card {
    background: white;
    color: #333;
    border-left: 4px solid #3483FA;
}

.qr-fullscreen-body .order-info .text-muted {
    color: #6c757d !important;
}

.qr-fullscreen-body .table {
    color: #333;
}

.qr-fullscreen-body .table-striped > tbody > tr:nth-of-type(odd) {
    --bs-table-accent-bg: #f8f9fa;
}

.qr-fullscreen-body .order-total {
    border-color: #3483FA;
}

.qr-fullscreen-body .order-total .h4 {
    color: #333;
}

.qr-fullscreen-body .order-total .text-success {
    color: #00A650 !important;
}

.qr-section {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(52,131,250,0.1);
    border: 2px solid #e8f0fe;
}

.qr-fullscreen-body .qr-section {
    background: white;
    border-color: #e8f0fe;
}

.qr-fullscreen-body .qr-label {
    color: #3483FA;
}

.qr-fullscreen-body .qr-total {
    color: #333;
}

.qr-fullscreen-body .advertisement-section {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    color: #fff;
}

.qr-label {
    font-size: 0.95rem;
    color: #3483FA;
    font-weight: 600;
    letter-spacing: 0.3px;
}

.qr-image {
    max-width: 220px;
    height: auto;
    border: 3px solid #fff;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(52,131,250,0.15);
    margin: 10px 0;
}

.qr-total {
    font-size: 1.2rem;
    font-weight: 600;
    color: #333;
    margin-top: 12px;
}

.text-mp-green {
    color: #00A650;
    font-weight: 700;
}

.advertisement-section {
    padding: 40px 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100%;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    color: #fff;
}

.success-view .mp-badge {
    opacity: 0.8;
}

.ad-logo {
    margin-bottom: 20px;
}

.ad-logo svg {
    max-width: 200px;
    height: auto;
}

.ad-text {
    font-size: 1.5rem;
    font-weight: 500;
    color: #fff;
    margin-bottom: 30px;
}

.ad-footer {
    font-size: 0.9rem;
    color: #fff;
    opacity: 0.7;
}
</style>