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
                    
                    <div v-else-if="setting.name === 'printing_mode'" class="form-group">
                        <div class="form-check form-switch">
                            <input class="form-check-input" 
                                   type="checkbox"
                                   :id="`toggle-${setting.id}`"
                                   :checked="setting.value === 'vps'"
                                   @change="togglePrintingMode(setting, $event)">
                            <label class="form-check-label" :for="`toggle-${setting.id}`">
                                {{ setting.value === 'vps' ? 'VPS' : 'Local' }}
                            </label>
                        </div>
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

        <div v-if="showAgentSection" class="mt-4">
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
                            Descarga el <strong>ErdenPrintAgent.exe</strong>, ejecútalo en la PC local conectada a la impresora y pega la clave API cuando la solicite.
                        </small>
                    </div>
                    <div class="mt-2">
                        <a v-if="downloadAvailable"
                           :href="'/pos/print-agent/download'"
                           class="btn btn-success btn-sm w-100"
                           download>
                            <i class="bi bi-download me-1"></i>
                            Descargar ErdenPrintAgent.exe
                        </a>
                        <div v-else class="alert alert-warning py-2 mb-0">
                            <small>
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                El ejecutable no está disponible en el servidor.
                                <a href="#" @click.prevent="showBuildInstructions = !showBuildInstructions">
                                    Ver instrucciones para generarlo
                                </a>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showAgentSection && showBuildInstructions" class="mt-3">
            <div class="setting-card">
                <div class="setting-header d-flex justify-content-between align-items-center">
                    <h6 class="setting-title mb-0">Instrucciones para generar ErdenPrintAgent.exe</h6>
                    <button type="button" class="btn-close" @click="showBuildInstructions = false"></button>
                </div>
                <div class="mt-2">
                    <ol class="mb-0 ps-3">
                        <li class="mb-1">Instala Python 3 desde <a href="https://python.org" target="_blank">python.org</a> (marca "Add to PATH")</li>
                        <li class="mb-1">Abre una terminal (CMD) y ejecuta: <code class="text-nowrap">pip install pyinstaller requests</code></li>
                        <li class="mb-1">Descarga <code>print-agent.py</code> del repositorio</li>
                        <li class="mb-1">En la terminal, ve a la carpeta del script y ejecuta:<br>
                            <code>pyinstaller --onefile --name "ErdenPrintAgent" --console print-agent.py</code></li>
                        <li class="mb-1">El .exe se genera en <code>dist/ErdenPrintAgent.exe</code></li>
                        <li>Sube el .exe al VPS: <code>scp dist/ErdenPrintAgent.exe root@149.50.133.48:/var/www/html/erden/storage/app/print-agent/</code></li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Remote Sync Settings -->
        <div class="mt-4">
            <div class="setting-card">
                <div class="setting-header">
                    <h6 class="setting-title">
                        <i class="bi bi-cloud-arrow-up me-1"></i>
                        Sincronización Remota
                    </h6>
                </div>
                <div class="setting-description">
                    <small class="text-muted">
                        Configura la sincronización automática de datos con el servidor VPS.
                        Los cambios en productos, categorías, usuarios y órdenes se sincronizarán en segundo plano.
                    </small>
                </div>
                <div class="mt-3">
                    <div class="mb-2">
                        <label class="form-label fw-semibold">URL del Servidor Remoto</label>
                        <div class="input-group input-group-sm">
                            <input type="text" 
                                   class="form-control font-monospace" 
                                   v-model="remoteUrlValue"
                                   placeholder="ej: https://149.50.133.48">
                            <button class="btn btn-outline-secondary" @click="copyText(remoteUrlValue)" title="Copiar URL">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Clave de Sincronización</label>
                        <div class="input-group input-group-sm">
                            <input type="text" 
                                   class="form-control font-monospace" 
                                   v-model="remoteKeyValue"
                                   placeholder="API Key del agente en VPS">
                            <button class="btn btn-outline-secondary" @click="copyText(remoteKeyValue)" title="Copiar clave">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                        <small class="text-muted">
                            Copia esta clave desde <strong>Configuración &gt; POS &gt; Agente de Impresión</strong> del VPS.
                        </small>
                    </div>
                    <div class="mt-2">
                        <button class="btn btn-primary btn-sm w-100" @click="saveSyncSettings" :disabled="savingSyncSettings">
                            <span v-if="savingSyncSettings" class="spinner-border spinner-border-sm"></span>
                            <i v-else class="bi bi-check-lg"></i>
                            Guardar configuración de sincronización
                        </button>
                    </div>
                    <div v-if="syncSettingsSaved" class="text-success small mt-1">
                        <i class="bi bi-check-circle me-1"></i>
                        Configuración guardada correctamente.
                    </div>
                    <div class="alert alert-info py-2 mb-0 mt-2">
                        <small>
                            <i class="bi bi-info-circle me-1"></i>
                            La sincronización se ejecuta automáticamente cada 5 minutos vía cron.
                            Solo funciona si el sistema está configurado como <strong>Local</strong> (no VPS).
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Backfill Section -->
        <div class="mt-4">
            <div class="setting-card">
                <div class="setting-header">
                    <h6 class="setting-title">
                        <i class="bi bi-arrow-repeat me-1"></i>
                        Sincronización Inicial
                    </h6>
                </div>
                <div class="setting-description">
                    <small class="text-muted">
                        Asigna sync_id a todos los registros existentes y los encola para sincronizar con el VPS.
                        Útil al configurar la sincronización por primera vez.
                    </small>
                </div>
                <div class="mt-3">
                    <button 
                        class="btn btn-warning btn-sm w-100" 
                        @click="startBackfill" 
                        :disabled="backfillRunning || backfillCompleted">
                        <span v-if="backfillRunning" class="spinner-border spinner-border-sm"></span>
                        <i v-else-if="backfillCompleted" class="bi bi-check-circle"></i>
                        <i v-else class="bi bi-cloud-upload"></i>
                        {{ backfillRunning ? 'Procesando...' : (backfillCompleted ? 'Backfill Completado' : 'Iniciar Sincronización Inicial') }}
                    </button>

                    <div v-if="backfillRunning" class="mt-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">{{ backfillEntity }}</span>
                            <span class="text-muted">{{ backfillProcessed }} / {{ backfillTotal }}</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning" 
                                 :style="{ width: backfillPercent + '%' }"></div>
                        </div>
                    </div>

                    <div v-if="backfillCompleted" class="alert alert-success py-2 mb-0 mt-2">
                        <small><i class="bi bi-check-circle me-1"></i>Backfill completado — {{ backfillQueued }} registros encolados.</small>
                    </div>

                    <div v-if="backfillError" class="alert alert-danger py-2 mb-0 mt-2">
                        <small><i class="bi bi-exclamation-triangle me-1"></i>{{ backfillError }}</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Webhook Code Section -->
        <div class="mt-4">
            <div class="setting-card">
                <div class="setting-header">
                    <h6 class="setting-title">
                        <i class="bi bi-link-45deg me-1"></i>
                        Código de Webhooks (Local Dev)
                    </h6>
                </div>
                <div class="setting-description">
                    <small class="text-muted">
                        Código único para identificar esta empresa al usar webhooks en entorno local.
                        Déjalo vacío si el VPS procesa los webhooks directamente.
                    </small>
                </div>
                <div class="mt-3">
                    <div class="input-group input-group-sm">
                        <input type="text" 
                               class="form-control font-monospace" 
                               v-model="webhookCodeValue"
                               placeholder="ej: mi-negocio-abc123"
                               maxlength="50">
                        <button class="btn btn-outline-primary" @click="saveWebhookCode" :disabled="savingWebhookCode">
                            <span v-if="savingWebhookCode" class="spinner-border spinner-border-sm"></span>
                            <i v-else class="bi bi-check-lg"></i>
                            Guardar
                        </button>
                    </div>
                    <div v-if="webhookCodeSaved" class="text-success small mt-1">
                        <i class="bi bi-check-circle me-1"></i>
                        Código guardado correctamente.
                    </div>
                    <div class="alert alert-info py-2 mb-0 mt-2">
                        <small>
                            <i class="bi bi-info-circle me-1"></i>
                            Configura este mismo código en tu servidor local para que el agente forwardee los webhooks.
                            Si está vacío, el VPS procesa los pagos normalmente.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test Mode Section -->
        <div class="mt-4">
            <div class="setting-card" :class="{ 'border-warning': testModeEnabled }">
                <div class="setting-header">
                    <h6 class="setting-title">
                        <i class="bi" :class="testModeEnabled ? 'bi-bug-fill text-warning' : 'bi-shield-check text-success'"></i>
                        Modo de Operación
                    </h6>
                </div>
                <div class="setting-description">
                    <small class="text-muted">
                        En modo Test puedes crear datos de prueba sin afectar la producción.
                        Al volver a Producción se eliminarán automáticamente.
                    </small>
                </div>
                <div class="mt-3">
                    <div class="d-flex gap-3">
                        <button 
                            class="btn flex-fill" 
                            :class="testModeEnabled ? 'btn-outline-success' : 'btn-success'"
                            @click="setTestMode(false)"
                            :disabled="!testModeEnabled"
                        >
                            <i class="bi bi-shield-check me-1"></i>
                            Producción
                        </button>
                        <button 
                            class="btn flex-fill" 
                            :class="testModeEnabled ? 'btn-warning' : 'btn-outline-warning'"
                            @click="confirmTestMode"
                            :disabled="testModeEnabled"
                        >
                            <i class="bi bi-bug me-1"></i>
                            Test
                        </button>
                    </div>
                    <div v-if="testModeEnabled" class="alert alert-warning mt-3 mb-0 py-2">
                        <small>
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Estás en <strong>Modo Test</strong>. Los datos creados se eliminarán al volver a Producción.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ConfirmModal ref="confirmModal" />
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import api from '../../../services/api';
import { useAuthStore } from '../../../stores/auth';
import ConfirmModal from '../../../components/ConfirmModal.vue';

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
const downloadAvailable = ref(false);
const showBuildInstructions = ref(false);
const testModeEnabled = ref(false);
const confirmModal = ref(null);
const webhookCodeValue = ref('');
const savingWebhookCode = ref(false);
const webhookCodeSaved = ref(false);
const remoteUrlValue = ref('');
const remoteKeyValue = ref('');
const savingSyncSettings = ref(false);
const syncSettingsSaved = ref(false);
const backfillRunning = ref(false);
const backfillCompleted = ref(false);
const backfillEntity = ref('');
const backfillProcessed = ref(0);
const backfillTotal = ref(0);
const backfillQueued = ref(0);
const backfillError = ref('');
let backfillPollTimer = null;

