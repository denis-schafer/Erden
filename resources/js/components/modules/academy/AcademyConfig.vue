<template>
    <div class="academy-config p-3">
        <h4 class="mb-3">Configuración</h4>
        <div v-if="loading" class="text-center py-5"><div class="spinner-border"></div></div>
        <div v-else class="row g-3">
            <div v-for="setting in settings" :key="setting.id" class="col-md-6">
                <div class="card"><div class="card-body">
                    <h6 class="card-title">{{ getLabel(setting.name) }}</h6>
                    <div v-if="setting.type === 'color'">
                        <input type="color" class="form-control form-control-color" :value="setting.value" @input="updateSetting(setting, $event.target.value)" style="width:60px;height:38px">
                    </div>
                    <div v-else-if="setting.type === 'image'">
                        <div class="d-flex align-items-center gap-2">
                            <input type="file" class="form-control form-control-sm" accept="image/*" @change="uploadImage($event, setting.name)" :id="'file-' + setting.name">
                            <a v-if="setting.value" :href="setting.value" target="_blank" class="btn btn-sm btn-outline-primary">Ver</a>
                            <button v-if="setting.value" class="btn btn-sm btn-outline-danger" :disabled="btnLoading['img-'+setting.id]" @click="deleteImage(setting)">
                                <i v-if="!btnLoading['img-'+setting.id]" class="bi bi-trash"></i>
                                <span v-else class="spinner-border spinner-border-sm"></span>
                            </button>
                        </div>
                    </div>
                    <div v-else-if="setting.type === 'boolean'">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" :checked="setting.value === '1'" @change="updateSetting(setting, $event.target.checked ? '1' : '0')" :id="'switch-' + setting.id">
                            <label class="form-check-label" :for="'switch-' + setting.id">{{ setting.value === '1' ? 'Activado' : 'Desactivado' }}</label>
                        </div>
                    </div>
                    <div v-else>
                        <input type="text" class="form-control form-control-sm" :value="setting.value" @input="updateSetting(setting, $event.target.value)">
                    </div>
                </div></div>
            </div>
        </div>
    </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
import api from '../../../services/api';
import { toast } from '../../../utils/toast';

const loading = ref(true);
const btnLoading = ref({});
const settings = ref([]);
let updateTimeout = null;

const labels = {
    'portal_title': 'Título del Portal',
    'primary_color': 'Color Primario',
    'secondary_color': 'Color Secundario',
    'logo': 'Logo del Portal',
    'background_image': 'Imagen de Fondo',
    'sidebar_drag_drop': 'Arrastrar módulos del menú',
    'max_attempts_per_exam': 'Intentos máximos por examen',
};

const getLabel = (name) => labels[name] || name;

const loadSettings = async () => {
    loading.value = true;
    try {
        const { data } = await api.get('/academy/config');
        settings.value = data;
    } finally { loading.value = false; }
};

const updateSetting = (setting, value) => {
    if (updateTimeout) clearTimeout(updateTimeout);
    updateTimeout = setTimeout(async () => {
        try {
            await api.put('/academy/config/' + setting.id, { value });
            toast.success('Configuración actualizada');
        } catch (e) { toast.error('Error al actualizar'); }
    }, 500);
};

const uploadImage = async (event, settingName) => {
    const file = event.target.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', settingName === 'logo' ? 'logo' : 'background');
    try {
        await api.post('/academy/config/upload', formData, { headers: { 'Content-Type': 'multipart/form-data' } });
        toast.success('Imagen subida');
        const { data } = await api.get('/academy/config');
        settings.value = data;
    } catch (e) { toast.error('Error al subir imagen'); }
};

const deleteImage = async (setting) => {
    btnLoading.value['img-' + setting.id] = true;
    try {
        await api.put('/academy/config/' + setting.id, { value: '' });
        const { data } = await api.get('/academy/config');
        settings.value = data;
        toast.success('Imagen eliminada');
    } catch (e) { toast.error('Error al eliminar imagen'); }
    btnLoading.value['img-' + setting.id] = false;
};

onMounted(loadSettings);
</script>