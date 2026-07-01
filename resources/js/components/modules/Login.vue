<template>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo" v-html="logoSvg"></div>
            </div>
            <div class="login-body">
                <form @submit.prevent="handleLogin">
                    <div class="form-group">
                        <label class="form-label">Usuario</label>
                        <div class="input-icon-wrapper">
                            <i class="bi bi-person"></i>
                            <input
                                v-model="form.username"
                                type="text"
                                class="form-control"
                                required
                                autocomplete="username"
                                placeholder="Ingrese su usuario"
                                @blur="checkUserType"
                            >
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contraseña</label>
                        <div class="input-icon-wrapper">
                            <i class="bi bi-lock"></i>
                            <input
                                v-model="form.password"
                                type="password"
                                class="form-control"
                                required
                                autocomplete="current-password"
                                placeholder="Ingrese su contraseña"
                            >
                        </div>
                    </div>
                    <div v-if="showCompanyField" class="form-group">
                        <label class="form-label">Empresa</label>
                        <div class="input-icon-wrapper">
                            <i class="bi bi-building"></i>
                            <input
                                v-model="form.company_db"
                                type="text"
                                class="form-control"
                                placeholder="Nombre o código de empresa"
                                required
                            >
                        </div>
                        <div class="form-text">Ingrese el nombre o código de la empresa</div>
                    </div>
                    <div v-if="error" class="alert alert-danger">{{ error }}</div>
                    <button type="submit" class="btn-login" :disabled="loading">
                        <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
                        <span v-else class="btn-icon">&#10132;</span>
                        Iniciar Sesión
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { toast as toastify } from '../../utils/toast';
import api from '../../services/api';

const authStore = useAuthStore();

const logoSvg = ref('');

const form = reactive({
    username: '',
    password: '',
    company_db: ''
});

const loading = ref(false);
const error = ref('');
const isGlobalUser = ref(false);
const userChecked = ref(true);

onMounted(async () => {
    try {
        const response = await fetch('/img/logo.svg');
        const text = await response.text();
        logoSvg.value = text;
    } catch (e) {
        console.warn('Failed to load logo SVG:', e);
    }
});

const showCompanyField = computed(() => {
    return !isGlobalUser.value;
});

const emit = defineEmits(['login-success']);

const checkUserType = async () => {
    if (!form.username) {
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
            form.company_db = '';
        }
    } catch (err) {
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
    padding: 1.5rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.login-card {
    width: 100%;
    max-width: 420px;
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    animation: cardEnter 0.5s ease-out;
}

@keyframes cardEnter {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.login-header {
    background: linear-gradient(135deg, #0a1628 0%, #1a4a6e 50%, #1a6d91 100%);
    padding: 0.5rem 2rem;
    text-align: center;
    line-height: 0;
}

.login-logo {
    display: flex;
    justify-content: center;
    align-items: center;
}

.login-logo svg {
    display: block;
    width: 100%;
    max-width: 140px;
    height: auto;
    margin-top: 5px;
}

.login-body {
    padding: 2rem;
}

.form-group {
    margin-bottom: 1.25rem;
}

.form-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.4rem;
    display: block;
}

.input-icon-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon-wrapper .bi {
    position: absolute;
    left: 14px;
    color: #adb5bd;
    font-size: 1rem;
    z-index: 2;
    transition: color 0.2s;
}

.input-icon-wrapper .form-control {
    padding-left: 42px;
    height: 46px;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    font-size: 0.95rem;
    transition: all 0.25s;
    background: #f8f9fa;
}

.input-icon-wrapper .form-control:focus {
    border-color: #2391c1;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(35,145,193,0.1);
}

.input-icon-wrapper:focus-within .bi {
    color: #2391c1;
}

.form-text {
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: 0.35rem;
}

.btn-login {
    width: 100%;
    height: 48px;
    border: none;
    border-radius: 10px;
    background: linear-gradient(135deg, #1a6d91, #2391c1);
    color: #fff;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(35,145,193,0.35);
}

.btn-login:active {
    transform: translateY(0);
}

.btn-login:disabled {
    opacity: 0.65;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.btn-icon {
    font-size: 1.1rem;
}

.alert {
    border-radius: 10px;
    font-size: 0.9rem;
    padding: 0.75rem 1rem;
}
</style>
