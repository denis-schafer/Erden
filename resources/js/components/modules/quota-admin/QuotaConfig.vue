<template>
    <div class="quota-config p-3">
        <h4 class="mb-4">Configuración</h4>
        <div v-if="loading" class="text-center py-5"><div class="spinner-border"></div></div>
        <template v-else>
            <div v-if="mpMessage" class="alert alert-dismissible fade show" :class="mpMessageType" role="alert">
                {{ mpMessage }}
                <button type="button" class="btn-close" @click="mpMessage = ''"></button>
            </div>

            <div class="card mb-4">
                <div class="card-header">Configuración General</div>
                <div class="card-body">
                    <div v-for="cfg in configs" :key="cfg.id" class="row mb-3 align-items-center">
                        <div class="col-md-4">
                            <strong>{{ getLabel(cfg.name) }}</strong>
                        </div>
                        <div class="col-md-6">
                            <input class="form-control form-control-sm" v-model="cfg.value" :disabled="cfg.name === 'mp_access_token'">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-sm btn-primary" @click="saveConfig(cfg)" :disabled="cfg.name === 'mp_access_token'">
                                Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">MercadoPago</div>
                <div class="card-body">
                    <div class="mb-3">
                        <p class="text-muted small">
                            Conecta tu cuenta de MercadoPago para recibir pagos online de los socios.
                            Necesitas tener configurado el Client ID y Client Secret en Configuración Global (Payment).
                        </p>
                        <p class="text-muted small">
                            <strong>Redirect URI:</strong> Configura en MercadoPago la URL: <code>{{ redirectUri }}</code>
                        </p>
                        <button class="btn btn-primary" @click="connectMP" :disabled="mpConnecting">
                            <span v-if="mpConnecting" class="spinner-border spinner-border-sm me-1"></span>
                            <i class="bi bi-wallet2"></i> {{ mpConnecting ? 'Conectando...' : 'Obtener Token MP' }}
                        </button>
                        <div v-if="mpClientId" class="mt-2 text-muted small">
                            Client ID: {{ mpClientId }} | Modo: {{ mpMode }}
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { toast } from '../../../utils/toast';

const configs = ref([]);
const loading = ref(true);
const mpConnecting = ref(false);
const mpClientId = ref(null);
const mpMode = ref('sandbox');
const mpMessage = ref('');
const mpMessageType = ref('alert-info');

const redirectUri = computed(() => {
    const cfg = configs.value.find(c => c.name === 'redirect_uri');
    return cfg?.value || window.location.origin + '/quota/mp/callback';
});

const getLabel = (name) => {
    const labels = {
        business_name: 'Nombre del Natatorio',
        redirect_uri: 'Redirect URI MP',
        mp_access_token: 'Access Token (solo lectura)',
    };
    return labels[name] || name;
};

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

const loadConfigs = async () => {
    try {
        const [{ data: configData }, { data: mpData }] = await Promise.all([
            axios.get('/quota/config'),
            axios.get('/quota/config/mp-client-id'),
        ]);
        configs.value = configData;
        mpClientId.value = mpData.mp_client_id;
        mpMode.value = mpData.mp_mode;
    } catch (e) { console.error(e); }
    finally { loading.value = false; }
};

const saveConfig = async (cfg) => {
    try {
        await axios.put(`/quota/config/${cfg.id}`, { value: cfg.value });
    } catch (e) {
        toast.error('Error al guardar');
    }
};

const connectMP = async () => {
    const redirectUriVal = configs.value.find(c => c.name === 'redirect_uri')?.value;

    if (!redirectUriVal) {
        mpMessage.value = 'Por favor configura la URL de Callback primero';
        mpMessageType.value = 'alert-warning';
        return;
    }

    mpConnecting.value = true;
    mpMessage.value = '';

    try {
        const mpData = await axios.get('/quota/config/mp-client-id');
        const clientId = mpData.data?.mp_client_id;
        const mode = mpData.data?.mp_mode || 'sandbox';

        if (!clientId) {
            mpMessage.value = 'Error: Client ID no configurado en sistema';
            mpMessageType.value = 'alert-danger';
            mpConnecting.value = false;
            return;
        }

        const codeVerifier = generateCodeVerifier();
        const codeChallenge = await generateCodeChallenge(codeVerifier);

        const authUrl = mode === 'production'
            ? 'https://auth.mercadopago.com/authorization'
            : 'https://auth-sandbox.mercadopago.com/authorization';

        const companyId = document.querySelector('meta[name="company-id"]')?.getAttribute('content') || '';

        const stateData = btoa(JSON.stringify({ companyId, codeVerifier, redirectUri: redirectUriVal }));
        const oauthUrl = `${authUrl}?response_type=code&client_id=${clientId}&redirect_uri=${encodeURIComponent(redirectUriVal)}&state=${stateData}&code_challenge=${codeChallenge}&code_challenge_method=S256`;

        window.open(oauthUrl, '_blank');
    } catch (error) {
        mpMessage.value = 'Error al abrir ventana: ' + error.message;
        mpMessageType.value = 'alert-danger';
        mpConnecting.value = false;
    }
};

const handleMpTokenObtained = (event) => {
    if (event.data?.type === 'mp_token_obtained') {
        if (event.data.token) {
            mpMessage.value = 'Token obtenido correctamente. Recargando configuración...';
            mpMessageType.value = 'alert-success';
            loadConfigs();
        }
        mpConnecting.value = false;
    }
};

onMounted(() => {
    loadConfigs();
    window.addEventListener('message', handleMpTokenObtained);
});

onUnmounted(() => {
    window.removeEventListener('message', handleMpTokenObtained);
});
</script>
