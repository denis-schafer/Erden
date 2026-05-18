<template>
    <div class="pos-config-container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Configuración del Negocio</h5>
            <button 
                class="btn btn-primary btn-sm" 
                :disabled="isGettingToken"
                @click="openMpOAuthPopup"
            >
                <span v-if="isGettingToken" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="bi bi-key me-1"></i>
                {{ isGettingToken ? 'Obteniendo...' : 'Obtener Token MP' }}
            </button>
        </div>

        <div v-if="mpMessage" class="alert alert-dismissible fade show" :class="mpMessageType" role="alert">
            {{ mpMessage }}
            <button type="button" class="btn-close" @click="mpMessage = ''"></button>
        </div>

        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="mt-2 text-muted">Cargando configuraciones...</p>
        </div>

        <div v-else class="settings-grid">
            <div v-for="setting in settings" :key="setting.id" class="setting-card">
                <div class="setting-header">
                    <h6 class="setting-title">{{ getSettingLabel(setting.name) }}</h6>
                </div>
                
                <div class="setting-description">
                    <small class="text-muted">{{ getSettingDescription(setting.name) }}</small>
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
                    
                    <div v-else-if="setting.type === 'selector'" class="form-group">
                        <select class="form-select form-select-sm"
                                :value="setting.value"
                                @change="updateStringSetting(setting, $event)">
                            <option value="80mm">80mm (48 caracteres)</option>
                            <option value="50mm">50mm (32 caracteres)</option>
                        </select>
                    </div>
                    
                    <div v-else class="form-group">
                        <input type="text" 
                               class="form-control form-control-sm" 
                               :value="setting.value"
                               @input="updateStringSetting(setting, $event)"
                               :placeholder="getSettingPlaceholder(setting.name)">
                    </div>
                </div>
            </div>
            
            <div v-if="settings.length === 0" class="text-center py-5 col-12">
                <i class="bi bi-gear display-1 text-muted"></i>
                <p class="mt-3">No hay configuraciones definidas</p>
            </div>
        </div>

        <div class="mt-4">
            <div class="setting-card">
                <div class="setting-header">
                    <h6 class="setting-title">Agente de Impresión Local</h6>
                </div>
                <div class="setting-description">
                    <small class="text-muted">
                        Clave para que el agente local (print-agent) se conecte al servidor y procese impresiones pendientes.
                    </small>
                </div>
                <div class="mt-3">
                    <div class="mb-2">
                        <label class="form-label fw-semibold">URL del Servidor</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" :value="serverUrl" readonly @click="copyText(serverUrl)">
                            <button class="btn btn-outline-secondary" @click="copyText(serverUrl)" title="Copiar URL">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Clave API</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control font-monospace" :value="agentKey" readonly @click="copyText(agentKey)">
                            <button class="btn btn-outline-secondary" @click="copyText(agentKey)" title="Copiar clave">
                                <i class="bi bi-clipboard"></i>
                            </button>
                            <button class="btn btn-outline-warning" @click="regenerateKey" :disabled="regenerating">
                                <span v-if="regenerating" class="spinner-border spinner-border-sm"></span>
                                <i v-else class="bi bi-arrow-clockwise"></i>
                                Regenerar
                            </button>
                        </div>
                    </div>
                    <div class="alert alert-info py-2 mb-0 mt-2">
                        <small>
                            <i class="bi bi-info-circle me-1"></i>
                            Descarga el <strong>print-agent.py</strong> desde el repositorio, ejecútalo en la PC local conectada a la impresora y pega esta clave cuando la solicite.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import api from '../../../services/api';
import { useAuthStore } from '../../../stores/auth';

const settings = ref([]);
const loading = ref(false);
const updateTimeout = ref(null);
const isGettingToken = ref(false);
const mpMessage = ref('');
const mpMessageType = ref('alert-info');
const authStore = useAuthStore();
const agentKey = ref('');
const serverUrl = ref('');
const regenerating = ref(false);

