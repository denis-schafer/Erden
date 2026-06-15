<template>
    <div class="portal-login">
        <div class="login-card">
            <div class="text-center mb-4">
                <h3>Portal de Socios</h3>
                <p class="text-muted">{{ businessName }}</p>
            </div>
            <form @submit.prevent="login">
                <div v-if="step === 'company'" class="mb-3">
                    <label class="form-label">Seleccionar Natatorio</label>
                    <select class="form-select" v-model="selectedCompany" required>
                        <option value="" disabled>Seleccione...</option>
                        <option v-for="c in companies" :key="c.id" :value="c.db">{{ c.name }}</option>
                    </select>
                    <button type="button" class="btn btn-primary w-100 mt-3" @click="selectCompany" :disabled="!selectedCompany">
                        Continuar
                    </button>
                </div>
                <template v-if="step === 'login'">
                    <div class="mb-3">
                        <label class="form-label">DNI</label>
                        <input class="form-control" v-model="form.dni" required placeholder="Ingrese su DNI" :disabled="authenticating">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña</label>
                        <input type="password" class="form-control" v-model="form.password" required placeholder="Ingrese su contraseña" :disabled="authenticating">
                    </div>
                    <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>
                    <button type="submit" class="btn btn-primary w-100" :disabled="authenticating">
                        <span v-if="authenticating" class="spinner-border spinner-border-sm me-1"></span>
                        Ingresar
                    </button>
                </template>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const emit = defineEmits(['login-success']);
const step = ref('company');
const companies = ref([]);
const selectedCompany = ref('');
const businessName = ref('Natatorio');
const authenticating = ref(false);
const error = ref('');
const form = ref({ dni: '', password: '' });

const loadCompanies = async () => {
    try {
        const { data } = await axios.get('/quota/companies');
        companies.value = data;
        if (data.length === 1) {
            selectedCompany.value = data[0].db;
            businessName.value = data[0].name;
            step.value = 'login';
        }
    } catch (e) {
        step.value = 'login';
    }
};

const selectCompany = () => {
    const c = companies.value.find(c => c.db === selectedCompany.value);
    if (c) businessName.value = c.name;
    step.value = 'login';
};

const login = async () => {
    authenticating.value = true;
    error.value = '';
    try {
        const { data } = await axios.post('/asociados/login', form.value, {
            headers: { 'X-Company-Db': selectedCompany.value },
        });
        emit('login-success', { ...data, company_db: selectedCompany.value });
    } catch (e) {
        error.value = e.response?.data?.message || 'Error al iniciar sesión';
    } finally {
        authenticating.value = false;
    }
};

onMounted(loadCompanies);
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
