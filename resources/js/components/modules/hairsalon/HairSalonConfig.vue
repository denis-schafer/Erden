<template>
    <div class="hairsalon-config p-3">
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
                            <button v-if="setting.value" class="btn btn-sm btn-outline-danger" @click="deleteImage(setting)"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                    <div v-else-if="setting.type === 'boolean'">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" :checked="setting.value === '1'" @change="updateSetting(setting, $event.target.checked ? '1' : '0')" :id="'switch-' + setting.id">
                            <label class="form-check-label" :for="'switch-' + setting.id">{{ setting.value === '1' ? 'Activado' : 'Desactivado' }}</label>
                        </div>
                    </div>
                    <div v-else-if="setting.type === 'select'">
                        <select v-if="setting.name === 'default_operator_id'" class="form-select form-select-sm" :value="setting.value" @change="updateSetting(setting, $event.target.value)">
                            <option value="">Ninguno</option>
                            <option v-for="o in operators" :key="o.id" :value="o.id">{{ o.name }}</option>
                        </select>
                        <select v-else class="form-select form-select-sm" :value="setting.value" @change="updateSetting(setting, $event.target.value)">
                            <option v-if="setting.name === 'cash_register_mode'" value="simple">Simple (solo movimientos)</option>
                            <option v-if="setting.name === 'cash_register_mode'" value="session">Con apertura/cierre de caja</option>
                            <option v-if="setting.name === 'calendar_view_mode'" value="weekly">Semanal</option>
                            <option v-if="setting.name === 'calendar_view_mode'" value="daily">Diaria</option>
                        </select>
                    </div>
                    <div v-else-if="setting.type === 'time'">
                        <input type="time" class="form-control form-control-sm" :value="setting.value" @change="updateSetting(setting, $event.target.value)">
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
import { ref, onMounted, onUnmounted } from 'vue';
import api from '../../../services/api';
import { useCache } from '../../../composables/useCache';
import { toast } from '../../../utils/toast';

const { fetch, refresh } = useCache();
const loading = ref(true);
const settings = ref([]);
const operators = ref([]);
let updateTimeout = null;

const labels = {
    'business_name': 'Nombre del Comercio',
    'business_address': 'Dirección',
    'business_phone': 'Teléfono',
    'logo': 'Logo',
    'primary_color': 'Color Primario',
    'secondary_color': 'Color Secundario',
    'background_image': 'Imagen de Fondo',
    'cash_register_mode': 'Modo de Caja',
    'calendar_start_time': 'Horario Inicio (Calendario)',
    'calendar_end_time': 'Horario Fin (Calendario)',
    'calendar_view_mode': 'Vista del Calendario',
    'default_operator_id': 'Operador por Defecto (Turnos)',
    'sidebar_drag_drop': 'Arrastrar módulos del menú',
};

const getLabel = (name) => labels[name] || name;

const loadSettings = async () => {
    loading.value = true;
    try {
        const [cfgRes, usersRes] = await Promise.all([
            fetch('hairsalon-configs', () => api.get('/hairsalon/config').then(r => r.data)),
            api.get('/hairsalon/users', { params: { per_page: 500 } }).then(r => r.data),
        ]);
        settings.value = cfgRes;
        operators.value = usersRes.data || [];
    } finally { loading.value = false; }
};

const updateSetting = (setting, value) => {
    if (updateTimeout) clearTimeout(updateTimeout);
    updateTimeout = setTimeout(async () => {
        try {
            await api.put('/hairsalon/config/' + setting.id, { value });
            await refresh('hairsalon-configs', () => api.get('/hairsalon/config').then(r => r.data));
            toast.success('Configuración actualizada');
        } catch (e) { toast.error('Error al actualizar'); }
    }, 500);
};

const uploadImage = async (event, type) => {
    const file = event.target.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', type === 'logo' ? 'logo' : 'background');
    try {
        await api.post('/hairsalon/config/upload', formData, { headers: { 'Content-Type': 'multipart/form-data' } });
        toast.success('Imagen subida');
        await refresh('hairsalon-configs', () => api.get('/hairsalon/config').then(r => r.data));
        settings.value = await fetch('hairsalon-configs', () => api.get('/hairsalon/config').then(r => r.data));
    } catch (e) { toast.error('Error al subir imagen'); }
};

const deleteImage = async (setting) => {
    try {
        await api.put('/hairsalon/config/' + setting.id, { value: '' });
        await refresh('hairsalon-configs', () => api.get('/hairsalon/config').then(r => r.data));
        settings.value = await fetch('hairsalon-configs', () => api.get('/hairsalon/config').then(r => r.data));
        toast.success('Imagen eliminada');
    } catch (e) { toast.error('Error al eliminar imagen'); }
};

const handleUserChanged = () => {
    api.get('/hairsalon/users', { params: { per_page: 500 } }).then(r => { operators.value = r.data.data || []; });
};

onMounted(() => { loadSettings(); window.addEventListener('hairsalon-user-changed', handleUserChanged); });
onUnmounted(() => { window.removeEventListener('hairsalon-user-changed', handleUserChanged); });
</script>
