<template>
    <div class="modal fade show d-block" tabindex="-1"
         style="background: rgba(0,0,0,0.5); z-index: 1060;"
         @click.self="$emit('close')">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Importar Socios</h5>
                    <button type="button" class="btn-close" @click="$emit('close')"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Formatos aceptados: .xlsx, .xls, .csv</p>
                    <p class="text-muted small">Columnas requeridas: DNI, Nombre, Apellido</p>
                    <p class="text-muted small">Opcionales: Teléfono, Dirección, Importe Cuota, Dcho Pileta</p>
                    <input type="file" class="form-control" accept=".xlsx,.xls,.csv" @change="handleFile" :disabled="uploading">
                    <div v-if="uploading" class="text-center mt-3">
                        <div class="spinner-border spinner-border-sm"></div> Subiendo...
                    </div>
                    <div v-if="result" class="mt-3">
                        <div class="alert alert-success">Importados: {{ result.imported }}</div>
                        <div v-if="result.errors?.length" class="alert alert-warning">
                            <strong>Errores ({{ result.errors.length }}):</strong>
                            <ul class="mb-0 small">
                                <li v-for="e in result.errors" :key="e.row">Fila {{ e.row }}: {{ e.message }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="$emit('close')">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { toast } from '../../../utils/toast';

const emit = defineEmits(['close', 'imported']);
const uploading = ref(false);
const result = ref(null);

const handleFile = async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    uploading.value = true;
    result.value = null;
    const formData = new FormData();
    formData.append('file', file);
    try {
        const { data } = await axios.post('/quota/partners/import', formData);
        result.value = data;
        if (data.imported > 0) {
            setTimeout(() => emit('imported'), 1500);
        }
    } catch (err) {
        toast.error(err.response?.data?.message || 'Error al importar');
    } finally {
        uploading.value = false;
    }
};
</script>

<style scoped>
.modal-dialog { width: 500px; max-width: 90%; }
</style>
