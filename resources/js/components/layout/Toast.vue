<template>
    <div class="toast-container">
        <div 
            v-for="notification in notifications" 
            :key="notification.id"
            class="toast"
            :class="'toast-' + notification.type"
        >
            <div class="toast-body">
                <span class="toast-icon">{{ getIcon(notification.type) }}</span>
                <span class="toast-text">{{ notification.message }}</span>
            </div>
            <button class="toast-btn-close" @click="remove(notification.id)">×</button>
        </div>
    </div>
</template>

<script setup>
import { useToastStore } from '../../stores/toast';
import { storeToRefs } from 'pinia';

const toastStore = useToastStore();
const { notifications } = storeToRefs(toastStore);

const getIcon = (type) => {
    const icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
    };
    return icons[type] || 'ℹ';
};

const remove = (id) => {
    toastStore.remove(id);
};
</script>

<style>
.toast-container {
    position: fixed;
    top: 60px;
    right: 20px;
    z-index: 99999;
    pointer-events: none;
    width: 350px;
}

.toast {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    border-radius: 6px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
    pointer-events: auto;
    animation: slideIn 0.3s ease-out;
    margin-bottom: 10px;
}

.toast-success {
    background: #198754;
    color: white;
}

.toast-error {
    background: #dc3545;
    color: white;
}

.toast-warning {
    background: #ffc107;
    color: #212529;
}

.toast-info {
    background: #0dcaf0;
    color: white;
}

.toast-body {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
}

.toast-icon {
    font-size: 1.2rem;
    font-weight: bold;
}

.toast-text {
    font-size: 0.95rem;
}

.toast-btn-close {
    background: transparent;
    border: none;
    color: inherit;
    font-size: 1.3rem;
    cursor: pointer;
    opacity: 0.7;
    padding: 0 0 0 10px;
    line-height: 1;
}

.toast-btn-close:hover {
    opacity: 1;
}

@keyframes slideIn {
    from {
        transform: translateX(-100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
</style>
