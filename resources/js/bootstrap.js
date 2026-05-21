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
        const isHttps = window.location.protocol === 'https:';
        const defaultPort = isHttps ? 443 : parseInt(import.meta.env.VITE_REVERB_PORT || '8080');
        
        console.log('[WebSocket] Connecting to:', (isHttps ? 'wss' : 'ws') + '://' + wsHost + ':' + defaultPort);
        
        const echo = new Echo({
            broadcaster: 'pusher',
            key: pusherKey,
            cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
            wsHost: wsHost,
            wsPort: defaultPort,
            wssPort: defaultPort,
            httpHost: wsHost,
            httpPort: defaultPort,
            forceTLS: isHttps,
            enabledTransports: isHttps ? ['wss', 'http'] : ['ws', 'http'],
            disableStats: true,
        });
        
        window.Echo = echo;

        echo.connector.pusher.connection.bind('connected', () => {
            console.log('[WS] Pusher connected: ' + echo.connector.pusher.connection.socket_id);
        });
        echo.connector.pusher.connection.bind('disconnected', () => {
            console.log('[WS] Pusher disconnected');
        });
        echo.connector.pusher.connection.bind('error', (err) => {
            console.log('[WS] Pusher error:', err);
        });
        
        const usersChannel = window.Echo.channel('users');
        usersChannel.subscribed(() => {
            console.log('[WS] SUCCESS: Subscribed to users channel');
        });
        usersChannel.error((err) => {
            console.log('[WS] ERROR: users channel subscription failed:', err);
        });
        
        usersChannel.listen('.UserSettingsUpdated', (event) => {
            window.dispatchEvent(new CustomEvent('pos-user-settings-updated', {
                detail: { ...event }
            }));
        });
        
        usersChannel.listen('.UserDisabled', (event) => {
            window.dispatchEvent(new CustomEvent('pos-user-disabled', {
                detail: { userId: event.id, disabledAt: event.disabled_at }
            }));
        });

        usersChannel.listen('.MpCodeReceived', (event) => {
            window.dispatchEvent(new CustomEvent('mp-code-received', {
                detail: { code: event.code, company_id: event.company_id }
            }));
        });

        usersChannel.listen('.PosQRUpdated', (event) => {
            console.log('[WS] PosQRUpdated received:', JSON.stringify(event));
            window.dispatchEvent(new CustomEvent('pos-qr-updated', {
                detail: { ...event }
            }));
        });

        usersChannel.listen('.OrderPaid', (event) => {
            console.log('[WS] OrderPaid received:', JSON.stringify(event));
            window.dispatchEvent(new CustomEvent('order-paid', {
                detail: { ...event }
            }));
        });

        window.Echo.channel('configs').listen('.ConfigUpdated', (event) => {
            window.dispatchEvent(new CustomEvent('pos-config-updated', {
                detail: { name: event.name, value: event.value }
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
    } catch (e) {
        // WebSockets not available
    }
};

setTimeout(initWebSockets, 1500);