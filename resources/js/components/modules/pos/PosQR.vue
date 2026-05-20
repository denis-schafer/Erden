<template>
    <div class="pos-order-display h-100 d-flex flex-column">
        <!-- Header -->
        <div class="order-header">
            <h5 class="mb-0">
                <i class="bi bi-receipt"></i> {{ order ? `Pedido: #${order.id}` : 'QR Payment' }}
            </h5>
            <div class="d-flex gap-2 align-items-center">
                <!-- User selector for admin -->
                <div v-if="isAdmin">
                    <select v-model="selectedUsername" @change="loadOrder" class="form-select form-select-sm">
                        <option value="">Seleccionar usuario</option>
                        <option v-for="user in users" :key="user.username" :value="user.username">
                            {{ user.name || user.username }}
                        </option>
                    </select>
                </div>
                <!-- Fullscreen button -->
                <button class="btn btn-sm btn-outline-light" @click="toggleBrowserFullscreen" :title="isBrowserFullscreen ? 'Salir de pantalla completa' : 'Pantalla completa'">
                    <i class="bi" :class="isBrowserFullscreen ? 'bi-fullscreen-exit' : 'bi-fullscreen'"></i>
                </button>
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
                    <!-- Vista de pago exitoso (PRIMERO en la cadena) -->
                    <div v-if="paymentSuccess" class="success-view text-center py-5">
                        <div class="mb-3">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                        </div>
                        <h3 class="text-success">¡Pago Realizado!</h3>
                        <p class="lead">Orden #{{ closedOrderId || order?.id }} pagada correctamente</p>
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
                            <div class="qr-label mb-2">Escanea para pagar</div>
                            <img :src="qrCode" alt="QR MercadoPago" class="qr-image" />
                            <div class="qr-total">Total: ${{ formatPrice(order.total) }}</div>
                        </div>
                        <div v-else-if="qrLoading" class="qr-section mb-3 text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Generando QR...</span>
                            </div>
                            <div class="small text-muted mt-2">Generando QR...</div>
                        </div>
                        <div v-else-if="qrError" class="qr-section mb-3 text-center">
                            <div class="text-danger small">{{ qrError }}</div>
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
            <!-- Vista de pago exitoso (PRIMERO en la cadena) -->
            <div v-if="paymentSuccess" class="success-view text-center py-5">
                <div class="mb-3">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                </div>
                <h3 class="text-success">¡Pago Realizado!</h3>
                <p class="lead">Orden #{{ closedOrderId || order?.id }} pagada correctamente</p>
            </div>
            
            <!-- Publicidad cuando no hay orden -->
            <div v-else-if="!order" class="advertisement-section h-100 d-flex flex-column justify-content-center align-items-center text-center">
                <div class="ad-logo mb-4">
                    <div class="ad-logo mb-4" v-html="logoSvg"></div>
                </div>
                <div class="ad-text h4 mb-4">Desarrollo de Software</div>
                <div class="ad-footer text-muted">
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
                    <div class="qr-label mb-2">Escanea para pagar</div>
                    <img :src="qrCode" alt="QR MercadoPago" class="qr-image" />
                    <div class="qr-total">Total: ${{ formatPrice(order.total) }}</div>
                </div>
                <div v-else-if="qrLoading" class="qr-section mb-3 text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Generando QR...</span>
                    </div>
                    <div class="small text-muted mt-2">Generando QR...</div>
                </div>
                <div v-else-if="qrError" class="qr-section mb-3 text-center">
                    <div class="text-danger small">{{ qrError }}</div>
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
        console.error('Error loading logo:', e);
    }
};

const order = ref(null);
const orders = ref([]);
const users = ref([]);
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
                    console.log('Screen Wake Lock activado');
                } catch (err) {
                    console.log('Wake Lock no soportado:', err);
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
        console.error('Error en fullscreen:', err);
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
    try {
        const response = await api.get('/pos/users');
        users.value = response.data.users || [];
    } catch (err) {
        console.error('Error loading users:', err);
    }
};

let lastOrderId = null;
let pollInterval = null;

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
            lastOrderId = order.value.id;
            if (order.value.status_id === 1) {
                generateQR();
            } else {
                // Orden no disponible para pago
                qrCode.value = null;
            }
        }
    } catch (err) {
        error.value = err.response?.data?.error || 'Error al cargar pedido';
        console.error('Error loading order:', err);
    } finally {
        loading.value = false;
    }
};

