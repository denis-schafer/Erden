<template>
    <div class="oauth-page">
        <div class="oauth-container">
            <div class="oauth-card">
                <h4 class="mb-4"><i class="bi bi-wallet2 me-2"></i>Obtener Token MercadoPago</h4>

                <div v-if="error" class="alert alert-danger">{{ error }}</div>
                <div v-if="success" class="alert alert-success">{{ success }}</div>

                <template v-if="step === 'search'">
                    <p class="text-muted small mb-3">Ingrese el nombre de la empresa para conectar con MercadoPago.</p>
                    <div class="input-group mb-3">
                        <input class="form-control" v-model="searchName" placeholder="Nombre de la empresa..."
                            @keyup.enter="lookupCompany" :disabled="looking">
                        <button class="btn btn-primary" @click="lookupCompany" :disabled="looking">
                            <span v-if="looking" class="spinner-border spinner-border-sm me-1"></span>
                            Buscar
                        </button>
                    </div>
                </template>

                <template v-if="step === 'confirm'">
                    <div class="mb-3 p-3 bg-light rounded">
                        <div class="mb-1"><strong>Empresa:</strong> {{ company.name }}</div>
                        <div class="mb-1"><strong>Client ID:</strong> <code>{{ company.mp_client_id }}</code></div>
                        <div><strong>Modo:</strong> Producción</div>
                    </div>
                    <button class="btn btn-success w-100" @click="connectMP" :disabled="connecting">
                        <span v-if="connecting" class="spinner-border spinner-border-sm me-1"></span>
                        <i class="bi bi-link-45deg"></i> Conectar con MercadoPago
                    </button>
                </template>

                <template v-if="step === 'token'">
                    <div class="mb-3 p-3 bg-light rounded">
                        <div class="mb-1"><strong>Empresa:</strong> {{ companyName }}</div>
                        <div class="mb-1"><strong>Token obtenido:</strong></div>
                        <div class="input-group">
                            <textarea class="form-control font-monospace" rows="3" readonly>{{ token }}</textarea>
                            <button class="btn btn-outline-secondary" @click="copyToken" title="Copiar">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100" @click="assignToken" :disabled="assigning">
                        <span v-if="assigning" class="spinner-border spinner-border-sm me-1"></span>
                        <i class="bi bi-check-lg"></i> Asignar Token a {{ companyName }}
                    </button>
                    <button class="btn btn-link w-100 mt-2" @click="reset">Buscar otra empresa</button>
                </template>

                <template v-if="step === 'done'">
                    <div class="text-center py-3">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                        <h5 class="mt-2 text-success">Token asignado correctamente</h5>
                        <p class="text-muted small">El token fue guardado en la configuración de {{ companyName }}.</p>
                        <button class="btn btn-primary mt-2" @click="reset">
                            <i class="bi bi-search"></i> Buscar otra empresa
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const step = ref('search');
const searchName = ref('');
const looking = ref(false);
const connecting = ref(false);
const assigning = ref(false);
const error = ref('');
const success = ref('');

const company = ref({});
const token = ref('');
const companyName = ref('');
const companyId = ref(null);

const lookupCompany = async () => {
    if (!searchName.value.trim()) return;
    error.value = '';
    success.value = '';
    looking.value = true;
    try {
        const { data } = await axios.get('/oauth/lookup', { params: { name: searchName.value.trim() } });
        company.value = data;
        step.value = 'confirm';
    } catch (e) {
        error.value = e.response?.data?.error || 'Error al buscar empresa';
    } finally {
        looking.value = false;
    }
};

const connectMP = async () => {
    connecting.value = true;
    error.value = '';
    try {
        const { data } = await axios.get('/oauth/authorize', { params: { company_id: company.value.id } });
        window.location.href = data.url;
    } catch (e) {
        error.value = e.response?.data?.error || 'Error al conectar con MercadoPago';
        connecting.value = false;
    }
};

const copyToken = async () => {
    try {
        await navigator.clipboard.writeText(token.value);
        success.value = 'Token copiado al portapapeles';
        setTimeout(() => success.value = '', 2000);
    } catch {
        error.value = 'No se pudo copiar';
    }
};

const assignToken = async () => {
    assigning.value = true;
    error.value = '';
    try {
        const { data } = await axios.post('/oauth/assign', {
            token: token.value,
            company_id: companyId.value,
        });
        success.value = data.message;
        step.value = 'done';
    } catch (e) {
        error.value = e.response?.data?.error || 'Error al asignar token';
    } finally {
        assigning.value = false;
    }
};

const reset = () => {
    step.value = 'search';
    searchName.value = '';
    company.value = {};
    token.value = '';
    companyName.value = '';
    companyId.value = null;
    error.value = '';
    success.value = '';
};

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const t = params.get('token');
    const cid = params.get('company_id');
    const cname = params.get('company_name');
    if (t && cid && cname) {
        token.value = t;
        companyId.value = cid;
        companyName.value = cname;
        step.value = 'token';
    }
});
</script>

<style scoped>
.oauth-page {
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.oauth-container {
    width: 100%;
    max-width: 480px;
}
.oauth-card {
    background: #fff;
    border-radius: 12px;
    padding: 32px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
}
.font-monospace { font-size: 13px; }
</style>
