<template>
    <div v-if="show" class="modal d-block" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ title }}</h5>
                    <button @click="cancel" type="button" class="btn-close"></button>
                </div>
                <div class="modal-body">
                    <p>{{ message }}</p>
                </div>
                <div class="modal-footer">
                    <button @click="cancel" type="button" class="btn btn-secondary">Cancelar</button>
                    <button @click="confirm" type="button" class="btn" :class="confirmClass">{{ confirmText }}</button>
                </div>
            </div>
        </div>
    </div>
    <div v-if="show" class="modal-backdrop fade show"></div>
</template>

<script setup>
import { ref } from 'vue';

const show = ref(false);
const title = ref('Confirmar');
const message = ref('');
const confirmText = ref('Aceptar');
const confirmClass = ref('btn-danger');
const resolvePromise = ref(null);

const open = (options = {}) => {
    title.value = options.title || 'Confirmar';
    message.value = options.message || '¿Está seguro?';
    confirmText.value = options.confirmText || 'Aceptar';
    confirmClass.value = options.confirmClass || 'btn-danger';
    show.value = true;
    
    return new Promise((resolve) => {
        resolvePromise.value = resolve;
    });
};

const confirm = () => {
    show.value = false;
    if (resolvePromise.value) resolvePromise.value(true);
};

const cancel = () => {
    show.value = false;
    if (resolvePromise.value) resolvePromise.value(false);
};

defineExpose({ open });
</script>