const startPolling = () => {
    if (pollInterval) clearInterval(pollInterval);
    
    // Polling solo para carga manual, no carga automáticamente al iniciar
    pollInterval = setInterval(async () => {
        if (!username.value || closedOrderId.value) return;
        
        try {
            const response = await api.get(`/pos/order-display/${username.value}`);
            const newOrder = response.data.order;
            
            // Detectar cambio de status: si la orden actual pasó de pending(1) a paid(3)
            if (order.value && newOrder && order.value.id === newOrder.id && order.value.status_id === 1 && newOrder.status_id === 3) {
                console.log('[Payment] Detected via polling:', newOrder.id);
                stopPolling(); // Detener polling para evitar interferencia
                
                // Usar la función centralizada
                showPaymentSuccess();
                return;
            }
            
            // Solo actualizar si hay una nueva orden y el usuario la seleccionada manualmente
            // (lastOrderId indica que el usuario ya eligió ver una orden)
            if (newOrder && newOrder.id !== lastOrderId && lastOrderId !== null) {
                console.log('New order detected via polling:', newOrder.id);
                lastOrderId = newOrder.id;
                order.value = newOrder;
                if (newOrder.status_id === 1) generateQR();
            }
        } catch (err) {
            // Ignore polling errors
        }
    }, 5000); // Check every 5 seconds
};

const stopPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

const generateQR = async () => {
    if (!order.value || !order.value.id) return;
    
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
        } else {
            qrError.value = response.data.message || 'Error al generar QR';
        }
    } catch (err) {
        qrError.value = err.response?.data?.message || 'Error al generar QR';
        console.error('Error generating QR:', err);
    } finally {
        qrLoading.value = false;
    }
};

const toggleFullscreen = () => {
    isFullscreen.value = false;
};

const closeOrder = () => {
    order.value = null;
    qrCode.value = null;
    lastOrderId = null;
    closedOrderId.value = null;
    paymentSuccess.value = false;
};

const showPaymentSuccess = () => {
    const currentOrderId = order.value?.id;
    console.log('[Payment] Showing success view for order:', currentOrderId);
    
    // Detener polling INMEDIATAMENTE
    stopPolling();
    
    // Cambiar estados
    paymentSuccess.value = true;
    qrCode.value = null;
    closedOrderId.value = currentOrderId;
    
    // Toast de éxito (usa la instancia del nivel superior)
    toastStore.success(`¡Pago completado! Orden #${currentOrderId}`);
    
    // Notificar a otros módulos que la orden fue actualizada (pagada)
    window.dispatchEvent(new CustomEvent('pos-order-updated'));
    console.log('[Payment] Dispatched pos-order-updated event');
    
    console.log('[Payment] State after set - paymentSuccess:', paymentSuccess.value, 'order:', order.value?.id);
    
    // Cerrar después de 3 segundos
    console.log('[Payment] Setting timeout for 3 seconds...');
    setTimeout(() => {
        console.log('[Payment] 3 seconds PASSED! Closing order:', currentOrderId);
        paymentSuccess.value = false;
        closeOrder();
        console.log('[Payment] After closeOrder - paymentSuccess:', paymentSuccess.value);
    }, 3000);
};

// Función que el polling llama cuando detecta cambio de status
const handlePaymentDetected = (updatedOrder) => {
    console.log('[Payment] Detected via polling/webocket:', updatedOrder.id);
    order.value = updatedOrder;
    showPaymentSuccess();
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
    console.log('Item:', item, 'Field:', field);
    if (item && typeof item === 'object') {
        return item[field] ?? null;
    }
    return null;
};

// WebSocket events - solo actualizan si ya hay una orden cargada, no cargan automáticamente
const handleOrderCreated = (event) => {
    // No cargar automáticamente - solo avisa que hay nueva orden
    console.log('Nueva orden creada:', event.detail);
};

const handleOrderUpdated = (event) => {
    // Solo actualizar si ya hay una orden cargada y es la misma
    if (order.value && event.detail.order?.id === order.value.id) {
        loadOrder();
    }
};
const handleOpenFullscreen = () => {
    isFullscreen.value = true;
};

const handleOpenSpecificOrder = async (event) => {
    const { orderId, username: targetUsername, total } = event.detail;
    console.log('[QR] handleOpenSpecificOrder:', { orderId, targetUsername, total });
    loading.value = true;
    error.value = null;
    qrCode.value = null;
    paymentSuccess.value = false;
    closedOrderId.value = null;
    
    try {
        const response = await api.get(`/pos/order-display/${targetUsername}/${orderId}`);
        console.log('[QR] order loaded:', response.data.order?.id, 'status:', response.data.order?.status_id);
        order.value = response.data.order;
        
        // Solo generar QR si la orden está en status pending (status_id = 1)
        if (order.value && order.value.id && order.value.status_id === 1) {
            generateQR();
        } else if (order.value && order.value.status_id !== 1) {
            console.log('[QR] order not pending (status:' + order.value.status_id + '), skipping QR');
            qrCode.value = null;
            error.value = null;
        } else {
            console.log('[QR] no order data received');
        }
    } catch (err) {
        console.error('[QR] Error loading order:', err);
        error.value = err.response?.data?.error || 'Error al cargar pedido';
    } finally {
        loading.value = false;
    }
};

