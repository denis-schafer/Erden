<template>
    <div class="global-config-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Configuración Global</h4>
            <div class="d-flex gap-2">
                <button 
                    v-for="tab in tabs" 
                    :key="tab.id"
                    class="btn btn-sm"
                    :class="activeTab === tab.id ? 'btn-primary' : 'btn-outline-primary'"
                    @click="activeTab = tab.id"
                >
                    <i :class="tab.icon"></i> {{ tab.name }}
                </button>
            </div>
        </div>

        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="mt-2 text-muted">Cargando configuraciones...</p>
        </div>

        <div v-else class="settings-grid">
            <div v-for="setting in filteredSettings" :key="setting.id" class="setting-card">
                <div class="setting-header">
                    <h6 class="setting-title">{{ getSettingLabel(setting.name) }}</h6>
                </div>
                
                <div class="setting-description">
                    <small class="text-muted">{{ setting.description }}</small>
                </div>
                
                <div class="setting-control mt-3">
                    <div v-if="setting.type === 'boolean'" class="form-group">
                        <div class="form-check form-switch">
                            <input class="form-check-input" 
                                   type="checkbox"
                                   :id="`toggle-${setting.id}`"
                                   :checked="isBooleanTrue(setting.value)"
                                   @change="toggleBoolean(setting, $event)">
                            <label class="form-check-label" :for="`toggle-${setting.id}`">
                                {{ isBooleanTrue(setting.value) ? 'Activado' : 'Desactivado' }}
                            </label>
                        </div>
                    </div>
                    
                    <div v-else-if="setting.type === 'number'" class="form-group">
                        <input type="number" 
                               class="form-control form-control-sm" 
                               :value="setting.value"
                               @input="updateStringSetting(setting, $event)">
                    </div>
                    
                    <div v-else class="form-group">
                        <input :type="setting.type === 'string' ? 'text' : 'text'" 
                               class="form-control form-control-sm" 
                               :value="setting.value"
                               @input="updateStringSetting(setting, $event)"
                               :placeholder="getSettingPlaceholder(setting.name)">
                    </div>
                </div>
            </div>
            
            <div v-if="filteredSettings.length === 0" class="text-center py-5 col-12">
                <i class="bi bi-gear display-1 text-muted"></i>
                <p class="mt-3">No hay configuraciones en esta sección</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../../services/api';
import { toast as toastify } from '../../../utils/toast';

const settings = ref([]);
const loading = ref(false);
const activeTab = ref('general');

const tabs = [
    { id: 'general', name: 'General', icon: 'bi bi-building' },
    { id: 'print', name: 'Impresión', icon: 'bi bi-printer' },
    { id: 'payment', name: 'Pagos', icon: 'bi bi-credit-card' },
];

const filteredSettings = computed(() => {
    return settings.value.filter(s => s.target === activeTab.value);
});

const settingLabels = {
    // General
    'business_name': 'Nombre del Negocio',
    'business_address': 'Dirección',
    'business_phone': 'Teléfono',
    'business_nit': 'NIT / Identificador Fiscal',
    // Print
    'enable_print': 'Impresión Automática',
    'printer_ip': 'IP de Impresora',
    'printer_port': 'Puerto de Impresora',
    'printer_width': 'Ancho del Papel',
    // Payment - MercadoPago
    'mp_client_id': 'Client ID',
    'mp_client_secret': 'Client Secret',
    'mp_public_key': 'Public Key',
    'mp_enable': 'Habilitar MercadoPago',
    'mp_mode': 'Modo',
    'mp_access_token': 'Access Token (OAuth)',
    'mp_token_expires_at': 'Expira',
};

const settingPlaceholders = {
    'business_name': 'Mi Negocio',
    'business_address': 'Calle 123, Ciudad',
    'business_phone': '+57 300 123 4567',
    'business_nit': '12345678-9',
    'printer_ip': '192.168.1.100',
    'printer_port': '9100',
    'printer_width': '48',
    'mp_client_id': 'Tu Client ID de MercadoPago',
    'mp_client_secret': 'Tu Client Secret',
    'mp_public_key': 'APP_USR-xxxxxxxxxxxx',
};

const getSettingLabel = (name) => settingLabels[name] || name;
const getSettingPlaceholder = (name) => settingPlaceholders[name] || '';

const isBooleanTrue = (value) => {
    return value === true || value === 'true' || value === '1' || value === 1;
};

const toggleBoolean = (setting, event) => {
    const newValue = event.target.checked ? 'true' : 'false';
    saveSetting(setting, newValue);
};

const updateStringSetting = (setting, event) => {
    const newValue = event.target.value;
    saveSetting(setting, newValue);
};

const saveSetting = (setting, newValue) => {
    setting.value = newValue;
    
    api.put(`/configs/${setting.id}`, { value: newValue })
        .then(() => {
            toastify.success('Configuración guardada');
        })
        .catch((error) => {
            toastify.error('Error al guardar: ' + (error.response?.data?.message || 'Error desconocido'));
        });
};

const loadSettings = async () => {
    loading.value = true;
    try {
        const response = await api.get('/configs');
        settings.value = response.data;
    } catch (error) {
        console.error('Error loading settings:', error);
        toastify.error('Error al cargar configuraciones');
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadSettings();
});
</script>

<style scoped>
.global-config-container {
    padding: 1.5rem;
    background: #f8f9fa;
    min-height: 100%;
}

.settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}

.setting-card {
    background: white;
    border-radius: 0.5rem;
    padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.setting-title {
    font-weight: 600;
    color: #212529;
    margin-bottom: 0.25rem;
}

.setting-description {
    color: #6c757d;
    font-size: 0.875rem;
}

.form-check-input {
    cursor: pointer;
}

.form-check-label {
    cursor: pointer;
    margin-left: 0.5rem;
}

.form-control-sm {
    max-width: 100%;
}
</style>