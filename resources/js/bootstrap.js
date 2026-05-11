import axios from 'axios';
import 'bootstrap';
import '@popperjs/core';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const initWebSockets = async () => {
    const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;
    if (!pusherKey) return;
    
    try {
        const Pusher = (await import('pusher-js')).default;
        window.Pusher = Pusher;
        
        const Echo = (await import('laravel-echo')).default;
        
        // SIEMPRE usar la IP/HOST actual de la página para que funcione desde otras PCs
        // import.meta.env.VITE_REVERB_HOST se ignora porque apunta a 'localhost' y solo funciona local
        const wsHost = window.location.hostname;
        const currentPort = window.location.port;
        const wsPort = currentPort ? parseInt(currentPort) : parseInt(import.meta.env.VITE_REVERB_PORT || '8080');
        
        console.log('[WebSocket] Connecting to:', wsHost + ':' + wsPort);
        
        const echo = new Echo({
            broadcaster: 'pusher',
            key: pusherKey,
            cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
            wsHost: wsHost,
            wsPort: wsPort,
            httpHost: wsHost,
            httpPort: wsPort,
            forceTLS: false,
            enabledTransports: ['ws', 'http'],
            disableStats: true,
        });
        
        window.Echo = echo;
        
        window.Echo.channel('configs').listen('.ConfigUpdated', (event) => {
            window.dispatchEvent(new CustomEvent('pos-config-updated', {
                detail: { name: event.name, value: event.value }
            }));
        });
        
        window.Echo.channel('users').listen('.UserSettingsUpdated', (event) => {
            window.dispatchEvent(new CustomEvent('pos-user-settings-updated', {
                detail: { ...event }
            }));
        });
        
        window.Echo.channel('users').listen('.UserDisabled', (event) => {
            window.dispatchEvent(new CustomEvent('pos-user-disabled', {
                detail: { userId: event.id, disabledAt: event.disabled_at }
            }));
        });

        window.Echo.channel('categories').listen('.CategoryDisabled', (event) => {
            window.dispatchEvent(new CustomEvent('pos-category-changed', {
                detail: { type: 'disabled', categoryId: event.id }
            }));
        });

        window.Echo.channel('categories').listen('.CategoryEnabled', (event) => {
            window.dispatchEvent(new CustomEvent('pos-category-changed', {
                detail: { type: 'enabled', categoryId: event.id }
            }));
        });

        window.Echo.channel('categories').listen('.CategoryUpdated', (event) => {
            window.dispatchEvent(new CustomEvent('pos-category-changed', {
                detail: { type: 'updated', categoryId: event.id }
            }));
        });

        window.Echo.channel('products').listen('.ProductDisabled', (event) => {
            window.dispatchEvent(new CustomEvent('pos-product-changed', {
                detail: { type: 'disabled', productId: event.id }
            }));
        });

        window.Echo.channel('products').listen('.ProductEnabled', (event) => {
            window.dispatchEvent(new CustomEvent('pos-product-changed', {
                detail: { type: 'enabled', productId: event.id }
            }));
        });

        window.Echo.channel('products').listen('.ProductUpdated', (event) => {
            window.dispatchEvent(new CustomEvent('pos-product-changed', {
                detail: { type: 'updated', productId: event.id }
            }));
        });

        window.Echo.channel('products').listen('.ProductReordered', (event) => {
            window.dispatchEvent(new CustomEvent('pos-product-reordered', {
                detail: { orders: event.orders }
            }));
        });

        window.Echo.channel('orders').listen('.OrderCreated', (event) => {
            window.dispatchEvent(new CustomEvent('pos-order-created', {
                detail: { ...event }
            }));
        });

        window.Echo.channel('orders').listen('.OrderUpdated', (event) => {
            window.dispatchEvent(new CustomEvent('pos-order-updated', {
                detail: { ...event }
            }));
        });

        window.Echo.channel('orders').listen('.OrderDeleted', (event) => {
            window.dispatchEvent(new CustomEvent('pos-order-deleted', {
                detail: { orderId: event.id }
            }));
        });

        window.Echo.channel('users').listen('.MpCodeReceived', (event) => {
            window.dispatchEvent(new CustomEvent('mp-code-received', {
                detail: { code: event.code, company_id: event.company_id }
            }));
        });

        window.Echo.channel('users').listen('.PosQRUpdated', (event) => {
            window.dispatchEvent(new CustomEvent('pos-qr-updated', {
                detail: { ...event }
            }));
        });

        // Listen for OrderPaid events to update POS Orders in real-time
        window.Echo.channel('users').listen('.OrderPaid', (event) => {
            window.dispatchEvent(new CustomEvent('order-paid', {
                detail: { ...event }
            }));
        });
    } catch (e) {
        // WebSockets not available
    }
};

setTimeout(initWebSockets, 1500);