const showAgentSection = computed(() => {
    const mode = settings.value.find(s => s.name === 'printing_mode');
    return mode?.value !== 'local';
});

const settingLabels = {
    'business_name': 'Nombre del Negocio',
    'business_address': 'Dirección',
    'business_phone': 'Teléfono',
    'business_nit': 'NIT',
    'ticket_title': 'Título del Ticket',
    'redirect_uri': 'URL de Callback (ngrok)',
    'mp_access_token': 'Token OAuth',
    'printing_mode': 'Modo de Impresión',
};

const settingDescriptions = {
    'business_name': 'Nombre que aparecerá en los tickets',
    'business_address': 'Dirección del negocio',
    'business_phone': 'Teléfono de contacto',
    'business_nit': 'Número de identificación fiscal',
    'ticket_title': 'Título que aparecerá en el ticket',
    'redirect_uri': 'URL de ngrok para recibir el callback de MercadoPago',
    'mp_access_token': 'Token OAuth generado (se obtiene automáticamente)',
    'printing_mode': 'VPS (a través del agente) / Local (directo a impresora)',
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
        
        const allowedSettings = ['business_name', 'business_address', 'business_phone', 'business_nit', 'ticket_title', 'redirect_uri', 'mp_access_token', 'printing_mode'];
        settings.value = allSettings.filter(s => allowedSettings.includes(s.name));

        // Load test mode status
        await loadTestModeStatus();

        // Load print agent info
        await loadPrintAgentInfo();

        // Load webhook code
        await loadWebhookCode();

        // Load sync settings
        await loadSyncSettings();
    } catch (error) {
        
    } finally {
        loading.value = false;
    }
};