const handleQRUpdated = (event) => {
    const currentUserId = authStore.user?.id;
    const { target_user_id, order_id, username, total } = event.detail;
    console.log('[QR] handleQRUpdated:', { currentUserId, target_user_id, order_id, username, total, match: target_user_id === currentUserId });
    
    if (target_user_id === currentUserId) {
        handleOpenSpecificOrder({
            detail: {
                orderId: order_id,
                username: username,
                total: total
            }
        });
    } else {
        console.log('[QR] target_user_id != currentUserId, ignorando');
    }
};

onMounted(() => {
    if (isAdmin.value) {
        loadUsers();
    }
    // Cargar logo para publicidad
    loadLogo();
    // No cargar orden por defecto - mostrar publicidad
    // startPolling() disponible para carga manual
    startPolling();
    
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
        if (!window.Echo) {
            console.log('[WebSocket] Echo not ready, retrying...');
            setTimeout(setupWebSocket, 500);
            return;
        }
        
        // Usar el operator_id de la orden actual, o del usuario autenticado
        const operatorId = order.value?.operator_id || authStore.user?.id;
        if (!operatorId) {
            console.log('[WebSocket] No operator_id available yet');
            return;
        }
        
        console.log('[WebSocket] Subscribing to: user.' + operatorId);
        // Limpiar suscripción anterior si existe
        window.Echo.leaveChannel(`user.${operatorId}`);
        window.Echo.channel(`user.${operatorId}`)
            .listen('.OrderPaid', (data) => {
                console.log('[WebSocket] .OrderPaid received:', data);
                // Evitar procesar si la orden ya se cerró o es una orden diferente
                if (!data.order || closedOrderId.value === data.order.id) {
                    console.log('[WebSocket] Order already closed or invalid, skipping');
                    return;
                }
                // Call showPaymentSuccess directly - order is already updated
                if (data.order?.id === order.value?.id) {
                    console.log('[WebSocket] Payment confirmed for order #' + data.order?.id);
                    order.value = data.order;
                    // Call showPaymentSuccess IMMEDIATELY (don't wait for polling)
                    showPaymentSuccess();
                }
            });
    };
    
    // Configurar WebSocket después de un breve retraso para asegurar que Echo esté listo
    setTimeout(setupWebSocket, 1000);
});

onUnmounted(() => {
    document.removeEventListener('fullscreenchange', handleFullscreenChange);
    document.removeEventListener('webkitfullscreenchange', handleFullscreenChange);
    stopPolling();
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
});

onUnmounted(() => {
    document.removeEventListener('fullscreenchange', handleFullscreenChange);
    document.removeEventListener('webkitfullscreenchange', handleFullscreenChange);
    stopPolling();
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
    background: #f8f9fa;
    min-height: 100%;
}

.order-header {
    background: #343a40;
    color: white;
    padding: 10px 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.order-header .form-select {
    background-color: #495057;
    color: white;
    border-color: #6c757d;
}

.order-card {
    background: white;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.order-items table {
    font-size: 0.85rem;
}

.order-items {
    max-height: 200px;
    overflow-y: auto;
}

.order-total {
    border-top: 2px solid #28a745;
    padding-top: 10px;
}

/* Fullscreen Modal Styles - like PosCaja */
.qr-fullscreen-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: #1a1a1a;
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
    background: #2d2d2d;
}

.qr-fullscreen-header {
    background: #343a40;
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
}

.qr-fullscreen-body .order-items {
    max-height: 200px;
    overflow-y: auto;
}

.qr-fullscreen-body .order-card {
    background: #3d3d3d;
    color: white;
}

.qr-fullscreen-body .order-info .text-muted {
    color: #aaa !important;
}

.qr-fullscreen-body .table {
    color: white;
}

.qr-fullscreen-body .table-striped > tbody > tr:nth-of-type(odd) {
    --bs-table-accent-bg: #444;
}

.qr-fullscreen-body .order-total {
    border-color: #28a745;
}

.qr-section {
    background: white;
    padding: 15px;
    border-radius: 8px;
}

.qr-label {
    font-size: 0.9rem;
    color: #666;
    font-weight: 500;
}

.qr-image {
    max-width: 200px;
    height: auto;
    border: 2px solid #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.qr-total {
    font-size: 1.2rem;
    font-weight: bold;
    color: #00d1a0;
    margin-top: 10px;
}

.advertisement-section {
    padding: 40px 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100%;
    background: #1a1a1a;
    color: #fff;
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
    color: #adb5bd;
    margin-bottom: 30px;
}

.ad-footer {
    font-size: 0.9rem;
    color: #6c757d;
}
</style>