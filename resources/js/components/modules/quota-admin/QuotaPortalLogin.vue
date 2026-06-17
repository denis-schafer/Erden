<template>
    <div class="portal-login">
        <div class="login-card">
            <div class="text-center mb-4">
                <img v-if="portalLogo" :src="portalLogo" style="max-height: 60px; margin-bottom: 10px;">
                <h3>Portal de Socios</h3>
                <p v-if="businessName" class="text-muted">{{ businessName }}</p>
            </div>

            <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>

            <template v-if="step === 'company'">
                <div class="mb-3">
                    <label class="form-label">Nombre del Natatorio</label>
                    <div class="input-group">
                        <input class="form-control" v-model="companyInput" placeholder="Ej: elOasis"
                            @keyup.enter="searchCompany" :disabled="searching">
                        <button class="btn btn-primary" @click="searchCompany" :disabled="searching">
                            <span v-if="searching" class="spinner-border spinner-border-sm"></span>
                            <span v-else>Buscar</span>
                        </button>
                    </div>
                </div>
            </template>

            <template v-if="step === 'login'">
                <form @submit.prevent="login">
                    <div class="mb-3">
                        <label class="form-label">DNI</label>
                        <input class="form-control" v-model="form.dni" required placeholder="Ingrese su DNI" :disabled="authenticating">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña</label>
                        <input type="password" class="form-control" v-model="form.password" required placeholder="Ingrese su contraseña" :disabled="authenticating">
                    </div>
                    <button type="submit" class="btn btn-primary w-100" :disabled="authenticating">
                        <span v-if="authenticating" class="spinner-border spinner-border-sm me-1"></span>
                        Ingresar
                    </button>
                </form>
            </template>

            <template v-if="step === 'auto-login'">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary mb-3"></div>
                    <p>Ingresando automáticamente...</p>
                    <p class="text-muted small">DNI: {{ form.dni }}</p>
                </div>
            </template>

            <template v-if="step === 'login-password'">
                <p class="text-muted small mb-3">
                    El usuario cambió su contraseña. Ingresá la nueva.
                </p>
                <form @submit.prevent="login">
                    <div class="mb-3">
                        <label class="form-label">DNI</label>
                        <input class="form-control" v-model="form.dni" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña</label>
                        <input type="password" class="form-control" v-model="form.password" required placeholder="Nueva contraseña" :disabled="authenticating" autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" :disabled="authenticating">
                        <span v-if="authenticating" class="spinner-border spinner-border-sm me-1"></span>
                        Ingresar
                    </button>
                </form>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const emit = defineEmits(['login-success']);

const props = defineProps({
    initialCompanyName: { type: String, default: '' },
    initialDni: { type: String, default: '' },
});

const step = ref('company');
const companyInput = ref('');
const businessName = ref('');
const selectedCompany = ref('');
const searching = ref(false);
const authenticating = ref(false);
const error = ref('');
const form = ref({ dni: '', password: '' });
const portalLogo = ref('');

const loadPortalConfig = async (companyDb) => {
    try {
        const { data } = await axios.get('/portal/config', { params: { company_db: companyDb } });
        portalLogo.value = data.logo || '';
        localStorage.setItem('portal_config', JSON.stringify(data));
    } catch (e) { /* defaults */ }
};

const searchCompany = async () => {
    if (!companyInput.value.trim()) return;
    searching.value = true;
    error.value = '';
    try {
        const { data } = await axios.get('/asociados/lookup-company', { params: { name: companyInput.value.trim() } });
        selectedCompany.value = data.db;
        businessName.value = data.name;
        await loadPortalConfig(data.db);
        step.value = 'login';
    } catch (e) {
        error.value = e.response?.data?.error || 'Empresa no encontrada';
    } finally {
        searching.value = false;
    }
};

const login = async () => {
    authenticating.value = true;
    error.value = '';
    try {
        const { data } = await axios.post('/asociados/login', {
            dni: form.value.dni,
            password: form.value.password,
        }, {
            headers: { 'X-Company-Db': selectedCompany.value },
        });
        emit('login-success', { ...data, company_db: selectedCompany.value });
    } catch (e) {
        error.value = e.response?.data?.message || 'Error al iniciar sesión';
    } finally {
        authenticating.value = false;
    }
};

const tryAutoLogin = async () => {
    step.value = 'auto-login';
    authenticating.value = true;
    error.value = '';
    try {
        const { data } = await axios.post('/asociados/login', {
            dni: form.value.dni,
            password: form.value.dni,
        }, {
            headers: { 'X-Company-Db': selectedCompany.value },
        });
        emit('login-success', { ...data, company_db: selectedCompany.value });
    } catch (e) {
        step.value = 'login-password';
        authenticating.value = false;
    }
};

onMounted(async () => {
    if (props.initialCompanyName) {
        companyInput.value = props.initialCompanyName;
        searching.value = true;
        try {
            const { data } = await axios.get('/asociados/lookup-company', { params: { name: props.initialCompanyName } });
            selectedCompany.value = data.db;
            businessName.value = data.name;
            await loadPortalConfig(data.db);

            if (props.initialDni) {
                form.value.dni = props.initialDni;
                await tryAutoLogin();
            } else {
                step.value = 'login';
            }
        } catch (e) {
            error.value = 'Empresa no encontrada';
            step.value = 'company';
        } finally {
            searching.value = false;
        }
    }
    // If no initialCompanyName, stay on 'company' step (user inputs name manually)
});
</script>

<style scoped>
.portal-login {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.login-card {
    background: white;
    border-radius: 12px;
    padding: 40px;
    width: 100%;
    max-width: 400px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}
</style>