const loadTestModeStatus = async () => {
    try {
        const response = await api.get('/pos/test-mode/status');
        testModeEnabled.value = response.data?.enabled || false;
    } catch (error) {
        testModeEnabled.value = false;
    }
};

const confirmTestMode = () => {
    confirmModal.value.open({
        title: 'Activar Modo Test',
        message: 'Podrás crear datos de prueba sin afectar la producción.',
        confirmText: 'Activar',
        type: 'warning',
        onConfirm: async () => {
            await api.post('/pos/test-mode/enable');
            testModeEnabled.value = true;
        }
    });
};

const setTestMode = async (enabled) => {
    if (!enabled && testModeEnabled.value) {
        confirmModal.value.open({
            title: 'Volver a Producción',
            message: 'Se eliminarán TODOS los datos de prueba (órdenes, productos, categorías, usuarios) creados en modo Test.',
            confirmText: 'Volver a Producción',
            type: 'danger',
            onConfirm: async () => {
                await api.post('/pos/test-mode/disable');
                testModeEnabled.value = false;
            }
        });
    }
};

const loadPrintAgentInfo = async () => {
    try {
        const response = await api.get('/pos/print-agent/info');
        agentKey.value = response.data?.agent_key || '';
        serverUrl.value = response.data?.server_url || '';
        downloadAvailable.value = response.data?.download_available || false;
    } catch (error) {
        // Silently fail - agent info may not be configured yet
    }
};

