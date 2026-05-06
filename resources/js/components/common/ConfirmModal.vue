<template>
    <div v-if="show" class="modal d-block" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
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
const confirmText = ref('Confirmar');
const confirmClass = ref('btn-primary');
const onConfirm = ref(null);
const onCancel = ref(null);

const open = (options) => {
    title.value = options.title || 'Confirmar';
    message.value = options.message;
    confirmText.value = options.confirmText || 'Confirmar';
    confirmClass.value = options.confirmClass || 'btn-primary';
    onConfirm.value = options.onConfirm;
    onCancel.value = options.onCancel || null;
    show.value = true;
};

const confirm = () => {
    show.value = false;
    if (onConfirm.value) onConfirm.value();
};

const cancel = () => {
    show.value = false;
    if (onCancel.value) onCancel.value();
};

defineExpose({ open });
</script>