<template>
    <div class="login-container">
        <div class="login-box">
            <div class="text-center mb-4">
                <h1 class="h3 mb-3 fw-normal">Erden</h1>
                <p class="text-muted">Sistema de Gestión</p>
            </div>
            <form @submit.prevent="handleLogin">
                <div class="mb-3">
                    <label class="form-label">Usuario</label>
                    <input 
                        v-model="form.username" 
                        type="text" 
                        class="form-control" 
                        required
                        autocomplete="username"
                        @blur="checkUserType"
                    >
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input 
                        v-model="form.password" 
                        type="password" 
                        class="form-control" 
                        required
                        autocomplete="current-password"
                    >
                </div>
                <div v-if="showCompanyField" class="mb-3">
                    <label class="form-label">Empresa</label>
                    <input 
                        v-model="form.company_db" 
                        type="text" 
                        class="form-control" 
                        placeholder=""
                        required
                    >
                    <div class="form-text">Ingrese el nombre o código de la empresa</div>
                </div>
                <div v-if="error" class="alert alert-danger">{{ error }}</div>
                <button type="submit" class="btn btn-primary w-100" :disabled="loading">
                    <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
                    Iniciar Sesión
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { toast as toastify } from '../../utils/toast';
import api from '../../services/api';

const authStore = useAuthStore();

const form = reactive({
    username: '',
    password: '',
    company_db: ''
});

const loading = ref(false);
const error = ref('');
const isGlobalUser = ref(false);
const userChecked = ref(true); // Inicia en true para mostrar el campo empresa por defecto

const showCompanyField = computed(() => {
    return !isGlobalUser.value; // Muestra empresa si NO es global
});

const emit = defineEmits(['login-success']);

const checkUserType = async () => {
    if (!form.username) {
        // Si borra el usuario, volver a mostrar empresa
        isGlobalUser.value = false;
        form.company_db = '';
        return;
    }
    
    try {
        const response = await api.get('/check-user', {
            params: { username: form.username }
        });
        isGlobalUser.value = response.data.is_global;
        
        if (isGlobalUser.value) {
            // Usuario global: ocultar empresa y limpiar campo
            form.company_db = '';
        }
        // Si NO es global, showCompanyField será true (muestra empresa)
    } catch (err) {
        // Error: asumir no global, mostrar empresa
        isGlobalUser.value = false;
    }
};

const handleLogin = async () => {
    loading.value = true;
    error.value = '';
    
    try {
        const data = await authStore.login(form);
        
        if (data.needs_company_selection) {
            emit('show-company-selector', data.companies);
        } else {
            authStore.setAuth(data);
            emit('login-success');
            toastify.success('Sesión iniciada correctamente');
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Credenciales incorrectas';
    } finally {
        loading.value = false;
    }
};
</script>

<style scoped>
.login-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f8f9fa;
}

.login-box {
    width: 100%;
    max-width: 400px;
    padding: 2rem;
    background: white;
    border-radius: 0.5rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
}
</style>