const settingLabels = {
    'business_name': 'Nombre del Negocio',
    'business_address': 'Dirección',
    'business_phone': 'Teléfono',
    'business_nit': 'NIT',
    'ticket_title': 'Título del Ticket',
    'redirect_uri': 'URL de Callback (ngrok)',
    'mp_access_token': 'Token OAuth',
};

const settingDescriptions = {
    'business_name': 'Nombre que aparecerá en los tickets',
    'business_address': 'Dirección del negocio',
    'business_phone': 'Teléfono de contacto',
    'business_nit': 'Número de identificación fiscal',
    'ticket_title': 'Título que aparecerá en el ticket',
    'redirect_uri': 'URL de ngrok para recibir el callback de MercadoPago',
    'mp_access_token': 'Token OAuth generado (se obtiene automáticamente)',
};

const settingPlaceholders = {
    'business_name': 'Mi Negocio',
    'business_address': 'Calle 123, Ciudad',
    'business_phone': '+57 300 123 4567',
    'business_nit': '12345678-9',
    'ticket_title': 'MI NEGOCIO',
    'redirect_uri': 'https://xxxx.ngrok-free.com/mp/callback',
};

const getSettingLabel = (name) => settingLabels[name] || name;
const getSettingDescription = (name) => settingDescriptions[name] || '';
const getSettingPlaceholder = (name) => settingPlaceholders[name] || '';

const generateCodeVerifier = () => {
    const array = new Uint8Array(32);
    window.crypto.getRandomValues(array);
    return btoa(String.fromCharCode.apply(null, array))
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=/g, '');
};

const generateCodeChallenge = async (verifier) => {
    const encoder = new TextEncoder();
    const data = encoder.encode(verifier);
    const hash = await window.crypto.subtle.digest('SHA-256', data);
    return btoa(String.fromCharCode.apply(null, new Uint8Array(hash)))
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=/g, '');
};

const openMpOAuthPopup = async () => {
    const redirectUri = settings.value.find(s => s.name === 'redirect_uri')?.value;
    const mpMode = await getMpModeFromParent();
    
    if (!redirectUri) {
        mpMessage.value = 'Por favor configura la URL de Callback primero';
        mpMessageType.value = 'alert-warning';
        return;
    }
    
    isGettingToken.value = true;
    mpMessage.value = '';
    
    try {
        const mpClientId = await getMpClientIdFromParent();

        if (!mpClientId) {
            mpMessage.value = 'Error: Client ID no configurado en sistema';
            mpMessageType.value = 'alert-danger';
            isGettingToken.value = false;
            return;
        }

        const codeVerifier = generateCodeVerifier();
        const codeChallenge = await generateCodeChallenge(codeVerifier);

        const authUrl = mpMode === 'production'
            ? 'https://auth.mercadopago.com/authorization'
            : 'https://auth-sandbox.mercadopago.com/authorization';

        const companyId = authStore.company?.id || authStore.company?.db;

        // Pasar companyId, codeVerifier y redirectUri en el state parameter (base64 encoded)
        const stateData = btoa(JSON.stringify({ companyId, codeVerifier, redirectUri }));
        const oauthUrl = `${authUrl}?response_type=code&client_id=${mpClientId}&redirect_uri=${encodeURIComponent(redirectUri)}&state=${stateData}&code_challenge=${codeChallenge}&code_challenge_method=S256`;

        // Abrir en nueva pestaña en lugar de popup
        window.open(oauthUrl, '_blank');

    } catch (error) {
        mpMessage.value = 'Error al abrir ventana: ' + error.message;
        mpMessageType.value = 'alert-danger';
        isGettingToken.value = false;
    }
};

const getMpClientIdFromParent = async () => {
    try {
        const response = await api.get('/configs/target/payment');
        const configs = response.data || [];
        const mpClientId = configs.find(c => c.name === 'mp_client_id');
        return mpClientId?.value || null;
    } catch (error) {
        return null;
    }
};

