<template>
    <div class="academy-import p-3">
        <h2 class="mb-3">Importar Curso</h2>
        <div class="row">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h5>Subir archivo</h5>
                        <p class="text-muted small">Selecciona un archivo <strong>.txt</strong> con el formato de marcadores del curso. El sistema tambien acepta <strong>.docx</strong> (Word).</p>
                        <div class="mb-3">
                            <input type="file" ref="fileInput" class="form-control" accept=".txt,.docx" @change="onFileChange">
                        </div>
                        <button class="btn btn-indigo" @click="importFile" :disabled="!selectedFile || uploading">
                            <span v-if="uploading" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="bi bi-upload me-1"></i>
                            Importar
                        </button>
                        <div v-if="message" class="mt-3 alert" :class="messageType">{{ message }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5>Formato del archivo (.txt)</h5>
                        <p class="text-muted small">El archivo .txt usa marcadores para definir la estructura:</p>
                        <ul class="small">
                            <li><strong>==COURSE==</strong> Nombre del curso</li>
                            <li><strong>==MODULE==</strong> Nombre del módulo</li>
                            <li><strong>==LESSON==</strong> Nombre de la lección + content: HTML</li>
                            <li><strong>==EXAM==</strong> Nombre del examen</li>
                            <li><strong>==QUESTION==</strong> text: + points: + type:</li>
                            <li><strong>==OPTION==</strong> text: + is_correct: (1 = correcta)</li>
                        </ul>
                        <p class="small text-muted">Descargá la plantilla de ejemplo para ver el formato completo.</p>
                        <a :href="'/academy/import/template'" class="btn btn-sm btn-outline-secondary" download>
                            <i class="bi bi-download"></i> Descargar plantilla (.txt)
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<script setup>
import { ref } from 'vue';
import api from '../../../services/api';
const fileInput = ref(null); const selectedFile = ref(null); const uploading = ref(false);
const message = ref(''); const messageType = ref('alert-info');
function onFileChange(e) { selectedFile.value = e.target.files[0] || null; message.value = ''; }
async function importFile() {
    if (!selectedFile.value) return;
    uploading.value = true; message.value = ''; messageType.value = 'alert-info';
    try {
        const formData = new FormData();
        formData.append('file', selectedFile.value);
        const { data } = await api.post('/academy/import', formData, { headers: { 'Content-Type': 'multipart/form-data' } });
        message.value = data.message || 'Curso importado correctamente';
        messageType.value = 'alert-success';
        selectedFile.value = null; if (fileInput.value) fileInput.value.value = '';
    } catch (e) {
        message.value = e.response?.data?.message || 'Error al importar';
        messageType.value = 'alert-danger';
    } finally { uploading.value = false; }
}
</script>