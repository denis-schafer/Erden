<template>
    <div v-if="show" class="session-modal-overlay">
        <div class="session-modal-content">
            <div class="session-modal-header">
                <h5 class="mb-0">
                    <i class="bi bi-clock-history me-2"></i>
                    Sesión por vencer
                </h5>
            </div>
            <div class="session-modal-body text-center">
                <p class="mb-3">Tu sesión está por expirar. ¿Deseas mantenerla activa?</p>
                <div class="countdown-display">
                    {{ formattedTime }}
                </div>
                <small class="text-muted">Si no actionas, serás redirigido al login automáticamente</small>
            </div>
            <div class="session-modal-footer">
                <button class="btn btn-primary btn-lg w-100" @click="extendSession">
                    <i class="bi bi-arrow-clockwise me-2"></i>
                    Extender Sesión
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import api from '../services/api';

const emit = defineEmits(['logout']);

const show = ref(false);
const remainingSeconds = ref(300);
let countdownInterval = null;

const formattedTime = computed(() => {
    const minutes = Math.floor(remainingSeconds.value / 60);
    const seconds = remainingSeconds.value % 60;
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
});

const extendSession = async () => {
    try {
        const r = await api.post('/refresh-session');
        if (r.data?.expires_at) {
            const expiresAt = new Date(r.data.expires_at).getTime();
            localStorage.setItem('session_expiry', expiresAt.toString());
        }
        resetCountdown();
    } catch (error) {
        console.error('Error extending session:', error);
        emit('logout');
    }
};

const resetCountdown = () => {
    show.value = false;
    remainingSeconds.value = 300;
    if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }
};

const startCountdown = (seconds) => {
    remainingSeconds.value = seconds;
    show.value = true;
    
    countdownInterval = setInterval(() => {
        remainingSeconds.value--;
        
        if (remainingSeconds.value <= 0) {
            clearInterval(countdownInterval);
            show.value = false;
            emit('logout');
        }
    }, 1000);
};

const startSessionMonitor = () => {
    setInterval(() => {
        const expiry = localStorage.getItem('session_expiry');
        if (!expiry) return;
        
        const expiryTime = parseInt(expiry);
        const now = Date.now();
        const remaining = Math.floor((expiryTime - now) / 1000);
        
        if (remaining <= 300 && remaining > 0 && !show.value) {
            startCountdown(remaining);
        }
    }, 10000);
};

onMounted(() => {
    startSessionMonitor();
});

onUnmounted(() => {
    if (countdownInterval) {
        clearInterval(countdownInterval);
    }
});

defineExpose({ startCountdown, resetCountdown });
</script>

<style scoped>
.session-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 99999;
    backdrop-filter: blur(4px);
}

.session-modal-content {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 400px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    overflow: hidden;
}

.session-modal-header {
    background: #ffc107;
    color: #000;
    padding: 1rem 1.5rem;
    text-align: center;
}

.session-modal-body {
    padding: 2rem 1.5rem;
}

.countdown-display {
    font-size: 3rem;
    font-weight: bold;
    color: #dc3545;
    margin: 1rem 0;
    font-family: monospace;
}

.session-modal-footer {
    padding: 0 1.5rem 1.5rem;
}

.session-modal-footer .btn {
    background: #0d6efd;
    border: none;
}

.session-modal-footer .btn:hover {
    background: #0b5ed7;
}
</style>