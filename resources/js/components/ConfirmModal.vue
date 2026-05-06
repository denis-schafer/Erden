<template>
    <div v-if="show" class="confirm-modal-overlay" @click.self="cancel">
        <div class="confirm-modal-content">
            <div class="confirm-modal-header" :class="typeClass">
                <h5 class="mb-0">{{ title }}</h5>
            </div>
            <div class="confirm-modal-body">
                <p class="mb-0">{{ message }}</p>
            </div>
            <div class="confirm-modal-footer">
                <button class="btn btn-secondary" @click="cancel">Cancelar</button>
                <button :class="confirmClass" @click="confirm">{{ confirmText }}</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const show = ref(false);
const title = ref('Confirmar');
const message = ref('');
const confirmText = ref('Confirmar');
const type = ref('danger');
const onConfirm = ref(() => {});

const typeClass = computed(() => {
    switch (type.value) {
        case 'danger': return 'bg-danger text-white';
        case 'warning': return 'bg-warning text-dark';
        case 'primary': return 'bg-primary text-white';
        default: return 'bg-danger text-white';
    }
});

const confirmClass = computed(() => {
    switch (type.value) {
        case 'danger': return 'btn btn-danger';
        case 'warning': return 'btn btn-warning';
        case 'primary': return 'btn btn-primary';
        default: return 'btn btn-danger';
    }
});

const open = (options) => {
    title.value = options.title || 'Confirmar';
    message.value = options.message || '';
    confirmText.value = options.confirmText || 'Confirmar';
    type.value = options.type || 'danger';
    onConfirm.value = options.onConfirm || (() => {});
    show.value = true;
};

const confirm = () => {
    onConfirm.value();
    show.value = false;
};

const cancel = () => {
    show.value = false;
};

defineExpose({ open });
</script>

<style scoped>
.confirm-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 99999;
}

.confirm-modal-content {
    background: white;
    border-radius: 8px;
    width: 90%;
    max-width: 400px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    overflow: hidden;
}

.confirm-modal-header {
    padding: 1rem;
    font-weight: 600;
}

.confirm-modal-body {
    padding: 1.5rem;
}

.confirm-modal-footer {
    padding: 1rem;
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}
</style>