const getMpModeFromParent = async () => {
    try {
        const response = await api.get('/configs/target/payment');
        const configs = response.data || [];
        const mpMode = configs.find(c => c.name === 'mp_mode');
        return mpMode?.value || 'sandbox';
    } catch (error) {
        return 'sandbox';
    }
};

const handleMpTokenObtained = (event) => {
    if (event.data?.type === 'mp_token_obtained') {
        const token = event.data.token;
        if (token) {
            mpMessage.value = 'Token OAuth obtenido correctamente. Recargando configuración...';
            mpMessageType.value = 'alert-success';
            loadSettings();
            isGettingToken.value = false;
        }
    }
};

const isBooleanTrue = (value) => {
    return value === true || value === 'true' || value === 1 || value === '1';
};

const loadSettings = async () => {
    loading.value = true;
    try {
        const response = await api.get('/pos/configs');
        const allSettings = response.data;
        
        const allowedSettings = ['business_name', 'business_address', 'business_phone', 'business_nit', 'ticket_title', 'redirect_uri', 'mp_access_token'];
        settings.value = allSettings.filter(s => allowedSettings.includes(s.name));

        // Load print agent info
        await loadPrintAgentInfo();
    } catch (error) {
        
    } finally {
        loading.value = false;
    }
};

const loadPrintAgentInfo = async () => {
    try {
        const response = await api.get('/pos/print-agent/info');
        agentKey.value = response.data?.agent_key || '';
        serverUrl.value = response.data?.server_url || '';
    } catch (error) {
        // Silently fail - agent info may not be configured yet
    }
};

const copyText = async (text) => {
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }
};

const regenerateKey = async () => {
    if (!confirm('¿Estás seguro? La clave actual dejará de funcionar y deberás actualizarla en el agente de impresión.')) {
        return;
    }
    regenerating.value = true;
    try {
        const response = await api.post('/pos/print-agent/regenerate');
        agentKey.value = response.data?.agent_key || '';
    } catch (error) {
        
    } finally {
        regenerating.value = false;
    }
};

const toggleBoolean = async (setting, event) => {
    const newValue = event.target.checked;
    const boolValue = newValue ? 'true' : 'false';
    
    try {
        await api.put(`/pos/configs/${setting.id}`, { value: boolValue });
        setting.value = boolValue;
        
        window.dispatchEvent(new CustomEvent('pos-config-updated', {
            detail: { name: setting.name, value: boolValue }
        }));
    } catch (error) {
        event.target.checked = !newValue;
    }
};

const updateStringSetting = (setting, event) => {
    const newValue = event.target.value;
    
    if (updateTimeout.value) {
        clearTimeout(updateTimeout.value);
    }
    
    updateTimeout.value = setTimeout(async () => {
        try {
            await api.put(`/pos/configs/${setting.id}`, { value: newValue });
            setting.value = newValue;
            
            window.dispatchEvent(new CustomEvent('pos-config-updated', {
                detail: { name: setting.name, value: newValue }
            }));
        } catch (error) {
            
        }
    }, 500);
};

onMounted(() => {
    loadSettings();
    window.addEventListener('message', handleMpTokenObtained);
});

onUnmounted(() => {
    window.removeEventListener('message', handleMpTokenObtained);
});
</script>

<style scoped>
.pos-config-container {
    height: 100%;
    display: flex;
    flex-direction: column;
    background-color: #f8f9fa;
    padding: 1rem;
    overflow-y: auto;
}

.settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}

.setting-card {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border: 1px solid #e9ecef;
}

.setting-header {
    margin-bottom: 0.5rem;
}

.setting-title {
    font-weight: 600;
    color: #343a40;
    margin: 0;
    font-size: 1rem;
}

.setting-description {
    font-size: 0.85rem;
    color: #6c757d;
}

.form-switch .form-check-input {
    width: 2.5em;
    height: 1.25em;
    cursor: pointer;
}

.form-switch .form-check-label {
    margin-left: 0.5rem;
    cursor: pointer;
}
</style>