const loadWebhookCode = async () => {
    try {
        const response = await api.get('/pos/webhook-code');
        webhookCodeValue.value = response.data?.webhook_code || '';
    } catch (error) {
        // Silently fail
    }
};

const saveWebhookCode = async () => {
    savingWebhookCode.value = true;
    webhookCodeSaved.value = false;
    try {
        const response = await api.put('/pos/webhook-code', { webhook_code: webhookCodeValue.value });
        webhookCodeValue.value = response.data?.webhook_code || '';
        webhookCodeSaved.value = true;
        setTimeout(() => { webhookCodeSaved.value = false; }, 3000);
    } catch (error) {
        // Silently fail
    } finally {
        savingWebhookCode.value = false;
    }
};

const loadSyncSettings = async () => {
    try {
        const response = await api.get('/pos/sync-settings');
        remoteUrlValue.value = response.data?.remote_url || '';
        remoteKeyValue.value = response.data?.remote_key || '';
    } catch (error) {
        // Silently fail
    }
};

const saveSyncSettings = async () => {
    savingSyncSettings.value = true;
    syncSettingsSaved.value = false;
    try {
        const response = await api.put('/pos/sync-settings', {
            remote_url: remoteUrlValue.value,
            remote_key: remoteKeyValue.value,
        });
        remoteUrlValue.value = response.data?.remote_url || '';
        remoteKeyValue.value = response.data?.remote_key || '';
        syncSettingsSaved.value = true;
        setTimeout(() => { syncSettingsSaved.value = false; }, 3000);
    } catch (error) {
        // Silently fail
    } finally {
        savingSyncSettings.value = false;
    }
};

const backfillPercent = computed(() => {
    if (backfillTotal.value === 0) return 0;
    return Math.round((backfillProcessed.value / backfillTotal.value) * 100);
});

const startBackfill = async () => {
    if (backfillCompleted.value) {
        confirmModal.value.open({
            title: 'Reiniciar Sincronización',
            message: 'Ya hay una sincronización inicial completada. Al reiniciar se resetearán todos los sync_id y se eliminarán los archivos de cola pendientes. ¿Desea continuar?',
            confirmText: 'Sí, reiniciar',
            cancelText: 'Cancelar',
            onConfirm: async () => {
                await doBackfill(true);
            },
        });
        return;
    }

    await doBackfill(false);
};

const doBackfill = async (force) => {
    backfillRunning.value = true;
    backfillCompleted.value = false;
    backfillError.value = '';
    backfillEntity.value = '';
    backfillProcessed.value = 0;
    backfillTotal.value = 0;
    backfillQueued.value = 0;

    try {
        await api.post('/pos/sync/backfill', { force });
        pollBackfillStatus();
    } catch (error) {
        backfillRunning.value = false;
        backfillError.value = error.response?.data?.message || error.message;
    }
};

const pollBackfillStatus = () => {
    backfillPollTimer = setInterval(async () => {
        try {
            const response = await api.get('/pos/sync/backfill-status');
            const data = response.data;

            if (!data || !data.status) {
                clearInterval(backfillPollTimer);
                backfillRunning.value = false;
                return;
            }

            if (data.status === 'completed') {
                clearInterval(backfillPollTimer);
                backfillRunning.value = false;
                backfillCompleted.value = true;
                backfillQueued.value = data.queued || 0;
                return;
            }

            if (data.status === 'running') {
                backfillEntity.value = data.entity || '';
                backfillProcessed.value = data.processed || 0;
                backfillTotal.value = data.total || 0;
                backfillQueued.value = data.queued || 0;
                return;
            }

            if (data.status && data.status.startsWith('error:')) {
                clearInterval(backfillPollTimer);
                backfillRunning.value = false;
                backfillError.value = data.status.replace('error:', '');
            }
        } catch (error) {
            // Keep polling
        }
    }, 2000);
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

const togglePrintingMode = async (setting, event) => {
    const newValue = event.target.checked ? 'vps' : 'local';
    
    try {
        await api.put(`/pos/configs/${setting.id}`, { value: newValue });
        setting.value = newValue;
    } catch (error) {
        event.target.checked = !event.target.checked;
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
    if (backfillPollTimer) {
        clearInterval(backfillPollTimer);
    }
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